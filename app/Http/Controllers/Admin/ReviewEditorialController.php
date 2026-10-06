<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditEvent;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Models\PublishedReviewPayload;
use App\Models\Region;
use App\Models\ReviewEditorialRecord;
use App\Models\ReviewPublicationDisposition;
use App\Services\Media\MediaRegistry;
use App\Services\Reviews\EditorialTransitionException;
use App\Services\Reviews\ReviewEditorialService;
use App\Support\Reviews\PublicReleaseVerifier;
use App\Support\Reviews\ReviewMediaSet;
use App\Support\Reviews\VideoAuthority;
use Closure;
use DomainException;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The review queue and review workspace (design record section 11). Admin is the human
 * editorial layer: it never edits n8n-owned content (review_page_json, schema_json_ld,
 * scores, QA fields, publish_status, slug or id). Every state change goes through
 * ReviewEditorialService / MediaRegistry, which enforce the rules and write the audit.
 * Each route carries its own permission; legacy admin-source rows are view-and-disposition
 * only.
 */
class ReviewEditorialController extends Controller
{
    public const TABS = ['overview', 'associations', 'media', 'qa', 'disposition', 'history'];

    public const FLAGS = [
        'stale' => 'Stale QA / release',
        'held' => 'Editorial hold',
        'withdrawn' => 'Withdrawn',
        'd3' => 'D3 divergence hold',
        'd1' => 'D1 safety contradiction',
        'missing_associations' => 'Missing associations',
        'has_video' => 'Has video',
        'legacy' => 'Legacy admin-source',
    ];

    public function __construct(
        private ReviewEditorialService $editorial,
        private MediaRegistry $media,
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'state' => ['nullable', Rule::in(ReviewEditorialRecord::STATES)],
            'producer_status' => ['nullable', 'string', 'max:64'],
            'flags' => ['nullable', 'array'],
            'flags.*' => [Rule::in(array_keys(self::FLAGS))],
            'channel' => ['nullable', 'string', 'max:191'],
            'character' => ['nullable', 'string', 'max:191'],
            'q' => ['nullable', 'string', 'max:191'],
        ]);
        $flags = $filters['flags'] ?? [];

        $query = PublishedReviewPayload::query()
            ->select('published_review_payloads.*')
            ->leftJoin('review_editorial_records as rer', 'rer.published_review_id', '=', 'published_review_payloads.published_review_id');

        if ($state = $filters['state'] ?? null) {
            $query->where(function ($q) use ($state) {
                $q->where('rer.editorial_state', $state);
                if ($state === ReviewEditorialRecord::STATE_PENDING_REVIEW) {
                    $q->orWhereNull('rer.published_review_id');
                }
            });
        }
        if ($status = $filters['producer_status'] ?? null) {
            $query->where('published_review_payloads.publish_status', $status);
        }
        if ($channel = $filters['channel'] ?? null) {
            $query->where('published_review_payloads.channel', $channel);
        }
        if ($character = $filters['character'] ?? null) {
            $query->where('published_review_payloads.character_id', $character);
        }
        if ($term = $filters['q'] ?? null) {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('published_review_payloads.h1', 'like', $like)
                    ->orWhere('published_review_payloads.product_uid', 'like', $like)
                    ->orWhere('published_review_payloads.published_review_id', 'like', $like);
            });
        }

        foreach ($flags as $flag) {
            $this->applyFlag($query, $flag);
        }

        $payloads = $query->orderByDesc('published_review_payloads.updated_at')
            ->orderBy('published_review_payloads.id')
            ->paginate(50)
            ->withQueryString();

        $ids = $payloads->getCollection()->pluck('published_review_id')->all();
        $keys = $payloads->getCollection()->pluck('id')->all();
        $records = ReviewEditorialRecord::query()->whereKey($ids)->get()->keyBy('published_review_id');
        $dispositions = ReviewPublicationDisposition::query()->whereKey($ids)->get()->keyBy('published_review_id');
        $passesD3 = PublishedReviewPayload::query()->whereKey($keys)->passesDivergenceHold()->pluck('id')->flip();
        $passesD1 = PublishedReviewPayload::query()->whereKey($keys)->passesSafetyContradictionGate()->pluck('id')->flip();

        $rows = $payloads->getCollection()->map(function (PublishedReviewPayload $payload) use ($records, $dispositions, $passesD3, $passesD1) {
            $record = $records->get($payload->published_review_id)
                ?? new ReviewEditorialRecord(['published_review_id' => $payload->published_review_id]);

            return [
                'payload' => $payload,
                'record' => $record,
                'stale' => $this->editorial->isStale($record, $payload),
                'd3' => ! $passesD3->has($payload->id),
                'd1' => ! $passesD1->has($payload->id),
                'withdrawn' => (bool) $dispositions->get($payload->published_review_id)?->isWithdrawn(),
            ];
        });

        return view('admin.review-editorial.index', [
            'payloads' => $payloads,
            'rows' => $rows,
            'filters' => $filters + ['flags' => $flags],
            'flagLabels' => self::FLAGS,
            'states' => ReviewEditorialRecord::STATES,
            'producerStatuses' => PublishedReviewPayload::query()->whereNotNull('publish_status')->distinct()->orderBy('publish_status')->pluck('publish_status'),
            'channels' => PublishedReviewPayload::query()->whereNotNull('channel')->distinct()->orderBy('channel')->pluck('channel'),
            'characters' => PublishedReviewPayload::query()->whereNotNull('character_id')->distinct()->orderBy('character_id')->pluck('character_id'),
            'authority' => VideoAuthority::resolve(),
        ]);
    }

    public function show(Request $request, string $review)
    {
        $payload = $this->payload($review);
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'overview';

        return view('admin.review-editorial.show', array_merge(
            $this->workspace($payload),
            ['tab' => $tab],
        ));
    }

    /**
     * Everything the workspace (and the preview wrapper) shows. Read-only.
     *
     * @return array<string, mixed>
     */
    public function workspace(PublishedReviewPayload $payload): array
    {
        $id = $payload->published_review_id;
        $record = $this->editorial->recordFor($id);
        $channel = $this->editorial->resolveChannel($payload);
        $character = $this->editorial->resolveCharacter($payload);
        $regions = Region::query()->orderBy('region_code')->get();

        $verifier = app(PublicReleaseVerifier::class);
        $verification = [];
        foreach (array_merge([null], $regions->pluck('region_code')->reject(fn ($c) => $c === 'GLOBAL')->values()->all()) as $code) {
            $verification[$code ?? '(unknown host)'] = $verifier->verify($payload, $code) !== null;
        }

        $attachments = MediaAttachment::query()->with('asset')
            ->where('owner_type', MediaAttachment::OWNER_REVIEW)
            ->where('owner_key', $id)
            ->orderBy('role')->orderByDesc('id')
            ->get();

        return [
            'payload' => $payload,
            'record' => $record,
            'authority' => VideoAuthority::resolve(),
            'fingerprints' => $this->editorial->currentFingerprints($payload),
            'stale' => $this->editorial->isStale($record, $payload),
            'd3' => $this->editorial->isDivergenceHeld($payload),
            'd1' => $this->editorial->isSafetyContradictory($payload),
            'disposition' => ReviewPublicationDisposition::query()->find($id),
            'isPipeline' => $payload->source === 'pipeline',
            'resolvedChannel' => $channel,
            'resolvedCharacter' => $character,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'suggestedCategoryId' => $record->category_id ?? $character?->category_id,
            'regions' => $regions,
            'selectedRegionIds' => $payload->regions()->pluck('regions.id')->all(),
            'blockers' => $this->editorial->releaseBlockers($payload, $record),
            'verification' => $verification,
            'attachments' => $attachments,
            'proposed' => ReviewMediaSet::proposed($id)->keyBy('role'),
            'active' => ReviewMediaSet::active($id)->keyBy('role'),
            'mediaRoles' => (array) config('media.roles.' . MediaAttachment::OWNER_REVIEW, []),
            'assets' => MediaAsset::query()->whereNull('archived_at')->latest('id')->limit(200)->get(),
            'events' => AdminAuditEvent::query()->with('actor:id,name')
                ->where('subject_type', 'review')->where('subject_key', $id)
                ->orderByDesc('occurred_at')->orderByDesc('id')->get(),
        ];
    }

    public function associations(Request $request, string $review): RedirectResponse
    {
        $payload = $this->payload($review);
        $data = $request->validate(['category_id' => ['required', 'integer']]);

        return $this->act($payload, 'associations', 'Associations confirmed.',
            fn () => $this->editorial->confirmAssociations($payload, (int) $data['category_id'], $request->user()->id));
    }

    public function regions(Request $request, string $review): RedirectResponse
    {
        $payload = $this->payload($review);
        $data = $request->validate([
            'region_ids' => ['nullable', 'array'],
            'region_ids.*' => ['integer'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        return $this->act($payload, 'associations', 'Region eligibility updated.',
            fn () => $this->editorial->setRegions($payload, $data['region_ids'] ?? [], $data['reason'], $request->user()->id));
    }

    public function notes(Request $request, string $review): RedirectResponse
    {
        $payload = $this->payload($review);
        $data = $request->validate(['internal_notes' => ['nullable', 'string', 'max:20000']]);

        return $this->act($payload, 'overview', 'Internal notes saved.',
            fn () => $this->editorial->updateNotes($payload, $data['internal_notes'] ?? null, $request->user()->id));
    }

    public function stageMedia(Request $request, string $review): RedirectResponse
    {
        $payload = $this->payload($review);
        $data = $request->validate([
            'media_asset_id' => ['required', 'integer'],
            'role' => ['required', Rule::in(ReviewMediaSet::roles())],
        ]);

        return $this->act($payload, 'media', 'Media staged. It renders only after a fresh QA pass and Release.', function () use ($payload, $data, $request) {
            $this->assertPipeline($payload);
            $asset = MediaAsset::query()->findOrFail($data['media_asset_id']);
            $this->media->stage($asset, MediaAttachment::OWNER_REVIEW, $payload->published_review_id, $data['role'], $request->user()->id);
        });
    }

    public function discardMedia(Request $request, string $review, int $attachment): RedirectResponse
    {
        $payload = $this->payload($review);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $attachment = MediaAttachment::query()
            ->where('owner_type', MediaAttachment::OWNER_REVIEW)
            ->where('owner_key', $payload->published_review_id)
            ->findOrFail($attachment);

        return $this->act($payload, 'media', 'Staged media discarded.', function () use ($payload, $attachment, $data, $request) {
            $this->assertPipeline($payload);
            if ($attachment->state !== MediaAttachment::STATE_PENDING) {
                throw new EditorialTransitionException(['Only staged (pending) media can be discarded here.']);
            }
            $this->media->retire($attachment, $data['reason'], $request->user()->id);
        });
    }

    public function qaPass(Request $request, string $review): RedirectResponse
    {
        $payload = $this->payload($review);
        $data = $this->validateBinding($request, false);

        return $this->act($payload, 'qa', 'QA pass recorded and bound to the reviewed content and media.',
            fn () => $this->editorial->qaPass($payload, $data['content_fingerprint'], $data['media_fingerprint'], $request->user()->id, $data['reason'] ?? null));
    }

    public function returnForCorrection(Request $request, string $review): RedirectResponse
    {
        $payload = $this->payload($review);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return $this->act($payload, 'qa', 'Returned for correction. Send the correction back to the pipeline (manual step).',
            fn () => $this->editorial->returnForCorrection($payload, $data['reason'], $request->user()->id));
    }

    public function release(Request $request, string $review): RedirectResponse
    {
        $payload = $this->payload($review);
        $data = $this->validateBinding($request, true);

        return $this->act($payload, 'qa', 'Released. Public eligibility is still subject to the public-release verifier.',
            fn () => $this->editorial->release($payload, $data['content_fingerprint'], $data['media_fingerprint'], $data['reason'], $request->user()->id));
    }

    public function unrelease(Request $request, string $review): RedirectResponse
    {
        $payload = $this->payload($review);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return $this->act($payload, 'qa', 'Unreleased.',
            fn () => $this->editorial->unrelease($payload, $data['reason'], $request->user()->id));
    }

    public function hold(Request $request, string $review): RedirectResponse
    {
        $payload = $this->payload($review);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return $this->act($payload, 'qa', 'Editorial hold applied.',
            fn () => $this->editorial->hold($payload, $data['reason'], $request->user()->id));
    }

    public function liftHold(Request $request, string $review): RedirectResponse
    {
        $payload = $this->payload($review);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return $this->act($payload, 'qa', 'Editorial hold lifted.',
            fn () => $this->editorial->liftHold($payload, $data['reason'], $request->user()->id));
    }

    public function withdraw(Request $request, string $review): RedirectResponse
    {
        $payload = $this->payload($review);
        $data = $request->validate([
            'proposed_class' => ['nullable', Rule::in(self::withdrawalClasses())],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        return $this->act($payload, 'disposition', 'Withdrawal recorded (treated as material until the class is confirmed).',
            fn () => $this->editorial->withdraw($payload, $data['proposed_class'] ?? null, $data['reason'], $request->user()->id));
    }

    public function classify(Request $request, string $review): RedirectResponse
    {
        $payload = $this->payload($review);
        $data = $request->validate([
            'class' => ['required', Rule::in(self::withdrawalClasses())],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        return $this->act($payload, 'disposition', 'Withdrawal class confirmed.',
            fn () => $this->editorial->confirmWithdrawalClass($payload, $data['class'], $data['reason'], $request->user()->id));
    }

    public function validity(Request $request, string $review): RedirectResponse
    {
        $payload = $this->payload($review);
        $data = $request->validate([
            'validity_state' => ['required', Rule::in([ReviewPublicationDisposition::VALIDITY_VALID, ReviewPublicationDisposition::VALIDITY_MATERIALLY_INVALID])],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        return $this->act($payload, 'disposition', 'Validity updated.',
            fn () => $this->editorial->setValidity($payload, $data['validity_state'], $data['reason'], $request->user()->id));
    }

    public function availability(Request $request, string $review): RedirectResponse
    {
        $payload = $this->payload($review);
        $data = $request->validate([
            'availability_state' => ['required', Rule::in([ReviewPublicationDisposition::AVAILABILITY_AVAILABLE, ReviewPublicationDisposition::AVAILABILITY_TEMPORARILY_UNAVAILABLE])],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        return $this->act($payload, 'disposition', 'Availability updated.',
            fn () => $this->editorial->setAvailability($payload, $data['availability_state'], $data['reason'], $request->user()->id));
    }

    public function reinstate(Request $request, string $review): RedirectResponse
    {
        $payload = $this->payload($review);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return $this->act($payload, 'disposition', 'Withdrawal cleared. A fresh Release is required before the review can be public again.',
            fn () => $this->editorial->reinstate($payload, $data['reason'], $request->user()->id));
    }

    /** @return string[] */
    public static function withdrawalClasses(): array
    {
        return [
            ReviewPublicationDisposition::CLASS_ORDINARY_EDITORIAL,
            ReviewPublicationDisposition::CLASS_MATERIAL,
            ReviewPublicationDisposition::CLASS_TEMPORARY_TECHNICAL,
        ];
    }

    private function payload(string $review): PublishedReviewPayload
    {
        return PublishedReviewPayload::query()->where('published_review_id', $review)->firstOrFail();
    }

    /** The fingerprints the reviewer saw, submitted with T1 and T3 (concurrency guard). */
    private function validateBinding(Request $request, bool $reasonRequired): array
    {
        return $request->validate([
            'content_fingerprint' => ['required', 'string', 'regex:/^[0-9a-f]{64}$/'],
            'media_fingerprint' => ['required', 'string', 'regex:/^[0-9a-f]{64}$/'],
            'reason' => [$reasonRequired ? 'required' : 'nullable', 'string', 'max:1000'],
        ]);
    }

    private function act(PublishedReviewPayload $payload, string $tab, string $success, Closure $action): RedirectResponse
    {
        $back = redirect()->route('admin.review-editorial.show', ['review' => $payload->published_review_id, 'tab' => $tab]);

        try {
            $action();
        } catch (EditorialTransitionException $e) {
            return $back->withErrors(['editorial' => $e->errors]);
        } catch (DomainException $e) {
            return $back->withErrors(['editorial' => [$e->getMessage()]]);
        }

        return $back->with('success', $success);
    }

    private function assertPipeline(PublishedReviewPayload $payload): void
    {
        if ($payload->source !== 'pipeline') {
            throw new EditorialTransitionException([
                'Legacy admin-source rows are not publicly eligible; only viewing and dispositions are available.',
            ]);
        }
    }

    private function applyFlag($query, string $flag): void
    {
        match ($flag) {
            'stale' => $query->whereIn('published_review_payloads.published_review_id', $this->staleIds()),
            'held' => $query->whereNotNull('rer.hold_at'),
            'withdrawn' => $query->whereExists(fn (QueryBuilder $d) => $d->selectRaw('1')
                ->from('review_publication_dispositions as d')
                ->whereColumn('d.published_review_id', 'published_review_payloads.published_review_id')
                ->whereNotNull('d.withdrawn_at')),
            'd3' => $query->whereNotIn('published_review_payloads.id', PublishedReviewPayload::query()->passesDivergenceHold()->select('id')),
            'd1' => $query->whereNotIn('published_review_payloads.id', PublishedReviewPayload::query()->passesSafetyContradictionGate()->select('id')),
            'missing_associations' => $query->whereNull('rer.associations_confirmed_at'),
            'has_video' => $query->whereExists(fn (QueryBuilder $a) => $a->selectRaw('1')
                ->from('media_attachments as ma')
                ->where('ma.owner_type', MediaAttachment::OWNER_REVIEW)
                ->whereColumn('ma.owner_key', 'published_review_payloads.published_review_id')
                ->where('ma.role', 'video')
                ->whereIn('ma.state', [MediaAttachment::STATE_PENDING, MediaAttachment::STATE_ACTIVE])),
            'legacy' => $query->where('published_review_payloads.source', 'admin'),
        };
    }

    /** Exact staleness (content under the active scheme AND proposed media), as the workspace computes it. */
    private function staleIds(): array
    {
        $records = ReviewEditorialRecord::query()
            ->whereIn('editorial_state', [ReviewEditorialRecord::STATE_QA_PASSED, ReviewEditorialRecord::STATE_RELEASED])
            ->get()->keyBy('published_review_id');

        if ($records->isEmpty()) {
            return [];
        }

        return PublishedReviewPayload::query()->whereIn('published_review_id', $records->keys())->get()
            ->filter(fn (PublishedReviewPayload $p) => $this->editorial->isStale($records[$p->published_review_id], $p))
            ->pluck('published_review_id')->all();
    }
}

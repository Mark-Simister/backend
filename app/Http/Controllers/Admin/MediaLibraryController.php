<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditEvent;
use App\Models\MediaAsset;
use App\Services\Media\MediaRegistry;
use Closure;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The media library (design record sections 7 and 11): register an external video by
 * allow-listed provider reference, upload a managed image or audio file to the media
 * disk, edit descriptive metadata, see where an asset is used, archive. Nothing is ever
 * deleted. No provider API is called - provider metadata fetch is not part of Sprint A.
 */
class MediaLibraryController extends Controller
{
    public function __construct(private MediaRegistry $registry)
    {
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'kind' => ['nullable', Rule::in([MediaAsset::KIND_VIDEO, MediaAsset::KIND_IMAGE, MediaAsset::KIND_AUDIO])],
            'archived' => ['nullable', Rule::in(['0', '1'])],
            'q' => ['nullable', 'string', 'max:191'],
        ]);

        $query = MediaAsset::query()->withCount(['attachments as live_attachments_count' => fn ($q) => $q->where('state', '!=', 'retired')]);

        if ($kind = $filters['kind'] ?? null) {
            $query->where('kind', $kind);
        }
        if (($filters['archived'] ?? '0') === '1') {
            $query->whereNotNull('archived_at');
        } else {
            $query->whereNull('archived_at');
        }
        if ($term = $filters['q'] ?? null) {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
            $query->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('external_ref', 'like', $like)->orWhere('path', 'like', $like));
        }

        return view('admin.media-library.index', [
            'assets' => $query->orderByDesc('id')->paginate(50)->withQueryString(),
            'filters' => $filters,
            'providers' => array_keys((array) config('media.providers', [])),
            'managed' => (array) config('media.managed', []),
        ]);
    }

    public function show(int $asset)
    {
        $asset = MediaAsset::query()->findOrFail($asset);

        return view('admin.media-library.show', [
            'asset' => $asset,
            'attachments' => $asset->attachments()->orderByDesc('id')->get(),
            'events' => AdminAuditEvent::query()->with('actor:id,name')
                ->where('subject_type', 'media_asset')->where('subject_key', (string) $asset->id)
                ->orderByDesc('occurred_at')->orderByDesc('id')->get(),
        ]);
    }

    public function storeExternal(Request $request): RedirectResponse
    {
        $data = $request->validate(array_merge([
            'reference' => ['required', 'string', 'max:500'],
        ], $this->metadataRules()));

        return $this->act(fn () => $this->registry->registerExternal($data['reference'], $data, $request->user()->id), 'External video registered.');
    }

    public function upload(Request $request): RedirectResponse
    {
        $data = $request->validate(array_merge([
            'kind' => ['required', Rule::in(array_keys((array) config('media.managed', [])))],
            'file' => ['required', 'file'],
        ], $this->metadataRules()));

        return $this->act(fn () => $this->registry->storeManaged($request->file('file'), $data['kind'], $data, $request->user()->id), 'File stored in the media library.');
    }

    public function updateMetadata(Request $request, int $asset): RedirectResponse
    {
        $asset = MediaAsset::query()->findOrFail($asset);
        $data = $request->validate($this->metadataRules());

        return $this->act(fn () => $this->registry->updateMetadata($asset, $data, $request->user()->id), 'Metadata saved.', $asset);
    }

    public function archive(Request $request, int $asset): RedirectResponse
    {
        $asset = MediaAsset::query()->findOrFail($asset);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return $this->act(fn () => $this->registry->archive($asset, $data['reason'], $request->user()->id), 'Asset archived. The file and record are retained.', $asset);
    }

    private function metadataRules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'alt_text' => ['nullable', 'string', 'max:500'],
            'rights_note' => ['nullable', 'string', 'max:2000'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'provider_published_at' => ['nullable', 'date'],
            'chapters' => ['nullable', 'string', 'max:20000'],
        ];
    }

    private function act(Closure $action, string $success, ?MediaAsset $asset = null): RedirectResponse
    {
        try {
            $result = $action();
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['media' => $e->getMessage()]);
        }

        $target = $asset ?? ($result instanceof MediaAsset ? $result : null);

        return ($target
            ? redirect()->route('admin.media-library.show', $target->id)
            : redirect()->route('admin.media-library.index'))->with('success', $success);
    }
}

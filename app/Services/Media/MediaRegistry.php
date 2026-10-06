<?php

namespace App\Services\Media;

use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Services\Audit\AdminAuditLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The provider-neutral media registry and attachment lifecycle (design record section 7).
 *
 *  - External items are provider references only, parsed against the allowlist; the embed
 *    URL is always built on the server. Laravel never hosts video binaries.
 *  - Managed images/audio go to the dedicated media disk, content-addressed by sha256.
 *  - Attachments: pending -> active -> retired, or pending -> retired. Review-role
 *    attachments become active ONLY through Release capture (captureReviewSet()).
 *  - Nothing is ever hard-deleted: assets are archived, attachments retired.
 */
class MediaRegistry
{
    public function __construct(private AdminAuditLog $audit)
    {
    }

    /**
     * Parse "provider:id" or a provider URL into [provider, id], against the allowlist.
     *
     * @return array{0: string, 1: string}
     */
    public function parseExternalReference(string $input): array
    {
        $input = trim($input);
        $provider = null;
        $id = null;

        if (preg_match('~^([a-z0-9_-]+):([^/\s]+)$~i', $input, $m)) {
            [$provider, $id] = [strtolower($m[1]), $m[2]];
        } elseif (preg_match('~^https://(?:www\.)?vimeo\.com/(\d+)(?:[/?#]|$)~i', $input, $m)
            || preg_match('~^https://player\.vimeo\.com/video/(\d+)(?:[/?#]|$)~i', $input, $m)) {
            [$provider, $id] = ['vimeo', $m[1]];
        } elseif (preg_match('~^https://(?:www\.|m\.)?youtube\.com/watch\?(?:.*&)?v=([A-Za-z0-9_-]{11})(?:[&#]|$)~i', $input, $m)
            || preg_match('~^https://youtu\.be/([A-Za-z0-9_-]{11})(?:[?&/#]|$)~i', $input, $m)
            || preg_match('~^https://(?:www\.)?youtube(?:-nocookie)?\.com/embed/([A-Za-z0-9_-]{11})(?:[?&/#]|$)~i', $input, $m)) {
            [$provider, $id] = ['youtube', $m[1]];
        }

        $spec = $provider === null ? null : config("media.providers.{$provider}");
        if (! is_array($spec)) {
            throw new MediaRegistryException('Unsupported media reference: only allow-listed providers (' . implode(', ', array_keys((array) config('media.providers'))) . ') over https are accepted.');
        }
        if (! preg_match($spec['id_pattern'], (string) $id)) {
            throw new MediaRegistryException("The {$provider} asset id is not in the expected form.");
        }

        return [$provider, (string) $id];
    }

    public function registerExternal(string $reference, array $metadata, ?int $by): MediaAsset
    {
        [$provider, $id] = $this->parseExternalReference($reference);
        $externalRef = "{$provider}:{$id}";

        if ($existing = MediaAsset::query()->where('external_ref', $externalRef)->first()) {
            throw new MediaRegistryException("{$externalRef} is already registered as media asset #{$existing->id}.");
        }

        return DB::transaction(function () use ($provider, $id, $externalRef, $metadata, $by) {
            $asset = MediaAsset::create(array_merge($this->metadata($metadata), [
                'kind' => MediaAsset::KIND_VIDEO,
                'storage' => MediaAsset::STORAGE_EXTERNAL,
                'provider' => $provider,
                'provider_asset_id' => $id,
                'external_ref' => $externalRef,
                'created_by' => $by,
            ]));

            $this->audit->record('media.registered_external', 'media_asset', (string) $asset->id, $by, null, null, null, null, [
                'external_ref' => $externalRef,
            ]);

            return $asset;
        });
    }

    public function storeManaged(UploadedFile $file, string $kind, array $metadata, ?int $by): MediaAsset
    {
        $spec = config("media.managed.{$kind}");
        if (! is_array($spec)) {
            throw new MediaRegistryException('Only image and audio files are managed by BeastieRated; videos are registered by provider reference.');
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($extension, $spec['mimes'], true)) {
            throw new MediaRegistryException("A {$kind} must be one of: " . implode(', ', $spec['mimes']) . '.');
        }
        if ($file->getSize() > $spec['max_kb'] * 1024) {
            throw new MediaRegistryException("The file exceeds the {$spec['max_kb']} KB limit for a {$kind}.");
        }
        // The type detected from the file's bytes (fileinfo), never the client's claim.
        $detected = (string) $file->getMimeType();
        if ($kind === MediaAsset::KIND_IMAGE && ! str_starts_with($detected, 'image/')) {
            throw new MediaRegistryException('The file content is not an image.');
        }
        if ($kind === MediaAsset::KIND_AUDIO
            && ! in_array($detected, (array) ($spec['content_types'][$extension] ?? []), true)) {
            throw new MediaRegistryException("The file content ({$detected}) is not .{$extension} audio.");
        }

        $sha256 = hash_file('sha256', $file->getRealPath());
        $disk = (string) config('media.disk');
        $directory = $kind . '/' . substr($sha256, 0, 2);
        $name = $sha256 . '.' . $extension;
        $path = $directory . '/' . $name;

        if ($existing = MediaAsset::query()->where('disk', $disk)->where('path', $path)->first()) {
            throw new MediaRegistryException("This file is already registered as media asset #{$existing->id}.");
        }

        [$width, $height] = $kind === MediaAsset::KIND_IMAGE ? $this->imageSize($file) : [null, null];

        Storage::disk($disk)->putFileAs($directory, $file, $name);

        return DB::transaction(function () use ($kind, $disk, $path, $sha256, $file, $detected, $width, $height, $metadata, $by) {
            $asset = MediaAsset::create(array_merge($this->metadata($metadata), [
                'kind' => $kind,
                'storage' => MediaAsset::STORAGE_MANAGED,
                'disk' => $disk,
                'path' => $path,
                'sha256' => $sha256,
                'mime_type' => $detected,
                'bytes' => $file->getSize(),
                'width' => $width,
                'height' => $height,
                'created_by' => $by,
            ]));

            $this->audit->record('media.stored_managed', 'media_asset', (string) $asset->id, $by, null, null, null, null, [
                'kind' => $kind, 'disk' => $disk, 'path' => $path, 'sha256' => $sha256,
            ]);

            return $asset;
        });
    }

    public function updateMetadata(MediaAsset $asset, array $metadata, ?int $by): MediaAsset
    {
        $changes = $this->metadata($metadata);
        $before = array_intersect_key($asset->only(array_keys($changes)), $changes);

        $asset->fill($changes);
        if (! $asset->isDirty()) {
            return $asset;
        }
        if ($asset->isDirty(MediaAsset::RENDERED_COLUMNS) && $asset->isCapturedByActiveReviewAttachment()) {
            throw new MediaRegistryException('This asset is captured by a released review; its rendered fields cannot change. Register a corrected asset, stage it and release again.');
        }

        $asset->save();   // the model also refuses identity changes and edits to captured rendered fields

        $this->audit->record('media.metadata_updated', 'media_asset', (string) $asset->id, $by, null, null, null, null, [
            'changed' => array_keys($asset->getChanges()),
            'before' => $this->jsonable($before),
        ]);

        return $asset;
    }

    /**
     * Stage an asset into a role (state pending). For a single-occupancy role, any other
     * pending attachment for the same owner and role is retired, so at most one is staged.
     */
    public function stage(MediaAsset $asset, string $ownerType, string $ownerKey, string $role, ?int $by): MediaAttachment
    {
        $spec = MediaAttachment::roleSpec($ownerType, $role);
        if ($spec === null) {
            throw new MediaRegistryException("[{$role}] is not a {$ownerType} media role.");
        }
        if (! in_array($asset->kind, $spec['kinds'], true)) {
            throw new MediaRegistryException("A {$asset->kind} asset cannot fill the {$role} role (it needs: " . implode(', ', $spec['kinds']) . ').');
        }
        if ($asset->isArchived()) {
            throw new MediaRegistryException('An archived asset cannot be attached.');
        }

        return DB::transaction(function () use ($asset, $ownerType, $ownerKey, $role, $spec, $by) {
            $position = 0;

            if ($spec['single']) {
                MediaAttachment::query()
                    ->where('owner_type', $ownerType)->where('owner_key', $ownerKey)->where('role', $role)
                    ->where('state', MediaAttachment::STATE_PENDING)
                    ->lockForUpdate()->get()
                    ->each(fn (MediaAttachment $old) => $this->markRetired($old, 'superseded by a newer staged attachment', $by));
            } else {
                $position = 1 + (int) MediaAttachment::query()
                    ->where('owner_type', $ownerType)->where('owner_key', $ownerKey)->where('role', $role)
                    ->whereIn('state', [MediaAttachment::STATE_PENDING, MediaAttachment::STATE_ACTIVE])
                    ->max('position');
            }

            $attachment = MediaAttachment::create([
                'media_asset_id' => $asset->id,
                'owner_type' => $ownerType,
                'owner_key' => $ownerKey,
                'role' => $role,
                'position' => $position,
                'state' => MediaAttachment::STATE_PENDING,
            ]);

            $this->audit->record('media.staged', $ownerType, $ownerKey, $by, null, MediaAttachment::STATE_PENDING, null, null, [
                'attachment_id' => $attachment->id, 'media_asset_id' => $asset->id, 'role' => $role,
            ]);

            return $attachment;
        });
    }

    /**
     * Activate a channel/character attachment, atomically retiring the previous occupant
     * of a single-occupancy role. Review-role attachments are refused here: they become
     * active only through Release capture.
     */
    public function activate(MediaAttachment $attachment, ?int $by): MediaAttachment
    {
        if ($attachment->owner_type === MediaAttachment::OWNER_REVIEW) {
            throw new MediaRegistryException('Review media becomes public only through Release (QA pass, then Release).');
        }

        return DB::transaction(function () use ($attachment, $by) {
            $attachment = MediaAttachment::query()->whereKey($attachment->id)->lockForUpdate()->firstOrFail();
            if ($attachment->state !== MediaAttachment::STATE_PENDING) {
                throw new MediaRegistryException('Only a pending attachment can be activated.');
            }

            $this->assertPublishable($attachment);
            $this->activateWithinTransaction($attachment, $by);

            return $attachment->refresh();
        });
    }

    /**
     * Release capture (T3) for one review, called inside ReviewEditorialService::release().
     * For each review role with a staged attachment: retire the current active occupant
     * and activate the staged one. Afterwards the active set equals the proposed set the
     * release was bound to.
     */
    public function captureReviewSet(string $publishedReviewId, ?int $by): void
    {
        $pending = MediaAttachment::query()
            ->where('owner_type', MediaAttachment::OWNER_REVIEW)
            ->where('owner_key', $publishedReviewId)
            ->where('state', MediaAttachment::STATE_PENDING)
            ->lockForUpdate()
            ->orderBy('id')
            ->get()
            ->groupBy('role')
            ->map(fn ($forRole) => $forRole->sortByDesc('id')->first());

        foreach ($pending as $attachment) {
            $this->assertPublishable($attachment);
            $this->activateWithinTransaction($attachment, $by);
        }
    }

    /**
     * Retire an attachment. An ACTIVE review-role attachment is refused: changing what a
     * released review shows is a release decision, made by staging and releasing again.
     */
    public function retire(MediaAttachment $attachment, string $reason, ?int $by): MediaAttachment
    {
        if (trim($reason) === '') {
            throw new MediaRegistryException('A reason is required.');
        }

        return DB::transaction(function () use ($attachment, $reason, $by) {
            $attachment = MediaAttachment::query()->whereKey($attachment->id)->lockForUpdate()->firstOrFail();

            if ($attachment->state === MediaAttachment::STATE_RETIRED) {
                return $attachment;
            }
            if ($attachment->owner_type === MediaAttachment::OWNER_REVIEW && $attachment->state === MediaAttachment::STATE_ACTIVE) {
                throw new MediaRegistryException('Released review media cannot be retired directly; stage the replacement and release again.');
            }

            $from = $attachment->state;
            $this->markRetired($attachment, $reason, $by);

            $this->audit->record('media.retired', $attachment->owner_type, $attachment->owner_key, $by, $from, MediaAttachment::STATE_RETIRED, $reason, null, [
                'attachment_id' => $attachment->id, 'role' => $attachment->role,
            ]);

            return $attachment->refresh();
        });
    }

    /**
     * Archive an asset. Refused while any non-retired attachment uses it, or while a Best
     * For edition's video_asset_id or a published selection's image reference points at
     * it (exact match). Nothing is ever deleted - files stay on the media disk.
     */
    public function archive(MediaAsset $asset, string $reason, ?int $by): MediaAsset
    {
        if (trim($reason) === '') {
            throw new MediaRegistryException('A reason is required.');
        }

        return DB::transaction(function () use ($asset, $reason, $by) {
            $asset = MediaAsset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            if ($asset->isArchived()) {
                return $asset;
            }

            if ($asset->attachments()->where('state', '!=', MediaAttachment::STATE_RETIRED)->exists()) {
                throw new MediaRegistryException('This asset is still attached; retire its attachments first.');
            }
            if ($asset->external_ref !== null
                && DB::table('best_for_editions')->where('video_asset_id', $asset->external_ref)->exists()) {
                throw new MediaRegistryException('A Best For edition references this video; it cannot be archived.');
            }
            $refs = array_values(array_filter([$asset->path, $asset->publicUrl()]));
            if ($refs !== [] && DB::table('best_for_edition_selections')->whereIn('product_image_ref_as_published', $refs)->exists()) {
                throw new MediaRegistryException('A published Best For selection references this file; it cannot be archived.');
            }

            $asset->forceFill([
                'archived_at' => now(),
                'archived_by' => $by,
                'archive_reason' => $reason,
            ])->save();

            $this->audit->record('media.archived', 'media_asset', (string) $asset->id, $by, null, null, $reason);

            return $asset->refresh();
        });
    }

    /**
     * What may go public: an image needs alt text; a video needs a title, a description
     * and its provider upload date (the VideoObject inputs). Applied at activation and at
     * Release capture, and by the editorial service at QA binding.
     */
    public function publishabilityErrors(MediaAttachment $attachment): array
    {
        $asset = $attachment->asset;
        $errors = [];

        if ($asset->isArchived()) {
            $errors[] = "Asset #{$asset->id} is archived.";
        }
        if ($asset->kind === MediaAsset::KIND_IMAGE && ! filled($asset->alt_text)) {
            $errors[] = "Image asset #{$asset->id} ({$attachment->role}) needs alt text.";
        }
        if ($asset->kind === MediaAsset::KIND_VIDEO) {
            foreach (['title', 'description', 'provider_published_at'] as $field) {
                if (! filled($asset->{$field})) {
                    $errors[] = "Video asset #{$asset->id} ({$attachment->role}) needs {$field}.";
                }
            }
        }

        return $errors;
    }

    private function assertPublishable(MediaAttachment $attachment): void
    {
        $errors = $this->publishabilityErrors($attachment);
        if ($errors !== []) {
            throw new MediaRegistryException(implode(' ', $errors));
        }
    }

    private function activateWithinTransaction(MediaAttachment $attachment, ?int $by): void
    {
        $spec = MediaAttachment::roleSpec($attachment->owner_type, $attachment->role);
        $single = (bool) ($spec['single'] ?? true);

        if ($single) {
            MediaAttachment::query()
                ->where('owner_type', $attachment->owner_type)->where('owner_key', $attachment->owner_key)
                ->where('role', $attachment->role)->where('state', MediaAttachment::STATE_ACTIVE)
                ->whereKeyNot($attachment->id)
                ->lockForUpdate()->get()
                ->each(fn (MediaAttachment $old) => $this->markRetired($old, 'replaced', $by));
        }

        $attachment->forceFill([
            'state' => MediaAttachment::STATE_ACTIVE,
            'active_slot' => $single ? MediaAttachment::slotKey($attachment->owner_type, $attachment->owner_key, $attachment->role) : null,
            'activated_at' => now(),
            'activated_by' => $by,
        ])->save();

        $this->audit->record('media.activated', $attachment->owner_type, $attachment->owner_key, $by, MediaAttachment::STATE_PENDING, MediaAttachment::STATE_ACTIVE, null, null, [
            'attachment_id' => $attachment->id, 'media_asset_id' => $attachment->media_asset_id, 'role' => $attachment->role,
        ]);
    }

    private function markRetired(MediaAttachment $attachment, string $reason, ?int $by): void
    {
        $attachment->forceFill([
            'state' => MediaAttachment::STATE_RETIRED,
            'active_slot' => null,
            'retired_at' => now(),
            'retired_by' => $by,
            'retire_reason' => $reason,
        ])->save();
    }

    /** Only the Admin-editable metadata fields, normalised. */
    private function metadata(array $input): array
    {
        $out = [];
        foreach (['title', 'description', 'alt_text', 'rights_note'] as $field) {
            if (array_key_exists($field, $input)) {
                $out[$field] = filled($input[$field]) ? trim((string) $input[$field]) : null;
            }
        }
        if (array_key_exists('duration_seconds', $input)) {
            $out['duration_seconds'] = filled($input['duration_seconds']) ? (int) $input['duration_seconds'] : null;
        }
        if (array_key_exists('provider_published_at', $input)) {
            $out['provider_published_at'] = filled($input['provider_published_at']) ? Carbon::parse($input['provider_published_at']) : null;
        }
        if (array_key_exists('chapters', $input)) {
            $out['chapters'] = $this->chapters($input['chapters']);
        }

        return $out;
    }

    /** Chapters as [{t: seconds, label: string}], in the order given. */
    private function chapters(mixed $chapters): ?array
    {
        if ($chapters === null || $chapters === '' || $chapters === []) {
            return null;
        }
        if (is_string($chapters)) {
            $decoded = json_decode($chapters, true);
            if (! is_array($decoded)) {
                throw new MediaRegistryException('Chapters must be a JSON list of {"t": seconds, "label": text}.');
            }
            $chapters = $decoded;
        }

        $out = [];
        foreach ((array) $chapters as $chapter) {
            if (! is_array($chapter) || ! isset($chapter['t'], $chapter['label']) || ! is_numeric($chapter['t'])) {
                throw new MediaRegistryException('Each chapter needs a numeric "t" (seconds) and a "label".');
            }
            $out[] = ['t' => (int) $chapter['t'], 'label' => (string) $chapter['label']];
        }

        return $out;
    }

    /** @return array{0: ?int, 1: ?int} */
    private function imageSize(UploadedFile $file): array
    {
        $size = @getimagesize($file->getRealPath());

        return $size === false ? [null, null] : [(int) $size[0], (int) $size[1]];
    }

    private function jsonable(array $values): array
    {
        return array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : $v, $values);
    }
}

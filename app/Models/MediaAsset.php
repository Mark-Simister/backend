<?php

namespace App\Models;

use App\Models\Concerns\RefusesHardDelete;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * The provider-neutral media registry (design record sections 7 and 12.4).
 *
 *   external  a provider reference (videos). Laravel never hosts video binaries.
 *             `external_ref` = provider:provider_asset_id - the same encoding as
 *             best_for_editions.video_asset_id.
 *   managed   a BeastieRated-managed image/audio file on the dedicated media disk.
 *
 * Identity columns are immutable once created. The publicly rendered fields are
 * immutable while the asset is captured by an active review attachment - a correction
 * means registering a new asset and releasing again. Assets are archived, never deleted.
 */
class MediaAsset extends Model
{
    use RefusesHardDelete;

    public const KIND_VIDEO = 'video';
    public const KIND_IMAGE = 'image';
    public const KIND_AUDIO = 'audio';

    public const STORAGE_EXTERNAL = 'external';
    public const STORAGE_MANAGED = 'managed';

    /** Columns that define what the asset IS. Never change after creation. */
    public const IDENTITY_COLUMNS = ['kind', 'storage', 'provider', 'provider_asset_id', 'external_ref', 'disk', 'path', 'sha256'];

    /** Columns a public surface renders (they feed the M1 media fingerprint). */
    public const RENDERED_COLUMNS = ['title', 'description', 'alt_text', 'provider_published_at', 'duration_seconds', 'chapters'];

    protected $table = 'media_assets';

    protected $fillable = [
        'kind', 'storage', 'provider', 'provider_asset_id', 'external_ref',
        'disk', 'path', 'sha256', 'mime_type', 'bytes', 'width', 'height', 'duration_seconds',
        'title', 'description', 'alt_text', 'rights_note',
        'chapters', 'provider_metadata', 'provider_checked_at', 'provider_published_at',
        'created_by',
    ];

    protected $casts = [
        'bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'duration_seconds' => 'integer',
        'chapters' => 'array',
        'provider_metadata' => 'array',
        'provider_checked_at' => 'datetime',
        'provider_published_at' => 'datetime',
        'archived_at' => 'datetime',
        'archived_by' => 'integer',
        'created_by' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function (MediaAsset $asset) {
            $identity = array_intersect(array_keys($asset->getDirty()), self::IDENTITY_COLUMNS);
            if ($identity !== []) {
                throw new LogicException('Media asset identity is immutable: ' . implode(', ', $identity) . '.');
            }

            $rendered = array_intersect(array_keys($asset->getDirty()), self::RENDERED_COLUMNS);
            if ($rendered !== [] && $asset->isCapturedByActiveReviewAttachment()) {
                throw new LogicException(
                    'This asset is captured by a released review; its rendered fields are immutable. '
                    . 'Register a corrected asset and release again.'
                );
            }
        });
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MediaAttachment::class);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function isExternal(): bool
    {
        return $this->storage === self::STORAGE_EXTERNAL;
    }

    public function isCapturedByActiveReviewAttachment(): bool
    {
        return $this->exists && MediaAttachment::query()
            ->where('media_asset_id', $this->id)
            ->where('owner_type', MediaAttachment::OWNER_REVIEW)
            ->where('state', MediaAttachment::STATE_ACTIVE)
            ->exists();
    }

    /**
     * The embed URL, built on the server from the allow-listed provider template.
     * Null for managed assets or an unknown provider (fail closed).
     */
    public function embedUrl(): ?string
    {
        if (! $this->isExternal() || $this->provider === null || $this->provider_asset_id === null) {
            return null;
        }

        $template = config("media.providers.{$this->provider}.embed");

        return is_string($template) ? str_replace('{id}', rawurlencode($this->provider_asset_id), $template) : null;
    }

    /**
     * Public URL of a managed file. Requires the explicit MEDIA_URL; when it is not
     * configured no URL is produced (fail closed) - APP_URL is never used for media.
     */
    public function publicUrl(): ?string
    {
        if ($this->isExternal() || $this->disk === null || $this->path === null) {
            return null;
        }

        if (! filled(config("filesystems.disks.{$this->disk}.url"))) {
            return null;
        }

        return Storage::disk($this->disk)->url($this->path);
    }
}

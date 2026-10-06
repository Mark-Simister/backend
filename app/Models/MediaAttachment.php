<?php

namespace App\Models;

use App\Models\Concerns\RefusesHardDelete;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which media asset fills which role for which owner (design record sections 7, 12.4).
 *
 * Lifecycle: pending -> active -> retired, or pending -> retired. A single-occupancy
 * role holds at most one active attachment, enforced by the nullable-unique
 * `active_slot`. Review-role attachments become active ONLY through Release capture
 * (T3). Attachments are retired, never deleted. State changes only through
 * App\Services\Media\MediaRegistry.
 */
class MediaAttachment extends Model
{
    use RefusesHardDelete;

    public const OWNER_REVIEW = 'review';
    public const OWNER_CHANNEL = 'channel';
    public const OWNER_CHARACTER = 'character';

    public const STATE_PENDING = 'pending';
    public const STATE_ACTIVE = 'active';
    public const STATE_RETIRED = 'retired';

    protected $table = 'media_attachments';

    protected $fillable = [
        'media_asset_id', 'owner_type', 'owner_key', 'role', 'position', 'state',
    ];

    protected $attributes = [
        'state' => self::STATE_PENDING,
        'position' => 0,
    ];

    protected $casts = [
        'media_asset_id' => 'integer',
        'position' => 'integer',
        'activated_at' => 'datetime',
        'activated_by' => 'integer',
        'retired_at' => 'datetime',
        'retired_by' => 'integer',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    public static function slotKey(string $ownerType, string $ownerKey, string $role): string
    {
        return "{$ownerType}:{$ownerKey}:{$role}";
    }

    /** @return array{kinds: string[], single: bool}|null */
    public static function roleSpec(string $ownerType, string $role): ?array
    {
        $spec = config("media.roles.{$ownerType}.{$role}");

        return is_array($spec) ? $spec : null;
    }
}

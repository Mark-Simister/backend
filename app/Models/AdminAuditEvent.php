<?php

namespace App\Models;

use App\Models\Concerns\RefusesHardDelete;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only audit trail for Admin Content & Media Operations
 * (design record sections 4.9 and 12.4).
 *
 * Rows are written once by App\Services\Audit\AdminAuditLog and never updated or
 * deleted. `details` carries old/new values of Admin-owned fields only - never n8n
 * review content. `actor_id` is NULL for system actions.
 */
class AdminAuditEvent extends Model
{
    use RefusesHardDelete;

    protected $table = 'admin_audit_events';

    public $timestamps = false;

    protected $fillable = [
        'occurred_at',
        'actor_id',
        'action',
        'subject_type',
        'subject_key',
        'from_state',
        'to_state',
        'reason',
        'content_fingerprint',
        'details',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'actor_id' => 'integer',
        'details' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Admin audit events are append-only and cannot be modified.');
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

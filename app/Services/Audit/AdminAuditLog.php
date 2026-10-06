<?php

namespace App\Services\Audit;

use App\Models\AdminAuditEvent;

/**
 * Writes the append-only Admin audit trail (design record section 4.9). Every editorial
 * transition and every action with public consequences records one event. `details`
 * must carry Admin-owned values only - never n8n review content.
 */
class AdminAuditLog
{
    public function record(
        string $action,
        string $subjectType,
        string $subjectKey,
        ?int $actorId = null,
        ?string $fromState = null,
        ?string $toState = null,
        ?string $reason = null,
        ?string $contentFingerprint = null,
        array $details = [],
    ): AdminAuditEvent {
        return AdminAuditEvent::create([
            'occurred_at' => now(),
            'actor_id' => $actorId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_key' => $subjectKey,
            'from_state' => $fromState,
            'to_state' => $toState,
            'reason' => $reason,
            'content_fingerprint' => $contentFingerprint,
            'details' => $details === [] ? null : $details,
        ]);
    }
}

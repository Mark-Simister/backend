<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin Content & Media Operations - the append-only audit trail
 * (design record sections 4.9 and 12.4, `admin_audit_events`).
 *
 * `details` holds old/new values of Admin-owned fields only, never n8n review content.
 * The model has no update or delete path. `actor_id` is NULL for system actions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_audit_events', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at');
            $table->foreignId('actor_id')->nullable()->constrained('users', 'id', 'admin_audit_actor_fk')->nullOnDelete();
            $table->string('action', 64);
            $table->string('subject_type', 32);
            $table->string('subject_key', 191);
            $table->string('from_state', 32)->nullable();
            $table->string('to_state', 32)->nullable();
            $table->text('reason')->nullable();
            $table->char('content_fingerprint', 64)->nullable();
            $table->json('details')->nullable();

            $table->index(['subject_type', 'subject_key', 'occurred_at'], 'admin_audit_subject_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_events');
    }
};

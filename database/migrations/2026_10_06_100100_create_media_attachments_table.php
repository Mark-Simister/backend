<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin Content & Media Operations - which asset fills which role for which owner
 * (design record section 12.4, `media_attachments`).
 *
 * `active_slot` is a nullable UNIQUE column holding `owner_type:owner_key:role` only
 * while a single-occupancy role is active - the same nullable-unique technique as
 * best_for_editions.current_for_subject_id. Neither MySQL nor SQLite collides NULLs in
 * a unique index, so pending and retired rows never conflict.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_asset_id')->constrained('media_assets')->restrictOnDelete();
            $table->enum('owner_type', ['review', 'channel', 'character']);
            $table->string('owner_key', 191);
            $table->string('role', 48);
            $table->unsignedSmallInteger('position')->default(0);
            $table->enum('state', ['pending', 'active', 'retired'])->default('pending');
            $table->string('active_slot', 255)->nullable();

            $table->timestamp('activated_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('retired_at')->nullable();
            $table->foreignId('retired_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('retire_reason')->nullable();
            $table->timestamps();

            $table->unique('active_slot', 'media_att_active_slot_unique');
            $table->index(['owner_type', 'owner_key', 'state'], 'media_att_owner_state_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_attachments');
    }
};

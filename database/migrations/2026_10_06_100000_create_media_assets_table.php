<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\Schema;

/**
 * Admin Content & Media Operations - the provider-neutral media registry
 * (design record section 12.4, `media_assets`).
 *
 * External items are provider references only (videos are never hosted by Laravel):
 * `provider` + `provider_asset_id`, plus the immutable `external_ref`
 * (`provider:provider_asset_id`) shared with best_for_editions.video_asset_id.
 * Managed items (images, audio) are files on the dedicated `media` disk, identified by
 * `disk` + `path` and content-addressed by `sha256`.
 *
 * Index names are explicit and short so every identifier stays within MySQL's
 * 64-character limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->enum('kind', ['video', 'image', 'audio']);
            $table->enum('storage', ['external', 'managed']);

            // External provider reference. Provider ids are case-sensitive (YouTube), so
            // on MySQL the id and the reference are binary-collated: uniqueness and
            // lookups are exact, as they already are on SQLite.
            $table->string('provider', 32)->nullable();
            $this->exact($table->string('provider_asset_id', 191)->nullable());
            $this->exact($table->string('external_ref', 230)->nullable());

            // Managed file.
            $table->string('disk', 64)->nullable();
            $table->string('path', 255)->nullable();
            $table->char('sha256', 64)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('bytes')->nullable();

            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            $table->string('title', 255)->nullable();
            $table->text('description')->nullable();
            $table->text('alt_text')->nullable();
            $table->text('rights_note')->nullable();

            $table->json('chapters')->nullable();
            $table->json('provider_metadata')->nullable();
            $table->timestamp('provider_checked_at')->nullable();
            $table->timestamp('provider_published_at')->nullable();

            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('archive_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['provider', 'provider_asset_id'], 'media_assets_provider_asset_unique');
            $table->unique('external_ref', 'media_assets_external_ref_unique');
            $table->unique(['disk', 'path'], 'media_assets_disk_path_unique');
            $table->index('sha256', 'media_assets_sha256_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }

    private function exact(ColumnDefinition $column): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            $column->collation('utf8mb4_bin');
        }
    }
};

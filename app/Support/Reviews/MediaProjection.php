<?php

namespace App\Support\Reviews;

use App\Models\MediaAttachment;

/**
 * The M1 media projection (design record section 6.2): the sorted list of
 * {role, attachment_id, asset:{id, kind, storage, external_ref, disk, path, sha256,
 * title, description, alt_text, provider_published_at, duration_seconds, chapters}}
 * for a review's attachment set. Values are normalised so the projection is identical
 * on SQLite and MySQL (integers as int, the upload date as Y-m-d H:i:s).
 */
final class MediaProjection
{
    /**
     * @param  iterable<MediaAttachment>  $attachments  each with `asset` loaded or loadable
     */
    public static function build(iterable $attachments): array
    {
        $rows = [];

        foreach ($attachments as $attachment) {
            $asset = $attachment->asset;

            $rows[] = [
                'role' => (string) $attachment->role,
                'attachment_id' => (int) $attachment->id,
                'asset' => [
                    'id' => (int) $asset->id,
                    'kind' => $asset->kind,
                    'storage' => $asset->storage,
                    'external_ref' => $asset->external_ref,
                    'disk' => $asset->disk,
                    'path' => $asset->path,
                    'sha256' => $asset->sha256,
                    'title' => $asset->title,
                    'description' => $asset->description,
                    'alt_text' => $asset->alt_text,
                    'provider_published_at' => $asset->provider_published_at?->format('Y-m-d H:i:s'),
                    'duration_seconds' => $asset->duration_seconds === null ? null : (int) $asset->duration_seconds,
                    'chapters' => $asset->chapters,
                ],
            ];
        }

        usort($rows, fn (array $a, array $b) => [$a['role'], $a['attachment_id']] <=> [$b['role'], $b['attachment_id']]);

        return $rows;
    }
}

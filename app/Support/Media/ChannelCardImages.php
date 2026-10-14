<?php

namespace App\Support\Media;

use App\Models\MediaAsset;
use App\Models\MediaAttachment;

/**
 * Public channel card artwork from the managed-media registry (design record sections 7
 * and 9.2; BR-CHANNEL-MEDIA-ADAPTER-A0).
 *
 * A channel's card artwork is its active `card_image` attachment. Only that role is
 * consulted: `hero_artwork` is a separate slot and is never used as a substitute. Pending
 * and retired attachments, archived or non-image assets, and any asset without a public
 * URL (MEDIA_URL unset - MediaAsset::publicUrl() fails closed) are ignored, so the caller
 * falls back to the legacy `channels.image` column.
 *
 * All active channel card images are read in one batched lookup per HTTP request and kept
 * on that request, so a listing of N channels costs the same as a single channel. It is
 * consulted only by public channel-discovery listings (Channel::useCardImage() and
 * Channel::cardImageUrl()); channel detail, admin, console and queued serialisation keep
 * the legacy value and never reach this lookup.
 *
 * Attachment identity: a channel's `owner_key` is the decimal `channels.id`. Database ids
 * are environment-specific, so channel attachments must be reconciled (re-keyed) if media
 * content is ever transferred between environments.
 */
final class ChannelCardImages
{
    public const ROLE = 'card_image';

    private const REQUEST_ATTRIBUTE = 'br.channel_card_images';

    /** @var array<string, string>|null channel id (owner_key) => public URL */
    private ?array $urls = null;

    /** The resolver for the current request, created on first use. */
    public static function forCurrentRequest(): self
    {
        $attributes = app('request')->attributes;
        $resolver = $attributes->get(self::REQUEST_ATTRIBUTE);

        if (! $resolver instanceof self) {
            $resolver = new self();
            $attributes->set(self::REQUEST_ATTRIBUTE, $resolver);
        }

        return $resolver;
    }

    /** The managed card image URL for a channel, or null when none is eligible. */
    public function urlFor(int|string|null $channelId): ?string
    {
        if ($channelId === null || $channelId === '') {
            return null;
        }

        $this->urls ??= $this->load();

        return $this->urls[(string) $channelId] ?? null;
    }

    /** @return array<string, string> */
    private function load(): array
    {
        $attachments = MediaAttachment::query()
            ->where('owner_type', MediaAttachment::OWNER_CHANNEL)
            ->where('role', self::ROLE)
            ->where('state', MediaAttachment::STATE_ACTIVE)
            ->with('asset')
            ->orderBy('id')
            ->get();

        $urls = [];
        foreach ($attachments as $attachment) {
            $asset = $attachment->asset;
            if ($asset === null || $asset->kind !== MediaAsset::KIND_IMAGE || $asset->isArchived()) {
                continue;
            }

            $url = $asset->publicUrl();
            if ($url !== null) {
                $urls[(string) $attachment->owner_key] = $url;
            }
        }

        return $urls;
    }
}

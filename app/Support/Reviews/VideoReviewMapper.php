<?php

namespace App\Support\Reviews;

use App\Models\Video;

/**
 * Transforms an admin video into the payload's review_page_json shape.
 *
 * VERIFIED AGAINST REAL DATA (not an assumed shape): only 1 of 24 top-level keys
 * (`qa`) is identical between videos.review and review_page_json — everything
 * else is restructured or synthesized from video columns/relations.
 *
 * Two paths:
 *   rich — videos.review has genuine content (verdict/pros/cons/report/Q&A)
 *   thin — no review content: product + score only. Sections with no substance
 *          are OMITTED entirely (never faked); the Blade guards missing sections.
 */
class VideoReviewMapper
{
    /** Sections whose presence means there is genuine review content. */
    public const SUBSTANCE_KEYS = ['quick_verdict', 'pros', 'cons', 'full_report', 'owner_consensus', 'qa'];

    public function contentTier(Video $video): string
    {
        $r = is_array($video->review) ? $video->review : [];

        foreach (self::SUBSTANCE_KEYS as $key) {
            $v = $r[$key] ?? null;
            $has = is_array($v)
                ? count(array_filter($v, fn ($x) => ! blank($x))) > 0
                : ! blank($v);
            if ($has) {
                return 'rich';
            }
        }

        return 'thin';
    }

    /** @return array the review_page_json payload section */
    public function map(Video $video, string $slug, string $tier): array
    {
        $r = is_array($video->review) ? $video->review : [];
        $character = $video->character;
        $channel = $video->channel;

        $productName = (string) $video->product_name;
        $heroImage = $r['hero_image'] ?? $video->thumb_url;
        $h1 = $r['h1'] ?? ($productName . ' Review');

        // ---- always present (genuinely known from columns/relations) ----
        $out = [
            'page_identity' => array_filter([
                'published_review_id' => 'admin__' . $video->id,
                'review_slug' => $slug,
                'h1' => $h1,
                'seo_title' => $video->seo_title ?: $h1,
                'seo_description' => $video->seo_description ?: ($r['product_subtitle'] ?? null),
                'og_image_url' => $video->og_image_url ?: $heroImage,
                'status' => 'admin_published',
            ], fn ($v) => ! blank($v)),

            'product' => array_filter([
                'product_name' => $productName,
                'brand' => $r['product_details']['brand'] ?? null,
                'asin' => $video->product_asin_sku,
                'category' => $r['product_details']['category'] ?? null,
                'hero_image_url' => $heroImage,
            ], fn ($v) => ! blank($v)),

            'hero' => array_filter([
                'title' => $h1,
                'subtitle' => $r['product_subtitle'] ?? null,
                'image_url' => $heroImage,
                'channel' => $channel?->name,
                'character_name' => $character?->name,
                'character_id' => $video->character_id,
            ], fn ($v) => ! blank($v)),

            'beastiescore' => array_filter([
                'final_score' => $video->final_beastie_score,
                'public_score' => $video->public_rating,
                'character_score' => $video->character_score,
                'editorial_score' => $video->editorial_score,
                'confidence_tier' => $r['confidence'] ?? null,
            ], fn ($v) => $v !== null),

            'seo' => array_filter([
                'seo_title' => $video->seo_title ?: $h1,
                'seo_description' => $video->seo_description ?: ($r['product_subtitle'] ?? null),
                'og_image_url' => $video->og_image_url ?: $heroImage,
                'review_slug' => $slug,
            ], fn ($v) => ! blank($v)),

            'disclosure' => [
                'affiliate_disclosure_text' => $r['disclosure']
                    ?? 'BeastieRated is an AI-powered review service. Our verdicts are generated from aggregated public review data and cited third-party sources. We do not conduct hands-on product testing. As an Amazon Associate, we earn from qualifying purchases.',
            ],
        ];

        // commerce — only what genuinely exists
        $commerce = array_filter([
            'primary_affiliate_url' => $video->affiliate_link,
            'retailers' => $r['retailers'] ?? [],
            'all_alternatives' => $r['alternatives'] ?? [],
        ], fn ($v) => ! blank($v));
        if ($commerce) {
            $out['commerce'] = $commerce;
        }

        // video — only when a real video exists
        if (filled($video->video_url) || filled($r['chapters'] ?? null)) {
            $out['video'] = array_filter([
                'video_url' => $video->video_url,
                'youtube_id' => $video->youtube_id,
                'chapters' => $r['chapters'] ?? [],
            ], fn ($v) => ! blank($v));
        }

        if ($tier === 'thin') {
            return $out;   // stop here — never synthesize review content that doesn't exist
        }

        // ---- rich only: real review content, restructured into the target shape ----
        if (! blank($r['quick_verdict'] ?? null)) {
            $out['quick_verdict'] = array_filter([
                'summary' => $r['product_subtitle'] ?? null,
                'good_for' => $r['quick_verdict']['good_for'] ?? null,
                'watch_out_for' => $r['quick_verdict']['watch_out_for'] ?? null,
                'beastie_take' => $r['quick_verdict']['beastie_take'] ?? null,
                'cta_text' => $video->cta_text,
            ], fn ($v) => ! blank($v));
        }

        if (! blank($r['character_take'] ?? null)) {
            $out['character_take'] = array_filter([
                'character_id' => $video->character_id,
                'character_name' => $character?->name,
                'quote' => $r['character_take']['quote'] ?? null,
                'summary' => $r['character_take']['text'] ?? null,       // text → summary
                'highlights' => $r['character_take']['trait_chips'] ?? [], // trait_chips → highlights
            ], fn ($v) => ! blank($v));
        }

        if (! blank($r['pros'] ?? null) || ! blank($r['cons'] ?? null)) {
            $out['pros_cons'] = array_filter([
                'pros' => $r['pros'] ?? [],
                'cons' => $r['cons'] ?? [],
            ], fn ($v) => ! blank($v));
        }

        if (! blank($r['best_for'] ?? null) || ! blank($r['not_best_for'] ?? null)) {
            $out['buyer_fit'] = array_filter([
                'summary' => $r['quick_verdict']['good_for'] ?? null,
                'best_for' => $r['best_for'] ?? [],
                'not_best_for' => $r['not_best_for'] ?? [],
            ], fn ($v) => ! blank($v));
        }

        if (! blank($r['full_report'] ?? null)) {
            $out['full_character_review'] = array_filter([
                'overview' => $r['owner_consensus'] ?? null,
                'character_verdict' => $r['character_take']['text'] ?? null,
                'sections' => $r['full_report'] ?? [],
            ], fn ($v) => ! blank($v));
        }

        if (! blank($r['owner_consensus'] ?? null)) {
            $out['owner_consensus'] = ['summary' => $r['owner_consensus']];   // string → object
        }

        if (! blank($r['sources'] ?? null)) {
            $out['sources'] = [
                'typed_source_links' => array_map(fn ($s) => array_filter([
                    'domain' => $s['name'] ?? null,
                    'url' => $s['url'] ?? null,
                    'type' => 'review',
                ], fn ($v) => ! blank($v)), $r['sources']),
            ];
        }

        if (! blank($r['safety_note'] ?? null)) {
            $out['safety'] = ['public_safety_note' => $r['safety_note']];
        }

        if (! blank($r['evidence'] ?? null)) {
            $out['evidence_breakdown'] = array_filter([
                'public_rating_count_retailer' => $r['reviews_analysed'] ?? null,
                'analysed_evidence_count' => $this->evidenceItem($r, 'Analysed Signals'),
                'source_count' => $this->evidenceItem($r, 'Source Families'),
            ], fn ($v) => $v !== null);
        }

        if (! blank($r['qa'] ?? null)) {
            $out['qa'] = $r['qa'];   // the one 1:1 mapping
        }

        if (! blank($r['transcript'] ?? null)) {
            $out['transcript'] = ['available' => true, 'text' => $r['transcript']];
        }

        // review_facts — the LLM-extractable block, only when there's real content
        $out['review_facts'] = array_filter([
            'product' => $productName,
            'brand' => $r['product_details']['brand'] ?? null,
            'category' => $r['product_details']['category'] ?? null,
            'reviewer' => $character?->name,
            'channel' => $channel?->name,
            'beastiescore' => $video->final_beastie_score,
            'public_score' => $video->public_rating,
            'public_rating_count' => $r['reviews_analysed'] ?? null,
            'confidence_tier' => $r['confidence'] ?? null,
            'best_for' => $r['best_for'] ?? [],
            'watch_out_for' => $r['not_best_for'] ?? [],
        ], fn ($v) => ! blank($v));

        return $out;
    }

    /** Pull a labelled count out of review.evidence.items ([[label, value], …]). */
    private function evidenceItem(array $r, string $label): ?int
    {
        foreach (($r['evidence']['items'] ?? []) as $item) {
            if (($item[0] ?? null) === $label && is_numeric($item[1] ?? null)) {
                return (int) $item[1];
            }
        }

        return null;
    }
}

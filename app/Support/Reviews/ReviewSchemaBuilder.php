<?php

namespace App\Support\Reviews;

use App\Models\Video;

/**
 * Builds the schema.org JSON-LD graph for an admin-published review.
 *
 * HONESTY RULES (never mark up content that doesn't exist):
 *   always : Organization, WebPage, BreadcrumbList, Product
 *   rich   : + Review (rating/body/pros/cons), + FAQPage (iff Q&A),
 *            + Product.aggregateRating (iff a real public score AND a real count)
 *   thin   : NO Review, NO AggregateRating, NO FAQPage — ever.
 * VideoObject only when a real video_url exists.
 *
 * URLs are built on a beastierated.com base; PublicReviewPageController rewrites
 * that host to the actual serving (regional) host at render time.
 */
class ReviewSchemaBuilder
{
    public const BASE = 'https://beastierated.com';

    public function build(Video $video, string $slug, string $tier): array
    {
        $r = is_array($video->review) ? $video->review : [];
        $url = self::BASE . '/review/' . $slug;
        $productId = $url . '#product';
        $orgId = self::BASE . '#organization';

        $name = (string) $video->product_name;
        $image = $r['hero_image'] ?? $video->thumb_url;

        $graph = [];

        $graph[] = [
            '@type' => 'Organization',
            '@id' => $orgId,
            'name' => 'BeastieRated',
            'url' => self::BASE,
        ];

        $graph[] = array_filter([
            '@type' => 'WebPage',
            '@id' => $url . '#webpage',
            'url' => $url,
            'name' => $r['h1'] ?? ($name . ' Review'),
            'description' => $video->seo_description ?: ($r['product_subtitle'] ?? null),
            'isPartOf' => ['@id' => $orgId],
        ], fn ($v) => ! blank($v));

        $graph[] = [
            '@type' => 'BreadcrumbList',
            '@id' => $url . '#breadcrumb',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => self::BASE],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $name, 'item' => $url],
            ],
        ];

        // ---- Product (always: this is genuinely known) ----
        $product = array_filter([
            '@type' => 'Product',
            '@id' => $productId,
            'name' => $name,
            'sku' => $video->product_asin_sku,
            'image' => $image,
            'brand' => ! blank($r['product_details']['brand'] ?? null)
                ? ['@type' => 'Brand', 'name' => $r['product_details']['brand']]
                : null,
            'category' => $r['product_details']['category'] ?? null,
        ], fn ($v) => ! blank($v));

        if (filled($video->affiliate_link)) {
            $product['offers'] = array_filter([
                '@type' => 'Offer',
                'url' => $video->affiliate_link,
                'availability' => 'https://schema.org/InStock',
            ]);
        }

        if ($tier === 'rich') {
            // aggregateRating ONLY with a real public score AND a real count.
            $count = $r['reviews_analysed'] ?? null;
            if ($video->public_rating !== null && is_numeric($count) && (int) $count > 0) {
                $product['aggregateRating'] = [
                    '@type' => 'AggregateRating',
                    'ratingValue' => (float) $video->public_rating,
                    'bestRating' => 5,
                    'ratingCount' => (int) $count,
                ];
            }
            $product['review'] = ['@id' => $url . '#review'];
        }

        $graph[] = $product;

        // ---- Review + FAQPage: rich only, never synthesized ----
        if ($tier === 'rich') {
            $review = array_filter([
                '@type' => 'Review',
                '@id' => $url . '#review',
                'url' => $url,
                'itemReviewed' => ['@id' => $productId],
                'author' => ['@type' => 'Person', 'name' => $video->character?->name ?: 'BeastieRated'],
                'publisher' => ['@id' => $orgId],
                'reviewBody' => $this->reviewBody($r),
                'positiveNotes' => $this->notes($r['pros'] ?? []),
                'negativeNotes' => $this->notes($r['cons'] ?? []),
            ], fn ($v) => ! blank($v));

            if ($video->final_beastie_score !== null) {
                $review['reviewRating'] = [
                    '@type' => 'Rating',
                    'ratingValue' => (float) $video->final_beastie_score,
                    'bestRating' => 5,
                    'worstRating' => 0,
                ];
            }
            $graph[] = $review;

            $faq = $this->faq($r['qa'] ?? []);
            if ($faq) {
                $graph[] = ['@type' => 'FAQPage', '@id' => $url . '#faq', 'mainEntity' => $faq];
            }
        }

        // ---- VideoObject only when a real video exists ----
        if (filled($video->video_url)) {
            $graph[] = array_filter([
                '@type' => 'VideoObject',
                'name' => $r['h1'] ?? ($name . ' Review'),
                'thumbnailUrl' => $video->thumb_url ?: $image,
                'contentUrl' => $video->video_url,
                'embedUrl' => $video->video_url,
            ], fn ($v) => ! blank($v));
        }

        return ['@context' => 'https://schema.org', '@graph' => array_values($graph)];
    }

    private function reviewBody(array $r): ?string
    {
        $parts = array_filter([
            $r['product_subtitle'] ?? null,
            $r['owner_consensus'] ?? null,
        ]);
        foreach (($r['full_report'] ?? []) as $sec) {
            if (! blank($sec['body'] ?? null)) {
                $parts[] = trim(($sec['heading'] ?? '') . ': ' . $sec['body']);
            }
        }

        return $parts ? trim(implode("\n\n", $parts)) : null;
    }

    private function notes(array $items): ?array
    {
        $items = array_values(array_filter($items));
        if (! $items) {
            return null;
        }

        return [
            '@type' => 'ItemList',
            'itemListElement' => array_map(fn ($t, $i) => [
                '@type' => 'ListItem', 'position' => $i + 1, 'name' => $t,
            ], $items, array_keys($items)),
        ];
    }

    private function faq(array $qa): array
    {
        $out = [];
        foreach ($qa as $item) {
            $q = $item['q'] ?? $item['question'] ?? null;
            $a = $item['a'] ?? $item['answer'] ?? null;
            if ($q && $a) {
                $out[] = ['@type' => 'Question', 'name' => $q, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a]];
            }
        }

        return $out;
    }
}

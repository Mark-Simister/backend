<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class VideoResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            "id" => $this->id,
            "title" => $this->title,
            "description" => $this->description,
            "type" => $this->type,
            "video_url" => $this->video_url,

            // Arrays
            "tags" => $this->csvToArray($this->tags),
            'tag_ids' => $this->tag_pairs, 
            "highlight_tags" => $this->mapHighlightTags($this->highlight_tags),
            "hashtags" => $this->csvToArray($this->hashtags),
            "video_platforms" => $this->jsonToArray($this->video_platforms),

            // Rating / Review
            "rating_type" => $this->rating_type,
            "sponsorship_type" => $this->sponsorship_type,
            "public_rating" => $this->public_rating,

            // dynamically calculated from reviews relation
            "review_details" => $this->whenLoaded('reviews', function () {
                $count = $this->reviews->count();
                if ($count === 0) {
                    return null; // no reviews -> no rating block
                }

                $avg = round($this->reviews->avg('rating'), 1);

                $stars = [];
                for ($i = 1; $i <= 5; $i++) {
                    $stars[$i] = $this->reviews->where('rating', $i)->count();
                }

                return [
                    'count' => $count,
                    'avg' => $avg,
                    'stars' => $stars,
                ];
            }),

            // Assets with full URL
            "thumbnail_url" => $this->assetUrl($this->thumbnail_url ?: $this->thumbnail_image),
            "product_thumbnail" => $this->assetUrl($this->product_thumbnail),
            "caption_file" => $this->assetUrl($this->caption_file),
            "raw_video_path" => $this->assetUrl($this->raw_video_path),

            // Other fields (direct)
            "status" => $this->status,
            "is_ai_generated" => (bool) $this->is_ai_generated,
            "is_finalized" => (bool) $this->is_finalized,
            "qa_passed" => (bool) $this->qa_passed,
            "post_schedule_at" => $this->post_schedule_at,

            "seo_title" => $this->seo_title,
            "seo_description" => $this->seo_description,
            "cta_text" => $this->cta_text,
            "og_image_url" => $this->og_image_url,
            "twitter_title" => $this->twitter_title,
            "twitter_description" => $this->twitter_description,

            'regions' => $this->whenLoaded('regions', function () {
                return $this->regions->map(function ($region) {
                    return [
                        'id' => $region->id,
                        'region_name' => $region->region_name, // Include the region name
                    ];
                });
            }),

            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at,
        ];
    }

    private function csvToArray($value)
    {
        // Accept: null | string (e.g. "a,b,c") | array(["a","b","c"])
        if (is_null($value))
            return [];
        if (is_array($value)) {
            // Normalize inner values
            return array_values(array_filter(array_map(
                fn($v) => is_string($v) ? trim($v) : $v,
                $value
            )));
        }
        // assume string
        return array_values(array_filter(array_map('trim', explode(',', (string) $value))));
    }

    private function jsonToArray($value)
    {
        // Accept: null | string(JSON) | array
        if (is_null($value))
            return [];
        if (is_array($value))
            return $value;

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function jsonToObject($value)
    {
        // Accept: null | string(JSON) | array/object
        if (is_null($value))
            return null;
        if (is_array($value) || is_object($value))
            return $value;

        $decoded = json_decode($value, true);
        return $decoded ?? null;
    }


    private function assetUrl($path)
    {
        if (!$path)
            return null;
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }
        return asset($path);
    }
    private function mapHighlightTags($value)
{
    $ids = $this->csvToArray($value);
    if (empty($ids)) return [];

    $tags = \App\Models\HighlightTag::whereIn('id', $ids)
        ->get(['id','label','emoji'])->keyBy('id');

    return collect($ids)->map(function ($id) use ($tags) {
        $tag = $tags->get((int) $id);
        return $tag ? [
            'id'    => $tag->id,
            'label' => $tag->label,
            'emoji' => $tag->emoji,
        ] : null;
    })->filter()->values();
}
}
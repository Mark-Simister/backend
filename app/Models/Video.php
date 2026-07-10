<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Video extends Model
{
    use HasFactory;
    protected $appends = ['tag_ids_array'];
    protected $fillable = [
        'title',
        'description',
        'type',
        'video_type',
        'video_platforms',
        'video_url',
        'is_featured',
        'thumbnail_url',
        'thumbnail_image',
        'youtube_id',
        'wistia_id',
        'raw_video_path',
        'raw_video_file',
        'caption_file',

        'product_name',
        'product_asin_sku',
        'affiliate_link',
        'public_rating',
        'character_score',
        'editorial_score',
        'final_beastie_score',
        'product_thumbnail',

        'character_id',
        'channel_id',
        'category_id',
        'access_level',
        'tags',
        'tag_ids',
        'highlight_tags',
        'auto_tags',


        'review_type',
        'public_rating',
        'review_details',
        'review',
        'sponsored',
        'sponsorship_type',

        'is_draft',
        'status',
        'is_ai_generated',
        'is_finalized',
        'is_qa_passed',
        'post_schedule_at',

        'seo_title',
        'seo_description',
        'hashtags',
        'cta_text',
        'og_image_url',
        'open_graph_image',
        'twitter_title',
        'twitter_description',

        // Phase 4 — admin SEO publishing state
        'review_slug',
        'seo_publish_status',
        'seo_published_at',
        'seo_last_published_at',
        'seo_publish_error',
    ];

    protected $casts = [
        'highlight_tags' => 'array',
        'auto_tags' => 'array',
        'hashtags' => 'array',
        'tags' => 'array',
        'review' => 'array',
        'seo_published_at' => 'datetime',
        'seo_last_published_at' => 'datetime',
    ];

    /** The published SEO snapshot for this video (Phase 4), if any. */
    public function seoPayload()
    {
        return $this->hasOne(PublishedReviewPayload::class, 'video_id');
    }
    /**
     * Normalised thumbnail URL. thumbnail_image holds either an absolute URL
     * (e.g. Vimeo CDN) or a public-relative path (e.g. review_thumbnails/…);
     * return absolute URLs as-is and asset()-wrap relative paths.
     */
    public function getThumbUrlAttribute()
    {
        if (! $this->thumbnail_image) return null;
        return str_starts_with($this->thumbnail_image, 'http') ? $this->thumbnail_image : asset($this->thumbnail_image);
    }

    public function character()
    {
        return $this->belongsTo(Character::class);
    }

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }
    //     public function channel()
    // {
    //     return $this->belongsToMany(Channel::class, 'video_channel');
    // }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function highlightTags()
    {
        return $this->belongsToMany(HighlightTag::class);
    }

    public function setAutoTagsAttribute($value)
    {
        $this->attributes['auto_tags'] = $value
            ? json_encode(array_filter(array_map('trim', explode(',', $value))))
            : null;
    }

    public function setHashtagsAttribute($value)
    {
        $this->attributes['hashtags'] = $value
            ? json_encode(array_filter(array_map('trim', explode(',', $value))))
            : null;
    }

    public function setTagsAttribute($value)
    {
        $this->attributes['tags'] = $value
            ? json_encode(array_filter(array_map('trim', explode(',', $value))))
            : null;
    }
    public function reviews()
    {
        return $this->hasMany(\App\Models\Review::class, 'video_id', 'id');
    }
    public function regions()
    {
        return $this->belongsToMany(\App\Models\Region::class, 'video_region');
    }
    public function getTagIdsArrayAttribute(): array
    {
        return collect(explode(',', (string) $this->tag_ids))
            ->map(fn($s) => trim($s))
            ->filter()
            ->map(fn($s) => (int) $s)
            ->values()
            ->all();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->orderBy('created_at', 'desc');
    }

    public function topLevelComments(): HasMany
    {
        return $this->hasMany(Comment::class)
            ->whereNull('parent_id')
            ->orderBy('created_at', 'desc');
    }

    // public function likedByUsers()
    // {
    //     return $this->belongsToMany(\App\Models\User::class, 'video_likes')->withTimestamps();
    // }
    public function favoritedByUsers()
    {
        return $this->belongsToMany(\App\Models\User::class, 'video_favorites')->withTimestamps();
    }
    public function watchHistories()
    {
        return $this->hasMany(\App\Models\VideoWatchHistory::class);
    }
    public function likes()
{
    return $this->hasMany(\App\Models\VideoLike::class, 'video_id');
}

public function likedByUsers()
{
    return $this->belongsToMany(
        \App\Models\User::class,
        'video_likes',
        'video_id',
        'user_id'
    )->withTimestamps();
}

public function seoRegions()
{
    return $this->hasMany(SeoRegion::class);
}

public function seos()
{
    return $this->hasMany(SeoRegion::class);
}

public function tags()
    {
        return $this->belongsToMany(Tag::class, 'tags', 'video_id', 'tag_id');
        
    }

    


}

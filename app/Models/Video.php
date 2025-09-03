<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use HasFactory;

    // protected $fillable = [
    //     'title',
    //     'description',
    //     'type',
    //     'video_url',
    //     'thumbnail_url',
    //     'character_id',
    //     'channel_id',
    //     'category_id',
    //     'access_level',
    //     'affiliate_link',
    // ];
    protected $fillable = [
    'title',
    'description',
    'type', // youtube, vimeo, etc.
    'video_type', // short, full review, reel, etc.
    'video_platforms',
    'video_url',
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

    'character_id', // primary reviewer
    'channel_id',
    'category_id',
    'access_level',
    'tags',             // JSON/string
    'highlight_tags',   // JSON/string
    'auto_tags',        // JSON/string


    'review_type', 
    'public_rating', 
    'review_details', 
    'sponsored', // true/false
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
];

protected $casts = [
    'highlight_tags' => 'array',
    'auto_tags'      => 'array',
    'hashtags'       => 'array',
    'tags'           => 'array',
];
    // protected $casts = [
    //     'post_schedule_at' => 'datetime',
    // ];
    public function character() {
        return $this->belongsTo(Character::class);
    }

    public function channel() {
        return $this->belongsTo(Channel::class);
    }

    public function category() {
        return $this->belongsTo(Category::class);
    }

    // public function tags() {
    // return $this->belongsToMany(Tag::class);
    // }

    public function highlightTags() {
        return $this->belongsToMany(HighlightTag::class);
    }

    // public function supportingReviewers() {
    //     return $this->belongsToMany(Character::class, 'supporting_reviewer_video');
    // }


//     public function setHighlightTagsAttribute($value)
// {
//     $this->attributes['highlight_tags'] = $value
//         ? json_encode(array_filter(array_map('trim', explode(',', $value))))
//         : null;
// }

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


// public function getHighlightTagsAttribute($value)
// {
//     return $value ? implode(', ', json_decode($value, true)) : '';
// }

// public function getAutoTagsAttribute($value)
// {
//     return $value ? implode(', ', json_decode($value, true)) : '';
// }

// public function getHashtagsAttribute($value)
// {
//     return $value ? implode(', ', json_decode($value, true)) : '';
// }

// public function getTagsAttribute($value)
// {
//     return $value ? implode(', ', json_decode($value, true)) : '';
// }

public function reviews()
{
    return $this->hasMany(\App\Models\Review::class, 'video_id', 'id');
}
public function regions()
{
    return $this->belongsToMany(\App\Models\Region::class, 'video_region');
}
}

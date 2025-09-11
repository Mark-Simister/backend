<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoRegion extends Model
{
    protected $table = 'seo_region'; 

    protected $fillable = [
        'video_id',
        'region_id',
        'seo_title',
        'seo_description',
        'hashtags',
        'cta_text',
        'og_image_url',
        'twitter_title',
        'twitter_description',
    ];
public function video()
{
    return $this->belongsTo(Video::class, 'video_id', 'id');
}

public function region()
{
    return $this->belongsTo(Region::class);
}

}

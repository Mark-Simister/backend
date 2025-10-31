<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Channel extends Model
{
    use HasFactory;


    protected $fillable = [
        'name',
        'image',
        'video',
        'primary_color',
        'secondary_color',
        'accent_color',
        'background_color',
        'text_color',
        'hover_color',
        'highlight_color',
        'cta',
        'channel_category',
    ];

    protected $hidden = ['image'];
    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        return $this->image ? asset($this->image) : null;
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    // public function categories()
    // {
    //     return $this->belongsToMany(Category::class, 'category_channel');
    // }
    public function videos()
    {
        return $this->belongsToMany(Video::class, 'video_channel');
    }

    public function regions()
    {
        return $this->belongsToMany(Region::class, 'channel_region');
    }

    public function followers()
    {
        return $this->hasMany(ChannelFollow::class, 'channel_id');
    }
}

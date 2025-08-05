<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'type',
        'video_url',
        'thumbnail_url',
        'character_id',
        'channel_id',
        'category_id',
        'access_level',
        'affiliate_link',
    ];

    public function character() {
        return $this->belongsTo(Character::class);
    }

    public function channel() {
        return $this->belongsTo(Channel::class);
    }

    public function category() {
        return $this->belongsTo(Category::class);
    }
}

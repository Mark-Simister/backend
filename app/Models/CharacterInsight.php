<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class CharacterInsight extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'short_description',
        'character_insight_image',
        'video_id',
    ];

    public function video()
    {
        return $this->belongsTo(Video::class);
    }
}

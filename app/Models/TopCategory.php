<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TopCategory extends Model
{
    protected $table = 'top_categories';
    protected $fillable = [
        'category_type',
        'title',
        'thumbnail',
        'video_ids',
        'comment_types',
        'explain_video',
        'video_timestamps',
        'overview_title',     
        'overview_description',
        'status',
    ];

    protected $casts = [
        'video_ids' => 'array', 
        'comment_types' => 'array',
        'video_timestamps' => 'array',
    ];
}
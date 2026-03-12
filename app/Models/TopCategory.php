<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TopCategory extends Model
{
    protected $table = 'top_categories';
    protected $fillable = [
        'category_type',
        'title',
        'video_ids',
        'comment_types',
        'explain_video',
        'video_timestamps',
        'status',
    ];

    protected $casts = [
        'video_ids' => 'array', 
        'comment_types' => 'array',
        'video_timestamps' => 'array',
    ];
}
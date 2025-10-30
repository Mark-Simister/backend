<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoWatchHistory extends Model
{
    protected $fillable = ['video_id', 'user_id', 'last_position_seconds', 'total_watched_seconds', 'watched_at', 'completed_at', 'is_completed' ,'reason',];
     protected $casts = [
        'last_position_seconds'   => 'integer',
        'total_watched_seconds'   => 'integer',
        'is_completed'            => 'boolean',
        'watched_at'              => 'datetime',
        'completed_at'            => 'datetime',
        'created_at'              => 'datetime',
        'updated_at'              => 'datetime',
    ];
    public function video()
    {
        return $this->belongsTo(Video::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoWatchHistory extends Model
{
    protected $fillable = ['video_id', 'user_id', 'last_position_seconds', 'watched_at'];
    protected $casts = ['watched_at' => 'datetime'];
    public function video()
    {
        return $this->belongsTo(Video::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

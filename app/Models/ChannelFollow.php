<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelFollow extends Model
{
    protected $table = 'follow_channels';
    protected $fillable = [
        'channel_id',
        'user_id',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }
    
}

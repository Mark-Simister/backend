<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelRegion extends Model
{
    protected $table = 'channel_region';
    public $timestamps = false;
    protected $fillable = [
        'channel_id',
        'region_id',
    ];

    // Relations (handy for eager-loading)
    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }
}

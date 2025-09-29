<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimilarProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'video_id',
        'region_id',
        'name',
        'short_description',
        'url',
    ];

    public function video()
    {
        return $this->belongsTo(Video::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }
}

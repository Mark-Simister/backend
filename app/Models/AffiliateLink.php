<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AffiliateLink extends Model
{
    use HasFactory;

    // Specify the table associated with the model (optional)
    protected $table = 'affiliate_links';

    // Define the fillable attributes
    protected $fillable = [
        'video_id',
        'region_id',
        'retailer',
        'url',
    ];

    // Define the relationship to the Video model
    public function video()
    {
        return $this->belongsTo(Video::class);
    }

    // Define the relationship to the Region model
    public function region()
    {
        return $this->belongsTo(Region::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductReview extends Model
{
    use HasFactory;

    
    protected $table = 'product_reviews';

    protected $fillable = [
        'character_id', 
        'video_id', 
        'review_url', 
        'is_featured', 
        'is_active',
        'views'
    ];

    public function character()
    {
        return $this->belongsTo(Character::class);
    }

    public function video()
    {
        return $this->belongsTo(Video::class);
    }
    
}

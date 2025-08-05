<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Character extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'persona',
        'details',
        'image',
        'channel_id',
        'location',
        'age',
        'species',
        'style_vibe',
        'durability_score',
        'durability_notes',
        'comfort_score',
        'comfort_notes',
        'style_score',
        'style_notes',
        'affordability_score',
        'affordability_notes',
        'tech_feature_score',
        'tech_feature_notes',
        'eco_friendliness_score',
        'eco_friendliness_notes',
        'engagement_score',
        'engagement_notes',
        'ease_of_use_score',
        'ease_of_use_notes',
        'performance_score',
        'performance_notes',
        'brand_reputation_score',
        'brand_reputation_notes',
    ];
    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }
}

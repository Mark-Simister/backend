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
        'thumbnail_image',
        'category_id',
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

        'sex',
        'page_heading',
        'page_sub_heading',
        'preferences',
        'loved_pet1',
        'loved_pet2',
        'loved_pet3',
        'hated_pet1',
        'hated_pet2',
        'hated_pet3',
        'character_page_url_slug',
        'public_private_toggle',
        'character_launch_date',
        'character_popularity_score',
        'editor_notes_content_guidelines',
        'character_role',
        'character_tag',
        'video',
    ];
    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }
    public function tags()
    {
        return $this->belongsToMany(CharacterTag::class);
    }

    public function roles()
    {
        return $this->belongsToMany(CharacterRole::class);
    }
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function videos()
    {
        return $this->hasMany(\App\Models\Video::class, 'character_id');
    }
    public function regions()
    {
        return $this->belongsToMany(\App\Models\Region::class, 'character_region');
    }
    public function subscriptionRegions()
    {
        return $this->belongsToMany(Region::class, 'subscription_region', 'subscription_id', 'region_id')->withTimestamps();
    }
    public function bloopers()
    {
        return $this->hasMany(Blooper::class);
    }

    public function productReviews()
    {
        return $this->hasMany(ProductReview::class, 'character_id', 'id');
    }


}

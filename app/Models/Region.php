<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    use HasFactory;
    // protected $hidden = ['pivot'];

    protected $fillable = [
        'region_name',
        'region_code',
        'description',
        'is_active',
        'currency',
        'currency_symbol',
        'motif_color',
        'motif_type',
        'opacity',
    ];
    protected $casts = [
        'motif_color' => 'array',
    ];
    public function channels()
    {
        return $this->belongsToMany(Channel::class, 'channel_region');
    }
    public function characters()
    {
        return $this->belongsToMany(\App\Models\Character::class, 'character_region');
    }

    public function getNameAttribute()
    {
        return $this->region_name;
    }

    public function categories()
    {
        return $this->belongsToMany(\App\Models\Category::class, 'category_region');
    }

    public function subscriptions()
    {
        return $this->belongsToMany(SubscriptionListing::class, 'subscription_region', 'region_id', 'subscription_id');
    }

    public function regions()
    {
        return $this->belongsToMany(Region::class, 'subscription_region', 'subscription_id', 'region_id')
            ->withTimestamps();
    }
    public function currency_get()
    {
        return $this->belongsTo(Currency::class, 'currency_symbol');
    }
}

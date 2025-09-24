<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'channel_id', 'image'];

    // Auto-generate slug on saving if needed
    protected static function booted()
    {
        static::creating(function ($category) {
            $category->slug = Str::slug($category->name);
        });

        static::updating(function ($category) {
            $category->slug = Str::slug($category->name);
        });
    }

    // public function channel()
    // {
    //     return $this->belongsTo(Channel::class, 'channel_id');
    // }

    public function channel()
    {
        return $this->belongsToMany(Channel::class, 'category_channel');
    }

    public function characters()
    {
        return $this->hasMany(Character::class);
    }
    public function regions()
    {
        return $this->belongsToMany(\App\Models\Region::class, 'category_region');
    }

}

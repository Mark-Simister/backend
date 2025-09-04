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
}

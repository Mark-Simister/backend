<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryRegion extends Model
{
    protected $table = 'category_region';
    public $timestamps = false;

    protected $fillable = [
        'category_id',
        'region_id',
    ];

    public function category()
    {
        return $this->belongsTo(\App\Models\Category::class);
    }

    public function region()
    {
        return $this->belongsTo(\App\Models\Region::class);
    }
}

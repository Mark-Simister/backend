<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteImage extends Model
{
    protected $fillable = ['key', 'label', 'image'];

    public function getImageUrlAttribute()
    {
        return $this->image ? asset($this->image) : null;
    }
}

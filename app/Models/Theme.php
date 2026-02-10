<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Theme extends Model
{
    
    protected $fillable = [
        'name',
        'button_color',
        'link_color',
        'dark_bg_color',
        'light_bg_color',
    ];

    // Relation: A theme can be used by many users
    public function userThemes()
    {
        return $this->hasMany(UserTheme::class);
    }
}

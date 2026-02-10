<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTheme extends Model
{


    protected $fillable = [
        'user_id',
        'theme_id',
        'button_color',
        'link_color',
        'dark_bg_color',
        'light_bg_color',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

public function theme()
{
    return $this->belongsTo(\App\Models\Theme::class, 'theme_id');
}
}

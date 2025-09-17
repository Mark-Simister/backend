<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryFollow extends Model
{
    protected $table = 'category_follow';
    protected $fillable = [
       'category_id',
       'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
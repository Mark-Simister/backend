<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Form extends Model
{
    protected $fillable = ['name', 'video_path', 'cta_type', 'fields', 'is_active'];

    protected $casts = [
        'fields' => 'array', // JSON as array
    ];

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class);
    }
}

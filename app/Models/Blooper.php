<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Blooper extends Model
{
     use HasFactory;

    protected $fillable = ['character_id', 'video', 'image', 'name', 'description', 'stars'];

    public function character()
    {
        return $this->belongsTo(Character::class);
    }
}

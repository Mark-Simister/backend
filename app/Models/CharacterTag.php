<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CharacterTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function characters()
    {
        return $this->belongsToMany(Character::class);
    }
}

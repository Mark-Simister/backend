<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GlobalColor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'hex_value',
        'usage',
    ];
}

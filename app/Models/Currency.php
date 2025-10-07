<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $table = 'currency';

    // The attributes that are mass assignable.
    protected $fillable = [
        'currency_code',
        'currency_name',
        'currency_symbol',
    ];
}

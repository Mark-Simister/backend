<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionRegion extends Model
{
    use HasFactory;

    protected $table = 'subscription_region';

    protected $fillable = [
        'subscription_id',
        'region_id',
    ];
}

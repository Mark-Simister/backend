<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionListing extends Model
{
    use HasFactory;

    protected $table = 'subscription_listing'; 

    protected $fillable = [
        'subscription_name',
        'sub_description',
        'price',
        'duration',
        'duration_unit',
        'type',
    ];
}

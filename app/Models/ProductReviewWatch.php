<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductReviewWatch extends Model
{
    use HasFactory;

    protected $table = 'product_review_watch';

    protected $fillable = [
        'product_review_id',
        'user_id',
    ];
}

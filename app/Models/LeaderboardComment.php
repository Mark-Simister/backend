<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaderboardComment extends Model
{
 

    protected $table = 'leaderboardcomments';

    protected $fillable = ['user_id', 'comment', 'type'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

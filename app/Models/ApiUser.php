<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApiUser extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    /**
     * Explicitly set table name to 'users'
     */
    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'is_verified',
        'is_blocked',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_verified' => 'boolean',
            'is_blocked' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key-value array containing any custom claims.
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function subscriptions_api()
    {
        // FK column in subscriptions = 'user_id'
        return $this->hasMany(\App\Models\Subscription::class, 'user_id', 'id');
    }

    public function reviews_api()
    {
        // FK column in reviews = 'user_id'
        return $this->hasMany(\App\Models\Review::class, 'user_id', 'id');
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->is_blocked ? 'Blocked' : 'Active';
    }

}
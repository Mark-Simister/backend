<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;
// use Spatie\Permission\Models\Role;

class Role extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = ['name', 'guard_name'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'model_has_roles', 'role_id', 'model_id');
    }
}

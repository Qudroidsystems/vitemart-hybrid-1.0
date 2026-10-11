<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class RoleLoginRule extends Model
{
    protected $fillable = ['role_id', 'enabled', 'start_time', 'end_time', 'days'];

    protected $casts = [
        'enabled' => 'boolean',
        'days' => 'array',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}

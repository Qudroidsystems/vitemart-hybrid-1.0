<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BranchEnrollmentCode extends Model
{
    protected $fillable = ['branch_id', 'code_hash', 'expires_at', 'used_at', 'used_by_installation_id', 'created_by'];

    protected $casts = ['expires_at' => 'datetime', 'used_at' => 'datetime'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}

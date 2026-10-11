<?php

namespace App\Models;

use App\Traits\HasUid;
use Illuminate\Database\Eloquent\Model;

/** This server's identity. Exactly one row. */
class Installation extends Model
{
    use HasUid;

    protected $table = 'installation';

    protected $fillable = [
        'uid', 'mode', 'organization_id', 'branch_id', 'central_url', 'remote_installation_uid', 'token',
        'hmac_secret', 'enrolled_at', 'bootstrapped_at', 'app_version',
    ];

    protected $casts = [
        'token' => 'encrypted',
        'hmac_secret' => 'encrypted',
        'enrolled_at' => 'datetime',
        'bootstrapped_at' => 'datetime',
    ];

    protected $hidden = ['token', 'hmac_secret'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}

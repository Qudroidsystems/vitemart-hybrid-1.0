<?php

namespace App\Models;

use App\Traits\HasUid;
use Illuminate\Database\Eloquent\Model;

/** HQ side: one enrolled branch server. */
class BranchInstallation extends Model
{
    use HasUid;

    protected $fillable = [
        'uid', 'branch_id', 'token_hash', 'hmac_secret', 'fingerprint', 'status', 'enrolled_at', 'revoked_at',
        'last_seen_at', 'last_push_at', 'last_pull_at', 'last_ip', 'app_version', 'pending_events',
        'dead_letters', 'disk_free_mb', 'last_backup_at',
    ];

    protected $casts = [
        'hmac_secret' => 'encrypted',
        'enrolled_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'last_push_at' => 'datetime',
        'last_pull_at' => 'datetime',
        'last_backup_at' => 'datetime',
    ];

    protected $hidden = ['token_hash', 'hmac_secret'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->gt(now()->subMinutes(10));
    }
}

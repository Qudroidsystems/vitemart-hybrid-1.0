<?php

namespace App\Models;

use App\Traits\HasUid;
use App\Traits\BelongsToBranch;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasUid, BelongsToBranch;
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'user_name', 'event', 'module', 'auditable_type', 'auditable_id', 'auditable_label',
        'old_values', 'new_values', 'description', 'ip_address', 'user_agent', 'url', 'method', 'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getEventColorAttribute(): string
    {
        return match (true) {
            in_array($this->event, ['created', 'login', 'approved', 'sale', 'received']) => 'success',
            in_array($this->event, ['updated', 'price_changed', 'adjusted', 'settings']) => 'primary',
            in_array($this->event, ['deleted', 'voided', 'rejected', 'force_logout', 'login_failed', 'login_blocked']) => 'danger',
            in_array($this->event, ['logout', 'returned']) => 'warning',
            default => 'secondary',
        };
    }

    /** Keys changed between old/new, for the diff view. */
    public function changedKeys(): array
    {
        return array_values(array_unique(array_merge(
            array_keys($this->old_values ?? []),
            array_keys($this->new_values ?? [])
        )));
    }
}

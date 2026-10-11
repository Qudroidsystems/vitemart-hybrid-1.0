<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasUid;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasUid, Auditable;

    protected string $auditModule = 'branches';

    protected $fillable = [
        'uid', 'code', 'number', 'name', 'type', 'status', 'address', 'phone', 'email', 'manager_user_id',
        'opening_hours', 'receipt_header', 'receipt_footer', 'settings',
    ];

    protected $casts = ['opening_hours' => 'array', 'settings' => 'array', 'number' => 'integer'];

    public function installations()
    {
        return $this->hasMany(BranchInstallation::class);
    }

    public function activeInstallation()
    {
        return $this->hasOne(BranchInstallation::class)->where('status', 'active')->latestOfMany();
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'branch_user')->withTimestamps();
    }

    public function locations()
    {
        return $this->hasMany(StockLocation::class);
    }

    public function isWarehouse(): bool
    {
        return $this->type === 'warehouse';
    }

    public function scopeStores($q)
    {
        return $q->where('type', 'store');
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function auditLabel(): string
    {
        return "{$this->code} – {$this->name}";
    }

    /** Payload shared with other installations. */
    public function toSync(): array
    {
        return $this->only(['uid', 'code', 'number', 'name', 'type', 'status', 'address', 'phone', 'email',
            'opening_hours', 'receipt_header', 'receipt_footer']);
    }
}

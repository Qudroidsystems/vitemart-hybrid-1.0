<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToBranch;
use App\Traits\HasUid;
use Illuminate\Database\Eloquent\Model;

class CashRegister extends Model
{
    use HasUid, BelongsToBranch, Auditable;

    public const COOKIE = 'vm_register';

    protected $fillable = ['uid', 'branch_id', 'code', 'name', 'stock_location_id', 'status', 'receipt_printer', 'last_used_at', 'last_user_id'];

    protected $casts = ['last_used_at' => 'datetime'];

    public function location()
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    public function shifts()
    {
        return $this->hasMany(PosShift::class);
    }

    public function openShift(): ?PosShift
    {
        return $this->shifts()->where('status', 'open')->latest('opened_at')->first();
    }

    public function scopeHere($q)
    {
        return $q->where('branch_id', \App\Services\MultiBranch\Install::branchId());
    }

    public function auditLabel(): string
    {
        return "Register {$this->code} – {$this->name}";
    }
}

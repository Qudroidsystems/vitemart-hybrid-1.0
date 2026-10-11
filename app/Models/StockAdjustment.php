<?php

namespace App\Models;

use App\Traits\HasUid;
use App\Traits\BelongsToBranch;

use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    use HasUid, BelongsToBranch;
    public const TYPES = [
        'adjustment' => 'Stock Adjustment',
        'count'      => 'Stock Count (Stock-take)',
        'receive'    => 'Goods Received',
        'damage'     => 'Damaged / Breakage',
        'expired'    => 'Expired Goods',
    ];

    public const REASONS = [
        'Stock count correction', 'Goods received', 'Damaged', 'Expired', 'Theft / shrinkage',
        'Returned to supplier', 'Customer return', 'Internal use', 'Data entry error', 'Other',
    ];

    protected $fillable = [
        'reference', 'type', 'status', 'stock_location_id', 'reason', 'notes', 'supplier', 'supplier_invoice',
        'total_value_change', 'items_count', 'created_by', 'approved_by', 'approved_at',
        'rejected_by', 'rejected_at', 'rejection_reason',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'total_value_change' => 'decimal:2',
    ];

    protected string $auditModule = 'inventory';

    public function items()
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    public function location()
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'approved' => 'success',
            'pending' => 'warning',
            'rejected' => 'danger',
            'cancelled' => 'secondary',
            default => 'info',
        };
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public static function nextReference(string $type = 'adjustment'): string
    {
        return \App\Services\MultiBranch\DocumentNumber::next(in_array($type, ['count', 'receive', 'damage', 'expired']) ? $type : 'adjustment');
    }

    public function auditLabel(): string
    {
        return $this->reference;
    }
}

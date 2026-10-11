<?php

namespace App\Models;

use App\Traits\HasUid;
use Illuminate\Database\Eloquent\Model;

class PriceChangeRequest extends Model
{
    use HasUid;

    protected $fillable = ['uid', 'branch_id', 'product_id', 'product_uid', 'variation_uid', 'pack_uid', 'item_label', 'field',
        'current_value', 'requested_value', 'promo_starts_at', 'promo_ends_at', 'reason', 'status', 'sent',
        'requested_by_name', 'requested_by', 'approved_value', 'scope', 'decided_by_name', 'decided_at', 'decision_note', 'applied'];

    protected $casts = ['sent' => 'boolean', 'applied' => 'boolean', 'decided_at' => 'datetime',
        'promo_starts_at' => 'datetime', 'promo_ends_at' => 'datetime'];

    public const FIELDS = ['price' => 'Selling price', 'wholesale_price' => 'Wholesale price', 'promo_price' => 'Promo price'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function toSync(): array
    {
        return $this->only(['uid', 'product_uid', 'variation_uid', 'pack_uid', 'item_label', 'field', 'current_value', 'requested_value',
                'reason', 'requested_by_name']) + [
            'promo_starts_at' => $this->promo_starts_at?->toIso8601String(),
            'promo_ends_at' => $this->promo_ends_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    public function decisionPayload(): array
    {
        return $this->only(['uid', 'status', 'approved_value', 'scope', 'decided_by_name', 'decision_note'])
            + ['decided_at' => $this->decided_at?->toIso8601String()];
    }
}

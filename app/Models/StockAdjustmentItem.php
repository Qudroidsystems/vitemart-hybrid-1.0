<?php

namespace App\Models;

use App\Traits\HasUid;

use Illuminate\Database\Eloquent\Model;

class StockAdjustmentItem extends Model
{
    use HasUid;
    protected $fillable = [
        'stock_adjustment_id', 'product_id', 'product_variation_id', 'product_unit_id', 'quantity_per_unit',
        'mode', 'entered_quantity', 'system_quantity', 'quantity_change', 'balance_after', 'unit_cost',
        'reason', 'expiry_date', 'batch_number',
    ];

    protected $casts = [
        'quantity_per_unit' => 'float',
        'entered_quantity' => 'float',
        'system_quantity' => 'float',
        'quantity_change' => 'float',
        'balance_after' => 'float',
        'unit_cost' => 'decimal:2',
        'expiry_date' => 'date',
    ];

    public function adjustment()
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variation()
    {
        return $this->belongsTo(ProductVariation::class, 'product_variation_id');
    }

    public function pack()
    {
        return $this->belongsTo(ProductUnit::class, 'product_unit_id');
    }

    public function getDisplayNameAttribute(): string
    {
        $name = $this->product->title ?? 'Deleted product';
        if ($this->variation) {
            $name .= ' – ' . $this->variation->label;
        }
        return $name;
    }

    public function getProjectedBalanceAttribute(): float
    {
        return $this->system_quantity + $this->quantity_change;
    }
}

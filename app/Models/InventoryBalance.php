<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryBalance extends Model
{
    public const CREATED_AT = null;

    protected $fillable = ['branch_id', 'product_id', 'variation_key', 'on_hand', 'reserved', 'quarantined', 'in_transit', 'incoming'];

    protected $casts = ['on_hand' => 'float', 'reserved' => 'float', 'quarantined' => 'float', 'in_transit' => 'float', 'incoming' => 'float'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /** What can be sold or promised right now. */
    public function getAvailableAttribute(): float
    {
        return round($this->on_hand - $this->reserved, 3);
    }
}

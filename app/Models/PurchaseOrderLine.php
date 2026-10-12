<?php

namespace App\Models;

use App\Traits\HasUid;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderLine extends Model
{
    use HasUid;

    protected $fillable = ['uid','purchase_order_id','product_id','product_variation_id','product_unit_id','qty_per_unit','ordered_qty','received_qty','unit_cost'];

    protected $casts = ['qty_per_unit' => 'float', 'ordered_qty' => 'float', 'received_qty' => 'float', 'unit_cost' => 'float'];

    public function order()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
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

    public function outstanding(): float
    {
        return max(0, round($this->ordered_qty - $this->received_qty, 3));
    }
}

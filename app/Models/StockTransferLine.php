<?php

namespace App\Models;

use App\Traits\HasUid;
use Illuminate\Database\Eloquent\Model;

class StockTransferLine extends Model
{
    use HasUid;

    protected $fillable = ['uid','stock_transfer_id','product_id','product_variation_id','product_unit_id','qty_per_unit','requested_qty','approved_qty','dispatched_qty','received_qty','damaged_qty','unit_cost','discrepancy_qty','discrepancy_status','discrepancy_note'];

    protected $casts = ['qty_per_unit' => 'float', 'requested_qty' => 'float', 'approved_qty' => 'float', 'dispatched_qty' => 'float', 'received_qty' => 'float', 'damaged_qty' => 'float', 'discrepancy_qty' => 'float'];

    public function transfer()
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
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

    public function label(): string
    {
        return ($this->product->title ?? '?') . ($this->variation ? ' – ' . $this->variation->label : '');
    }
}

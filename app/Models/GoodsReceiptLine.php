<?php

namespace App\Models;

use App\Traits\HasUid;
use Illuminate\Database\Eloquent\Model;

class GoodsReceiptLine extends Model
{
    use HasUid;

    protected $fillable = ['uid','goods_receipt_id','purchase_order_line_id','product_id','product_variation_id','product_unit_id','qty_per_unit','quantity','free_qty','unit_cost','expiry_date','batch_number'];

    protected $casts = ['qty_per_unit' => 'float', 'quantity' => 'float', 'free_qty' => 'float', 'unit_cost' => 'float', 'expiry_date' => 'date'];

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
}

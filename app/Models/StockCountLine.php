<?php

namespace App\Models;

use App\Traits\HasUid;
use Illuminate\Database\Eloquent\Model;

class StockCountLine extends Model
{
    use HasUid;

    protected $fillable = ['uid','stock_count_id','product_id','product_variation_id','counted_qty','system_qty','variance','recount','recounted','counted_by','counted_at'];

    protected $casts = ['counted_qty' => 'float', 'system_qty' => 'float', 'variance' => 'float', 'recount' => 'boolean', 'recounted' => 'boolean', 'counted_at' => 'datetime'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variation()
    {
        return $this->belongsTo(ProductVariation::class, 'product_variation_id');
    }
}

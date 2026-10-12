<?php

namespace App\Models;

use App\Traits\HasUid;
use Illuminate\Database\Eloquent\Model;

class SupplierReturnLine extends Model
{
    use HasUid;

    protected $fillable = ['uid','supplier_return_id','product_id','product_variation_id','product_unit_id','qty_per_unit','quantity','unit_cost'];

    protected $casts = ['qty_per_unit' => 'float', 'quantity' => 'float', 'unit_cost' => 'float'];

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

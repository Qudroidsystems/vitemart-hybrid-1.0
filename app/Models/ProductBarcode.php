<?php

namespace App\Models;

use App\Traits\HasUid;
use Illuminate\Database\Eloquent\Model;

class ProductBarcode extends Model
{
    use HasUid;

    protected $fillable = ['uid', 'barcode', 'product_id', 'product_variation_id', 'product_unit_id', 'kind', 'note', 'origin_branch_id'];

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

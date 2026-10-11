<?php

namespace App\Models;

use App\Traits\HasUid;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasUid;
    protected $fillable = [
        'order_id',
        'product_id',
        'title',
        'unit_price',
        'quantity',
        'total_price',
        'unit_id',
        'unit_name',
        'variation_id',
        'image',
        'brand_name',
        'selected_variation',
        'sku',
        'barcode',
        'product_unit_id',
        'quantity_per_unit',
        'base_quantity',
        'cost_price',
        'discount_amount',
        'returned_quantity',
        'is_unit_mode',
        'price_overridden',
    ];

    protected $casts = [
        'selected_variation' => 'array',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'quantity' => 'float',
        'base_quantity' => 'float',
        'returned_quantity' => 'float',
        'quantity_per_unit' => 'float',
        'cost_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
    ];

    public function variation()
    {
        return $this->belongsTo(ProductVariation::class, 'variation_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}

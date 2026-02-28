<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
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
    ];

    protected $casts = [
        'selected_variation' => 'array',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

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

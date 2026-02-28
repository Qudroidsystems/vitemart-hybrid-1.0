<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderDiscount extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'order_item_id',
        'type',              // 'order' or 'item'
        'discount_type',     // 'percent' or 'fixed'
        'discount_value',
        'amount',
        'applied_by',
        'applied_at',
    ];

    // Optional: Cast applied_at as datetime
    protected $casts = [
        'applied_at' => 'datetime',
        'discount_value' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    // Relationships (optional but recommended)
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function appliedBy()
    {
        return $this->belongsTo(User::class, 'applied_by');
    }
}

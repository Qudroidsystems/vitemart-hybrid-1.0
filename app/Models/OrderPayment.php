<?php

namespace App\Models;

use App\Traits\HasUid;
use App\Traits\BelongsToBranch;

use Illuminate\Database\Eloquent\Model;

class OrderPayment extends Model
{
    use HasUid, BelongsToBranch;
    protected $fillable = ['order_id', 'method', 'amount', 'reference', 'pos_shift_id', 'user_id'];

    protected $casts = ['amount' => 'decimal:2'];

    public const METHODS = [
        'cash' => 'Cash',
        'card' => 'Card / POS',
        'transfer' => 'Bank Transfer',
        'credit' => 'Customer Credit',
        'points' => 'Loyalty Points',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}

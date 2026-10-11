<?php

namespace App\Models;

use App\Traits\HasUid;
use App\Traits\BelongsToBranch;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    use HasUid, BelongsToBranch;
    protected $fillable = ['order_id', 'user_id', 'amount', 'reason', 'status', 'processed_at'];

    protected $casts = [
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
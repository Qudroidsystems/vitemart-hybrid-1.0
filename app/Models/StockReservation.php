<?php

namespace App\Models;

use App\Traits\HasUid;
use Illuminate\Database\Eloquent\Model;

class StockReservation extends Model
{
    use HasUid;

    protected $fillable = ['uid', 'branch_id', 'product_id', 'product_variation_id', 'quantity', 'reference_type', 'reference',
        'status', 'expires_at', 'created_by'];

    protected $casts = ['quantity' => 'float', 'expires_at' => 'datetime'];
}

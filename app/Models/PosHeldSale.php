<?php

namespace App\Models;

use App\Traits\BelongsToBranch;

use Illuminate\Database\Eloquent\Model;

class PosHeldSale extends Model
{
    use BelongsToBranch;
    protected $fillable = ['user_id', 'label', 'customer_id', 'cart', 'total'];

    protected $casts = ['cart' => 'array', 'total' => 'decimal:2'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}

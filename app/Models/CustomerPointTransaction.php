<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPointTransaction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'customer_id',
        'order_id',
        'points_earned',
        'points_redeemed',
        'amount_spent',
        'discount_applied',
        'description',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'points_earned' => 'integer',
        'points_redeemed' => 'integer',
        'amount_spent' => 'decimal:2',
        'discount_applied' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the customer that owns the transaction.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the order associated with this transaction (nullable).
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    /**
     * Get the user who created this transaction (cashier/admin).
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope a query to only include transactions where points were earned.
     */
    public function scopeEarned($query)
    {
        return $query->where('points_earned', '>', 0);
    }

    /**
     * Scope a query to only include transactions where points were redeemed.
     */
    public function scopeRedeemed($query)
    {
        return $query->where('points_redeemed', '>', 0);
    }
}

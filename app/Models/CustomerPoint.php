<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerPoint extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'customer_points';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'customer_id',
        'points',
        'points_value', // Optional: cached monetary value of points
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'points' => 'integer',
        'points_value' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the customer that owns the points.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get all point transactions for this customer.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(CustomerPointTransaction::class, 'customer_id');
    }

    /**
     * Scope a query to only include customers with positive points.
     */
    public function scopeHasPoints($query)
    {
        return $query->where('points', '>', 0);
    }

    /**
     * Get the redeemable monetary value of points.
     * Uses config/loyalty.redeem_rate (default 100 points = ₦100)
     */
    public function getRedeemableValueAttribute(): float
    {
        $redeemRate = config('loyalty.redeem_rate', 100);
        return $this->points / $redeemRate;
    }

    /**
     * Add points to customer.
     */
    public function addPoints(int $points, string $description = '', $orderId = null, $createdBy = null): void
    {
        $this->increment('points', $points);
        $this->increment('points_value', $points / config('loyalty.earn_rate', 1));

        $this->transactions()->create([
            'order_id' => $orderId,
            'points_earned' => $points,
            'amount_spent' => 0,
            'description' => $description,
            'created_by' => $createdBy ?? auth()->id(),
        ]);
    }

    /**
     * Redeem points from customer.
     */
    public function redeemPoints(int $points, float $discountApplied, string $description = '', $orderId = null): void
    {
        if ($points > $this->points) {
            throw new \Exception('Insufficient points');
        }

        $this->decrement('points', $points);

        $this->transactions()->create([
            'order_id' => $orderId,
            'points_redeemed' => $points,
            'discount_applied' => $discountApplied,
            'description' => $description,
            'created_by' => auth()->id(),
        ]);
    }
}

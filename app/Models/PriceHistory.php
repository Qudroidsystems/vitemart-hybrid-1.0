<?php

namespace App\Models;

use App\Traits\HasUid;
use App\Traits\BelongsToBranch;

use Illuminate\Database\Eloquent\Model;

class PriceHistory extends Model
{
    use HasUid, BelongsToBranch;
    protected $fillable = [
        'product_id', 'product_variation_id', 'product_unit_id', 'field', 'old_value', 'new_value',
        'batch_id', 'source', 'reason', 'user_id',
    ];

    protected $casts = [
        'old_value' => 'decimal:2',
        'new_value' => 'decimal:2',
    ];

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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getChangePercentAttribute(): ?float
    {
        if (!$this->old_value || (float) $this->old_value == 0.0) {
            return null;
        }
        return round((($this->new_value - $this->old_value) / $this->old_value) * 100, 1);
    }
}

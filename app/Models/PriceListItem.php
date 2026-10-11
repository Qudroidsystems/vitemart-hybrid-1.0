<?php

namespace App\Models;

use App\Traits\HasUid;
use Illuminate\Database\Eloquent\Model;

class PriceListItem extends Model
{
    use HasUid;

    protected $fillable = ['uid', 'price_list_id', 'product_id', 'product_variation_id', 'product_unit_id', 'item_key',
        'price', 'wholesale_price', 'promo_price', 'promo_starts_at', 'promo_ends_at', 'version', 'updated_by'];

    protected $casts = ['promo_starts_at' => 'datetime', 'promo_ends_at' => 'datetime'];

    public static function keyFor(int $productId, ?int $variationId = null, ?int $packId = null): string
    {
        return 'p' . $productId . '-v' . ($variationId ?: 0) . '-u' . ($packId ?: 0);
    }

    protected static function booted(): void
    {
        static::saving(function (self $i) {
            $i->item_key = static::keyFor($i->product_id, $i->product_variation_id, $i->product_unit_id);
            if ($i->exists && $i->isDirty(['price', 'wholesale_price', 'promo_price', 'promo_starts_at', 'promo_ends_at'])) {
                $i->version = (int) $i->version + 1;
            }
        });
    }

    public function priceList()
    {
        return $this->belongsTo(PriceList::class);
    }

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

    public function promoActive(): bool
    {
        if ($this->promo_price === null || (float) $this->promo_price <= 0) {
            return false;
        }
        $now = now();
        return (!$this->promo_starts_at || $this->promo_starts_at <= $now) && (!$this->promo_ends_at || $this->promo_ends_at >= $now);
    }
}

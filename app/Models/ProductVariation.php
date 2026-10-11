<?php

namespace App\Models;

use App\Traits\HasUid;

use App\Services\BarcodeGenerator;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductVariation extends Model
{
    use HasUid;
    use Auditable;

    protected string $auditModule = 'products';
    protected array $auditExclude = ['stock'];

    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'barcode',
        'price',
        'cost_price',
        'sale_price',
        'wholesale_price',
        'stock',
        'reorder_level',
        'is_active',
        'attributes',
        'image'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'stock' => 'float',
        'reorder_level' => 'float',
        'is_active' => 'boolean',
        'attributes' => 'array',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Generate barcode if not provided
        static::creating(function ($variation) {
            if (empty($variation->barcode)) {
                $variation->barcode = BarcodeGenerator::next();
            }
        });

        // Make sure SKU is unique if not provided
        static::creating(function ($variation) {
            if (empty($variation->sku)) {
                $baseSku = $variation->product->sku ?? 'PROD';
                $variation->sku = $baseSku . '-' . strtoupper(Str::random(6));
            }
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the display name with attributes
     */
    public function getDisplayNameAttribute()
    {
        $productName = $this->product->title ?? '';
        $label = $this->label;

        return $productName . ($label ? ' (' . $label . ')' : '');
    }

    /**
     * "Red / XL" – from the saved name or the attribute values.
     */
    public function getLabelAttribute(): string
    {
        if (!empty($this->getAttributeFromArray('name'))) {
            return $this->getAttributeFromArray('name');
        }
        $attrs = $this->getAttribute('attributes');
        if (is_string($attrs)) {
            $attrs = json_decode($attrs, true);
        }
        return is_array($attrs) ? implode(' / ', array_filter(array_map('strval', $attrs))) : '';
    }

    public function getSellingPriceAttribute(): float
    {
        if ($this->sale_price !== null && (float) $this->sale_price > 0) {
            return (float) $this->sale_price;
        }
        if ($this->price !== null && (float) $this->price > 0) {
            return (float) $this->price;
        }
        return (float) ($this->product?->selling_price ?? 0);
    }

    public function getEffectiveCostAttribute(): float
    {
        return (float) ($this->cost_price ?? $this->product?->cost_price ?? 0);
    }

    public function auditLabel(): string
    {
        return ($this->product->title ?? 'Product') . ' – ' . $this->label;
    }

    /**
     * Get the effective price (sale_price if available, otherwise price)
     */
    public function getEffectivePriceAttribute()
    {
        return $this->selling_price;
    }

    /**
     * Scope to search by barcode
     */
    public function scopeByBarcode($query, $barcode)
    {
        return $query->where('barcode', $barcode);
    }

    /**
     * Check if variation is in stock
     */
    public function getIsInStockAttribute()
    {
        return (float) $this->stock > 0;
    }

    /**
     * Check if variation is on sale
     */
    public function getIsOnSaleAttribute()
    {
        return !is_null($this->sale_price) && $this->sale_price < $this->price;
    }
}

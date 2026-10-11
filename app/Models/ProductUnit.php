<?php

namespace App\Models;

use App\Traits\HasUid;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * A "packaging" of a product: Piece ×1, Pack ×6, Carton ×24 …
 * Each pack can carry its own selling price, wholesale price and barcode.
 * Stock is always held in the product's base unit (the pack with is_base = 1).
 */
class ProductUnit extends Model
{
    use HasUid;
    use \App\Traits\IndexesBarcodes;
    use Auditable;

    protected $table = 'product_unit';

    protected $fillable = [
        'product_id', 'unit_id', 'quantity_per_unit', 'price', 'wholesale_price', 'cost_price',
        'barcode', 'is_base', 'is_sellable', 'sort_order',
    ];

    protected $casts = [
        'quantity_per_unit' => 'float',
        'price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'is_base' => 'boolean',
        'is_sellable' => 'boolean',
    ];

    protected string $auditModule = 'products';

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function getNameAttribute(): string
    {
        return $this->unit->name ?? 'Unit';
    }

    public function getLabelAttribute(): string
    {
        $name = $this->unit->name ?? 'Unit';
        $qty = $this->quantity_per_unit;
        if ($this->is_base || $qty == 1) {
            return $name;
        }
        $baseName = $this->product?->baseUnitName() ?? 'pcs';
        return $name . ' (' . rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.') . ' ' . $baseName . ')';
    }

    public function auditLabel(): string
    {
        return ($this->product->title ?? 'Product') . ' – ' . ($this->unit->name ?? 'pack');
    }
}

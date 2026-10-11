<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PriceChangeBatch;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariation;
use App\Models\SystemSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Works out what an item costs at the till, and applies / records price changes.
 *
 * Price for one line = (base-unit price) adjusted for the pack the customer is buying:
 *   - Pack with its own price (e.g. Carton of 24 = ₦11,500) → that price.
 *   - Pack without a price → base price × quantity in the pack.
 *   - Variation with its own price buying a priced pack → pack price scaled by
 *     (variation price ÷ product price) so the carton discount carries over.
 *   - Wholesale customers get wholesale prices where they are set.
 *   - An active promo price replaces the base price (packs inherit the same % off).
 */
class PricingService
{
    public const FIELDS = [
        'price' => 'Selling price',
        'sale_price' => 'Sale price',
        'cost_price' => 'Cost price',
        'wholesale_price' => 'Wholesale price',
        'promo_price' => 'Promo price',
    ];

    public function quote(Product $product, ?ProductVariation $variation = null, ?ProductUnit $pack = null, ?Customer $customer = null): array
    {
        // Branch price lists replace catalogue prices item by item.
        [$product, $variation, $pack, $priceList] = app(\App\Services\Catalogue\PriceListResolver::class)->apply($product, $variation, $pack);

        $wholesale = $customer && strtolower((string) $customer->customer_type) === 'wholesale';
        $qtyPer = $pack ? max(0.001, (float) $pack->quantity_per_unit) : 1.0;

        // ---- base-unit retail price
        $productBase = $product->selling_price;
        $base = $variation ? $variation->selling_price : $productBase;
        $regularBase = $variation
            ? (float) ($variation->price ?: $product->price)
            : (float) $product->price;

        // ---- wholesale base
        $wholesaleBase = null;
        if ($wholesale) {
            $wholesaleBase = $variation?->wholesale_price ?: $product->wholesale_price;
            $wholesaleBase = $wholesaleBase !== null && (float) $wholesaleBase > 0 ? (float) $wholesaleBase : null;
        }

        // ---- pack price
        $price = null;
        $source = 'base';
        if ($pack && !$pack->is_base && $qtyPer != 1.0) {
            if ($wholesale && $pack->wholesale_price !== null && (float) $pack->wholesale_price > 0) {
                $price = (float) $pack->wholesale_price;
                $source = 'pack_wholesale';
            } elseif ($pack->price !== null && (float) $pack->price > 0) {
                $price = (float) $pack->price;
                $source = 'pack';
                // Scale to the variation's price level.
                if ($variation && (float) $product->price > 0 && $variation->price && (float) $variation->price != (float) $product->price) {
                    $price = $price * ((float) $variation->price / (float) $product->price);
                }
                // Promo: give the same percentage off on the pack.
                if ($product->isPromoActive() && (float) $product->price > 0 && !$variation) {
                    $price = $price * ((float) $product->promo_price / (float) $product->price);
                    $source = 'pack_promo';
                }
            }
        } elseif ($pack && ($pack->is_base || $qtyPer == 1.0) && $pack->price !== null && (float) $pack->price > 0 && !$variation && !$product->isPromoActive()) {
            $price = (float) $pack->price;
            $source = 'pack';
        }

        if ($price === null) {
            $unit = $wholesaleBase ?? $base;
            $price = $unit * $qtyPer;
            $source = $wholesaleBase ? 'wholesale' : ($product->isPromoActive() && !$variation ? 'promo' : 'base');
        }

        // ---- cost
        $unitCost = $variation ? $variation->effective_cost : (float) ($product->cost_price ?? 0);
        $cost = ($pack && $pack->cost_price !== null && (float) $pack->cost_price > 0)
            ? (float) $pack->cost_price
            : $unitCost * $qtyPer;

        $price = round($price, 2);

        return [
            'price' => $price,
            'regular_price' => round($regularBase * $qtyPer, 2),
            'cost' => round($cost, 2),
            'quantity_per_unit' => $qtyPer,
            'source' => $source,
            'below_cost' => $cost > 0 && $price < $cost,
            'wholesale' => $wholesale,
            'price_list' => $priceList,
        ];
    }

    // -----------------------------------------------------------------
    // Price changes
    // -----------------------------------------------------------------

    /**
     * Change a price on a product / variation / pack and record it.
     */
    public function setPrice(Product|ProductVariation|ProductUnit $model, string $field, ?float $value, string $source = 'manual', ?string $reason = null, ?string $batchId = null): ?PriceHistory
    {
        // On a branch server, head-office products keep head-office prices; the branch requests changes.
        // (Cost from goods received and approved requests are the exceptions.)
        $product = $model instanceof Product ? $model : $model->product;
        if (!in_array($source, ['receive', 'branch_request', 'sync'], true)
            && \App\Services\Catalogue\CatalogueRequests::governed($product)) {
            throw new \App\Exceptions\CentralProductException($product->title);
        }

        $old = $model->{$field};
        $old = $old === null ? null : (float) $old;
        if ($old === $value || ($old !== null && $value !== null && abs($old - $value) < 0.005)) {
            return null;
        }

        $model->{$field} = $value;
        $model->save();

        return $this->recordHistory($model, $field, $old, $value, $source, $reason, $batchId);
    }

    public function recordHistory($model, string $field, $old, $new, string $source = 'manual', ?string $reason = null, ?string $batchId = null): PriceHistory
    {
        [$productId, $variationId, $packId] = match (true) {
            $model instanceof ProductVariation => [$model->product_id, $model->id, null],
            $model instanceof ProductUnit => [$model->product_id, null, $model->id],
            default => [$model->id, null, null],
        };

        return PriceHistory::create([
            'product_id' => $productId,
            'product_variation_id' => $variationId,
            'product_unit_id' => $packId,
            'field' => $model instanceof ProductUnit ? 'pack_' . $field : $field,
            'old_value' => $old,
            'new_value' => $new,
            'batch_id' => $batchId,
            'source' => $source,
            'reason' => $reason,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Compare two arrays of price fields and write history rows for differences.
     */
    public function recordDiff($model, array $before, string $source = 'manual', ?string $reason = null): void
    {
        foreach ($before as $field => $old) {
            $new = $model->{$field};
            $o = $old === null || $old === '' ? null : round((float) $old, 2);
            $n = $new === null || $new === '' ? null : round((float) $new, 2);
            if ($o !== $n) {
                $this->recordHistory($model, $field, $o, $n, $source, $reason);
            }
        }
    }

    /**
     * Build the list of products affected by a bulk change and the new price for each.
     *
     * $criteria: product_ids[], category_id, brand_id, search, include_variations, include_packs
     * $rule:     field, method (percent|amount|set|markup), value, rounding, direction (increase|decrease)
     */
    public function previewBulk(array $criteria, array $rule): Collection
    {
        $field = $rule['field'] ?? 'price';
        $q = Product::query()->with(['variations', 'packs.unit', 'category:id,name']);
        if (\App\Services\MultiBranch\Install::isBranch()) {
            $q->where('owner', '!=', 'central'); // head-office products: request changes instead
        }

        if (!empty($criteria['product_ids'])) {
            $q->whereIn('id', (array) $criteria['product_ids']);
        }
        if (!empty($criteria['category_id'])) {
            $q->where('category_id', $criteria['category_id']);
        }
        if (!empty($criteria['brand_id'])) {
            $q->where('brand_id', $criteria['brand_id']);
        }
        if (!empty($criteria['search'])) {
            $s = $criteria['search'];
            $q->where(fn ($w) => $w->where('title', 'like', "%$s%")->orWhere('sku', 'like', "%$s%")->orWhere('barcode', $s));
        }

        $rows = collect();
        foreach ($q->orderBy('title')->limit(2000)->get() as $product) {
            $rows->push($this->previewRow($product, $product, $field, $rule));

            if (!empty($criteria['include_variations']) && in_array($field, ['price', 'sale_price', 'cost_price', 'wholesale_price'])) {
                foreach ($product->variations as $v) {
                    if ($v->{$field} !== null) {
                        $rows->push($this->previewRow($product, $v, $field, $rule));
                    }
                }
            }
            if (!empty($criteria['include_packs']) && in_array($field, ['price', 'wholesale_price', 'cost_price'])) {
                foreach ($product->packs as $pack) {
                    if ($pack->{$field} !== null && !$pack->is_base) {
                        $rows->push($this->previewRow($product, $pack, $field, $rule));
                    }
                }
            }
        }

        return $rows->filter(fn ($r) => $r['new'] !== null && $r['changed'])->values();
    }

    protected function previewRow(Product $product, $model, string $field, array $rule): array
    {
        $old = $model->{$field} !== null ? (float) $model->{$field} : null;
        $new = $this->applyRule($old, $model, $rule);
        $cost = $model instanceof ProductVariation ? $model->effective_cost
            : ($model instanceof ProductUnit ? (float) ($model->cost_price ?? ($product->cost_price * $model->quantity_per_unit)) : (float) $product->cost_price);

        $label = $product->title;
        $kind = 'product';
        if ($model instanceof ProductVariation) {
            $label .= ' – ' . $model->label;
            $kind = 'variation';
        } elseif ($model instanceof ProductUnit) {
            $label .= ' – ' . ($model->unit->name ?? 'Pack') . ' ×' . $model->quantity_per_unit;
            $kind = 'pack';
        }

        return [
            'key' => $kind . ':' . $model->id,
            'kind' => $kind,
            'id' => $model->id,
            'product_id' => $product->id,
            'label' => $label,
            'sku' => $model->sku ?? $product->sku,
            'category' => $product->category->name ?? null,
            'old' => $old,
            'new' => $new,
            'cost' => $cost,
            'changed' => $new !== null && ($old === null || abs($new - $old) >= 0.005),
            'below_cost' => $field !== 'cost_price' && $cost > 0 && $new !== null && $new < $cost,
        ];
    }

    public function applyRule(?float $old, $model, array $rule): ?float
    {
        $value = (float) ($rule['value'] ?? 0);
        $method = $rule['method'] ?? 'percent';
        $dir = ($rule['direction'] ?? 'increase') === 'decrease' ? -1 : 1;

        $new = match ($method) {
            'percent' => $old === null ? null : $old * (1 + $dir * $value / 100),
            'amount' => $old === null ? null : $old + $dir * $value,
            'set' => $value,
            // Selling price = cost + markup %
            'markup' => (function () use ($model, $value) {
                $cost = $model instanceof ProductVariation ? $model->effective_cost : (float) ($model->cost_price ?? 0);
                return $cost > 0 ? $cost * (1 + $value / 100) : null;
            })(),
            default => $old,
        };

        if ($new === null) {
            return null;
        }

        $new = max(0, $new);
        $rounding = (float) ($rule['rounding'] ?? SystemSetting::get('price_rounding', 0));
        if ($rounding > 0) {
            $new = ceil(round($new / $rounding, 6)) * $rounding;
        }
        return round($new, 2);
    }

    /**
     * Apply a previewed bulk change. Returns the batch.
     */
    public function applyBulk(array $criteria, array $rule, ?array $onlyKeys = null, ?string $description = null): PriceChangeBatch
    {
        $field = $rule['field'] ?? 'price';
        $rows = $this->previewBulk($criteria, $rule);
        if ($onlyKeys !== null) {
            $rows = $rows->whereIn('key', $onlyKeys)->values();
        }

        return DB::transaction(function () use ($rows, $field, $criteria, $rule, $description) {
            $batchId = (string) Str::uuid();
            $batch = PriceChangeBatch::create([
                'batch_id' => $batchId,
                'description' => $description ?: $this->describeRule($rule),
                'criteria' => ['criteria' => $criteria, 'rule' => $rule],
                'items_count' => $rows->count(),
                'user_id' => auth()->id(),
            ]);

            AuditLogger::mute(function () use ($rows, $field, $batchId, $rule) {
                foreach ($rows as $row) {
                    $model = match ($row['kind']) {
                        'variation' => ProductVariation::find($row['id']),
                        'pack' => ProductUnit::find($row['id']),
                        default => Product::find($row['id']),
                    };
                    if ($model) {
                        $this->setPrice($model, $field, $row['new'], 'bulk', $rule['reason'] ?? null, $batchId);
                    }
                }
            });

            AuditLogger::log('price_changed', "Bulk price change: {$batch->description} ({$rows->count()} items)", 'pricing',
                $batch, null, ['rule' => $rule, 'items' => $rows->count()]);

            return $batch;
        });
    }

    public function revertBatch(PriceChangeBatch $batch): int
    {
        if ($batch->reverted_at) {
            throw new \RuntimeException('This price change has already been reverted.');
        }

        return DB::transaction(function () use ($batch) {
            $count = 0;
            $revertId = (string) Str::uuid();
            AuditLogger::mute(function () use ($batch, &$count, $revertId) {
                foreach ($batch->histories as $h) {
                    $field = str_starts_with($h->field, 'pack_') ? substr($h->field, 5) : $h->field;
                    $model = $h->product_unit_id ? ProductUnit::find($h->product_unit_id)
                        : ($h->product_variation_id ? ProductVariation::find($h->product_variation_id) : Product::find($h->product_id));
                    if ($model && $this->setPrice($model, $field, $h->old_value !== null ? (float) $h->old_value : null, 'revert', 'Reverted batch ' . substr($batch->batch_id, 0, 8), $revertId)) {
                        $count++;
                    }
                }
            });
            $batch->update(['reverted_at' => now(), 'reverted_by' => auth()->id()]);
            AuditLogger::log('price_changed', "Reverted bulk price change: {$batch->description} ({$count} items)", 'pricing', $batch);
            return $count;
        });
    }

    public function describeRule(array $rule): string
    {
        $field = self::FIELDS[$rule['field'] ?? 'price'] ?? $rule['field'];
        $v = $rule['value'] ?? 0;
        $dir = ($rule['direction'] ?? 'increase') === 'decrease' ? 'Decrease' : 'Increase';
        return match ($rule['method'] ?? 'percent') {
            'percent' => "$dir $field by {$v}%",
            'amount' => "$dir $field by " . number_format((float) $v, 2),
            'set' => "Set $field to " . number_format((float) $v, 2),
            'markup' => "Set $field to cost + {$v}%",
            default => "Change $field",
        };
    }
}

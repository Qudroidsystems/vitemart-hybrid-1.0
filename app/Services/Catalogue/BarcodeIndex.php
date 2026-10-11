<?php

namespace App\Services\Catalogue;

use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductUnit;
use App\Models\ProductVariation;
use Illuminate\Support\Facades\Schema;

/**
 * product_barcodes holds every code that identifies something sellable:
 *  - "primary": the barcode column on the product / variation / pack
 *  - "extra":   other codes for the same item (new packaging, a second supplier, an old code)
 * The unique index on `barcode` is what guarantees a code can never point at two items.
 */
class BarcodeIndex
{
    protected static ?bool $ready = null;

    public static function ready(): bool
    {
        return static::$ready ??= Schema::hasTable('product_barcodes');
    }

    /** @return array{0:int,1:?int,2:?int} product_id, variation_id, pack_id */
    protected static function keys($model): array
    {
        return match (true) {
            $model instanceof Product => [$model->id, null, null],
            $model instanceof ProductVariation => [$model->product_id, $model->id, null],
            $model instanceof ProductUnit => [$model->product_id, null, $model->id],
        };
    }

    protected static function scope($model)
    {
        [$pid, $vid, $uid] = static::keys($model);
        return ProductBarcode::where('product_id', $pid)
            ->where('kind', 'primary')
            ->when($vid, fn ($q) => $q->where('product_variation_id', $vid), fn ($q) => $q->whereNull('product_variation_id'))
            ->when($uid, fn ($q) => $q->where('product_unit_id', $uid), fn ($q) => $q->whereNull('product_unit_id'));
    }

    public static function syncPrimary($model): void
    {
        if (!static::ready()) {
            return;
        }
        [$pid, $vid, $uid] = static::keys($model);
        $code = trim((string) $model->barcode);
        $row = static::scope($model)->first();

        if ($code === '') {
            $row?->delete();
            return;
        }
        if ($row && $row->barcode === $code) {
            return;
        }
        // The code may already exist as an "extra" on this very item – promote it.
        $existing = ProductBarcode::where('barcode', $code)->first();
        if ($existing && $existing->product_id === $pid && $existing->product_variation_id === $vid && $existing->product_unit_id === $uid) {
            $row?->delete();
            $existing->update(['kind' => 'primary']);
            return;
        }
        if ($existing) {
            // Validation should have stopped this; never let one code point at two items.
            throw new \App\Exceptions\DuplicateBarcodeException($code);
        }

        if ($row) {
            $row->update(['barcode' => $code]);
        } else {
            ProductBarcode::create([
                'barcode' => $code, 'product_id' => $pid, 'product_variation_id' => $vid, 'product_unit_id' => $uid,
                'kind' => 'primary', 'origin_branch_id' => \App\Services\MultiBranch\Install::branchId(),
            ]);
        }
    }

    public static function forget($model): void
    {
        if (!static::ready()) {
            return;
        }
        [$pid, $vid, $uid] = static::keys($model);
        $q = ProductBarcode::where('product_id', $pid);
        if ($model instanceof Product) {
            $q->delete(); // product gone: all its codes go
            return;
        }
        $vid ? $q->where('product_variation_id', $vid)->delete() : $q->where('product_unit_id', $uid)->delete();
    }

    /** Add an extra code to an item. */
    public static function addExtra(Product $product, string $code, ?ProductVariation $variation = null, ?ProductUnit $pack = null, ?string $note = null): ProductBarcode
    {
        $code = trim($code);
        if ($code === '' || mb_strlen($code) > 64) {
            throw new \InvalidArgumentException('Enter a barcode of up to 64 characters.');
        }
        if (ProductBarcode::where('barcode', $code)->exists() || \App\Services\BarcodeGenerator::exists($code)) {
            throw new \App\Exceptions\DuplicateBarcodeException($code);
        }
        return ProductBarcode::create([
            'barcode' => $code, 'product_id' => $product->id, 'product_variation_id' => $variation?->id,
            'product_unit_id' => $pack?->id, 'kind' => 'extra', 'note' => $note,
            'origin_branch_id' => \App\Services\MultiBranch\Install::branchId(),
        ]);
    }

    /** Re-create the primary rows from the barcode columns (repair tool). */
    public static function rebuild(): int
    {
        $n = 0;
        foreach ([Product::class, ProductVariation::class, ProductUnit::class] as $cls) {
            $cls::query()->whereNotNull('barcode')->where('barcode', '!=', '')->chunkById(500, function ($rows) use (&$n) {
                foreach ($rows as $m) {
                    try {
                        static::syncPrimary($m);
                        $n++;
                    } catch (\App\Exceptions\DuplicateBarcodeException $e) {
                        // left for the "duplicate barcodes" report
                    }
                }
            });
        }
        return $n;
    }
}

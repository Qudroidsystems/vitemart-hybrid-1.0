<?php

namespace App\Services\Catalogue;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductUnit;
use App\Models\ProductVariation;
use App\Models\Unit;
use App\Services\BarcodeGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A product as it travels between servers: identified by uid, with category / brand / units by name.
 * Used for branch → head-office submissions now, and for the catalogue feed in the sync phase.
 */
class ProductPayload
{
    public static function export(Product $p): array
    {
        $p->loadMissing(['packs.unit', 'variations', 'category', 'brand']);
        $base = $p->packs->firstWhere('is_base', true);
        $extras = class_exists(ProductBarcode::class)
            ? ProductBarcode::where('product_id', $p->id)->where('kind', 'extra')->get()
            : collect();

        return [
            'uid' => $p->uid,
            'version' => (int) ($p->version ?? 1),
            'title' => $p->title,
            'sku' => $p->sku,
            'barcode' => $p->barcode,
            'plu_code' => $p->plu_code,
            'description' => $p->description,
            'product_type' => $p->product_type ?: 'simple',
            'category' => $p->category ? ['uid' => $p->category->uid, 'name' => $p->category->name] : null,
            'brand' => $p->brand ? ['uid' => $p->brand->uid, 'name' => $p->brand->name] : null,
            'base_unit' => $base?->unit?->name,
            'base_unit_uid' => $base?->unit?->uid,
            'base_uid' => $base?->uid,
            'base_barcode' => $base?->barcode,
            'price' => (float) $p->price,
            'cost_price' => $p->cost_price !== null ? (float) $p->cost_price : null,
            'wholesale_price' => $p->wholesale_price !== null ? (float) $p->wholesale_price : null,
            'reorder_level' => $p->reorder_level,
            'is_weighted' => (bool) $p->is_weighted,
            'track_stock' => $p->track_stock !== false,
            'allow_price_override' => (bool) $p->allow_price_override,
            'packs' => $p->packs->where('is_base', false)->values()->map(fn (ProductUnit $u) => [
                'uid' => $u->uid,
                'unit' => $u->unit?->name,
                'unit_uid' => $u->unit?->uid,
                'quantity_per_unit' => (float) $u->quantity_per_unit,
                'price' => $u->price !== null ? (float) $u->price : null,
                'wholesale_price' => $u->wholesale_price !== null ? (float) $u->wholesale_price : null,
                'barcode' => $u->barcode,
                'is_sellable' => (bool) $u->is_sellable,
            ])->all(),
            'variations' => $p->variations->map(fn (ProductVariation $v) => [
                'uid' => $v->uid,
                'name' => $v->name,
                'sku' => $v->sku,
                'barcode' => $v->barcode,
                'price' => $v->price !== null ? (float) $v->price : null,
                'cost_price' => $v->cost_price !== null ? (float) $v->cost_price : null,
                'wholesale_price' => $v->wholesale_price !== null ? (float) $v->wholesale_price : null,
                'attributes' => $v->attributes,
                'is_active' => (bool) $v->is_active,
            ])->all(),
            'extra_barcodes' => $extras->map(fn ($b) => ['barcode' => $b->barcode, 'note' => $b->note])->all(),
        ];
    }

    /** Barcodes in the payload that already belong to a different item on this server. */
    public static function conflicts(array $d): array
    {
        $codes = array_filter(array_merge(
            [$d['barcode'] ?? null],
            array_column($d['packs'] ?? [], 'barcode'),
            array_column($d['variations'] ?? [], 'barcode'),
            array_column($d['extra_barcodes'] ?? [], 'barcode'),
        ));
        $existing = Product::where('uid', $d['uid'])->first();
        $out = [];
        foreach ($codes as $code) {
            $row = ProductBarcode::where('barcode', $code)->first();
            if ($row && (!$existing || $row->product_id !== $existing->id)) {
                $out[] = $code;
            }
        }
        return $out;
    }

    /** Remove barcodes that already belong to another product (the rest of the record still applies). */
    public static function stripConflicts(array $d): array
    {
        $clash = static::conflicts($d);
        if (!$clash) {
            return $d;
        }
        if (in_array($d['barcode'] ?? null, $clash, true)) {
            $d['barcode'] = null;
        }
        foreach (['packs', 'variations'] as $k) {
            foreach ($d[$k] ?? [] as $i => $c) {
                if (in_array($c['barcode'] ?? null, $clash, true)) {
                    $d[$k][$i]['barcode'] = null;
                }
            }
        }
        $d['extra_barcodes'] = array_values(array_filter($d['extra_barcodes'] ?? [], fn ($b) => !in_array($b['barcode'] ?? null, $clash, true)));
        return $d;
    }

    /**
     * Create or update a product from a payload.  `owner` is central for head-office products.
     */
    public static function import(array $d, string $owner = 'central', ?int $originBranchId = null): Product
    {
        if ($c = static::conflicts($d)) {
            throw ValidationException::withMessages(['barcode' => 'Barcode already used by another product: ' . implode(', ', $c)]);
        }

        return DB::transaction(function () use ($d, $owner, $originBranchId) {
            $category = static::named(Category::class, $d['category'] ?? null);
            $brand = static::named(Brand::class, $d['brand'] ?? null);

            $p = Product::where('uid', $d['uid'])->first() ?? new Product();
            $isNew = !$p->exists;
            if ($isNew) {
                $p->forceFill(['uid' => $d['uid'], 'stock' => 0, 'origin_branch_id' => $originBranchId]);
            }
            $p->forceFill([
                'title' => $d['title'],
                'sku' => $d['sku'] ?? null,
                'barcode' => $d['barcode'] ?? null,
                'plu_code' => $d['plu_code'] ?? null,
                'description' => $d['description'] ?? null,
                'product_type' => $d['product_type'] ?? 'simple',
                'category_id' => $category?->id,
                'brand_id' => $brand?->id,
                'price' => $d['price'] ?? 0,
                'cost_price' => $d['cost_price'] ?? null,
                'wholesale_price' => $d['wholesale_price'] ?? null,
                'reorder_level' => $d['reorder_level'] ?? 10,
                'is_weighted' => (bool) ($d['is_weighted'] ?? false),
                'track_stock' => (bool) ($d['track_stock'] ?? true),
                'allow_price_override' => (bool) ($d['allow_price_override'] ?? false),
                'owner' => $owner,
                'central_status' => $owner === 'central' ? 'published' : 'local',
                'version' => max((int) ($d['version'] ?? 1), (int) ($p->version ?? 0)),
            ]);
            if ($isNew) {
                $p->is_active = true;
            }
            $p->save();

            // Base unit
            $baseUnit = static::unit($d['base_unit'] ?? null, $d['base_unit_uid'] ?? null);
            $base = ProductUnit::where('product_id', $p->id)->where('is_base', true)->first() ?? new ProductUnit(['product_id' => $p->id]);
            if (!empty($d['base_uid']) && $base->uid !== $d['base_uid']) {
                if ($base->exists && $base->uid) {
                    \App\Services\Sync\UidAliases::adopted('product_unit', $base->uid, $d['base_uid']);
                }
                $base->uid = $d['base_uid'];
            }
            $base->fill(['unit_id' => $baseUnit->id, 'quantity_per_unit' => 1, 'is_base' => true, 'is_sellable' => true, 'sort_order' => 0]);
            if (array_key_exists('base_barcode', $d)) {
                $base->barcode = $d['base_barcode'];
            }
            $base->save();

            $keep = [$base->id];
            foreach ($d['packs'] ?? [] as $i => $row) {
                $pack = ProductUnit::where('uid', $row['uid'])->first();
                if (!$pack && $p->exists) {
                    // same pack (unit + quantity) created on this server under another uid → it becomes this one
                    $same = ProductUnit::where('product_id', $p->id)->where('is_base', false)
                        ->where('unit_id', static::unit($row['unit'] ?? null)->id)->where('quantity_per_unit', $row['quantity_per_unit'])->first();
                    if ($same) {
                        \App\Services\Sync\UidAliases::adopted('product_unit', $same->uid, $row['uid']);
                        $same->uid = $row['uid'];
                        $pack = $same;
                    }
                }
                $pack ??= (new ProductUnit())->forceFill(['uid' => $row['uid']]);
                $pack->fill([
                    'product_id' => $p->id,
                    'unit_id' => static::unit($row['unit'] ?? null, $row['unit_uid'] ?? null)->id,
                    'quantity_per_unit' => $row['quantity_per_unit'],
                    'price' => $row['price'] ?? null,
                    'wholesale_price' => $row['wholesale_price'] ?? null,
                    'barcode' => $row['barcode'] ?? null,
                    'is_base' => false,
                    'is_sellable' => $row['is_sellable'] ?? true,
                    'sort_order' => $i + 1,
                ])->save();
                $keep[] = $pack->id;
            }
            ProductUnit::where('product_id', $p->id)->whereNotIn('id', $keep)->get()->each->delete();

            $keepV = [];
            foreach ($d['variations'] ?? [] as $row) {
                $v = ProductVariation::where('uid', $row['uid'])->first();
                if (!$v && $p->exists && (!empty($row['barcode']) || !empty($row['name']))) {
                    $same = ProductVariation::where('product_id', $p->id)
                        ->where(fn ($q) => $q->when(!empty($row['barcode']), fn ($w) => $w->where('barcode', $row['barcode']))
                            ->when(!empty($row['name']), fn ($w) => $w->orWhere('name', $row['name'])))->first();
                    if ($same) {
                        \App\Services\Sync\UidAliases::adopted('product_variations', $same->uid, $row['uid']);
                        $same->uid = $row['uid'];
                        $v = $same;
                    }
                }
                $v ??= (new ProductVariation())->forceFill(['uid' => $row['uid']]);
                $v->forceFill([
                    'product_id' => $p->id,
                    'name' => $row['name'] ?? null,
                    'sku' => $row['sku'] ?? null,
                    'barcode' => $row['barcode'] ?? null,
                    'price' => $row['price'] ?? null,
                    'cost_price' => $row['cost_price'] ?? null,
                    'wholesale_price' => $row['wholesale_price'] ?? null,
                    'attributes' => $row['attributes'] ?? null,
                    'is_active' => $row['is_active'] ?? true,
                ]);
                if (!$v->exists) {
                    $v->origin_branch_id = $originBranchId;
                }
                $v->save();
                $keepV[] = $v->id;
            }
            // Variations dropped from the payload: delete if never stocked, otherwise just stop selling them.
            foreach ($p->variations()->whereNotIn('id', $keepV ?: [0])->get() as $gone) {
                DB::table('stocks')->where('product_variation_id', $gone->id)->exists()
                    ? $gone->update(['is_active' => false])
                    : $gone->delete();
            }

            foreach ($d['extra_barcodes'] ?? [] as $e) {
                if (!ProductBarcode::where('barcode', $e['barcode'])->exists()) {
                    BarcodeIndex::addExtra($p, $e['barcode'], null, null, $e['note'] ?? null);
                }
            }

            return $p->fresh(['packs.unit', 'variations']);
        });
    }

    protected static function named(string $class, ?array $ref)
    {
        if (!$ref || empty($ref['name'])) {
            return null;
        }
        if (!empty($ref['uid']) && ($m = $class::where('uid', $ref['uid'])->first())) {
            return $m;
        }
        return $class::where('name', $ref['name'])->first()
            ?? tap(new $class(['name' => $ref['name']]), function ($m) use ($ref) {
                if (!empty($ref['uid'])) {
                    $m->uid = $ref['uid'];
                }
                $m->save();
            });
    }

    protected static function unit(?string $name, ?string $uid = null): Unit
    {
        $name = $name ?: 'Piece';
        return ($uid ? Unit::where('uid', $uid)->first() : null)
            ?? Unit::where('name', $name)->first()
            ?? tap(new Unit(['name' => $name, 'short_name' => strtolower(substr($name, 0, 3)), 'is_active' => true]), function ($u) use ($uid) {
                if ($uid) {
                    $u->uid = $uid;
                }
                $u->save();
            });
    }
}

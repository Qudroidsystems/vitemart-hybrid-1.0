<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariation;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Creates / updates a product with its packs (units), attributes and variations.
 *
 * Variations and packs are UPSERTED by id – never deleted and recreated – so their
 * stock history and sales history stay linked. Removed variations that have stock
 * or sales are deactivated instead of deleted.
 */
class ProductService
{
    public const PRICE_FIELDS = ['price', 'cost_price', 'sale_price', 'wholesale_price', 'promo_price'];

    public function __construct(
        protected PricingService $pricing,
        protected StockAdjustmentService $adjustments,
    ) {
    }

    public function rules(?Product $product = null): array
    {
        $id = $product?->id;

        return [
            'title' => 'required|string|max:255',
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($id)],
            'barcode' => ['nullable', 'string', 'max:64'],
            'plu_code' => ['nullable', 'string', 'max:10', Rule::unique('products', 'plu_code')->ignore($id)],
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'promo_price' => 'nullable|numeric|min:0',
            'promo_starts_at' => 'nullable|date',
            'promo_ends_at' => 'nullable|date|after_or_equal:promo_starts_at',
            'reorder_level' => 'nullable|numeric|min:0',
            'brand_id' => 'nullable|exists:brands,id',
            'category_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'product_type' => 'required|in:simple,variable',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',

            'base_unit_id' => 'required|exists:units,id',
            'packs' => 'nullable|array',
            'packs.*.id' => 'nullable|integer',
            'packs.*.unit_id' => 'required_with:packs|exists:units,id',
            'packs.*.quantity_per_unit' => 'required_with:packs|numeric|min:0.001',
            'packs.*.price' => 'nullable|numeric|min:0',
            'packs.*.wholesale_price' => 'nullable|numeric|min:0',
            'packs.*.cost_price' => 'nullable|numeric|min:0',
            'packs.*.barcode' => 'nullable|string|max:64',

            'attributes_input' => 'nullable|array',
            'variations' => 'nullable|array',
            'variations.*.id' => 'nullable|integer',
            'variations.*.name' => 'nullable|string|max:255',
            'variations.*.sku' => 'nullable|string|max:100',
            'variations.*.barcode' => 'nullable|string|max:64',
            'variations.*.price' => 'nullable|numeric|min:0',
            'variations.*.cost_price' => 'nullable|numeric|min:0',
            'variations.*.sale_price' => 'nullable|numeric|min:0',
            'variations.*.wholesale_price' => 'nullable|numeric|min:0',
            'variations.*.opening_stock' => 'nullable|numeric|min:0',
            'variations.*.image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',

            'opening_stock' => 'nullable|numeric|min:0',
            'opening_location_id' => 'nullable|exists:stock_locations,id',
        ];
    }

    public function save(Request $request, ?Product $product = null): Product
    {
        $request->validate($this->rules($product), [
            'base_unit_id.required' => 'Choose the unit the product is counted in (e.g. Piece).',
        ]);

        $this->validateBarcodes($request, $product);

        $creating = !$product;

        $product = DB::transaction(function () use ($request, $product, $creating) {
            $data = [
                'title' => trim($request->title),
                'sku' => $request->filled('sku') ? strtoupper(trim($request->sku))
                    : BarcodeGenerator::suggestSku($request->title, optional(\App\Models\Category::find($request->category_id))->name),
                'plu_code' => $request->filled('plu_code') ? trim($request->plu_code) : null,
                'description' => $request->description,
                'product_type' => $request->product_type,
                'brand_id' => $request->brand_id ?: null,
                'category_id' => $request->category_id ?: null,
                'is_featured' => $request->boolean('is_featured'),
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
                'is_weighted' => $request->boolean('is_weighted'),
                'track_stock' => $request->has('track_stock') ? $request->boolean('track_stock') : true,
                'allow_price_override' => $request->boolean('allow_price_override'),
                'reorder_level' => $request->input('reorder_level', 10) ?? 10,
                'promo_starts_at' => $request->promo_starts_at ?: null,
                'promo_ends_at' => $request->promo_ends_at ?: null,
            ];
            foreach (self::PRICE_FIELDS as $f) {
                $data[$f] = $request->filled($f) ? round((float) $request->input($f), 2) : null;
            }
            $data['price'] = $data['price'] ?? 0;
            if ($request->filled('barcode')) {
                $data['barcode'] = trim($request->barcode);
            }

            if ($request->hasFile('thumbnail')) {
                if ($product?->thumbnail) {
                    Storage::disk('public')->delete($product->thumbnail);
                }
                $data['thumbnail'] = $request->file('thumbnail')->store('product', 'public');
            }

            if ($creating) {
                $data['stock'] = 0;
                $product = Product::create($data); // barcode auto-generated if empty
                $this->pricing->recordHistory($product, 'price', null, $product->price, 'create', 'Product created');
            } else {
                $before = $product->only(self::PRICE_FIELDS);
                if (empty($data['barcode']) && !$product->barcode) {
                    $data['barcode'] = BarcodeGenerator::next();
                }
                $product->update($data);
                $this->pricing->recordDiff($product, $before, 'manual', 'Edited on product form');
            }

            $this->syncPacks($product, $request);
            $this->syncImages($product, $request);
            $this->syncAttributesAndVariations($product, $request);

            return $product;
        });

        // Opening stock – posted as an approved "goods received" entry so it shows in history.
        $this->postOpeningStock($product, $request, $creating);

        return $product->fresh(['packs.unit', 'variations']);
    }

    protected function validateBarcodes(Request $request, ?Product $product): void
    {
        $errors = [];
        $seen = [];
        $check = function (?string $code, string $field, ?string $table = null, $ignoreId = null) use (&$errors, &$seen) {
            $code = trim((string) $code);
            if ($code === '') {
                return;
            }
            if (isset($seen[$code])) {
                $errors[$field] = "Barcode {$code} is used twice on this form.";
                return;
            }
            $seen[$code] = true;
            if (BarcodeGenerator::exists($code, $table, $ignoreId)) {
                $owner = BarcodeGenerator::owner($code);
                $isSelf = $owner && (
                    ($owner['type'] === 'product' && $product && $owner['model']->id === $product->id) ||
                    ($owner['type'] === 'variation' && $product && $owner['model']->product_id === $product->id) ||
                    ($owner['type'] === 'pack' && $product && $owner['model']->product_id === $product->id)
                );
                if (!$isSelf) {
                    $name = $owner['type'] === 'product' ? $owner['model']->title : ($owner['model']->product->title ?? 'another product');
                    $errors[$field] = "Barcode {$code} already belongs to {$name}.";
                }
            }
        };

        $check($request->barcode, 'barcode', 'products', $product?->id);
        foreach ((array) $request->input('packs', []) as $i => $p) {
            $check($p['barcode'] ?? null, "packs.$i.barcode", 'product_unit', $p['id'] ?? null);
        }
        foreach ((array) $request->input('variations', []) as $i => $v) {
            $check($v['barcode'] ?? null, "variations.$i.barcode", 'product_variations', $v['id'] ?? null);
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    protected function syncPacks(Product $product, Request $request): void
    {
        $baseUnitId = (int) $request->base_unit_id;
        $keep = [];

        // Base pack (quantity 1)
        $base = ProductUnit::where('product_id', $product->id)->where('is_base', true)->first()
            ?? ProductUnit::where('product_id', $product->id)->where('unit_id', $baseUnitId)->first()
            ?? new ProductUnit(['product_id' => $product->id]);
        $base->fill([
            'unit_id' => $baseUnitId,
            'quantity_per_unit' => 1,
            'is_base' => true,
            'is_sellable' => true,
            'sort_order' => 0,
        ]);
        if ($request->filled('base_barcode')) {
            $base->barcode = trim($request->base_barcode);
        }
        $base->save();
        $keep[] = $base->id;

        foreach ((array) $request->input('packs', []) as $i => $row) {
            if (empty($row['unit_id']) || empty($row['quantity_per_unit'])) {
                continue;
            }
            $pack = !empty($row['id'])
                ? ProductUnit::where('product_id', $product->id)->find($row['id'])
                : null;
            $pack = $pack ?? new ProductUnit(['product_id' => $product->id]);

            $before = $pack->exists ? $pack->only(['price', 'wholesale_price', 'cost_price']) : null;

            $pack->fill([
                'unit_id' => (int) $row['unit_id'],
                'quantity_per_unit' => (float) $row['quantity_per_unit'],
                'price' => isset($row['price']) && $row['price'] !== '' ? round((float) $row['price'], 2) : null,
                'wholesale_price' => isset($row['wholesale_price']) && $row['wholesale_price'] !== '' ? round((float) $row['wholesale_price'], 2) : null,
                'cost_price' => isset($row['cost_price']) && $row['cost_price'] !== '' ? round((float) $row['cost_price'], 2) : null,
                'is_base' => false,
                'is_sellable' => !isset($row['is_sellable']) || filter_var($row['is_sellable'], FILTER_VALIDATE_BOOLEAN),
                'sort_order' => $i + 1,
            ]);

            $barcode = trim((string) ($row['barcode'] ?? ''));
            if ($barcode !== '') {
                $pack->barcode = $barcode;
            } elseif (!$pack->barcode && !empty($row['auto_barcode'])) {
                $pack->barcode = BarcodeGenerator::next();
            }
            $pack->save();
            $keep[] = $pack->id;

            if ($before) {
                $this->pricing->recordDiff($pack, $before, 'manual', 'Edited on product form');
            } elseif ($pack->price) {
                $this->pricing->recordHistory($pack, 'price', null, (float) $pack->price, 'create');
            }
        }

        // Remove packs that were deleted on the form.
        ProductUnit::where('product_id', $product->id)->whereNotIn('id', $keep)->get()->each->delete();
    }

    protected function syncImages(Product $product, Request $request): void
    {
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('product/gallery', 'public');
                $product->images()->create(['image_path' => $path]);
            }
        }
    }

    protected function syncAttributesAndVariations(Product $product, Request $request): void
    {
        // NOTE: $request->attributes is Laravel's internal parameter bag – the form field
        // is therefore called attributes_input.
        $attrInput = (array) $request->input('attributes_input', []);
        $product->attributes()->delete();
        foreach ($attrInput as $attr) {
            $name = trim($attr['name'] ?? '');
            $values = $attr['values'] ?? '';
            $values = is_array($values) ? $values : preg_split('/\s*,\s*/', (string) $values);
            $values = array_values(array_filter(array_map('trim', $values), fn ($v) => $v !== ''));
            if ($name && $values) {
                $product->attributes()->create(['name' => $name, 'values' => $values]);
            }
        }

        if ($request->product_type !== 'variable') {
            // Switching back to simple: deactivate (keep history) rather than delete.
            $product->variations()->update(['is_active' => false]);
            return;
        }

        $keep = [];
        $files = $request->file('variations', []);

        foreach ((array) $request->input('variations', []) as $i => $row) {
            $attributes = [];
            foreach ((array) ($row['attributes'] ?? []) as $k => $v) {
                if ($v !== null && $v !== '') {
                    $attributes[$k] = $v;
                }
            }

            $variation = !empty($row['id']) ? $product->variations()->find($row['id']) : null;
            $creating = !$variation;
            $variation = $variation ?? new ProductVariation(['product_id' => $product->id]);
            $before = $creating ? null : $variation->only(['price', 'cost_price', 'sale_price', 'wholesale_price']);

            $num = fn ($k) => isset($row[$k]) && $row[$k] !== '' ? round((float) $row[$k], 2) : null;

            $variation->fill([
                'name' => trim((string) ($row['name'] ?? '')) ?: implode(' / ', $attributes),
                'attributes' => $attributes,
                'price' => $num('price') ?? $product->price,
                'cost_price' => $num('cost_price'),
                'sale_price' => $num('sale_price'),
                'wholesale_price' => $num('wholesale_price'),
                'is_active' => !isset($row['is_active']) || filter_var($row['is_active'], FILTER_VALIDATE_BOOLEAN),
            ]);
            if (!empty($row['sku'])) {
                $variation->sku = strtoupper(trim($row['sku']));
            } elseif (!$variation->sku) {
                $suffix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', implode('', $attributes)), 0, 8)) ?: ($i + 1);
                $variation->sku = ($product->sku ?: 'PRD') . '-' . $suffix;
                while (ProductVariation::where('sku', $variation->sku)->where('id', '!=', $variation->id ?? 0)->exists()) {
                    $variation->sku .= rand(0, 9);
                }
            }
            if (!empty($row['barcode'])) {
                $variation->barcode = trim($row['barcode']);
            }

            $file = $files[$i]['image'] ?? null;
            if ($file instanceof UploadedFile) {
                $variation->image = $file->store('product/variations', 'public');
            }

            $variation->product_id = $product->id;
            $variation->save(); // barcode auto-generated on create
            $keep[] = $variation->id;

            if ($before) {
                $this->pricing->recordDiff($variation, $before, 'manual', 'Edited on product form');
            }

            if ($creating && !empty($row['opening_stock']) && (float) $row['opening_stock'] > 0) {
                $this->pendingVariationStock[] = [$variation->id, (float) $row['opening_stock'], $num('cost_price')];
            }
        }

        // Variations removed from the form
        $product->variations()->whereNotIn('id', $keep)->get()->each(function (ProductVariation $v) {
            $hasHistory = DB::table('stocks')->where('product_variation_id', $v->id)->exists()
                || DB::table('order_items')->where('variation_id', (string) $v->id)->exists();
            $hasHistory ? $v->update(['is_active' => false]) : $v->delete();
        });
    }

    protected array $pendingVariationStock = [];

    protected function postOpeningStock(Product $product, Request $request, bool $creating = true): void
    {
        $items = [];
        if ($creating && $product->product_type === 'simple' && (float) $request->input('opening_stock', 0) > 0) {
            $items[] = [
                'product_id' => $product->id,
                'mode' => 'add',
                'quantity' => (float) $request->opening_stock,
                'unit_cost' => $product->cost_price,
            ];
        }
        foreach ($this->pendingVariationStock as [$vid, $qty, $cost]) {
            $items[] = [
                'product_id' => $product->id,
                'variation_id' => $vid,
                'mode' => 'add',
                'quantity' => $qty,
                'unit_cost' => $cost ?? $product->cost_price,
            ];
        }
        $this->pendingVariationStock = [];

        if (!$items) {
            return;
        }

        // Opening stock is entered by the person creating the product; it follows the
        // normal approval rule (pending if they can't approve).
        $this->adjustments->create([
            'type' => 'receive',
            'location_id' => $request->opening_location_id,
            'reason' => 'Opening stock',
            'notes' => 'Entered when the product was created',
            'items' => $items,
        ], 'submit');
    }

    /** Units list; makes sure the basic supermarket units exist. */
    public static function ensureDefaultUnits(): void
    {
        $defaults = [
            ['Piece', 'pc'], ['Pack', 'pk'], ['Carton', 'ctn'], ['Dozen', 'dz'], ['Crate', 'crt'],
            ['Bag', 'bag'], ['Kilogram', 'kg'], ['Bottle', 'btl'], ['Sachet', 'sct'], ['Roll', 'rl'],
        ];
        foreach ($defaults as [$name, $short]) {
            if (!Unit::where('name', $name)->exists()) {
                $abbrTaken = Unit::where('abbreviation', $short)->exists();
                Unit::create(['name' => $name, 'short_name' => $short, 'abbreviation' => $abbrTaken ? null : $short, 'is_active' => true, 'is_default' => $name === 'Piece' && !Unit::where('is_default', true)->exists()]);
            }
        }
    }
}

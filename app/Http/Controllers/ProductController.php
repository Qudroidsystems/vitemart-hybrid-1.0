<?php

namespace App\Http\Controllers;

use App\Exports\ProductsExport;
use App\Imports\ProductsImport;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductUnit;
use App\Models\ProductVariation;
use App\Models\StockLocation;
use App\Models\Unit;
use App\Services\AuditLogger;
use App\Services\BarcodeGenerator;
use App\Services\PricingService;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    public function __construct(protected ProductService $products)
    {
        $this->middleware('permission:View product|Create product|Update product|Delete product', ['only' => ['index', 'show', 'lookup']]);
        $this->middleware('permission:Create product', ['only' => ['create', 'store', 'storeUnit']]);
        $this->middleware('permission:Update product', ['only' => ['edit', 'update', 'bulkAction']]);
        $this->middleware('permission:Delete product', ['only' => ['destroy']]);
        $this->middleware('permission:Print barcode labels|Create product|Update product', ['only' => ['labels']]);
    }

    // =================================================================
    // List
    // =================================================================

    public function index(Request $request)
    {
        $pagetitle = 'Products';

        $query = Product::query()
            ->with(['brand:id,name', 'category:id,name', 'packs.unit:id,name,short_name'])
            ->withCount(['variations as active_variations_count' => fn ($q) => $q->where('is_active', true)]);

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', $search)
                  ->orWhere('plu_code', $search)
                  ->orWhereHas('variations', fn ($v) => $v->where('barcode', $search)->orWhere('sku', 'like', "%{$search}%"))
                  ->orWhereHas('packs', fn ($p) => $p->where('barcode', $search));
            });
        }
        if ($request->filled('brand')) {
            $query->where('brand_id', $request->brand);
        }
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }
        if ($request->filled('type')) {
            $query->where('product_type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }
        switch ($request->stock) {
            case 'in_stock':
                $query->whereColumn('stock', '>', 'reorder_level');
                break;
            case 'low_stock':
                $query->where('stock', '>', 0)->whereColumn('stock', '<=', 'reorder_level');
                break;
            case 'out_of_stock':
                $query->where('stock', '<=', 0);
                break;
        }
        if ($request->boolean('promo')) {
            $query->whereNotNull('promo_price')->where(fn ($q) => $q->whereNull('promo_ends_at')->orWhere('promo_ends_at', '>=', now()));
        }
        if ($request->boolean('below_cost')) {
            $query->whereNotNull('cost_price')->whereRaw('COALESCE(NULLIF(sale_price,0), price) < cost_price');
        }
        if ($request->boolean('no_barcode')) {
            $query->where(fn ($q) => $q->whereNull('barcode')->orWhere('barcode', ''));
        }

        $sort = $request->get('sort', 'latest');
        match ($sort) {
            'name' => $query->orderBy('title'),
            'price_high' => $query->orderByDesc('price'),
            'price_low' => $query->orderBy('price'),
            'stock_low' => $query->orderBy('stock'),
            'stock_high' => $query->orderByDesc('stock'),
            default => $query->latest(),
        };

        $perPage = min(200, max(10, (int) $request->get('per_page', 25)));
        $products = $query->paginate($perPage)->withQueryString();

        $brands = Brand::orderBy('name')->get(['id', 'name']);
        $categories = Category::orderBy('name')->get(['id', 'name', 'parent_id']);
        $locations = StockLocation::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'is_default']);

        $stats = Product::query()->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) as out_of_stock,
                SUM(CASE WHEN stock > 0 AND stock <= reorder_level THEN 1 ELSE 0 END) as low_stock,
                SUM(CASE WHEN stock > 0 THEN stock * COALESCE(cost_price, 0) ELSE 0 END) as stock_cost,
                SUM(CASE WHEN stock > 0 THEN stock * COALESCE(NULLIF(sale_price, 0), price) ELSE 0 END) as stock_retail
            ')->first();

        return view('products.index', compact('products', 'brands', 'categories', 'locations', 'pagetitle', 'stats'));
    }

    // =================================================================
    // Create / edit
    // =================================================================

    public function create(Request $request)
    {
        ProductService::ensureDefaultUnits();

        if ($request->wantsJson()) {
            return response()->json([
                'brands' => Brand::orderBy('name')->get(),
                'categories' => Category::whereNull('parent_id')->with('children')->orderBy('name')->get(),
                'units' => Unit::orderBy('name')->get(),
            ]);
        }

        return $this->form(null, $request);
    }

    public function edit(Request $request, $id)
    {
        $product = Product::with(['packs.unit', 'variations', 'attributes', 'images', 'brand', 'category'])->findOrFail($id);

        if ($request->wantsJson()) {
            return response()->json($this->productPayload($product));
        }

        return $this->form($product, $request);
    }

    protected function form(?Product $product, Request $request)
    {
        $pagetitle = $product ? 'Edit ' . $product->title : 'Add Product';
        $units = Unit::where(fn ($q) => $q->where('is_active', true)->orWhereNull('is_active'))->orderBy('name')->get(['id', 'name', 'short_name']);
        $brands = Brand::orderBy('name')->get(['id', 'name']);
        $categories = Category::orderBy('name')->get(['id', 'name', 'parent_id']);
        $locations = StockLocation::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'is_default']);
        $payload = $product ? $this->productPayload($product) : null;

        // "Duplicate" – start a new product from an existing one.
        if (!$product && $request->filled('copy')) {
            $source = Product::with(['packs.unit', 'variations', 'attributes'])->find($request->copy);
            if ($source) {
                $payload = $this->productPayload($source);
                $payload['id'] = null;
                $payload['title'] .= ' (copy)';
                $payload['sku'] = '';
                $payload['barcode'] = '';
                $payload['plu_code'] = '';
                $payload['packs'] = array_map(fn ($p) => array_merge($p, ['id' => null, 'barcode' => '']), $payload['packs']);
                $payload['variations'] = array_map(fn ($v) => array_merge($v, ['id' => null, 'barcode' => '', 'sku' => '', 'stock' => 0]), $payload['variations']);
                $payload['thumbnail'] = null;
                $payload['gallery'] = [];
            }
        }

        $defaultUnitId = Unit::where('is_default', true)->value('id') ?? Unit::where('name', 'Piece')->value('id');

        return view('products.form', compact('product', 'payload', 'units', 'brands', 'categories', 'locations', 'pagetitle', 'defaultUnitId'));
    }

    protected function productPayload(Product $product): array
    {
        $base = $product->basePack();

        return [
            'id' => $product->id,
            'title' => $product->title,
            'sku' => $product->sku,
            'barcode' => $product->barcode ?? '',
            'plu_code' => $product->plu_code ?? '',
            'price' => $product->price,
            'cost_price' => $product->cost_price,
            'sale_price' => $product->sale_price,
            'wholesale_price' => $product->wholesale_price,
            'promo_price' => $product->promo_price,
            'promo_starts_at' => $product->promo_starts_at?->format('Y-m-d\\TH:i'),
            'promo_ends_at' => $product->promo_ends_at?->format('Y-m-d\\TH:i'),
            'reorder_level' => $product->reorder_level,
            'description' => $product->description ?? '',
            'product_type' => $product->product_type ?: 'simple',
            'is_featured' => (bool) $product->is_featured,
            'is_active' => (bool) ($product->is_active ?? true),
            'is_weighted' => (bool) $product->is_weighted,
            'track_stock' => $product->track_stock !== false,
            'allow_price_override' => (bool) $product->allow_price_override,
            'brand_id' => $product->brand_id,
            'category_id' => $product->category_id,
            'stock' => (float) $product->stock,
            'base_unit_id' => $base?->unit_id,
            'base_barcode' => $base?->barcode ?? '',
            // legacy keys used by older JS
            'primary_unit_id' => $base?->unit_id,
            'packs' => $product->packs->where('is_base', false)->values()->map(fn ($p) => [
                'id' => $p->id,
                'unit_id' => $p->unit_id,
                'quantity_per_unit' => $p->quantity_per_unit,
                'price' => $p->price,
                'wholesale_price' => $p->wholesale_price,
                'cost_price' => $p->cost_price,
                'barcode' => $p->barcode ?? '',
                'is_sellable' => (bool) $p->is_sellable,
            ])->all(),
            'attributes' => $product->attributes->map(fn ($a) => [
                'name' => $a->name,
                'values' => is_array($a->values) ? $a->values : (json_decode($a->values, true) ?? []),
            ])->values()->all(),
            'variations' => $product->variations->map(fn ($v) => [
                'id' => $v->id,
                'name' => $v->label,
                'sku' => $v->sku ?? '',
                'barcode' => $v->barcode ?? '',
                'price' => $v->price,
                'cost_price' => $v->cost_price,
                'sale_price' => $v->sale_price,
                'wholesale_price' => $v->wholesale_price,
                'stock' => (float) $v->stock,
                'is_active' => $v->is_active !== false,
                'attributes' => $v->getAttribute('attributes') ?? [],
                'image' => $v->image ? asset('storage/' . $v->image) : null,
            ])->values()->all(),
            'thumbnail' => $product->thumbnail ? asset('storage/' . $product->thumbnail) : null,
            'gallery' => $product->images->map(fn ($img) => ['id' => $img->id, 'url' => asset('storage/' . $img->image_path)])->all(),
        ];
    }

    public function store(Request $request)
    {
        try {
            $product = $this->products->save($request);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Failed to create product: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'product' => ['id' => $product->id, 'title' => $product->title, 'barcode' => $product->barcode, 'sku' => $product->sku],
            'redirect' => route('products.index'),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        // Branch server editing a head-office product: apply what the branch may change,
        // turn price changes into requests for head office.
        if (\App\Services\Catalogue\CatalogueRequests::governed($product)) {
            $request->validate([
                'price' => 'nullable|numeric|min:0', 'wholesale_price' => 'nullable|numeric|min:0', 'promo_price' => 'nullable|numeric|min:0',
                'reorder_level' => 'nullable|numeric|min:0', 'change_reason' => 'nullable|string|max:255',
            ]);
            $r = app(\App\Services\Catalogue\CatalogueRequests::class)->saveAtBranch($request, $product);
            return response()->json([
                'success' => true,
                'message' => $r['message'],
                'requests' => $r['requests'],
                'product' => ['id' => $product->id, 'title' => $product->title, 'barcode' => $product->barcode, 'sku' => $product->sku],
                'redirect' => route('products.index'),
            ]);
        }

        try {
            $product = $this->products->save($request, $product);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Failed to update product: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'product' => ['id' => $product->id, 'title' => $product->title, 'barcode' => $product->barcode, 'sku' => $product->sku],
            'redirect' => route('products.index'),
        ]);
    }

    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);

            if (\App\Services\Catalogue\CatalogueRequests::governed($product)) {
                return response()->json(['success' => false, 'message' => 'This product belongs to the head-office catalogue. To stop selling it here, untick "Active" on the product.'], 422);
            }

            $hasHistory = DB::table('order_items')->where('product_id', $product->id)->exists()
                || DB::table('stocks')->where('product_id', $product->id)->exists();

            if ($hasHistory) {
                // Deleting would wipe sales and stock history (cascade). Archive instead.
                $product->update(['is_active' => false]);
                return response()->json([
                    'success' => true,
                    'archived' => true,
                    'message' => 'This product has sales or stock history, so it was deactivated instead of deleted.',
                ]);
            }

            if ($product->thumbnail) {
                Storage::disk('public')->delete($product->thumbnail);
            }
            foreach ($product->images as $img) {
                Storage::disk('public')->delete($img->image_path);
            }
            foreach ($product->variations as $variation) {
                if ($variation->image) {
                    Storage::disk('public')->delete($variation->image);
                }
            }

            $product->images()->delete();
            $product->attributes()->delete();
            $product->variations()->delete();
            $product->packs()->delete();
            $product->delete();

            return response()->json(['success' => true, 'message' => 'Product deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete product: ' . $e->getMessage()], 500);
        }
    }

    // =================================================================
    // Barcode / SKU helpers
    // =================================================================

    public function generateBarcode(Request $request)
    {
        $count = min(50, max(1, (int) $request->get('count', 1)));
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = BarcodeGenerator::next();
        }
        return response()->json(['barcode' => $codes[0], 'barcodes' => $codes]);
    }

    public function generateSku(Request $request)
    {
        $category = $request->filled('category_id') ? Category::find($request->category_id)?->name : null;
        return response()->json(['sku' => BarcodeGenerator::suggestSku((string) $request->get('title', 'Product'), $category)]);
    }

    public function checkBarcode(Request $request)
    {
        $code = trim((string) $request->get('code'));
        if ($code === '') {
            return response()->json(['available' => true]);
        }
        $owner = BarcodeGenerator::owner($code);
        $ignoreProduct = (int) $request->get('product_id');
        if ($owner) {
            $pid = $owner['type'] === 'product' ? $owner['model']->id : $owner['model']->product_id;
            if ($ignoreProduct && $pid === $ignoreProduct) {
                return response()->json(['available' => true]);
            }
            $name = $owner['type'] === 'product' ? $owner['model']->title : ($owner['model']->product->title ?? '');
            return response()->json(['available' => false, 'message' => "Already used by {$name}", 'product_id' => $pid]);
        }
        return response()->json([
            'available' => true,
            'valid_ean13' => BarcodeGenerator::isValidEan13($code),
        ]);
    }

    /**
     * Search used by stock adjustments, price control and label printing.
     * Returns each sellable item (product / variation) with its packs.
     */
    public function lookup(Request $request)
    {
        $q = trim((string) $request->get('q'));
        $ids = array_filter(explode(',', (string) $request->get('ids')));

        $query = Product::query()->with(['packs.unit:id,name,short_name', 'variations' => fn ($v) => $v->where('is_active', true)]);

        if ($ids) {
            $query->whereIn('id', $ids);
        } elseif ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%")->orWhere('sku', 'like', "{$q}%")->orWhere('barcode', $q)->orWhere('plu_code', $q)
                  ->orWhereHas('variations', fn ($v) => $v->where('barcode', $q)->orWhere('sku', $q))
                  ->orWhereHas('packs', fn ($p) => $p->where('barcode', $q));
            });
        } else {
            return response()->json([]);
        }

        $locationId = $request->get('location_id');
        $stock = app(\App\Services\StockService::class);

        $out = [];
        foreach ($query->orderBy('title')->limit($ids ? 500 : 25)->get() as $p) {
            $packs = $p->packs->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->unit->name ?? 'Unit',
                'short' => $u->unit->short_name ?? '',
                'qty' => (float) $u->quantity_per_unit,
                'is_base' => (bool) $u->is_base,
                'price' => $u->price !== null ? (float) $u->price : null,
                'barcode' => $u->barcode,
            ])->values();

            $rows = $p->product_type === 'variable' && $p->variations->isNotEmpty()
                ? $p->variations->map(fn ($v) => [$p, $v])
                : collect([[$p, null]]);

            foreach ($rows as [$prod, $var]) {
                $out[] = [
                    'key' => $prod->id . ':' . ($var?->id ?? 0),
                    'product_id' => $prod->id,
                    'variation_id' => $var?->id,
                    'title' => $prod->title . ($var ? ' – ' . $var->label : ''),
                    'sku' => $var?->sku ?? $prod->sku,
                    'barcode' => $var?->barcode ?? $prod->barcode,
                    'price' => $var ? $var->selling_price : $prod->selling_price,
                    'regular_price' => (float) ($var?->price ?: $prod->price),
                    'cost_price' => $var ? $var->effective_cost : (float) $prod->cost_price,
                    'stock' => $locationId
                        ? $stock->balance($prod->id, $var?->id, (int) $locationId)
                        : (float) ($var ? $var->stock : $prod->stock),
                    'unit' => $prod->baseUnitName(),
                    'packs' => $packs,
                    'is_weighted' => (bool) $prod->is_weighted,
                    'thumbnail' => $prod->thumbnail ? asset('storage/' . $prod->thumbnail) : null,
                ];
            }
        }

        return response()->json($out);
    }

    /**
     * Printable barcode labels for selected products (shelf labels / sticker sheets).
     */
    public function labels(Request $request)
    {
        $ids = array_filter(array_map('intval', explode(',', (string) $request->get('ids'))));
        $products = Product::with(['packs.unit', 'variations' => fn ($q) => $q->where('is_active', true)])
            ->whereIn('id', $ids)->orderBy('title')->get();

        $labels = [];
        foreach ($products as $p) {
            $copies = max(1, (int) $request->input("copies.{$p->id}", $request->get('copies_all', 1)));
            $push = function ($name, $code, $price, $sub = null) use (&$labels, $copies) {
                if (!$code) {
                    return;
                }
                for ($i = 0; $i < $copies; $i++) {
                    $labels[] = compact('name', 'code', 'price', 'sub');
                }
            };

            if ($p->product_type === 'variable' && $p->variations->isNotEmpty()) {
                foreach ($p->variations as $v) {
                    $push($p->title, $v->barcode, $v->selling_price, $v->label);
                }
            } else {
                $push($p->title, $p->barcode, $p->selling_price, null);
            }
            if ($request->boolean('include_packs')) {
                $pricing = app(PricingService::class);
                foreach ($p->packs as $pack) {
                    if (!$pack->is_base && $pack->barcode) {
                        $push($p->title, $pack->barcode, $pricing->quote($p, null, $pack)['price'], ($pack->unit->name ?? 'Pack') . ' of ' . qty($pack->quantity_per_unit));
                    }
                }
            }
        }

        AuditLogger::log('labels_printed', count($labels) . ' barcode labels printed for ' . $products->count() . ' products', 'products');

        return view('products.labels', [
            'labels' => $labels,
            'products' => $products,
            'showPrice' => $request->has('size') ? $request->boolean('show_price') : true,
            'size' => $request->get('size', '38x25'),
            'store' => \App\Models\StoreSetting::getSettings(),
        ]);
    }

    /** Quick-create a unit from the product form. */
    public function storeUnit(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:50|unique:units,name',
            'short_name' => 'nullable|string|max:10',
        ]);
        $unit = Unit::create([
            'name' => $data['name'],
            'short_name' => $data['short_name'] ?? strtolower(substr($data['name'], 0, 3)),
            'is_active' => true,
            'is_default' => false,
        ]);
        return response()->json(['success' => true, 'unit' => $unit->only(['id', 'name', 'short_name'])]);
    }

    /**
     * Bulk actions from the product list (selected rows).
     */
    public function bulkAction(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'action' => 'required|in:activate,deactivate,feature,unfeature,category,brand,reorder_level,generate_barcodes',
            'value' => 'nullable',
        ]);

        $products = Product::whereIn('id', $data['ids'])->get();
        $n = 0;
        $skipped = 0;
        $branchMay = ['activate', 'deactivate', 'reorder_level', 'feature', 'unfeature'];
        foreach ($products as $p) {
            if (!in_array($data['action'], $branchMay, true) && \App\Services\Catalogue\CatalogueRequests::governed($p)) {
                $skipped++;
                continue;
            }
            if (in_array($data['action'], ['activate', 'deactivate', 'reorder_level'], true) && \App\Services\Catalogue\CatalogueRequests::governed($p)) {
                \Illuminate\Support\Facades\DB::table('product_local_overrides')->insertOrIgnore([
                    'product_id' => $p->id, 'field' => $data['action'] === 'reorder_level' ? 'reorder_level' : 'is_active',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            switch ($data['action']) {
                case 'activate': $p->update(['is_active' => true]); break;
                case 'deactivate': $p->update(['is_active' => false]); break;
                case 'feature': $p->update(['is_featured' => true]); break;
                case 'unfeature': $p->update(['is_featured' => false]); break;
                case 'category': $p->update(['category_id' => $data['value'] ?: null]); break;
                case 'brand': $p->update(['brand_id' => $data['value'] ?: null]); break;
                case 'reorder_level': $p->update(['reorder_level' => (float) $data['value']]); break;
                case 'generate_barcodes':
                    if (!$p->barcode || !BarcodeGenerator::isValidEan13($p->barcode)) {
                        $p->update(['barcode' => BarcodeGenerator::next()]);
                    }
                    break;
            }
            $n++;
        }

        return response()->json(['success' => true, 'message' => "{$n} product(s) updated."
            . ($skipped ? " {$skipped} head-office product(s) skipped – head office manages that." : '')]);
    }

    /** Legacy bulk price update from the old product page – now recorded in price history. */
    public function bulkUpdate(Request $request)
    {
        $ids = array_filter(explode(',', (string) $request->product_ids));
        $fields = array_filter($request->only(['price', 'cost_price', 'sale_price']), fn ($v) => $v !== null && $v !== '');
        $pricing = app(PricingService::class);

        foreach (Product::whereIn('id', $ids)->get() as $product) {
            foreach ($fields as $f => $v) {
                $pricing->setPrice($product, $f, (float) $v, 'bulk', 'Bulk update from product list');
            }
            $extra = array_filter($request->only(['is_featured', 'category_id']), fn ($v) => $v !== null && $v !== '');
            if ($extra) {
                $product->update($extra);
            }
        }

        return response()->json(['message' => 'Bulk update completed']);
    }

    public function deleteImage($id, $imageId)
    {
        try {
            $product = Product::findOrFail($id);
            $image = $product->images()->findOrFail($imageId);

            Storage::disk('public')->delete($image->image_path);
            $image->delete();

            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete image'
            ], 500);
        }
    }

    public function show($id)
    {
        $product = Product::with([
            'brand',
            'category',
            'images',
            'units',
            'reviews.user',
            'reviews' => fn($q) => $q->with(['user' => fn($q) => $q->select('id', 'first_name', 'last_name', 'profile_image')])->latest()
        ])
        ->withCount(['variations', 'reviews', 'orderItems as order_items_count'])
        ->findOrFail($id);

        $totalSold = $product->orderItems()->sum('quantity');
        $averageRating = $product->reviews->avg('rating') ?? 0;
        $ratingBreakdown = [
            '5' => $product->reviews->where('rating', 5)->count(),
            '4' => $product->reviews->where('rating', 4)->count(),
            '3' => $product->reviews->where('rating', 3)->count(),
            '2' => $product->reviews->where('rating', 2)->count(),
            '1' => $product->reviews->where('rating', 1)->count(),
        ];

        $price = $product->sale_price ?? $product->price;
        $revenue = $totalSold * $price;

        $pagetitle = $product->title . ' - Product Details';

        return view('products.show', compact(
            'product', 'pagetitle', 'averageRating', 'ratingBreakdown', 'revenue', 'totalSold'
        ));
    }

    public function storeReview(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'rating'   => 'required|numeric|min:1|max:5',
            'comment'  => 'required|string|max:1000',
            'user_name'=> 'nullable|string|max:255',
        ]);

        $review = ProductReview::create([
            'product_id' => $product->id,
            'user_id'    => auth()->id() ?? null,
            'rating'     => $request->rating,
            'comment'    => $request->comment,
            'user_name'  => $request->user_name ?? (auth()->user()?->name ?? 'Anonymous'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Review added successfully',
            'review'  => $review
        ]);
    }

    public function inventoryLog($id)
    {
        try {
            $product = Product::findOrFail($id);
            $logs = $product->inventoryLogs()
                ->with('user:id,first_name,last_name,email')
                ->latest()
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'type' => $log->type,
                        'quantity' => $log->quantity,
                        'previous_stock' => $log->previous_stock,
                        'new_stock' => $log->new_stock,
                        'reference' => $log->reference,
                        'notes' => $log->notes,
                        'user_name' => $log->user ? $log->user->name : 'System',
                        'created_at' => $log->created_at->toDateTimeString(),
                        'formatted_date' => $log->created_at->format('M d, Y h:i A'),
                        'formatted_quantity' => $log->formatted_quantity,
                        'type_name' => $log->type_name,
                        'type_class' => $log->type_class
                    ];
                });

            return response()->json($logs);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load inventory data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function template()
    {
        $headers = [
            'title','sku','barcode','price','cost_price','sale_price','description','brand_id','category_id','is_featured','primary_unit_id'
        ];

        $callback = function() use ($headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="product_import_template.csv"',
        ]);
    }

    public function realtimeStock()
    {
        $products = Product::select('id', 'stock')->get();
        return response()->json($products);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|mimes:csv,txt']);
        Excel::import(new ProductsImport, $request->file('file'));
        return response()->json(['message' => 'Import completed']);
    }

    public function export()
    {
        return Excel::download(new ProductsExport, 'products_' . now()->format('Y-m-d_His') . '.csv');
    }

    public function getUnits()
    {
        $units = Unit::orderBy('name')->get();
        return response()->json($units);
    }
}

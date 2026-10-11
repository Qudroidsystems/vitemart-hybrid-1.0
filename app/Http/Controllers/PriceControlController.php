<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\PriceChangeBatch;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariation;
use App\Services\AuditLogger;
use App\Services\PricingService;
use Illuminate\Http\Request;

class PriceControlController extends Controller
{
    public function __construct(protected PricingService $pricing)
    {
        $this->middleware('permission:Manage price control|View price history', ['only' => ['index', 'history', 'batch']]);
        $this->middleware('permission:Manage price control', ['only' => ['preview', 'apply', 'quickUpdate', 'promo']]);
        $this->middleware('permission:Revert price change|Manage price control', ['only' => ['revert']]);
    }

    public function index(Request $request)
    {
        $pagetitle = 'Price Control';
        $categories = Category::orderBy('name')->get(['id', 'name']);
        $brands = Brand::orderBy('name')->get(['id', 'name']);
        $batches = PriceChangeBatch::with('user:id,first_name,last_name', 'revertedBy:id,first_name,last_name')
            ->latest()->limit(15)->get();
        $recent = PriceHistory::with(['product:id,title,sku', 'variation', 'pack.unit', 'user:id,first_name,last_name'])
            ->latest()->limit(25)->get();

        $stats = [
            'changes_today' => PriceHistory::whereDate('created_at', today())->count(),
            'changes_30d' => PriceHistory::where('created_at', '>=', now()->subDays(30))->count(),
            'promos_active' => Product::whereNotNull('promo_price')->where('promo_price', '>', 0)
                ->where(fn ($q) => $q->whereNull('promo_starts_at')->orWhere('promo_starts_at', '<=', now()))
                ->where(fn ($q) => $q->whereNull('promo_ends_at')->orWhere('promo_ends_at', '>=', now()))->count(),
            'below_cost' => Product::whereNotNull('cost_price')->where('cost_price', '>', 0)
                ->whereRaw('COALESCE(NULLIF(sale_price,0), price) < cost_price')->count(),
        ];

        $preselected = $request->filled('ids') ? explode(',', $request->ids) : [];

        return view('pricing.index', compact('pagetitle', 'categories', 'brands', 'batches', 'recent', 'stats', 'preselected'));
    }

    protected function input(Request $request): array
    {
        $data = $request->validate([
            'field' => 'required|in:price,sale_price,cost_price,wholesale_price,promo_price',
            'method' => 'required|in:percent,amount,set,markup',
            'direction' => 'nullable|in:increase,decrease',
            'value' => 'required|numeric|min:0',
            'rounding' => 'nullable|numeric|min:0',
            'reason' => 'nullable|string|max:255',
            'product_ids' => 'nullable|array',
            'category_id' => 'nullable|integer',
            'brand_id' => 'nullable|integer',
            'search' => 'nullable|string|max:100',
            'include_variations' => 'nullable|boolean',
            'include_packs' => 'nullable|boolean',
        ]);

        $criteria = array_filter([
            'product_ids' => $data['product_ids'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'brand_id' => $data['brand_id'] ?? null,
            'search' => $data['search'] ?? null,
            'include_variations' => (bool) ($data['include_variations'] ?? false),
            'include_packs' => (bool) ($data['include_packs'] ?? false),
        ], fn ($v) => $v !== null && $v !== []);

        $rule = [
            'field' => $data['field'],
            'method' => $data['method'],
            'direction' => $data['direction'] ?? 'increase',
            'value' => (float) $data['value'],
            'rounding' => (float) ($data['rounding'] ?? 0),
            'reason' => $data['reason'] ?? null,
        ];

        return [$criteria, $rule];
    }

    public function preview(Request $request)
    {
        [$criteria, $rule] = $this->input($request);
        if (empty($criteria['product_ids']) && empty($criteria['category_id']) && empty($criteria['brand_id']) && empty($criteria['search'])) {
            return response()->json(['success' => false, 'message' => 'Choose products, a category, a brand or a search term first.'], 422);
        }
        $rows = $this->pricing->previewBulk($criteria, $rule);

        return response()->json([
            'success' => true,
            'description' => $this->pricing->describeRule($rule),
            'count' => $rows->count(),
            'below_cost' => $rows->where('below_cost', true)->count(),
            'rows' => $rows->take(1000)->values(),
        ]);
    }

    public function apply(Request $request)
    {
        [$criteria, $rule] = $this->input($request);
        $keys = $request->has('keys') ? (array) $request->input('keys') : null;

        $rows = $this->pricing->previewBulk($criteria, $rule);
        if ($keys !== null) {
            $rows = $rows->whereIn('key', $keys);
        }
        if ($rows->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Nothing to change.'], 422);
        }
        if ($rule['field'] !== 'cost_price' && $rows->where('below_cost', true)->isNotEmpty() && !$request->user()->can('Sell below cost') && !$request->boolean('confirm_below_cost')) {
            return response()->json([
                'success' => false,
                'needs_confirmation' => true,
                'message' => $rows->where('below_cost', true)->count() . ' item(s) would be priced below cost.',
            ], 422);
        }

        $batch = $this->pricing->applyBulk($criteria, $rule, $keys);

        return response()->json([
            'success' => true,
            'message' => "Updated {$batch->items_count} price(s). You can undo this from the history list.",
            'batch_id' => $batch->id,
        ]);
    }

    /** Inline edit of one price (from the product list / price table). */
    public function quickUpdate(Request $request)
    {
        $data = $request->validate([
            'kind' => 'required|in:product,variation,pack',
            'id' => 'required|integer',
            'field' => 'required|in:price,sale_price,cost_price,wholesale_price,promo_price',
            'value' => 'nullable|numeric|min:0',
            'reason' => 'nullable|string|max:255',
        ]);

        $model = match ($data['kind']) {
            'variation' => ProductVariation::findOrFail($data['id']),
            'pack' => ProductUnit::findOrFail($data['id']),
            default => Product::findOrFail($data['id']),
        };

        $value = $data['value'] === null || $data['value'] === '' ? null : round((float) $data['value'], 2);
        if ($data['field'] === 'price' && $value === null) {
            return response()->json(['success' => false, 'message' => 'Selling price cannot be empty.'], 422);
        }

        $product = $model instanceof Product ? $model : $model->product;
        if (\App\Services\Catalogue\CatalogueRequests::governed($product)) {
            if (!in_array($data['field'], ['price', 'wholesale_price', 'promo_price'], true) || $value === null) {
                return response()->json(['success' => false, 'message' => 'Head office manages this price.'], 422);
            }
            app(\App\Services\Catalogue\CatalogueRequests::class)->request(
                $product, $model instanceof ProductVariation ? $model : null, $model instanceof ProductUnit ? $model : null,
                $data['field'], $value, $data['reason'] ?? 'Quick edit'
            );
            app(\App\Services\Catalogue\CatalogueRequests::class)->pushQuietly();
            return response()->json(['success' => true, 'changed' => false, 'requested' => true,
                'message' => 'Sent to head office for approval. The price changes here once it is approved.']);
        }

        $history = $this->pricing->setPrice($model, $data['field'], $value, 'manual', $data['reason'] ?? 'Quick edit');

        return response()->json(['success' => true, 'changed' => (bool) $history, 'message' => $history ? 'Price updated.' : 'No change.']);
    }

    /** Put selected products on promotion (or end the promotion). */
    public function promo(Request $request)
    {
        $data = $request->validate([
            'product_ids' => 'required|array|min:1',
            'action' => 'required|in:start,end',
            'percent' => 'nullable|required_if:action,start|numeric|min:0.1|max:95',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $n = 0;
        $skipped = 0;
        foreach (Product::whereIn('id', $data['product_ids'])->get() as $p) {
            if (\App\Services\Catalogue\CatalogueRequests::governed($p)) {
                $skipped++; // head-office promotions come from head office
                continue;
            }
            if ($data['action'] === 'end') {
                $this->pricing->setPrice($p, 'promo_price', null, 'promo', 'Promotion ended');
                $p->update(['promo_starts_at' => null, 'promo_ends_at' => null]);
            } else {
                $promo = round((float) $p->price * (1 - $data['percent'] / 100), 2);
                $this->pricing->setPrice($p, 'promo_price', $promo, 'promo', "Promotion {$data['percent']}% off");
                $p->update(['promo_starts_at' => $data['starts_at'] ?? now(), 'promo_ends_at' => $data['ends_at'] ?? null]);
            }
            $n++;
        }

        AuditLogger::log('price_changed', ($data['action'] === 'end' ? 'Ended promotion on ' : "Started {$data['percent']}% promotion on ") . "$n product(s)", 'pricing');

        return response()->json(['success' => true, 'message' => "$n product(s) updated."
            . ($skipped ? " {$skipped} head-office product(s) skipped – promotions on those come from head office." : '')]);
    }

    public function history(Request $request)
    {
        $q = PriceHistory::with(['product:id,title,sku', 'variation', 'pack.unit', 'user:id,first_name,last_name'])->latest();

        if ($request->filled('product_id')) {
            $q->where('product_id', $request->product_id);
        }
        if ($request->filled('field')) {
            $q->where('field', $request->field);
        }
        if ($request->filled('source')) {
            $q->where('source', $request->source);
        }
        if ($request->filled('from')) {
            $q->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $q->whereDate('created_at', '<=', $request->to);
        }

        if ($request->wantsJson()) {
            return response()->json($q->limit(200)->get()->map(fn ($h) => [
                'id' => $h->id,
                'date' => $h->created_at->format('d M Y, h:i A'),
                'item' => $this->historyLabel($h),
                'field' => $h->field,
                'old' => $h->old_value,
                'new' => $h->new_value,
                'change' => $h->change_percent,
                'source' => $h->source,
                'reason' => $h->reason,
                'user' => $h->user ? trim($h->user->first_name . ' ' . $h->user->last_name) : 'System',
            ]));
        }

        $pagetitle = 'Price History';
        $histories = $q->paginate(50)->withQueryString();
        $product = $request->filled('product_id') ? Product::find($request->product_id) : null;

        return view('pricing.history', compact('pagetitle', 'histories', 'product'));
    }

    public function batch(PriceChangeBatch $batch)
    {
        $batch->load(['histories.product:id,title,sku', 'histories.variation', 'histories.pack.unit']);
        return response()->json([
            'description' => $batch->description,
            'reverted' => (bool) $batch->reverted_at,
            'rows' => $batch->histories->map(fn ($h) => [
                'item' => $this->historyLabel($h),
                'old' => $h->old_value,
                'new' => $h->new_value,
            ]),
        ]);
    }

    public function revert(PriceChangeBatch $batch)
    {
        try {
            $n = $this->pricing->revertBatch($batch);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['success' => true, 'message' => "Reverted {$n} price(s) to their previous values."]);
    }

    protected function historyLabel(PriceHistory $h): string
    {
        $label = $h->product->title ?? 'Deleted product';
        if ($h->variation) {
            $label .= ' – ' . $h->variation->label;
        }
        if ($h->pack) {
            $label .= ' – ' . ($h->pack->unit->name ?? 'Pack') . ' ×' . qty($h->pack->quantity_per_unit);
        }
        return $label;
    }
}

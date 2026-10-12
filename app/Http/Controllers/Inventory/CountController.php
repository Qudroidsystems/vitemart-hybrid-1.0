<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\StockCount;
use App\Models\StockLocation;
use App\Services\Inventory\CountService;
use App\Services\MultiBranch\Install;
use App\Services\StockService;
use Illuminate\Http\Request;

class CountController extends Controller
{
    public function __construct(protected CountService $service)
    {
        $this->middleware('permission:Count stock|Create stock adjustment|Approve stock adjustment');
    }

    public function index()
    {
        $pagetitle = 'Stock counts';
        $counts = StockCount::with('location:id,name')->withCount(['lines', 'lines as counted_count' => fn ($q) => $q->whereNotNull('counted_qty')])
            ->latest('id')->paginate(30);
        $locations = $this->locations();
        $categories = Category::orderBy('name')->get(['id', 'name']);
        return view('inventory-ops.counts.index', compact('pagetitle', 'counts', 'locations', 'categories'));
    }

    protected function locations()
    {
        return StockLocation::where('is_active', true)
            ->when(Install::branchId(), fn ($q) => $q->where('branch_id', Install::branchId()))
            ->where(fn ($q) => $q->whereIn('type', ['store', 'warehouse', 'quarantine'])->orWhereNull('type'))
            ->orderByDesc('is_default')->orderBy('name')->get(['id', 'name']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:150',
            'location_id' => 'required|integer|exists:stock_locations,id',
            'scope' => 'required|in:all,category,in_stock,selected',
            'category_id' => 'nullable|required_if:scope,category|integer',
            'product_ids' => 'nullable|array',
            'blind' => 'nullable|boolean',
            'recount_threshold' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:500',
        ]);
        $count = $this->service->create($data + ['blind' => $request->boolean('blind', true)]);
        return response()->json(['success' => true, 'redirect' => route('counts.show', $count)]);
    }

    public function show(StockCount $count, StockService $stock)
    {
        $pagetitle = 'Count ' . $count->number;
        $count->load('location', 'adjustment');
        $lines = $count->lines()->with('product:id,title,sku,barcode,category_id', 'product.category:id,name', 'variation:id,name,attributes,barcode')->get()
            ->sortBy(fn ($l) => ($l->product->category->name ?? 'zzz') . '|' . ($l->product->title ?? ''))->values();
        // what the counters may see
        $showSystem = !$count->blind || $count->status !== 'open';
        $system = [];
        if ($showSystem && $count->status === 'open') {
            foreach ($lines as $l) {
                $system[$l->id] = $stock->balance($l->product_id, $l->product_variation_id, $count->stock_location_id);
            }
        }
        return view('inventory-ops.counts.show', compact('pagetitle', 'count', 'lines', 'showSystem', 'system'));
    }

    public function record(Request $request, StockCount $count)
    {
        $data = $request->validate(['entries' => 'required|array', 'entries.*.line_id' => 'required|integer', 'entries.*.counted_qty' => 'nullable|numeric|min:0']);
        $n = $this->service->record($count, $data['entries']);
        return response()->json(['success' => true, 'saved' => $n]);
    }

    public function submit(Request $request, StockCount $count)
    {
        $r = $this->service->submit($count, $request->boolean('uncounted_as_zero'), $request->boolean('skip_recount') && $request->user()->can('Approve stock adjustment'));
        if ($r['status'] === 'recount') {
            return response()->json(['success' => true, 'recount' => $r['recount'],
                'message' => "{$r['recount']} item(s) are very different from what the system expects. Please count them again, then submit."]);
        }
        $adj = $r['count']->adjustment;
        return response()->json(['success' => true, 'message' => $adj && $adj->status === 'approved'
            ? 'Submitted and stock updated.' : 'Submitted. A supervisor approves the differences under Stock adjustments.', 'redirect' => route('counts.show', $count)]);
    }

    public function cancel(StockCount $count)
    {
        $this->service->cancel($count);
        return response()->json(['success' => true]);
    }
}

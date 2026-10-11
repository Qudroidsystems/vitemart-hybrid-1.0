<?php

namespace App\Http\Controllers;

use App\Models\StockAdjustment;
use App\Models\StockLocation;
use App\Services\StockAdjustmentService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StockAdjustmentController extends Controller
{
    public function __construct(protected StockAdjustmentService $service)
    {
        $this->middleware('permission:View stock adjustment|Create stock adjustment|Approve stock adjustment|Adjust stock|Manage stock adjustments', ['only' => ['index', 'show']]);
        $this->middleware('permission:Create stock adjustment|Adjust stock|Manage stock adjustments|Receive stock', ['only' => ['create', 'store', 'quick']]);
        $this->middleware('permission:Approve stock adjustment', ['only' => ['approve', 'reject', 'bulkApprove']]);
    }

    public function index(Request $request)
    {
        $pagetitle = 'Stock Adjustments';

        $q = StockAdjustment::with(['location:id,name', 'creator:id,first_name,last_name', 'approver:id,first_name,last_name'])
            ->latest();

        $status = $request->get('status', 'all');
        if ($status !== 'all') {
            $q->where('status', $status);
        }
        if ($request->filled('type')) {
            $q->where('type', $request->type);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn ($w) => $w->where('reference', 'like', "%$s%")->orWhere('reason', 'like', "%$s%")
                ->orWhere('supplier', 'like', "%$s%")->orWhereHas('items.product', fn ($p) => $p->where('title', 'like', "%$s%")));
        }
        if ($request->filled('from')) {
            $q->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $q->whereDate('created_at', '<=', $request->to);
        }

        $adjustments = $q->paginate(25)->withQueryString();

        $counts = StockAdjustment::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');
        $pendingValue = StockAdjustment::where('status', 'pending')->sum('total_value_change');

        return view('adjustments.index', compact('pagetitle', 'adjustments', 'counts', 'status', 'pendingValue'));
    }

    public function create(Request $request)
    {
        $type = $request->get('type', 'adjustment');
        $pagetitle = StockAdjustment::TYPES[$type] ?? 'Stock Adjustment';
        $locations = StockLocation::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'is_default']);
        if ($locations->isEmpty()) {
            $locations = collect([app(StockService::class)->defaultLocation()]);
        }
        $ids = $request->get('ids', '');
        $needsApproval = $this->service->needsApproval($request->user());
        $canApprove = $request->user()->can('Approve stock adjustment');
        $types = StockAdjustment::TYPES;
        $reasons = StockAdjustment::REASONS;

        return view('adjustments.create', compact('pagetitle', 'type', 'locations', 'ids', 'needsApproval', 'canApprove', 'types', 'reasons'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:' . implode(',', array_keys(StockAdjustment::TYPES)),
            'location_id' => 'required|exists:stock_locations,id',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'supplier' => 'nullable|string|max:255',
            'supplier_invoice' => 'nullable|string|max:100',
            'action' => 'nullable|in:draft,submit,approve',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variation_id' => 'nullable|exists:product_variations,id',
            'items.*.pack_id' => 'nullable|integer',
            'items.*.mode' => 'nullable|in:add,remove,set',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'items.*.reason' => 'nullable|string|max:255',
            'items.*.expiry_date' => 'nullable|date',
            'items.*.batch_number' => 'nullable|string|max:100',
        ]);

        $action = $data['action'] ?? 'submit';
        if ($action === 'approve' && !$request->user()->can('Approve stock adjustment')) {
            $action = 'submit';
        }

        try {
            $adj = $this->service->create($data, $action);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first(), 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $msg = match ($adj->status) {
            'approved' => "{$adj->reference} posted – stock updated.",
            'draft' => "{$adj->reference} saved as draft.",
            default => "{$adj->reference} submitted and waiting for approval.",
        };

        return response()->json(['success' => true, 'message' => $msg, 'status' => $adj->status,
            'redirect' => route('adjustments.show', $adj)]);
    }

    /**
     * Adjust one product straight from the product list / product page.
     */
    public function quick(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'variation_id' => 'nullable|exists:product_variations,id',
            'location_id' => 'nullable|exists:stock_locations,id',
            'pack_id' => 'nullable|integer',
            'mode' => 'required|in:add,remove,set',
            'quantity' => 'required|numeric|min:0',
            'unit_cost' => 'nullable|numeric|min:0',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $type = match ($data['mode']) {
            'set' => 'count',
            'remove' => in_array($data['reason'], ['Damaged', 'Expired']) ? 'damage' : 'adjustment',
            default => $data['reason'] === 'Goods received' ? 'receive' : 'adjustment',
        };

        try {
            $adj = $this->service->create([
                'type' => $type,
                'location_id' => $data['location_id'] ?? null,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'items' => [[
                    'product_id' => $data['product_id'],
                    'variation_id' => $data['variation_id'] ?? null,
                    'pack_id' => $data['pack_id'] ?? null,
                    'mode' => $data['mode'],
                    'quantity' => $data['quantity'],
                    'unit_cost' => $data['unit_cost'] ?? null,
                ]],
            ], 'submit');
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'status' => $adj->status,
            'message' => $adj->status === 'approved'
                ? 'Stock updated.'
                : "Adjustment {$adj->reference} sent for approval. Stock will change once it is approved.",
            'stock' => (float) \App\Models\Product::find($data['product_id'])->stock,
        ]);
    }

    public function show(StockAdjustment $adjustment)
    {
        $adjustment->load(['items.product:id,title,sku,stock,cost_price', 'items.variation', 'items.pack.unit', 'location', 'creator', 'approver', 'rejecter']);
        $pagetitle = $adjustment->reference;
        $stock = app(StockService::class);
        $currentBalances = [];
        foreach ($adjustment->items as $item) {
            $currentBalances[$item->id] = $stock->balance($item->product_id, $item->product_variation_id, $adjustment->stock_location_id);
        }
        $canApprove = auth()->user()->can('Approve stock adjustment');

        return view('adjustments.show', compact('pagetitle', 'adjustment', 'currentBalances', 'canApprove'));
    }

    public function approve(StockAdjustment $adjustment)
    {
        try {
            $this->service->approve($adjustment);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['success' => true, 'message' => "{$adjustment->reference} approved. Stock has been updated."]);
    }

    public function bulkApprove(Request $request)
    {
        $ids = (array) $request->input('ids', []);
        $ok = 0;
        $errors = [];
        foreach (StockAdjustment::whereIn('id', $ids)->where('status', 'pending')->get() as $adj) {
            try {
                $this->service->approve($adj);
                $ok++;
            } catch (\Throwable $e) {
                $errors[] = "{$adj->reference}: {$e->getMessage()}";
            }
        }
        return response()->json([
            'success' => $ok > 0 || empty($errors),
            'message' => "$ok adjustment(s) approved." . ($errors ? ' Failed: ' . implode('; ', $errors) : ''),
        ]);
    }

    public function reject(Request $request, StockAdjustment $adjustment)
    {
        $request->validate(['reason' => 'required|string|max:255']);
        try {
            $this->service->reject($adjustment, $request->reason);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['success' => true, 'message' => "{$adjustment->reference} rejected."]);
    }

    public function cancel(StockAdjustment $adjustment)
    {
        if (!in_array($adjustment->status, ['pending', 'draft'])) {
            return response()->json(['success' => false, 'message' => 'Only pending adjustments can be cancelled.'], 422);
        }
        if ($adjustment->created_by !== auth()->id() && !auth()->user()->can('Approve stock adjustment')) {
            abort(403);
        }
        $adjustment->update(['status' => 'cancelled']);
        return response()->json(['success' => true, 'message' => "{$adjustment->reference} cancelled."]);
    }
}

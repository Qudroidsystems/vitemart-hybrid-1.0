<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\Inventory\PurchasingService;
use App\Services\MultiBranch\Install;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function __construct(protected PurchasingService $service)
    {
        $this->middleware('permission:View purchasing|Create purchase order|Approve purchase order', ['only' => ['index', 'show', 'print']]);
        $this->middleware('permission:Create purchase order', ['only' => ['create', 'store', 'submit', 'send', 'cancel', 'close']]);
        $this->middleware('permission:Approve purchase order', ['only' => ['approve']]);
    }

    public function index(Request $request)
    {
        $pagetitle = 'Purchase orders';
        $q = PurchaseOrder::with('supplier:id,name', 'branch:id,code')->withCount('lines')->latest('id');
        if (Install::isBranch()) {
            $q->where('branch_id', Install::branchId());
        }
        if ($s = $request->get('status')) {
            $s === 'open' ? $q->whereIn('status', ['submitted', 'approved', 'sent', 'partial']) : $q->where('status', $s);
        }
        if ($request->filled('supplier_id')) {
            $q->where('supplier_id', $request->supplier_id);
        }
        if ($n = trim((string) $request->get('q'))) {
            $q->where('number', 'like', "%{$n}%");
        }
        $orders = $q->paginate(30)->withQueryString();
        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);
        $waiting = PurchaseOrder::where('status', 'submitted')->count();
        return view('purchasing.orders.index', compact('pagetitle', 'orders', 'suppliers', 'waiting'));
    }

    public function create()
    {
        $pagetitle = 'New purchase order';
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name', 'lead_time_days']);
        $branches = Install::isCentral() ? Branch::where('status', 'active')->orderBy('number')->get(['id', 'code', 'name']) : collect();
        return view('purchasing.orders.create', compact('pagetitle', 'suppliers', 'branches'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'expected_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
            'action' => 'nullable|in:draft,submit',
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => 'required|integer',
            'lines.*.variation_id' => 'nullable|integer',
            'lines.*.pack_id' => 'nullable|integer',
            'lines.*.qty' => 'nullable|numeric|min:0',
            'lines.*.unit_cost' => 'nullable|numeric|min:0',
        ]);
        $po = $this->service->createOrder($data);
        return response()->json(['success' => true, 'message' => "{$po->number} " . (PurchaseOrder::STATUSES[$po->status] ?? $po->status) . '.', 'redirect' => route('purchase-orders.show', $po)]);
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $po = $purchaseOrder->load('lines.product:id,title,sku,barcode', 'lines.variation', 'lines.pack.unit', 'supplier', 'branch', 'receipts');
        $pagetitle = 'Purchase order ' . $po->number;
        $here = Install::isStandalone() || $po->branch_id === Install::branchId();
        return view('purchasing.orders.show', compact('pagetitle', 'po', 'here'));
    }

    public function print(PurchaseOrder $purchaseOrder)
    {
        $po = $purchaseOrder->load('lines.product:id,title,sku,barcode', 'lines.variation', 'lines.pack.unit', 'supplier', 'branch');
        $store = \App\Models\StoreSetting::getSettings();
        return view('purchasing.orders.print', compact('po', 'store'));
    }

    public function submit(PurchaseOrder $purchaseOrder)
    {
        $po = $this->service->submitOrder($purchaseOrder);
        return response()->json(['success' => true, 'message' => $po->status === 'approved' ? 'Approved.' : 'Sent for approval.']);
    }

    public function approve(PurchaseOrder $purchaseOrder)
    {
        $this->service->approveOrder($purchaseOrder);
        return response()->json(['success' => true, 'message' => 'Approved.']);
    }

    public function send(PurchaseOrder $purchaseOrder)
    {
        $this->service->markSent($purchaseOrder);
        return response()->json(['success' => true, 'message' => 'Marked as sent to the supplier.']);
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder)
    {
        $data = $request->validate(['reason' => 'required|string|max:255']);
        $this->service->cancelOrder($purchaseOrder, $data['reason']);
        return response()->json(['success' => true, 'message' => 'Cancelled.']);
    }

    public function close(PurchaseOrder $purchaseOrder)
    {
        $this->service->closeOrder($purchaseOrder);
        return response()->json(['success' => true, 'message' => 'Closed – anything not delivered is no longer expected.']);
    }
}

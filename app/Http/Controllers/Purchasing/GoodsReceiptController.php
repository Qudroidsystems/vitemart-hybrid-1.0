<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\StockLocation;
use App\Models\Supplier;
use App\Services\Inventory\PurchasingService;
use App\Services\MultiBranch\Install;
use Illuminate\Http\Request;

class GoodsReceiptController extends Controller
{
    public function __construct(protected PurchasingService $service)
    {
        $this->middleware('permission:View purchasing|Post goods receipt', ['only' => ['index', 'show']]);
        $this->middleware('permission:Post goods receipt', ['only' => ['create', 'store']]);
    }

    public function index(Request $request)
    {
        $pagetitle = 'Goods received';
        $q = GoodsReceipt::with('supplier:id,name', 'purchaseOrder:id,number', 'receiver:id,first_name,last_name')->withCount('lines')->latest('id');
        if ($request->filled('supplier_id')) {
            $q->where('supplier_id', $request->supplier_id);
        }
        if ($n = trim((string) $request->get('q'))) {
            $q->where(fn ($w) => $w->where('number', 'like', "%{$n}%")->orWhere('supplier_invoice', 'like', "%{$n}%"));
        }
        $receipts = $q->paginate(30)->withQueryString();
        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);
        return view('purchasing.receipts.index', compact('pagetitle', 'receipts', 'suppliers'));
    }

    public function create(Request $request)
    {
        $pagetitle = 'Receive goods';
        $po = $request->filled('po') ? PurchaseOrder::with('lines.product:id,title,sku,barcode', 'lines.variation', 'lines.pack.unit', 'supplier')->findOrFail($request->po) : null;
        $openOrders = PurchaseOrder::with('supplier:id,name')->whereIn('status', ['approved', 'sent', 'partial'])
            ->when(Install::branchId() && !Install::isStandalone(), fn ($q) => $q->where('branch_id', Install::branchId()))->latest('id')->get(['id', 'number', 'supplier_id']);
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $locations = StockLocation::where('is_active', true)->when(Install::branchId(), fn ($q) => $q->where('branch_id', Install::branchId()))
            ->where(fn ($q) => $q->whereIn('type', ['store', 'warehouse'])->orWhereNull('type'))->orderByDesc('is_default')->get(['id', 'name']);
        return view('purchasing.receipts.create', compact('pagetitle', 'po', 'openOrders', 'suppliers', 'locations'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => 'required_without:purchase_order_id|nullable|integer|exists:suppliers,id',
            'purchase_order_id' => 'nullable|integer|exists:purchase_orders,id',
            'location_id' => 'nullable|integer|exists:stock_locations,id',
            'supplier_invoice' => 'nullable|string|max:80',
            'invoice_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => 'required|integer',
            'lines.*.variation_id' => 'nullable|integer',
            'lines.*.pack_id' => 'nullable|integer',
            'lines.*.qty' => 'nullable|numeric|min:0',
            'lines.*.free_qty' => 'nullable|numeric|min:0',
            'lines.*.unit_cost' => 'nullable|numeric|min:0',
            'lines.*.expiry_date' => 'nullable|date',
            'lines.*.batch_number' => 'nullable|string|max:60',
            'lines.*.po_line_id' => 'nullable|integer',
        ]);
        $grn = $this->service->receive($data);
        return response()->json(['success' => true, 'message' => "{$grn->number} posted – stock updated.", 'redirect' => route('goods-receipts.show', $grn)]);
    }

    public function show(GoodsReceipt $goodsReceipt)
    {
        $grn = $goodsReceipt->load('lines.product:id,title,sku,barcode', 'lines.variation', 'lines.pack.unit', 'supplier', 'purchaseOrder', 'location', 'receiver');
        $pagetitle = $grn->number;
        return view('purchasing.receipts.show', compact('pagetitle', 'grn'));
    }
}

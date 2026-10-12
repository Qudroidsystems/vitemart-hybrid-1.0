<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\StockLocation;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Services\Inventory\PurchasingService;
use App\Services\MultiBranch\Install;
use Illuminate\Http\Request;

class SupplierReturnController extends Controller
{
    public function __construct(protected PurchasingService $service)
    {
        $this->middleware('permission:View purchasing|Return to supplier', ['only' => ['index', 'show']]);
        $this->middleware('permission:Return to supplier', ['only' => ['create', 'store']]);
    }

    public function index()
    {
        $pagetitle = 'Returns to suppliers';
        $returns = SupplierReturn::with('supplier:id,name')->withCount('lines')->latest('id')->paginate(30);
        return view('purchasing.returns.index', compact('pagetitle', 'returns'));
    }

    public function create(Request $request)
    {
        $pagetitle = 'Return to supplier';
        $grn = $request->filled('grn') ? GoodsReceipt::with('lines.product:id,title,sku,barcode', 'lines.variation', 'lines.pack.unit')->find($request->grn) : null;
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $locations = StockLocation::where('is_active', true)->when(Install::branchId(), fn ($q) => $q->where('branch_id', Install::branchId()))
            ->orderByDesc('is_default')->get(['id', 'name', 'type']);
        return view('purchasing.returns.create', compact('pagetitle', 'grn', 'suppliers', 'locations'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'goods_receipt_id' => 'nullable|integer|exists:goods_receipts,id',
            'location_id' => 'nullable|integer|exists:stock_locations,id',
            'reason' => 'required|string|max:255',
            'credit_note' => 'nullable|string|max:80',
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => 'required|integer',
            'lines.*.variation_id' => 'nullable|integer',
            'lines.*.pack_id' => 'nullable|integer',
            'lines.*.qty' => 'nullable|numeric|min:0',
            'lines.*.unit_cost' => 'nullable|numeric|min:0',
        ]);
        $ret = $this->service->returnToSupplier($data);
        return response()->json(['success' => true, 'message' => "{$ret->number} posted – stock reduced.", 'redirect' => route('supplier-returns.show', $ret)]);
    }

    public function show(SupplierReturn $supplierReturn)
    {
        $ret = $supplierReturn->load('lines.product:id,title,sku', 'lines.variation', 'lines.pack.unit', 'supplier', 'location');
        $pagetitle = $ret->number;
        return view('purchasing.returns.show', compact('pagetitle', 'ret'));
    }
}

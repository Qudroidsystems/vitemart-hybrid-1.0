<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Services\Inventory\TransferService;
use App\Services\MultiBranch\Install;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    public function __construct(protected TransferService $service)
    {
        $this->middleware('permission:Request stock transfer|Approve stock transfer|Dispatch stock transfer|Receive stock transfer|Allocate stock', ['only' => ['index', 'show', 'print']]);
        $this->middleware('permission:Request stock transfer|Allocate stock', ['only' => ['create', 'store']]);
        $this->middleware('permission:Approve stock transfer|Allocate stock', ['only' => ['approve', 'reject']]);
        $this->middleware('permission:Dispatch stock transfer', ['only' => ['dispatchTransfer']]);
        $this->middleware('permission:Receive stock transfer', ['only' => ['receive']]);
        $this->middleware('permission:Resolve transfer discrepancy|Receive stock transfer', ['only' => ['resolve']]);
    }

    public function index(Request $request)
    {
        $pagetitle = 'Stock transfers';
        $here = Install::branchId();
        $tab = $request->get('tab', Install::isCentral() ? 'all' : 'incoming');
        $q = StockTransfer::with('fromBranch:id,code,name', 'toBranch:id,code,name')->withCount('lines')->latest('id');
        if ($tab === 'incoming' && $here) {
            $q->where('to_branch_id', $here);
        } elseif ($tab === 'outgoing' && $here) {
            $q->where('from_branch_id', $here);
        }
        if ($s = $request->get('status')) {
            $s === 'open' ? $q->whereNotIn('status', ['closed', 'cancelled', 'rejected']) : $q->where('status', $s);
        }
        if ($n = trim((string) $request->get('q'))) {
            $q->where('number', 'like', "%{$n}%");
        }
        $transfers = $q->paginate(30)->withQueryString();
        $counts = [
            'incoming' => $here ? StockTransfer::where('to_branch_id', $here)->whereIn('status', ['dispatched'])->count() : 0,
            'outgoing' => $here ? StockTransfer::where('from_branch_id', $here)->whereIn('status', ['requested', 'approved'])->count() : 0,
            'discrepancy' => StockTransfer::where('status', 'received')->where('has_discrepancy', true)->count(),
        ];
        return view('inventory-ops.transfers.index', compact('pagetitle', 'transfers', 'tab', 'counts'));
    }

    public function create(Request $request)
    {
        $pagetitle = 'New transfer';
        $branches = Branch::where('status', 'active')->orderBy('number')->get(['id', 'code', 'name', 'type']);
        $here = Install::branchId();
        $direction = $request->get('direction', 'request'); // request = bring stock here, send = send from here
        return view('inventory-ops.transfers.create', compact('pagetitle', 'branches', 'here', 'direction'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'from_branch_id' => 'required|integer|exists:branches,id',
            'to_branch_id' => 'required|integer|exists:branches,id|different:from_branch_id',
            'notes' => 'nullable|string|max:500',
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => 'required|integer',
            'lines.*.variation_id' => 'nullable|integer',
            'lines.*.pack_id' => 'nullable|integer',
            'lines.*.qty' => 'nullable|numeric|min:0',
        ]);
        $user = $request->user();
        // sending from here (or head office allocating) with approval rights → approved straight away
        $approveNow = ((int) $data['from_branch_id'] === Install::branchId() || Install::isCentral())
            && ($user->can('Approve stock transfer') || $user->can('Allocate stock'));
        $t = $this->service->create((int) $data['from_branch_id'], (int) $data['to_branch_id'], $data['lines'], $data['notes'] ?? null, $approveNow);
        return response()->json(['success' => true, 'message' => "Transfer {$t->number} " . ($t->status === 'approved' ? 'created and approved.' : 'requested.'),
            'redirect' => route('transfers.show', $t)]);
    }

    public function show(StockTransfer $transfer)
    {
        $pagetitle = 'Transfer ' . $transfer->number;
        $transfer->load('lines.product:id,title,sku,barcode', 'lines.variation', 'lines.pack.unit', 'fromBranch', 'toBranch');
        $here = Install::branchId();
        $standalone = Install::isStandalone();
        $can = [
            'approve' => $transfer->status === 'requested' && (Install::isCentral() || $standalone || $here === $transfer->from_branch_id),
            'dispatch' => $transfer->status === 'approved' && ($standalone || $here === $transfer->from_branch_id),
            'receive' => $transfer->status === 'dispatched' && ($standalone || $here === $transfer->to_branch_id),
            'cancel' => in_array($transfer->status, ['requested', 'approved'], true),
            'resolve' => $transfer->status === 'received' && (Install::isCentral() || $standalone || $here === $transfer->to_branch_id),
            'found' => $standalone || $here === $transfer->to_branch_id,
        ];
        return view('inventory-ops.transfers.show', compact('pagetitle', 'transfer', 'can'));
    }

    public function print(StockTransfer $transfer)
    {
        $transfer->load('lines.product:id,title,sku,barcode', 'lines.variation', 'lines.pack.unit', 'fromBranch', 'toBranch');
        return view('inventory-ops.transfers.print', compact('transfer'));
    }

    protected function qtys(Request $request): array
    {
        $data = $request->validate(['qty' => 'nullable|array', 'qty.*' => 'nullable|numeric|min:0']);
        return array_filter($data['qty'] ?? [], fn ($v) => $v !== null && $v !== '');
    }

    public function approve(Request $request, StockTransfer $transfer)
    {
        $t = $this->service->approve($transfer->load('lines'), $this->qtys($request));
        return response()->json(['success' => true, 'message' => "Approved. {$t->fromBranch->code} can now pick and dispatch it."]);
    }

    public function reject(Request $request, StockTransfer $transfer)
    {
        $data = $request->validate(['reason' => 'required|string|max:255']);
        $this->service->reject($transfer, $data['reason']);
        return response()->json(['success' => true, 'message' => 'Rejected.']);
    }

    public function cancel(Request $request, StockTransfer $transfer)
    {
        $data = $request->validate(['reason' => 'required|string|max:255']);
        abort_unless($request->user()->canany(['Request stock transfer', 'Approve stock transfer', 'Allocate stock']), 403);
        $this->service->cancel($transfer->load('lines'), $data['reason']);
        return response()->json(['success' => true, 'message' => 'Cancelled.']);
    }

    public function dispatchTransfer(Request $request, StockTransfer $transfer)
    {
        $carrier = $request->validate(['carrier' => 'nullable|string|max:120'])['carrier'] ?? null;
        $t = $this->service->dispatch($transfer->load('lines'), $this->qtys($request), $carrier);
        return response()->json(['success' => true, 'message' => "Dispatched. The stock is now “in transit” until {$t->toBranch->code} receives it."]);
    }

    public function receive(Request $request, StockTransfer $transfer)
    {
        $data = $request->validate(['note' => 'nullable|string|max:255', 'damaged' => 'nullable|array', 'damaged.*' => 'nullable|numeric|min:0']);
        $damaged = array_filter($data['damaged'] ?? [], fn ($v) => $v !== null && $v !== '' && (float) $v > 0);
        $t = $this->service->receive($transfer->load('lines'), $this->qtys($request), $data['note'] ?? null, $damaged);
        return response()->json(['success' => true, 'message' => $t->has_discrepancy
            ? 'Received – some quantities differ from what was sent. Resolve the differences below.' : 'Received in full. Transfer closed.']);
    }

    public function resolve(Request $request, StockTransfer $transfer, StockTransferLine $line)
    {
        $data = $request->validate(['resolution' => 'required|in:written_off,charged,found', 'note' => 'nullable|string|max:200', 'found_qty' => 'nullable|numeric|min:0']);
        $this->service->resolve($transfer->load('lines'), $line, $data['resolution'], $data['note'] ?? null, isset($data['found_qty']) ? (float) $data['found_qty'] : null);
        return response()->json(['success' => true, 'message' => 'Saved.']);
    }
}

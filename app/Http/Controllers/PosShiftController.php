<?php

namespace App\Http\Controllers;

use App\Models\PosCashMovement;
use App\Models\PosShift;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

/**
 * Cash drawer sessions: open with a float → sell → pay-ins / pay-outs → count & close (Z-report).
 */
class PosShiftController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:View pos|Manage pos shifts');
        $this->middleware('permission:View all pos shifts|Manage pos shifts', ['only' => ['index']]);
    }

    public function index(Request $request)
    {
        $pagetitle = 'Cash Drawer Shifts';
        $q = PosShift::with('user:id,first_name,last_name', 'closer:id,first_name,last_name')->latest('opened_at');
        if ($request->filled('user_id')) {
            $q->where('user_id', $request->user_id);
        }
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        if ($request->filled('from')) {
            $q->whereDate('opened_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $q->whereDate('opened_at', '<=', $request->to);
        }
        $shifts = $q->paginate(25)->withQueryString();
        $cashiers = User::whereIn('id', PosShift::select('user_id')->distinct())->orderBy('first_name')->get(['id', 'first_name', 'last_name']);

        return view('pos.shifts', compact('pagetitle', 'shifts', 'cashiers'));
    }

    public function current(Request $request)
    {
        $shift = PosShift::currentFor($request->user()->id);
        if (!$shift) {
            return response()->json(['open' => false]);
        }
        return response()->json(['open' => true, 'shift' => $this->payload($shift)]);
    }

    public function open(Request $request)
    {
        $data = $request->validate([
            'opening_float' => 'required|numeric|min:0',
            'register' => 'nullable|string|max:50',
            'cash_register_id' => 'nullable|integer',
        ]);
        if (PosShift::currentFor($request->user()->id)) {
            return response()->json(['success' => false, 'message' => 'You already have an open shift.'], 422);
        }

        // One drawer = one shift at a time.
        $registers = \App\Models\CashRegister::here()->where('status', 'active')->get();
        $register = !empty($data['cash_register_id']) ? $registers->firstWhere('id', (int) $data['cash_register_id'])
            : ($registers->count() === 1 ? $registers->first() : null);
        if ($registers->count() > 1 && !$register) {
            return response()->json(['success' => false, 'message' => 'Choose which till you are opening.', 'choose_register' => true], 422);
        }
        if ($register && ($busy = $register->openShift()) && $busy->user_id !== $request->user()->id) {
            $who = trim(($busy->user->first_name ?? '') . ' ' . ($busy->user->last_name ?? ''));
            return response()->json(['success' => false, 'message' => "Till {$register->code} already has an open shift ({$who}). Close it first."], 422);
        }

        $shift = PosShift::create([
            'user_id' => $request->user()->id,
            'register' => $register ? $register->code : ($data['register'] ?? null),
            'opened_at' => now(),
            'opening_float' => $data['opening_float'],
            'status' => 'open',
        ]);
        if ($register) {
            $shift->forceFill(['cash_register_id' => $register->id])->save();
            $register->forceFill(['last_used_at' => now(), 'last_user_id' => $request->user()->id])->saveQuietly();
        }
        AuditLogger::log('shift_opened', 'Opened cash drawer with float ' . money($shift->opening_float), 'pos', ['PosShift', $shift->id, 'Shift #' . $shift->id]);

        return response()->json(['success' => true, 'message' => 'Shift opened.', 'shift' => $this->payload($shift)]);
    }

    public function cashMovement(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:in,out',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
        ]);
        $shift = PosShift::currentFor($request->user()->id);
        if (!$shift) {
            return response()->json(['success' => false, 'message' => 'Open a shift first.'], 422);
        }
        PosCashMovement::create($data + ['pos_shift_id' => $shift->id, 'user_id' => $request->user()->id]);
        AuditLogger::log('cash_' . $data['type'], ($data['type'] === 'in' ? 'Cash paid in: ' : 'Cash paid out: ') . money($data['amount']) . " – {$data['reason']}", 'pos', ['PosShift', $shift->id, 'Shift #' . $shift->id]);

        return response()->json(['success' => true, 'message' => 'Recorded.', 'shift' => $this->payload($shift->fresh())]);
    }

    public function close(Request $request, PosShift $shift)
    {
        $data = $request->validate([
            'counted_cash' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);
        if ($shift->status !== 'open') {
            return response()->json(['success' => false, 'message' => 'This shift is already closed.'], 422);
        }
        if ($shift->user_id !== $request->user()->id && !$request->user()->can('Manage pos shifts')) {
            abort(403);
        }

        $summary = $shift->computeSummary();
        $variance = round((float) $data['counted_cash'] - $summary['expected_cash'], 2);

        $shift->update([
            'closed_at' => now(),
            'expected_cash' => $summary['expected_cash'],
            'counted_cash' => $data['counted_cash'],
            'variance' => $variance,
            'cash_in' => $summary['cash_in'],
            'cash_out' => $summary['cash_out'],
            'summary' => $summary,
            'status' => 'closed',
            'notes' => $data['notes'] ?? null,
            'closed_by' => $request->user()->id,
        ]);

        AuditLogger::log('shift_closed', "Closed shift #{$shift->id}: expected " . money($summary['expected_cash']) . ', counted ' . money($data['counted_cash']) . ', variance ' . money($variance), 'pos', ['PosShift', $shift->id, 'Shift #' . $shift->id], null, $summary);

        return response()->json(['success' => true, 'message' => 'Shift closed.', 'report_url' => route('pos.shifts.report', $shift), 'variance' => $variance]);
    }

    public function report(Request $request, PosShift $shift)
    {
        if ($shift->user_id !== $request->user()->id && !$request->user()->can('View all pos shifts') && !$request->user()->can('Manage pos shifts')) {
            abort(403);
        }
        $shift->load('user', 'closer', 'cashMovements');
        $summary = $shift->status === 'closed' && $shift->summary ? $shift->summary : $shift->computeSummary();
        $topItems = \App\Models\OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.pos_shift_id', $shift->id)->where('orders.status', '!=', 'cancelled')
            ->selectRaw('order_items.title, SUM(order_items.quantity) qty, SUM(order_items.total_price) total')
            ->groupBy('order_items.title')->orderByDesc('total')->limit(10)->get();
        $store = \App\Models\StoreSetting::getSettings();

        return view('pos.shift-report', compact('shift', 'summary', 'topItems', 'store'));
    }

    protected function payload(PosShift $shift): array
    {
        $s = $shift->computeSummary();
        return [
            'id' => $shift->id,
            'opened_at' => $shift->opened_at->format('d M, h:i A'),
            'opening_float' => (float) $shift->opening_float,
            'orders' => $s['orders_count'],
            'sales' => $s['gross_sales'],
            'cash_sales' => $s['cash_sales'],
            'cash_in' => $s['cash_in'],
            'cash_out' => $s['cash_out'],
            'refunds' => $s['refunds'],
            'expected_cash' => $s['expected_cash'],
            'by_method' => $s['by_method'],
        ];
    }
}

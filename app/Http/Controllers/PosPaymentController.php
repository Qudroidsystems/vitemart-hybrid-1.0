<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Transfers (and other payments) that the till accepted but nobody has seen land in the account yet.
 * A supervisor or accountant checks the bank and confirms or fails each one.
 */
class PosPaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:Confirm payments');
    }

    public function index(Request $request)
    {
        $pagetitle = 'Payments to confirm';
        $status = $request->get('status', 'pending');
        $q = OrderPayment::query()
            ->join('orders', 'orders.id', '=', 'order_payments.order_id')
            ->leftJoin('users', 'users.id', '=', 'order_payments.user_id')
            ->leftJoin('customers', 'customers.id', '=', 'orders.customer_id')
            ->leftJoin('cash_registers', 'cash_registers.id', '=', 'order_payments.cash_register_id')
            ->select('order_payments.*', 'orders.total_amount', 'orders.status as order_status',
                DB::raw("TRIM(CONCAT(COALESCE(users.first_name,''),' ',COALESCE(users.last_name,''))) as cashier"),
                DB::raw("TRIM(CONCAT(COALESCE(customers.first_name,''),' ',COALESCE(customers.last_name,''))) as customer"),
                'cash_registers.code as till');
        if ($status !== 'all') {
            $q->where('order_payments.status', $status);
        }
        if ($request->filled('method')) {
            $q->where('order_payments.method', $request->get('method'));
        }
        if ($s = trim((string) $request->get('q'))) {
            $q->where(fn ($w) => $w->where('order_payments.order_id', 'like', "%{$s}%")->orWhere('order_payments.reference', 'like', "%{$s}%"));
        }
        if (\App\Services\MultiBranch\Install::branchId() && \Illuminate\Support\Facades\Schema::hasColumn('order_payments', 'branch_id')) {
            $q->where(fn ($w) => $w->whereNull('order_payments.branch_id')->orWhere('order_payments.branch_id', \App\Services\MultiBranch\Install::branchId()));
        }
        $payments = $q->orderByDesc('order_payments.created_at')->paginate(50)->withQueryString();

        $totals = OrderPayment::where('status', 'pending')->selectRaw('COUNT(*) c, COALESCE(SUM(amount),0) t')->first();
        $oldest = OrderPayment::where('status', 'pending')->min('created_at');

        return view('pos.payments', compact('pagetitle', 'payments', 'status', 'totals', 'oldest'));
    }

    public function confirm(Request $request, OrderPayment $payment)
    {
        $data = $request->validate(['reference' => 'nullable|string|max:100', 'provider' => 'nullable|string|max:40']);
        if ($payment->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Already ' . $payment->status . '.'], 422);
        }
        DB::transaction(function () use ($payment, $data, $request) {
            $payment->update([
                'status' => 'confirmed',
                'confirmed_by' => $request->user()->id,
                'confirmed_at' => now(),
                'reference' => $data['reference'] ?? $payment->reference,
                'provider' => $data['provider'] ?? $payment->provider,
            ]);
            $this->refreshOrder($payment->order_id);
        });
        AuditLogger::log('payment_confirmed', "Confirmed {$payment->method} " . money($payment->amount) . " on {$payment->order_id}", 'pos', ['Order', $payment->order_id, $payment->order_id]);
        return response()->json(['success' => true, 'message' => 'Confirmed.']);
    }

    public function fail(Request $request, OrderPayment $payment)
    {
        $data = $request->validate(['reason' => 'required|string|max:255']);
        if ($payment->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Already ' . $payment->status . '.'], 422);
        }
        DB::transaction(function () use ($payment, $data, $request) {
            $payment->update(['status' => 'failed', 'status_note' => $data['reason'], 'confirmed_by' => $request->user()->id, 'confirmed_at' => now()]);
            $order = Order::find($payment->order_id);
            // The customer owes it now.
            if ($order?->customer_id) {
                DB::table('customers')->where('id', $order->customer_id)->increment('outstanding_balance', (float) $payment->amount);
            }
            $this->refreshOrder($payment->order_id);
        });
        AuditLogger::log('payment_failed', "Payment NOT received: {$payment->method} " . money($payment->amount) . " on {$payment->order_id} – {$data['reason']}", 'pos', ['Order', $payment->order_id, $payment->order_id]);
        return response()->json(['success' => true, 'message' => 'Marked as not received. The sale now shows as unpaid' . ($payment->order?->customer_id ? ' and the customer owes it.' : '.')]);
    }

    protected function refreshOrder(string $orderId): void
    {
        $order = Order::find($orderId);
        if (!$order || in_array($order->payment_status, ['refunded', 'partially_refunded'], true)) {
            return;
        }
        $pays = OrderPayment::where('order_id', $orderId)->get();
        $pending = $pays->where('status', 'pending')->sum('amount');
        $failed = $pays->where('status', 'failed')->sum('amount');
        $credit = $pays->where('method', 'credit')->sum('amount');
        $good = $pays->whereNotIn('status', ['failed', 'reversed', 'pending'])->where('method', '!=', 'credit')->sum('amount');
        $status = match (true) {
            $pending > 0 => 'pending',
            $good <= 0 => 'unpaid',
            $failed > 0 || $credit > 0 => 'partial',
            default => 'paid',
        };
        $order->forceFill(['payment_status' => $status])->save();
    }
}

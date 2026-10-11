<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Sales transactions list: every till sale with payments, filters, void / return / reprint.
 */
class PosTransactionController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:View pos|View sale|View order');
    }

    protected function query(Request $request)
    {
        $q = Order::query()->with(['customer:id,first_name,last_name', 'user:id,first_name,last_name'])
            ->withCount('items');

        if (!$request->user()->can('View sale') && !$request->user()->can('View order')) {
            $q->where('user_id', $request->user()->id); // cashiers see their own
        }
        if ($request->get('channel', 'pos') !== 'all') {
            $q->where(fn ($w) => $w->where('channel', 'pos')->orWhere('id', 'like', 'POS-%'));
        }

        $from = $request->get('from', today()->toDateString());
        $to = $request->get('to', today()->toDateString());
        $q->whereBetween('order_date', [$from . ' 00:00:00', $to . ' 23:59:59']);

        if ($request->filled('user_id')) {
            $q->where('user_id', $request->user_id);
        }
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        if ($request->filled('method')) {
            $m = $request->get('method');
            $q->where(fn ($w) => $w->where('payment_method', $m)->orWhereIn('id', OrderPayment::where('method', $m)->select('order_id')));
        }
        if ($request->filled('shift_id')) {
            $q->where('pos_shift_id', $request->shift_id);
        }
        if ($request->filled('search')) {
            $s = trim($request->search);
            $q->where(fn ($w) => $w->where('id', 'like', "%$s%")
                ->orWhereHas('customer', fn ($c) => $c->where('first_name', 'like', "%$s%")->orWhere('last_name', 'like', "%$s%")->orWhere('phone_number', 'like', "%$s%"))
                ->orWhereHas('items', fn ($i) => $i->where('title', 'like', "%$s%")->orWhere('barcode', $s)));
        }
        if ($request->filled('min')) {
            $q->where('total_amount', '>=', (float) $request->min);
        }
        if ($request->filled('max')) {
            $q->where('total_amount', '<=', (float) $request->max);
        }

        return [$q, $from, $to];
    }

    public function index(Request $request)
    {
        $pagetitle = 'Sales Transactions';
        [$q, $from, $to] = $this->query($request);

        $summary = (clone $q)->toBase()->cloneWithout(['columns', 'orders'])->selectRaw("
            COUNT(*) as orders,
            SUM(CASE WHEN status != 'cancelled' THEN total_amount ELSE 0 END) as revenue,
            SUM(CASE WHEN status != 'cancelled' THEN COALESCE(discount_amount,0) ELSE 0 END) as discounts,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as voided,
            SUM(CASE WHEN status != 'cancelled' THEN COALESCE(cost_total,0) ELSE 0 END) as cost
        ")->first();

        $orderIds = (clone $q)->reorder()->where('status', '!=', 'cancelled')->select('id');
        $byMethod = OrderPayment::whereIn('order_id', $orderIds)->selectRaw('method, SUM(amount) total, COUNT(DISTINCT order_id) c')
            ->groupBy('method')->get();
        $refunds = DB::table('refunds')->whereIn('order_id', (clone $q)->reorder()->select('id'))->sum('amount');

        $orders = $q->latest('order_date')->paginate(30)->withQueryString();
        $cashiers = User::whereIn('id', Order::select('user_id')->where('channel', 'pos')->distinct())->orderBy('first_name')->get(['id', 'first_name', 'last_name']);

        $payments = OrderPayment::whereIn('order_id', $orders->pluck('id'))->get()->groupBy('order_id');

        return view('pos.transactions', compact('pagetitle', 'orders', 'summary', 'byMethod', 'refunds', 'cashiers', 'from', 'to', 'payments'));
    }

    public function export(Request $request)
    {
        [$q] = $this->query($request);
        $file = 'sales_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Receipt', 'Date', 'Cashier', 'Customer', 'Items', 'Subtotal', 'Discount', 'Total', 'Cost', 'Profit', 'Payment', 'Status']);
            $q->latest('order_date')->chunk(500, function ($orders) use ($out) {
                foreach ($orders as $o) {
                    fputcsv($out, [
                        $o->id, $o->order_date?->format('Y-m-d H:i'),
                        $o->user ? trim($o->user->first_name . ' ' . $o->user->last_name) : '',
                        $o->customer ? trim($o->customer->first_name . ' ' . $o->customer->last_name) : 'Walk-in',
                        $o->items_count, $o->subtotal, $o->discount_amount, $o->total_amount, $o->cost_total,
                        $o->cost_total !== null ? round($o->total_amount - $o->cost_total, 2) : '',
                        $o->payment_method, $o->status,
                    ]);
                }
            });
            fclose($out);
        }, $file, ['Content-Type' => 'text/csv']);
    }
}

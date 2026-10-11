<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\PosAuthorizationRequired;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerPoint;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PosHeldSale;
use App\Models\PosShift;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StoreSetting;
use App\Models\SystemSetting;
use App\Services\AuditLogger;
use App\Services\PosService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosController extends Controller
{
    public function __construct(protected PosService $pos)
    {
        $this->middleware('permission:View pos|Create sale');
    }

    // =================================================================
    // Terminal
    // =================================================================

    public function index(Request $request)
    {
        $pagetitle = 'Point of Sale';
        $user = $request->user();
        $store = StoreSetting::getSettings();
        $shift = PosShift::currentFor($user->id);
        $categories = Category::whereHas('products', fn ($q) => $q->where('is_active', true))
            ->orderBy('name')->get(['id', 'name']);

        // Which till is this browser?  (remembered in a cookie on the till PC)
        $registers = \App\Models\CashRegister::here()->where('status', 'active')->orderBy('code')->get();
        $openShifts = PosShift::whereIn('cash_register_id', $registers->pluck('id'))->where('status', 'open')
            ->with('user:id,first_name,last_name')->get()->keyBy('cash_register_id');
        $register = $registers->firstWhere('id', (int) $request->cookie(\App\Models\CashRegister::COOKIE))
            ?? ($shift?->cash_register_id ? $registers->firstWhere('id', $shift->cash_register_id) : null)
            ?? ($registers->count() === 1 ? $registers->first() : null);

        $config = [
            'currency' => currency_symbol(),
            'register' => $register ? ['id' => $register->id, 'code' => $register->code, 'name' => $register->name] : null,
            'registers' => $registers->map(fn ($r) => [
                'id' => $r->id, 'code' => $r->code, 'name' => $r->name,
                'busy' => isset($openShifts[$r->id]) && $openShifts[$r->id]->user_id !== $user->id
                    ? trim(($openShifts[$r->id]->user->first_name ?? '') . ' ' . ($openShifts[$r->id]->user->last_name ?? '')) : null,
            ])->values(),
            'transferNeedsConfirmation' => (bool) SystemSetting::get('transfer_needs_confirmation', true),
            'cardNeedsRef' => (bool) SystemSetting::get('card_needs_terminal_ref', false),
            'requireShift' => (bool) SystemSetting::get('require_open_shift', false),
            'maxDiscount' => (float) ($user->max_discount_percent ?? SystemSetting::get('max_discount_percent', 10)),
            'blockBelowCost' => (bool) SystemSetting::get('block_sale_below_cost', true),
            'redeemRate' => (float) config('loyalty.redeem_rate', 100),
            'can' => [
                'discount' => $user->can('Give pos discount'),
                'price' => $user->can('Override pos price'),
                'void' => $user->can('Void pos sale'),
                'return' => $user->can('Process pos return'),
                'shifts' => $user->can('Manage pos shifts') || $user->can('View pos'),
                'below_cost' => $user->can('Sell below cost'),
                'confirm_payments' => $user->can('Confirm payments'),
            ],
            'user' => ['id' => $user->id, 'name' => trim($user->first_name . ' ' . $user->last_name)],
            'store' => ['name' => $store->store_name ?? config('app.name')],
            'routes' => [
                'lookup' => route('pos.lookup'),
                'browse' => route('pos.browse'),
                'checkout' => route('pos.checkout'),
                'authorize' => route('pos.authorize'),
                'receipt' => url('/pos/receipt'),
                'held' => route('pos.held.index'),
                'customers' => route('pos.customers'),
                'customerQuick' => route('customers.quick'),
                'shiftCurrent' => route('pos.shifts.current'),
                'shiftOpen' => route('pos.shifts.open'),
                'shiftCash' => route('pos.shifts.cash'),
                'shiftClose' => url('/pos/shifts'),
                'order' => url('/pos/order'),
                'void' => url('/pos/void-order'),
                'return' => url('/pos/return'),
                'today' => route('pos.today-sales'),
                'transactions' => route('pos.transactions'),
                'dashboard' => route('dashboard'),
                'logout' => route('logout'),
                'register' => route('pos.register.pick'),
            ],
        ];

        return view('pos.terminal', compact('pagetitle', 'store', 'shift', 'categories', 'config'));
    }

    /** Remember which till this browser is (cookie on the till PC). */
    public function pickRegister(Request $request)
    {
        $data = $request->validate(['register_id' => 'required|integer']);
        $register = \App\Models\CashRegister::here()->where('status', 'active')->findOrFail($data['register_id']);
        $register->forceFill(['last_used_at' => now(), 'last_user_id' => $request->user()->id])->saveQuietly();
        return response()->json(['success' => true, 'register' => $register->only(['id', 'code', 'name'])])
            ->cookie(\App\Models\CashRegister::COOKIE, (string) $register->id, 60 * 24 * 365 * 5);
    }

    /** Search box / barcode scanner */
    public function lookup(Request $request)
    {
        $customer = $request->filled('customer_id') ? Customer::find($request->customer_id) : null;
        return response()->json($this->pos->lookup((string) $request->get('q'), $customer));
    }

    /** Old endpoint kept for anything still calling it. */
    public function search(Request $request)
    {
        return response()->json($this->pos->lookup((string) $request->get('q'))['items']);
    }

    public function getProductByBarcode($barcode)
    {
        $r = $this->pos->lookup((string) $barcode);
        if ($r['exact'] && $r['items']) {
            return response()->json(['success' => true, 'product' => $r['items'][0]]);
        }
        return response()->json(['success' => false, 'message' => 'Product not found'], 404);
    }

    /** Product grid for touch screens (by category). */
    public function browse(Request $request)
    {
        $customer = $request->filled('customer_id') ? Customer::find($request->customer_id) : null;
        $q = Product::with(['packs.unit', 'variations' => fn ($v) => $v->where('is_active', true)])
            ->where('is_active', true);
        if ($request->filled('category')) {
            $q->where('category_id', $request->category);
        }
        if ($request->get('sort') === 'popular' || !$request->filled('category')) {
            $q->orderByDesc('sold_quantity');
        } else {
            $q->orderBy('title');
        }
        $items = [];
        foreach ($q->limit(60)->get() as $p) {
            if ($p->product_type === 'variable' && $p->variations->isNotEmpty()) {
                foreach ($p->variations as $v) {
                    $items[] = $this->pos->formatItem($p, $v, null, $customer);
                }
            } else {
                $items[] = $this->pos->formatItem($p, null, null, $customer);
            }
        }
        return response()->json(['items' => array_slice($items, 0, 60)]);
    }

    public function customers(Request $request)
    {
        $q = trim((string) $request->get('q'));
        $customers = Customer::query()
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('first_name', 'like', "%$q%")
                ->orWhere('last_name', 'like', "%$q%")->orWhere('phone_number', 'like', "%$q%")
                ->orWhere('loyalty_card_number', $q)->orWhere('company_name', 'like', "%$q%")))
            ->orderBy('first_name')->limit(20)
            ->get(['id', 'first_name', 'last_name', 'phone_number', 'company_name', 'customer_type', 'credit_limit', 'outstanding_balance']);

        $points = CustomerPoint::whereIn('customer_id', $customers->pluck('id'))->pluck('points', 'customer_id');

        return response()->json($customers->map(fn ($c) => [
            'id' => $c->id,
            'name' => trim($c->first_name . ' ' . $c->last_name),
            'phone' => $c->phone_number,
            'company' => $c->company_name,
            'type' => $c->customer_type,
            'points' => (int) ($points[$c->id] ?? 0),
            'credit_limit' => (float) $c->credit_limit,
            'owing' => (float) $c->outstanding_balance,
        ]));
    }

    // =================================================================
    // Checkout
    // =================================================================

    public function checkout(Request $request)
    {
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.variation_id' => 'nullable|integer',
            'items.*.pack_id' => 'nullable|integer',
            'items.*.qty' => 'required|numeric|min:0.001',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.discount_type' => 'nullable|in:percent,fixed',
            'items.*.discount_value' => 'nullable|numeric|min:0',
            'customer_id' => 'nullable|exists:customers,id',
            'discount_type' => 'nullable|in:percent,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'payments' => 'nullable|array',
            'payments.*.method' => 'required|in:cash,card,transfer,credit',
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.reference' => 'nullable|string|max:100',
            'payments.*.confirmed' => 'nullable|boolean',
            'register_id' => 'nullable|integer',
            'redeem_points' => 'nullable|integer|min:0',
            'note' => 'nullable|string|max:500',
            'held_sale_id' => 'nullable|integer',
            'auth_token' => 'nullable|string|max:64',
            'client_ref' => 'nullable|string|max:64',
        ]);

        try {
            $order = $this->pos->checkout($data);
        } catch (PosAuthorizationRequired $e) {
            return response()->json(['success' => false, 'needs_authorization' => $e->action, 'message' => $e->getMessage()], 403);
        } catch (InsufficientStockException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first(), 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Could not complete the sale: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sale completed',
            'order_id' => $order->id,
            'total' => (float) $order->total_amount,
            'paid' => (float) $order->amount_paid,
            'change' => (float) $order->change_due,
            'pending_payments' => (float) \App\Models\OrderPayment::where('order_id', $order->id)->where('status', 'pending')->sum('amount'),
            'receipt_url' => route('pos.receipt', $order->id),
        ]);
    }

    /** The old POS screen posted here. It has been replaced by the new terminal. */
    public function savePosOrder(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'The POS has been upgraded. Please reload the page to use the new till.',
        ], 410);
    }

    public function authorizeOverride(Request $request)
    {
        $data = $request->validate([
            'pin' => 'required|digits_between:4,6',
            'action' => 'required|in:' . implode(',', array_keys(PosService::OVERRIDES)),
        ]);

        try {
            $result = $this->pos->authorizeWithPin($data['pin'], $data['action']);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }

        return response()->json(['success' => true] + $result);
    }

    // =================================================================
    // Receipts, orders, void, returns
    // =================================================================

    public function generateReceipt($orderId)
    {
        $order = Order::where('id', $orderId)
            ->with(['customer', 'items.product', 'items.unit', 'user'])
            ->firstOrFail();

        if ($order->user_id != auth()->id() && !auth()->user()->can('Manage pos') && !auth()->user()->can('View sale') && !auth()->user()->can('Print receipt')) {
            abort(403);
        }

        $payments = class_exists(OrderPayment::class) ? OrderPayment::where('order_id', $order->id)->get() : collect();
        $footer = SystemSetting::get('receipt_footer');

        return view('pos.receipt', compact('order', 'payments', 'footer'));
    }

    public function orderDetails($orderId)
    {
        $order = Order::with(['items', 'customer', 'user'])->findOrFail($orderId);
        $payments = OrderPayment::where('order_id', $order->id)->get(['method', 'amount', 'reference']);

        return response()->json([
            'id' => $order->id,
            'date' => $order->order_date?->format('d M Y, h:i A'),
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'cashier' => $order->user ? trim($order->user->first_name . ' ' . $order->user->last_name) : null,
            'customer' => $order->customer ? trim($order->customer->first_name . ' ' . $order->customer->last_name) : null,
            'subtotal' => (float) ($order->subtotal ?? $order->items->sum('total_price')),
            'discount' => (float) $order->discount_amount,
            'total' => (float) $order->total_amount,
            'refunded' => (float) $order->totalRefunded(),
            'void_reason' => $order->void_reason,
            'payments' => $payments,
            'items' => $order->items->map(fn ($i) => [
                'id' => $i->id,
                'title' => $i->title,
                'qty' => (float) $i->quantity,
                'unit' => $i->unit_name,
                'price' => (float) $i->unit_price,
                'total' => (float) $i->total_price,
                'returned' => (float) ($i->returned_quantity ?? 0),
            ]),
            'receipt_url' => route('pos.receipt', $order->id),
        ]);
    }

    public function voidOrder(Request $request, $orderId)
    {
        $request->validate(['reason' => 'nullable|string|max:255', 'auth_token' => 'nullable|string']);
        $order = Order::findOrFail($orderId);

        $approvedBy = $this->pos->allowed('void', $request->auth_token);
        if (!$approvedBy) {
            return response()->json(['success' => false, 'needs_authorization' => 'void', 'message' => 'Voiding a sale needs supervisor approval.'], 403);
        }

        try {
            $this->pos->void($order, $request->input('reason') ?: 'No reason given', $approvedBy);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => "Sale {$order->id} voided. Stock has been returned."]);
    }

    public function returnItems(Request $request, $orderId)
    {
        $data = $request->validate([
            'lines' => 'required|array',
            'reason' => 'required|string|max:255',
            'method' => 'nullable|in:cash,card,transfer,credit',
            'restock' => 'nullable|boolean',
            'auth_token' => 'nullable|string',
        ]);
        $order = Order::findOrFail($orderId);

        if (!$this->pos->allowed('return', $data['auth_token'] ?? null)) {
            return response()->json(['success' => false, 'needs_authorization' => 'return', 'message' => 'Returns need supervisor approval.'], 403);
        }

        try {
            $refund = $this->pos->returnItems($order, $data['lines'], $data['reason'], $data['method'] ?? 'cash', $request->boolean('restock', true));
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Refund of ' . money($refund->amount) . ' recorded.', 'amount' => (float) $refund->amount]);
    }

    public function getTodaySales(Request $request)
    {
        $q = Order::whereDate('order_date', today())->where('status', '!=', 'cancelled');
        if (!$request->user()->can('View sale')) {
            $q->where('user_id', $request->user()->id);
        }
        $orders = (clone $q)->selectRaw('COUNT(*) c, COALESCE(SUM(total_amount),0) t')->first();
        $byMethod = OrderPayment::whereIn('order_id', (clone $q)->select('id'))
            ->selectRaw('method, SUM(amount) total')->groupBy('method')->pluck('total', 'method');
        $recent = (clone $q)->latest('order_date')->limit(15)->get(['id', 'total_amount', 'order_date', 'payment_method', 'status']);

        return response()->json([
            'success' => true,
            'data' => [
                'total_orders' => (int) $orders->c,
                'total_sales' => (float) $orders->t,
                'average_sale' => $orders->c ? round($orders->t / $orders->c, 2) : 0,
                'by_payment_method' => $byMethod,
                'recent' => $recent->map(fn ($o) => [
                    'id' => $o->id, 'total' => (float) $o->total_amount, 'time' => $o->order_date?->format('h:i A'),
                    'method' => $o->payment_method, 'status' => $o->status,
                ]),
            ],
        ]);
    }

    public function getProductStock($productId)
    {
        $product = Product::findOrFail($productId);
        return response()->json(['success' => true, 'stock' => $product->current_stock, 'product_name' => $product->title]);
    }

    // =================================================================
    // Held (parked) sales
    // =================================================================

    public function heldIndex(Request $request)
    {
        $q = PosHeldSale::with('customer:id,first_name,last_name', 'user:id,first_name')->latest();
        if (!$request->user()->can('View all pos shifts')) {
            $q->where('user_id', $request->user()->id);
        }
        return response()->json($q->limit(50)->get()->map(fn ($h) => [
            'id' => $h->id,
            'label' => $h->label,
            'customer' => $h->customer ? trim($h->customer->first_name . ' ' . $h->customer->last_name) : null,
            'items' => count($h->cart['items'] ?? []),
            'total' => (float) $h->total,
            'cashier' => $h->user->first_name ?? '',
            'time' => $h->created_at->diffForHumans(),
        ]));
    }

    public function holdSale(Request $request)
    {
        $data = $request->validate([
            'label' => 'nullable|string|max:100',
            'customer_id' => 'nullable|integer',
            'cart' => 'required|array',
            'total' => 'nullable|numeric',
        ]);
        $held = PosHeldSale::create([
            'user_id' => $request->user()->id,
            'label' => $data['label'] ?: 'Sale held at ' . now()->format('h:i A'),
            'customer_id' => $data['customer_id'] ?? null,
            'cart' => $data['cart'],
            'total' => $data['total'] ?? 0,
        ]);
        return response()->json(['success' => true, 'id' => $held->id, 'message' => 'Sale held.']);
    }

    public function heldShow(Request $request, PosHeldSale $held)
    {
        if ($held->user_id !== $request->user()->id && !$request->user()->can('View all pos shifts')) {
            abort(403);
        }
        return response()->json(['id' => $held->id, 'cart' => $held->cart, 'customer_id' => $held->customer_id]);
    }

    public function heldDestroy(Request $request, PosHeldSale $held)
    {
        if ($held->user_id !== $request->user()->id && !$request->user()->can('View all pos shifts')) {
            abort(403);
        }
        $held->delete();
        return response()->json(['success' => true]);
    }
}

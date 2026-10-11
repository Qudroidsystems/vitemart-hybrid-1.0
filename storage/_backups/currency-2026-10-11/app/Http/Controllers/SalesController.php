<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\StoreSetting;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:View sale|View sales report|View sales analytics', ['only' => ['index', 'ajaxDetails', 'userSales']]);
        $this->middleware('permission:Export sales report', ['only' => ['exportPdf', 'exportUserSalesPdf']]);
        $this->middleware('permission:View sales commission|Manage sales commission', ['only' => ['commissions', 'commissionDetails', 'markAsPaid', 'bulkMarkAsPaid', 'exportCommissionsPdf']]);
    }

    public function index(Request $request)
    {
        $pagetitle = "Sales Analytics & Management";

        // Default date range (last 30 days)
        $defaultStartDate = now()->subDays(30)->format('Y-m-d');
        $defaultEndDate = now()->format('Y-m-d');

        $dateFrom = $request->filled('date_from') ? $request->date_from : $defaultStartDate;
        $dateTo = $request->filled('date_to') ? $request->date_to : $defaultEndDate;

        $query = Order::with(['user', 'customer', 'items'])
            ->whereIn('status', ['completed', 'delivered'])
            ->whereDate('order_date', '>=', $dateFrom)
            ->whereDate('order_date', '<=', $dateTo)
            ->latest('order_date');

        // Filters
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        $sales = $query->paginate(25)->withQueryString();

        // Sales Persons
        $salesPersons = User::whereHas('orders')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        // Core Analytics for the date range
        $totalRevenue = $sales->sum('total_amount');
        $totalCommission = $sales->sum('commission_amount');
        $totalSalesCount = $sales->total();

        // Get summary for date range (not just paginated results)
        $summaryQuery = Order::whereIn('status', ['completed', 'delivered'])
            ->whereDate('order_date', '>=', $dateFrom)
            ->whereDate('order_date', '<=', $dateTo);

        if ($request->filled('user_id')) {
            $summaryQuery->where('user_id', $request->user_id);
        }

        $dateRangeSummary = [
            'total_revenue' => $summaryQuery->sum('total_amount'),
            'total_sales' => $summaryQuery->count(),
            'total_commission' => $summaryQuery->sum('commission_amount'),
            'average_sale' => $summaryQuery->count() > 0 ? $summaryQuery->sum('total_amount') / $summaryQuery->count() : 0,
        ];

        // Top Performers (Within selected date range)
        $topPerformers = User::whereHas('orders', function($q) use ($dateFrom, $dateTo) {
                $q->whereDate('order_date', '>=', $dateFrom)
                  ->whereDate('order_date', '<=', $dateTo)
                  ->whereIn('status', ['completed', 'delivered']);
            })
            ->withSum(['orders as revenue' => function($q) use ($dateFrom, $dateTo) {
                $q->whereDate('order_date', '>=', $dateFrom)
                  ->whereDate('order_date', '<=', $dateTo)
                  ->whereIn('status', ['completed', 'delivered']);
            }], 'total_amount')
            ->withCount(['orders as order_count' => function($q) use ($dateFrom, $dateTo) {
                $q->whereDate('order_date', '>=', $dateFrom)
                  ->whereDate('order_date', '<=', $dateTo)
                  ->whereIn('status', ['completed', 'delivered']);
            }])
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        // Monthly Revenue Trend (Last 12 months)
        $monthlySales = Order::selectRaw('DATE_FORMAT(order_date, "%Y-%m") as month, SUM(total_amount) as total')
            ->where('order_date', '>=', now()->subMonths(12))
            ->whereIn('status', ['completed', 'delivered'])
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month');

        // Payment Method Breakdown for date range
        $paymentBreakdown = Order::whereIn('status', ['completed', 'delivered'])
            ->whereDate('order_date', '>=', $dateFrom)
            ->whereDate('order_date', '<=', $dateTo)
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_amount) as total'))
            ->groupBy('payment_method')
            ->get();

        // Commission Breakdown
        $commissions = [
            'pending' => Order::whereIn('status', ['completed', 'delivered'])
                ->whereDate('order_date', '>=', $dateFrom)
                ->whereDate('order_date', '<=', $dateTo)
                ->sum('commission_amount'),
            'paid' => 0,
        ];

        return view('sales.index', compact(
            'pagetitle',
            'sales',
            'salesPersons',
            'totalRevenue',
            'totalCommission',
            'totalSalesCount',
            'topPerformers',
            'monthlySales',
            'paymentBreakdown',
            'commissions',
            'dateFrom',
            'dateTo',
            'dateRangeSummary'
        ));
    }

    public function userSales(Request $request, $userId = null)
{
    $pagetitle = "User Sales Report";

    // Get user
    $user = User::findOrFail($userId ?? $request->user_id);

    // Default date range (last 30 days)
    $defaultStartDate = now()->subDays(30)->format('Y-m-d');
    $defaultEndDate = now()->format('Y-m-d');

    $dateFrom = $request->filled('date_from') ? $request->date_from : $defaultStartDate;
    $dateTo = $request->filled('date_to') ? $request->date_to : $defaultEndDate;

    // Calculate days in period
    $start = \Carbon\Carbon::parse($dateFrom);
    $end = \Carbon\Carbon::parse($dateTo);
    $daysInPeriod = $start->diffInDays($end) + 1; // +1 to include both start and end days

    $query = Order::with(['customer', 'items'])
        ->where('user_id', $user->id)
        ->whereIn('status', ['completed', 'delivered'])
        ->whereDate('order_date', '>=', $dateFrom)
        ->whereDate('order_date', '<=', $dateTo)
        ->latest('order_date');

    if ($request->filled('payment_method')) {
        $query->where('payment_method', $request->payment_method);
    }

    $sales = $query->paginate(25)->withQueryString();

    // User summary for date range
    $userSummary = [
        'total_revenue' => $sales->sum('total_amount'),
        'total_sales' => $sales->total(),
        'total_commission' => $sales->sum('commission_amount'),
        'average_sale' => $sales->total() > 0 ? $sales->sum('total_amount') / $sales->total() : 0,
    ];

    // Get user performance metrics
    $userPerformance = Order::where('user_id', $user->id)
        ->whereIn('status', ['completed', 'delivered'])
        ->select(
            DB::raw('COUNT(*) as total_orders'),
            DB::raw('SUM(total_amount) as total_revenue'),
            DB::raw('AVG(total_amount) as avg_order_value'),
            DB::raw('MAX(total_amount) as largest_order'),
            DB::raw('MIN(total_amount) as smallest_order')
        )
        ->first();

    // Monthly performance for this user
    $monthlyPerformance = Order::where('user_id', $user->id)
        ->whereIn('status', ['completed', 'delivered'])
        ->selectRaw('DATE_FORMAT(order_date, "%Y-%m") as month, COUNT(*) as order_count, SUM(total_amount) as total_revenue')
        ->groupBy('month')
        ->orderBy('month', 'desc')
        ->limit(6)
        ->get();

    return view('sales.user-sales', compact(
        'pagetitle',
        'user',
        'sales',
        'dateFrom',
        'dateTo',
        'userSummary',
        'userPerformance',
        'monthlyPerformance',
        'daysInPeriod'
    ));
}


public function ajaxDetails($id)
{
    try {
        $order = Order::with([
            'user:id,first_name,last_name,email',
            'customer:id,name,phone',
            'items.product:id,title' // Changed from 'name' to 'title'
        ])->findOrFail($id);

        // Format the data for JSON response
        $formattedOrder = [
            'id' => $order->id,
            'order_date' => $order->order_date,
            'status' => $order->status,
            'payment_method' => $order->payment_method,
            'total_amount' => $order->total_amount,
            'commission_amount' => $order->commission_amount,
            'notes' => $order->notes,
            'user' => $order->user ? [
                'id' => $order->user->id,
                'name' => $order->user->first_name . ' ' . $order->user->last_name,
                'email' => $order->user->email
            ] : null,
            'customer' => $order->customer ? [
                'id' => $order->customer->id,
                'name' => $order->customer->name,
                'phone' => $order->customer->phone
            ] : null,
            'items' => $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product' => $item->product ? [
                        'id' => $item->product->id,
                        'name' => $item->product->title, // Changed from name to title
                        'title' => $item->product->title // Keep both for compatibility
                    ] : null,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'total' => $item->total ?? ($item->quantity * $item->price)
                ];
            })->toArray()
        ];

        return response()->json([
            'success' => true,
            'order' => $formattedOrder
        ]);
    } catch (\Exception $e) {
        \Log::error('Order details error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Order not found: ' . $e->getMessage()
        ], 404);
    }
}
    public function exportPdf(Request $request)
    {
        // Default date range (last 30 days)
        $defaultStartDate = now()->subDays(30)->format('Y-m-d');
        $defaultEndDate = now()->format('Y-m-d');

        $dateFrom = $request->filled('date_from') ? $request->date_from : $defaultStartDate;
        $dateTo = $request->filled('date_to') ? $request->date_to : $defaultEndDate;

        $query = Order::with(['user', 'customer', 'items'])
            ->whereIn('status', ['completed', 'delivered'])
            ->whereDate('order_date', '>=', $dateFrom)
            ->whereDate('order_date', '<=', $dateTo)
            ->latest('order_date');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
            $user = User::find($request->user_id);
        }
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        $sales = $query->get();

        // Get store settings
        $settings = StoreSetting::getSettings();

        // If no settings exist, create a default object
        if (!$settings) {
            $settings = new \stdClass();
            $settings->store_name = 'Store Management System';
            $settings->currency_symbol = '₦';
            $settings->address = null;
            $settings->phone = null;
            $settings->email = null;
            $settings->website = null;
            $settings->footer_note = null;
            $settings->logo_url = null;
        }

        $generatedAt = now()->format('F j, Y \a\t g:i A');
        $generatedBy = auth()->user()->name ?? 'System';

        $data = [
            'sales' => $sales,
            'settings' => $settings,
            'generatedAt' => $generatedAt,
            'generatedBy' => $generatedBy,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'user' => $user ?? null,
            'filters' => [
                'user_id' => $request->user_id,
                'payment_method' => $request->payment_method
            ]
        ];

        $pdf = Pdf::loadView('sales.pdf.report', $data)
                  ->setPaper('a4', 'landscape');

        return $pdf->stream('sales-report-' . now()->format('Y-m-d-His') . '.pdf');
    }

    public function exportUserSalesPdf(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        // Default date range (last 30 days)
        $defaultStartDate = now()->subDays(30)->format('Y-m-d');
        $defaultEndDate = now()->format('Y-m-d');

        $dateFrom = $request->filled('date_from') ? $request->date_from : $defaultStartDate;
        $dateTo = $request->filled('date_to') ? $request->date_to : $defaultEndDate;

        $query = Order::with(['customer', 'items'])
            ->where('user_id', $user->id)
            ->whereIn('status', ['completed', 'delivered'])
            ->whereDate('order_date', '>=', $dateFrom)
            ->whereDate('order_date', '<=', $dateTo)
            ->latest('order_date');

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        $sales = $query->get();

        // Get store settings
        $settings = StoreSetting::getSettings();

        // If no settings exist, create a default object
        if (!$settings) {
            $settings = new \stdClass();
            $settings->store_name = 'Store Management System';
            $settings->currency_symbol = '₦';
            $settings->address = null;
            $settings->phone = null;
            $settings->email = null;
            $settings->website = null;
            $settings->footer_note = null;
            $settings->logo_url = null;
        }

        $generatedAt = now()->format('F j, Y \a\t g:i A');
        $generatedBy = auth()->user()->name ?? 'System';

        $data = [
            'sales' => $sales,
            'settings' => $settings,
            'generatedAt' => $generatedAt,
            'generatedBy' => $generatedBy,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'user' => $user,
            'filters' => [
                'payment_method' => $request->payment_method
            ]
        ];

        $pdf = Pdf::loadView('sales.pdf.user-report', $data)
                  ->setPaper('a4', 'landscape');

        return $pdf->stream('user-sales-report-' . $user->id . '-' . now()->format('Y-m-d-His') . '.pdf');
    }

    // Commissions methods (keep existing)
    public function commissions(Request $request)
    {
        $query = Order::with(['user', 'customer'])
            ->whereNotNull('commission_amount')
            ->where('commission_amount', '>', 0)
            ->latest('order_date');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $commissions = $query->paginate(25)->withQueryString();

        $salesPersons = User::whereHas('orders')
            ->orderBy('name')
            ->get();

        $summary = [
            'total' => Order::whereNotNull('commission_amount')->sum('commission_amount'),
            'pending' => Order::whereNotNull('commission_amount')->sum('commission_amount'),
        ];

        $pagetitle = "Sales Commissions";

        return view('sales.commissions', compact('pagetitle', 'commissions', 'salesPersons', 'summary'));
    }

    public function commissionDetails($id)
    {
        try {
            $commission = Order::with(['user', 'customer'])
                ->whereNotNull('commission_amount')
                ->where('commission_amount', '>', 0)
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'commission' => $commission
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Commission record not found'
            ], 404);
        }
    }

    public function markAsPaid(Request $request, $id)
    {
        try {
            $order = Order::findOrFail($id);

            $order->update([
                'commission_paid' => true,
                'paid_date' => now(),
                'payment_method' => $request->payment_method ?? 'cash',
                'payment_notes' => $request->notes
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Commission marked as paid successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update commission status'
            ], 500);
        }
    }

    public function bulkMarkAsPaid(Request $request)
    {
        try {
            $commissionIds = $request->input('commission_ids', []);
            $paymentMethod = $request->input('payment_method', 'cash');
            $paymentDate = $request->input('payment_date', now()->format('Y-m-d'));
            $notes = $request->input('notes', '');

            if (empty($commissionIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No commissions selected'
                ], 400);
            }

            $count = Order::whereIn('id', $commissionIds)
                ->update([
                    'commission_paid' => true,
                    'paid_date' => $paymentDate,
                    'payment_method' => $paymentMethod,
                    'payment_notes' => $notes
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Commissions marked as paid successfully',
                'count' => $count
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update commission statuses'
            ], 500);
        }
    }

    public function exportCommissionsPdf(Request $request)
    {
        $query = Order::with(['user', 'customer'])
            ->whereNotNull('commission_amount')
            ->where('commission_amount', '>', 0)
            ->latest('order_date');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $commissions = $query->get();

        // Get store settings
        $settings = StoreSetting::getSettings();

        // If no settings exist, create a default object
        if (!$settings) {
            $settings = new \stdClass();
            $settings->store_name = 'Store Management System';
            $settings->currency_symbol = '₦';
            $settings->address = null;
            $settings->phone = null;
            $settings->email = null;
            $settings->website = null;
            $settings->footer_note = null;
            $settings->logo_url = null;
        }

        $generatedAt = now()->format('F j, Y \a\t g:i A');
        $generatedBy = auth()->user()->name ?? 'System';

        $data = [
            'commissions' => $commissions,
            'settings' => $settings,
            'generatedAt' => $generatedAt,
            'generatedBy' => $generatedBy,
            'filters' => [
                'user_id' => $request->user_id
            ]
        ];

        $pdf = Pdf::loadView('sales.pdf.commissions', $data)
                  ->setPaper('a4', 'landscape');

        return $pdf->download('commissions-report-' . now()->format('Y-m-d-His') . '.pdf');
    }
}

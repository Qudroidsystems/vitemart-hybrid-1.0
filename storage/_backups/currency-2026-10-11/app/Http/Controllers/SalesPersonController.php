<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\SalesCommission;
use App\Models\StoreSetting;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesPersonController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

        // Apply specific permission middleware for each method
        $this->middleware('permission:View personal sales dashboard', ['only' => ['dashboard']]);
        $this->middleware('permission:Export personal sales report', ['only' => ['exportPdf']]);
        $this->middleware('permission:View personal commission', ['only' => ['commissionStatement']]);
        $this->middleware('permission:Export personal commission report', ['only' => ['exportCommissionPdf']]);
        $this->middleware('permission:View personal performance metrics', ['only' => ['performance']]);
    }

    public function dashboard(Request $request)
    {
        $user = auth()->user();
        $pagetitle = "My Sales Dashboard";

        // Default date range (last 30 days)
        $defaultStartDate = now()->subDays(30)->format('Y-m-d');
        $defaultEndDate = now()->format('Y-m-d');

        $dateFrom = $request->filled('date_from') ? $request->date_from : $defaultStartDate;
        $dateTo = $request->filled('date_to') ? $request->date_to : $defaultEndDate;

        // Calculate days in period
        $start = Carbon::parse($dateFrom);
        $end = Carbon::parse($dateTo);
        $daysInPeriod = $start->diffInDays($end) + 1;

        // Query for user's sales - ensure user can only see their own data
        $query = Order::with(['customer', 'items'])
            ->where('user_id', $user->id)
            ->whereIn('status', ['completed', 'delivered'])
            ->whereDate('order_date', '>=', $dateFrom)
            ->whereDate('order_date', '<=', $dateTo)
            ->latest('order_date');

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        $sales = $query->paginate(20)->withQueryString();

        // Summary for date range
        $summary = [
            'total_revenue' => $sales->sum('total_amount'),
            'total_sales' => $sales->total(),
            'total_commission' => $sales->sum('commission_amount'),
            'average_sale' => $sales->total() > 0 ? $sales->sum('total_amount') / $sales->total() : 0,
            'daily_average' => $daysInPeriod > 0 ? ($sales->sum('total_amount') / $daysInPeriod) : 0,
        ];

        // Monthly performance (last 6 months) - only user's data
        $monthlyPerformance = Order::where('user_id', $user->id)
            ->whereIn('status', ['completed', 'delivered'])
            ->selectRaw('DATE_FORMAT(order_date, "%Y-%m") as month,
                         COUNT(*) as order_count,
                         SUM(total_amount) as total_revenue,
                         SUM(commission_amount) as total_commission')
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit(6)
            ->get();

        // Today's performance - only user's data
        $todayPerformance = Order::where('user_id', $user->id)
            ->whereIn('status', ['completed', 'delivered'])
            ->whereDate('order_date', today())
            ->select(
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(total_amount) as total_revenue'),
                DB::raw('SUM(commission_amount) as total_commission')
            )
            ->first();

        // This week's performance - only user's data
        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();
        $weekPerformance = Order::where('user_id', $user->id)
            ->whereIn('status', ['completed', 'delivered'])
            ->whereBetween('order_date', [$weekStart, $weekEnd])
            ->select(
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(total_amount) as total_revenue'),
                DB::raw('SUM(commission_amount) as total_commission')
            )
            ->first();

        // Payment method breakdown - only user's data
        $paymentBreakdown = Order::where('user_id', $user->id)
            ->whereIn('status', ['completed', 'delivered'])
            ->whereDate('order_date', '>=', $dateFrom)
            ->whereDate('order_date', '<=', $dateTo)
            ->select('payment_method',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total_amount) as total'))
            ->groupBy('payment_method')
            ->get();

        // Top products (last 30 days) - only user's data - FIXED: using unit_price
        $topProducts = Order::where('user_id', $user->id)
            ->whereIn('status', ['completed', 'delivered'])
            ->whereDate('order_date', '>=', now()->subDays(30))
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->select('products.id', 'products.title as product_name',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('SUM(order_items.quantity * order_items.unit_price) as total_revenue')) // FIXED HERE
            ->groupBy('products.id', 'products.title')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->get();

        // Commission summary - query from SalesCommission model
        $commissionSummary = [
            'pending' => SalesCommission::where('user_id', $user->id)
                ->where('status', 'pending')
                ->sum('commission_amount'),
            'paid' => SalesCommission::where('user_id', $user->id)
                ->where('status', 'paid')
                ->sum('commission_amount'),
            'total' => SalesCommission::where('user_id', $user->id)
                ->sum('commission_amount'),
        ];

        return view('salesperson.dashboard', compact(
            'pagetitle',
            'user',
            'sales',
            'dateFrom',
            'dateTo',
            'summary',
            'monthlyPerformance',
            'todayPerformance',
            'weekPerformance',
            'paymentBreakdown',
            'topProducts',
            'commissionSummary',
            'daysInPeriod'
        ));
    }

    public function exportPdf(Request $request)
    {
        if (!auth()->user()->can('Export personal sales report')) {
            abort(403, 'Unauthorized action.');
        }

        $user = auth()->user();

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

        $data = [
            'sales' => $sales,
            'settings' => $settings,
            'generatedAt' => $generatedAt,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'user' => $user,
            'filters' => [
                'payment_method' => $request->payment_method
            ]
        ];

        $pdf = Pdf::loadView('salesperson.pdf.report', $data)
                  ->setPaper('a4', 'landscape');

        return $pdf->stream('my-sales-report-' . now()->format('Y-m-d-His') . '.pdf');
    }

    public function commissionStatement()
    {
        $user = auth()->user();
        $pagetitle = "My Commission Statement";

        // Query from SalesCommission model with order relationship
        $commissions = SalesCommission::with(['order.customer'])
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->paginate(25);

        $summary = [
            'total' => SalesCommission::where('user_id', $user->id)
                ->sum('commission_amount'),
            'pending' => SalesCommission::where('user_id', $user->id)
                ->where('status', 'pending')
                ->sum('commission_amount'),
            'paid' => SalesCommission::where('user_id', $user->id)
                ->where('status', 'paid')
                ->sum('commission_amount'),
        ];

        return view('salesperson.commissions', compact(
            'pagetitle',
            'commissions',
            'summary'
        ));
    }

    public function exportCommissionPdf()
    {
        if (!auth()->user()->can('Export personal commission report')) {
            abort(403, 'Unauthorized action.');
        }

        $user = auth()->user();

        $commissions = SalesCommission::with(['order.customer'])
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->get();

        $settings = StoreSetting::getSettings();

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

        $data = [
            'commissions' => $commissions,
            'settings' => $settings,
            'generatedAt' => $generatedAt,
            'user' => $user,
        ];

        $pdf = Pdf::loadView('salesperson.pdf.commissions', $data)
                  ->setPaper('a4', 'landscape');

        return $pdf->stream('commission-statement-' . now()->format('Y-m-d-His') . '.pdf');
    }

    public function performance()
    {
        $user = auth()->user();
        $pagetitle = "My Performance Analytics";

        // Last 12 months performance - only user's data
        $monthlyPerformance = Order::where('user_id', $user->id)
            ->whereIn('status', ['completed', 'delivered'])
            ->selectRaw('DATE_FORMAT(order_date, "%Y-%m") as month,
                         COUNT(*) as order_count,
                         SUM(total_amount) as total_revenue,
                         AVG(total_amount) as avg_order_value,
                         SUM(commission_amount) as total_commission')
            ->where('order_date', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Weekly performance (last 12 weeks) - only user's data
        $weeklyPerformance = Order::where('user_id', $user->id)
            ->whereIn('status', ['completed', 'delivered'])
            ->selectRaw('YEARWEEK(order_date, 1) as week,
                         COUNT(*) as order_count,
                         SUM(total_amount) as total_revenue')
            ->where('order_date', '>=', now()->subWeeks(12))
            ->groupBy('week')
            ->orderBy('week')
            ->get();

        // Daily average by day of week - only user's data
        $dailyPerformance = Order::where('user_id', $user->id)
            ->whereIn('status', ['completed', 'delivered'])
            ->selectRaw('DAYNAME(order_date) as day_name,
                         DAYOFWEEK(order_date) as day_number,
                         COUNT(*) as order_count,
                         AVG(total_amount) as avg_revenue')
            ->groupBy('day_name', 'day_number')
            ->orderBy('day_number')
            ->get();

        // Customer statistics - only user's data
        $customerStats = Order::where('user_id', $user->id)
            ->whereIn('status', ['completed', 'delivered'])
            ->select(
                DB::raw('COUNT(DISTINCT customer_id) as unique_customers'),
                DB::raw('AVG(total_amount) as avg_order_value'),
                DB::raw('MAX(total_amount) as largest_order'),
                DB::raw('COUNT(*) as total_orders')
            )
            ->first();

        // Repeat customers - only user's data
        $repeatCustomers = Order::where('user_id', $user->id)
            ->whereIn('status', ['completed', 'delivered'])
            ->select('customer_id', DB::raw('COUNT(*) as order_count'))
            ->groupBy('customer_id')
            ->having('order_count', '>', 1)
            ->count();

        return view('salesperson.performance', compact(
            'pagetitle',
            'monthlyPerformance',
            'weeklyPerformance',
            'dailyPerformance',
            'customerStats',
            'repeatCustomers'
        ));
    }
}

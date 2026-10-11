<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPoint;
use App\Models\CustomerPointTransaction;
use Illuminate\Http\Request;

class LoyaltyReportController extends Controller
{
    public function index(Request $request)
    {
        $pagetitle = "Loyalty Points Report";

        $query = Customer::with('points')
            ->whereHas('points', function($q) {
                $q->where('points', '>', 0);
            });

        // Search
        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('first_name', 'like', "%{$request->search}%")
                  ->orWhere('last_name', 'like', "%{$request->search}%")
                  ->orWhere('phone_number', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            });
        }

        // Points range
        if ($request->filled('min_points')) {
            $query->whereHas('points', function($q) use ($request) {
                $q->where('points', '>=', $request->min_points);
            });
        }
        if ($request->filled('max_points')) {
            $query->whereHas('points', function($q) use ($request) {
                $q->where('points', '<=', $request->max_points);
            });
        }

        // Sort
        $sort = $request->get('sort', 'points');
        $direction = $request->get('direction', 'desc');

        $allowedSorts = ['name', 'points', 'points_value', 'created_at'];
        if (!in_array($sort, $allowedSorts)) $sort = 'points';

        if ($sort === 'name') {
            $query->orderBy('first_name')->orderBy('last_name');
        } else {
            $query->join('customer_points', 'customers.id', '=', 'customer_points.customer_id')
                  ->orderBy("customer_points.{$sort}", $direction)
                  ->select('customers.*');
        }

        $customers = $query->paginate(25)->appends($request->query());

        // Summary Stats
        $totalCustomers = CustomerPoint::where('points', '>', 0)->count();
        $totalPoints = CustomerPoint::sum('points');
        $totalValue = $totalPoints / config('loyalty.redeem_rate', 100);

        // === CHART DATA ===
        // 1. Top 10 customers by points
        $topCustomers = Customer::with('points')
            ->whereHas('points', fn($q) => $q->where('points', '>', 0))
            ->join('customer_points', 'customers.id', '=', 'customer_points.customer_id')
            ->orderByDesc('customer_points.points')
            ->limit(10)
            ->select('customers.first_name', 'customers.last_name', 'customer_points.points')
            ->get();

        // 2. Monthly points earned (last 12 months)
        $monthlyPoints = CustomerPointTransaction::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, SUM(points_earned) as total')
            ->where('points_earned', '>', 0)
            ->where('created_at', '>=', now()->subYear())
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month');

        $months = [];
        $pointsData = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i)->format('Y-m');
            $months[] = now()->subMonths($i)->format('M Y');
            $pointsData[] = $monthlyPoints->get($date, 0);
        }

        // 3. Points distribution buckets
        $distribution = CustomerPoint::selectRaw('
            CASE
                WHEN points = 0 THEN "0"
                WHEN points BETWEEN 1 AND 100 THEN "1-100"
                WHEN points BETWEEN 101 AND 500 THEN "101-500"
                WHEN points BETWEEN 501 AND 1000 THEN "501-1000"
                WHEN points > 1000 THEN "1000+"
            END as range,
            COUNT(*) as count
        ')
        ->groupBy('range')
        ->orderByRaw("FIELD(range, '0', '1-100', '101-500', '501-1000', '1000+')")
        ->pluck('count', 'range');

        return view('reports.loyalty-points.index', compact(
            'pagetitle',
            'customers',
            'totalCustomers',
            'totalPoints',
            'totalValue',
            'topCustomers',
            'months',
            'pointsData',
            'distribution'
        ));
    }

    public function customerHistory($customerId)
    {
        $customer = Customer::with('points')->findOrFail($customerId);
        $transactions = CustomerPointTransaction::where('customer_id', $customerId)
            ->with('order', 'createdBy')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('reports.loyalty-points.history', compact('customer', 'transactions'));
    }

    public function export(Request $request)
    {
        $query = Customer::with('points')->whereHas('points', fn($q) => $q->where('points', '>', 0));

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('first_name', 'like', "%{$request->search}%")
                  ->orWhere('last_name', 'like', "%{$request->search}%")
                  ->orWhere('phone_number', 'like', "%{$request->search}%");
            });
        }

        $customers = $query->get();

        $filename = 'loyalty-points-report-' . now()->format('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($customers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Customer Name', 'Phone', 'Email', 'Total Points', 'Value (₦)', 'Joined']);

            foreach ($customers as $c) {
                $value = $c->points->points / config('loyalty.redeem_rate', 100);
                fputcsv($file, [
                    $c->first_name . ' ' . $c->last_name,
                    $c->phone_number ?? '-',
                    $c->email ?? '-',
                    $c->points->points ?? 0,
                    number_format($value, 2),
                    $c->created_at->format('d M Y'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}

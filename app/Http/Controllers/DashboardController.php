<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(protected DashboardService $dash)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        // Cashiers without dashboard access go to the till.
        if (!$user->can('dashboard') && $user->can('View pos')) {
            return redirect()->route('pos.index');
        }

        $period = $request->get('period', 'today');
        [$start, $end, $prevStart, $prevEnd] = $this->dash->range($period, $request->get('from'), $request->get('to'));

        $kpi = $this->dash->kpis($start, $end);
        $prev = $this->dash->kpis($prevStart, $prevEnd);
        $change = $this->dash->change($kpi, $prev);
        $trend = $this->dash->trend($start, $end);
        $payments = $this->dash->paymentMix($start, $end);
        $topProducts = $this->dash->topProducts($start, $end);
        $categories = $this->dash->categories($start, $end);
        $cashiers = $this->dash->cashiers($start, $end);
        $canProfit = $user->can('View profit figures');

        if ($request->wantsJson()) {
            return response()->json(compact('kpi', 'change', 'trend', 'payments', 'topProducts', 'categories', 'cashiers') + [
                'updated' => now()->format('h:i A'),
            ]);
        }

        $inventory = $this->dash->inventory();
        $recent = $this->dash->recentSales();
        $security = $this->dash->security();
        $periods = $this->dash->periods;
        $pagetitle = 'Dashboard';

        return view('dashboard', compact('pagetitle', 'period', 'periods', 'start', 'end', 'prevStart', 'prevEnd', 'kpi', 'prev',
            'change', 'trend', 'payments', 'topProducts', 'categories', 'cashiers', 'inventory', 'recent', 'security', 'canProfit'));
    }

    /** CSV of daily sales for the selected period. */
    public function export(Request $request)
    {
        [$start, $end] = $this->dash->range($request->get('period', '30d'), $request->get('from'), $request->get('to'));
        $trend = $this->dash->trend($start->copy()->startOfDay(), $end->copy()->endOfDay()->max($start->copy()->addDay()->endOfDay()));
        $canProfit = $request->user()->can('View profit figures');

        return response()->streamDownload(function () use ($trend, $canProfit) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_filter(['Period', 'Sales', 'Revenue', $canProfit ? 'Gross profit' : null]));
            foreach ($trend['labels'] as $i => $label) {
                fputcsv($out, array_filter([$label, $trend['orders'][$i], $trend['revenue'][$i], $canProfit ? $trend['profit'][$i] : null], fn ($v) => $v !== null));
            }
            fclose($out);
        }, 'sales_summary_' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }
}

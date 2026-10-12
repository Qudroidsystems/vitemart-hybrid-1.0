<?php

namespace App\Http\Controllers\MultiBranch;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Services\MultiBranch\Install;
use App\Services\Reports\HqDashboardService;
use Illuminate\Http\Request;

/** Head office: all branches side by side, every figure labelled with how fresh it is. */
class HqDashboardController extends Controller
{
    public const PERIODS = ['today' => 'Today', 'yesterday' => 'Yesterday', '7d' => 'Last 7 days', '30d' => 'Last 30 days',
        'month' => 'This month', 'last_month' => 'Last month', 'year' => 'This year', 'custom' => 'Custom'];

    public function __construct(protected HqDashboardService $hq)
    {
        $this->middleware('permission:View branches|Manage branches|Access all branches');
    }

    protected function inputs(Request $request): array
    {
        $period = array_key_exists($request->get('period'), self::PERIODS) ? $request->get('period') : 'today';
        [$start, $end] = app(DashboardService::class)->range($period, $request->get('from'), $request->get('to'));
        $ids = array_values(array_filter(array_map('intval', (array) $request->get('branches', []))));
        $branches = $this->hq->branches($ids ?: null)
            ->filter(fn ($b) => $b->type !== 'warehouse' || $ids); // warehouses only when picked
        return [$period, $start, $end, $branches->values(), $ids];
    }

    public function index(Request $request)
    {
        if (!Install::isCentral()) {
            return redirect()->route('dashboard');
        }
        [$period, $start, $end, $branches, $ids] = $this->inputs($request);
        $rows = $this->hq->comparison($start, $end, $branches);
        $data = [
            'pagetitle' => 'All branches',
            'period' => $period, 'periods' => self::PERIODS, 'start' => $start, 'end' => $end,
            'allBranches' => $this->hq->branches(), 'picked' => $ids,
            'rows' => $rows,
            'totals' => $this->hq->totals($rows),
            'trend' => $this->hq->trend($start, $end, $branches),
            'top' => $this->hq->topProducts($start, $end, $branches),
            'mix' => $this->hq->paymentMix($start, $end, $branches),
            'branches' => $branches,
        ];
        return view('multibranch.hq-dashboard', $data);
    }

    public function export(Request $request)
    {
        abort_unless(Install::isCentral(), 404);
        [$period, $start, $end, $branches] = $this->inputs($request);
        $rows = $this->hq->comparison($start, $end, $branches);
        $name = 'branches-' . $start->format('Ymd') . '-' . $end->format('Ymd') . '.csv';
        return response()->streamDownload(function () use ($rows, $start, $end) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['From', $start->toDateTimeString(), 'To', $end->toDateTimeString()]);
            fputcsv($out, ['Branch', 'Data up to', 'Freshness', 'Sales', 'Revenue', 'Avg basket', 'Gross profit', 'Margin %', 'Discounts', 'Refunds',
                'Voids', 'Stock value', 'Low stock', 'Out of stock', 'Transfers in', 'Transfers out', 'Transfer differences']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['branch']->code . ' ' . $r['branch']->name, $r['fresh']['as_of']?->toDateTimeString() ?? 'never', $r['fresh']['label'],
                    $r['orders'], $r['revenue'], $r['basket'], $r['profit'], $r['margin'], $r['discounts'], $r['refunds'], $r['voids'],
                    $r['stock_value'], $r['low_stock'], $r['out_of_stock'], $r['transfers_in'], $r['transfers_out'], $r['transfer_issues']]);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }
}

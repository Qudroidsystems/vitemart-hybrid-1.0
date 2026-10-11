<?php

namespace App\Exports\SalesReport;

use App\Services\Reports\SalesReportService;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * The spreadsheet attached to sales-report emails.
 * Sheets: Summary · Cashiers · Transactions · Items sold · Refunds & voids · Shifts
 */
class SalesReportExport implements WithMultipleSheets
{
    public function __construct(protected array $report, protected SalesReportService $service)
    {
    }

    public static function rowsCsv(array $report, SalesReportService $service): string
    {
        $methods = $report['method_columns'];
        $labels = $report['method_labels'];
        $h = fopen('php://temp', 'r+');
        fwrite($h, "\xEF\xBB\xBF"); // so Excel reads ₦ and accents correctly
        fputcsv($h, self::txHeadings($methods, $labels));
        foreach ($service->transactionRows() as $t) {
            fputcsv($h, self::txRow($t, $methods));
        }
        rewind($h);
        $csv = stream_get_contents($h);
        fclose($h);
        return $csv;
    }

    public function sheets(): array
    {
        $r = $this->report;
        $s = $r['summary'];
        $methods = $r['method_columns'];
        $labels = $r['method_labels'];
        $money = fn ($v) => round((float) $v, 2);

        $summary = [
            ['Store', $r['store']],
            ['Period', $r['from']->format('d M Y H:i') . ' – ' . $r['to']->format('d M Y H:i')],
        ];
        if ($r['branch_label']) {
            $summary[] = ['Branch', $r['branch_label']];
        }
        $summary = array_merge($summary, [
            ['Generated', $r['generated_at']->format('d M Y H:i')],
            ['', ''],
            ['Transactions', $s['transactions']],
            ['Gross sales (before discounts)', $money($s['gross'])],
            ['Discounts', $money($s['discounts'])],
            ['Net sales', $money($s['net'])],
            ['Refunds (' . $s['refund_count'] . ')', $money($s['refunds'])],
            ['Net after refunds', $money($s['net_after_refunds'])],
            ['Voided sales (' . $s['voids'] . ')', $money($s['void_value'])],
            ['Average sale', $money($s['average'])],
            ['Items sold', $s['items']],
        ]);
        if (!empty($r['options']['include_profit'])) {
            $summary[] = ['Cost of goods', $money($s['cost'])];
            $summary[] = ['Gross profit', $money($s['profit'])];
            $summary[] = ['Margin %', $s['margin']];
        }
        $summary[] = ['', ''];
        $summary[] = ['Payment method', 'Amount'];
        foreach ($r['methods'] as $m) {
            $summary[] = [$m['label'] . ($m['pending'] > 0 ? ' (awaiting confirmation: ' . number_format($m['pending'], 2) . ')' : ''), $money($m['amount'])];
        }
        foreach ($r['branches'] as $b) {
            $summary[] = ['Branch ' . $b['name'], $money($b['net'])];
        }

        $cashierHead = array_merge(['Cashier', 'Transactions', 'Net sales', 'Average sale', 'Discounts'],
            array_map(fn ($m) => $labels[$m] ?? ucfirst($m), $methods), ['Refunds', 'Refund value', 'Voids', 'Void value', 'First sale', 'Last sale']);
        if (!empty($r['options']['include_profit'])) {
            $cashierHead[] = 'Gross profit';
        }
        $cashiers = array_map(function ($c) use ($methods, $money, $r) {
            $row = [$c['name'], $c['transactions'], $money($c['net']), $money($c['average']), $money($c['discounts'])];
            foreach ($methods as $m) {
                $row[] = $money($c['methods'][$m] ?? 0);
            }
            array_push($row, $c['refund_count'], $money($c['refunds']), $c['voids'], $money($c['void_value']),
                $c['first_at']?->format('d M H:i') ?? '', $c['last_at']?->format('d M H:i') ?? '');
            if (!empty($r['options']['include_profit'])) {
                $row[] = $money($c['profit']);
            }
            return $row;
        }, $r['cashiers']);
        $totalRow = ['TOTAL', $s['transactions'], $money($s['net']), $money($s['average']), $money($s['discounts'])];
        foreach ($methods as $m) {
            $totalRow[] = $money(collect($r['methods'])->firstWhere('method', $m)['amount'] ?? 0);
        }
        array_push($totalRow, $s['refund_count'], $money($s['refunds']), $s['voids'], $money($s['void_value']), '', '');
        if (!empty($r['options']['include_profit'])) {
            $totalRow[] = $money($s['profit']);
        }
        $cashiers[] = $totalRow;

        $tx = [];
        foreach ($this->service->transactionRows() as $t) {
            $tx[] = self::txRow($t, $methods);
        }

        $items = array_map(fn ($p) => [$p['title'], $p['qty'], $money($p['revenue']), $p['orders']], $this->service->products(null));

        $rv = [];
        foreach ($this->service->refundList(null) as $x) {
            $rv[] = ['Refund', $x['order_id'], $x['at']->format('d M Y H:i'), $x['by'], $money($x['amount']), $x['reason']];
        }
        foreach ($this->service->voidList(null) as $x) {
            $rv[] = ['Void', $x['order_id'], $x['at']->format('d M Y H:i'), $x['cashier'] . ($x['voided_by'] ? ' (approved by ' . $x['voided_by'] . ')' : ''), $money($x['amount']), $x['reason']];
        }

        $sheets = [
            new Sheet('Summary', ['Item', 'Value'], $summary),
            new Sheet('Cashiers', $cashierHead, $cashiers),
            new Sheet('Transactions', self::txHeadings($methods, $labels), $tx),
            new Sheet('Items sold', ['Product', 'Quantity', 'Revenue', 'Sales'], $items),
            new Sheet('Refunds & voids', ['Type', 'Receipt', 'Time', 'Cashier', 'Amount', 'Reason'], $rv),
        ];
        if ($r['shifts']) {
            $sheets[] = new Sheet('Shifts', ['Cashier', 'Till', 'Opened', 'Closed', 'Opening float', 'Expected cash', 'Counted cash', 'Over / short'],
                array_map(fn ($h) => [$h['cashier'], $h['register'], $h['opened_at']->format('d M Y H:i'), $h['closed_at']?->format('d M Y H:i') ?? 'still open',
                    $money($h['opening_float']), $h['expected'] === null ? '' : $money($h['expected']), $h['counted'] === null ? '' : $money($h['counted']),
                    $h['variance'] === null ? '' : $money($h['variance'])], $r['shifts']));
        }
        return $sheets;
    }

    protected static function txHeadings(array $methods, array $labels): array
    {
        return array_merge(['Receipt', 'Date', 'Time', 'Cashier', 'Till', 'Branch', 'Customer', 'Lines', 'Subtotal', 'Discount', 'Total'],
            array_map(fn ($m) => $labels[$m] ?? ucfirst($m), $methods), ['Change given', 'Status']);
    }

    protected static function txRow(array $t, array $methods): array
    {
        $row = [$t['id'], $t['at']->format('Y-m-d'), $t['at']->format('H:i:s'), $t['cashier'], $t['register'], $t['branch'], $t['customer'],
            $t['lines'], round($t['subtotal'], 2), round($t['discount'], 2), round($t['total'], 2)];
        foreach ($methods as $m) {
            $row[] = round($t['methods'][$m] ?? 0, 2);
        }
        $row[] = round($t['change'], 2);
        $row[] = $t['status'] === 'cancelled' ? 'VOIDED' : ($t['pending'] ? $t['status'] . ' (payment awaiting confirmation)' : $t['status']);
        return $row;
    }
}

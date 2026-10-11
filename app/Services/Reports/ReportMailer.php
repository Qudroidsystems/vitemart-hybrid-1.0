<?php

namespace App\Services\Reports;

use App\Exports\SalesReport\SalesReportExport;
use App\Mail\SalesReportMail;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Mail;

/** Builds a sales report and emails it (with the spreadsheet) to a list of addresses. */
class ReportMailer
{
    /** Above this many transactions the attachment is a CSV (a big .xlsx would use too much memory). */
    public const XLSX_LIMIT = 5000;

    public function __construct(protected SalesReportService $reports)
    {
    }

    /**
     * @return array{status:string,message:string,report:array}
     */
    public function send(array $to, CarbonInterface $from, CarbonInterface $until, array $options = [], ?string $title = null, bool $skipIfEmpty = false): array
    {
        @set_time_limit(300);
        $report = $this->reports->build($from, $until, $options);
        $report['title'] = $title ?: 'Sales report';
        $s = $report['summary'];

        if ($skipIfEmpty && $s['transactions'] + $s['voids'] + $s['refund_count'] === 0) {
            return ['status' => 'skipped', 'message' => 'No sales in ' . self::periodLabel($report['from'], $report['to']) . ' – nothing sent.', 'report' => $report];
        }

        $attachment = ($options['include_transactions'] ?? true) ? $this->attachment($report) : null;
        Mail::to(array_values(array_unique($to)))->send(new SalesReportMail($report, $attachment));

        return [
            'status' => 'sent',
            'message' => 'Sent to ' . implode(', ', $to) . ' – ' . $s['transactions'] . ' sale(s), ' . money($s['net_after_refunds']) . '.',
            'report' => $report,
        ];
    }

    /** @return array{name:string,data:string,mime:string} */
    public function attachment(array $report): array
    {
        $base = 'sales-' . $report['from']->format('Y-m-d') . ($report['from']->isSameDay($report['to']) ? '' : '-to-' . $report['to']->format('Y-m-d'));
        $count = $report['summary']['transactions'] + $report['summary']['voids'];
        // The spreadsheet re-reads the rows, so point the builder at this report's period and options.
        $service = $this->reports->prepare($report['from'], $report['to'], $report['options']);

        if ($count <= self::XLSX_LIMIT && class_exists(\Maatwebsite\Excel\Facades\Excel::class)) {
            try {
                $data = \Maatwebsite\Excel\Facades\Excel::raw(new SalesReportExport($report, $service), \Maatwebsite\Excel\Excel::XLSX);
                return ['name' => $base . '.xlsx', 'data' => $data, 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
            } catch (\Throwable $e) {
                report($e);
            }
        }
        return ['name' => $base . '-transactions.csv', 'data' => SalesReportExport::rowsCsv($report, $service), 'mime' => 'text/csv'];
    }

    public static function periodLabel(CarbonInterface $from, CarbonInterface $to): string
    {
        $from = Carbon::instance($from);
        $to = Carbon::instance($to);
        $wholeDay = $from->eq($from->copy()->startOfDay()) && $to->format('H:i') === '23:59';
        if ($from->isSameDay($to)) {
            return $from->format('D j M Y') . ($wholeDay ? '' : ', ' . $from->format('H:i') . ' – ' . $to->format('H:i'));
        }
        if ($from->isSameMonth($to) && $from->day === 1 && $to->copy()->addSecond()->day === 1) {
            return $from->format('F Y');
        }
        $sameYear = $from->year === $to->year;
        return $from->format($sameYear ? 'D j M' : 'D j M Y') . ($wholeDay || $from->format('H:i') === '00:00' ? '' : ' ' . $from->format('H:i'))
            . ' – ' . $to->format('D j M Y') . ($to->format('H:i') === '23:59' ? '' : ' ' . $to->format('H:i'));
    }
}

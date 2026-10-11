@php
    $s = $r['summary'];
    $o = $r['options'];
    $cols = $r['method_columns'];
    $labels = $r['method_labels'];
    $short = ['cash' => 'Cash', 'card' => 'Card', 'transfer' => 'Transfer', 'credit' => 'Credit', 'points' => 'Points'];
    $th = 'padding:8px 10px;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#64748b;text-align:right;border-bottom:2px solid #e2e8f0;white-space:nowrap;';
    $thl = $th . 'text-align:left;';
    $td = 'padding:8px 10px;font-size:13px;color:#0f172a;text-align:right;border-bottom:1px solid #eef2f7;white-space:nowrap;';
    $tdl = $td . 'text-align:left;white-space:normal;';
    $h2 = 'margin:26px 0 10px;font-size:15px;color:#0f172a;font-weight:800;';
    $pending = collect($r['methods'])->sum('pending');
    $credit = collect($r['methods'])->firstWhere('method', 'credit')['amount'] ?? 0;
    $txByCashier = collect($r['transactions'])->groupBy('cashier');
    $appUrl = rtrim((string) config('app.url'), '/');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $r['title'] ?? 'Sales report' }}</title>
<style>
    @media (max-width: 600px) {
        .vm-pad { padding-left: 14px !important; padding-right: 14px !important; }
        .vm-kpi { display: block !important; width: 100% !important; padding: 0 0 10px 0 !important; }
    }
</style>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#0f172a;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;">
<tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:720px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0;table-layout:fixed;">

    {{-- header --}}
    <tr><td style="background:#0f766e;background:linear-gradient(135deg,#0f766e,#115e59);padding:24px 28px;color:#ffffff;">
        <div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;opacity:.8;">{{ $r['store'] }}{{ $r['branch_label'] ? ' · ' . $r['branch_label'] : '' }}</div>
        <div style="font-size:22px;font-weight:800;margin-top:4px;">{{ $r['title'] ?? 'Sales report' }}</div>
        <div style="font-size:14px;margin-top:4px;opacity:.9;">{{ $period }}</div>
    </td></tr>

    <tr><td class="vm-pad" style="padding:22px 28px 6px;">

        {{-- headline numbers --}}
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td class="vm-kpi" width="50%" style="padding:0 6px 12px 0;vertical-align:top;">
                    <div style="background:#ecfdf5;border:1px solid #bbf7d0;border-radius:12px;padding:14px 16px;">
                        <div style="font-size:12px;color:#047857;font-weight:700;">TOTAL SALES (after refunds)</div>
                        <div style="font-size:26px;font-weight:800;color:#065f46;margin-top:2px;">{{ money($s['net_after_refunds']) }}</div>
                    </div>
                </td>
                <td class="vm-kpi" width="50%" style="padding:0 0 12px 6px;vertical-align:top;">
                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;">
                        <div style="font-size:12px;color:#64748b;font-weight:700;">TRANSACTIONS</div>
                        <div style="font-size:26px;font-weight:800;margin-top:2px;">{{ number_format($s['transactions']) }}</div>
                    </div>
                </td>
            </tr>
        </table>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:12px;">
            @php
                $lines = [
                    ['Gross sales (before discounts)', money($s['gross'])],
                    ['Discounts given', '−' . money($s['discounts'])],
                    ['Net sales', money($s['net'])],
                    ['Refunds (' . $s['refund_count'] . ')', '−' . money($s['refunds'])],
                    ['Total after refunds', money($s['net_after_refunds']), true],
                    ['Average sale', money($s['average'])],
                    ['Items sold', qty($s['items'])],
                    ['Voided sales (' . $s['voids'] . ')', money($s['void_value'])],
                ];
                if (!empty($o['include_profit'])) {
                    $lines[] = ['Cost of goods', money($s['cost'])];
                    $lines[] = ['Gross profit (' . $s['margin'] . '%)', money($s['profit']), true];
                }
                if ($s['first_at']) {
                    $lines[] = ['First / last sale', $s['first_at']->format('H:i') . ' / ' . $s['last_at']->format('H:i')];
                }
            @endphp
            @foreach ($lines as $i => $l)
                <tr>
                    <td style="padding:9px 14px;font-size:13.5px;color:{{ !empty($l[2]) ? '#0f172a' : '#475569' }};font-weight:{{ !empty($l[2]) ? '800' : '400' }};{{ $i ? 'border-top:1px solid #eef2f7;' : '' }}">{{ $l[0] }}</td>
                    <td style="padding:9px 14px;font-size:13.5px;text-align:right;font-weight:{{ !empty($l[2]) ? '800' : '600' }};{{ $i ? 'border-top:1px solid #eef2f7;' : '' }}">{{ $l[1] }}</td>
                </tr>
            @endforeach
        </table>

        @if ($pending > 0 || $credit > 0)
            <div style="margin-top:12px;background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:10px 14px;font-size:13px;color:#9a3412;">
                @if ($pending > 0)<div><strong>{{ money($pending) }}</strong> in transfers is still waiting for a supervisor to confirm it arrived.</div>@endif
                @if ($credit > 0)<div><strong>{{ money($credit) }}</strong> was sold on customer credit (not yet paid).</div>@endif
            </div>
        @endif

        {{-- payment methods --}}
        <h2 style="{{ $h2 }}">Payments received</h2>
        @if ($r['methods'])
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr><th style="{{ $thl }}">Method</th><th style="{{ $th }}">Sales</th><th style="{{ $th }}">Amount</th></tr>
                @foreach ($r['methods'] as $m)
                    <tr><td style="{{ $tdl }}">{{ $m['label'] }}@if ($m['pending'] > 0) <span style="color:#c2410c;font-size:12px;">({{ money($m['pending']) }} unconfirmed)</span>@endif</td>
                        <td style="{{ $td }}">{{ $m['count'] }}</td><td style="{{ $td }}font-weight:700;">{{ money($m['amount']) }}</td></tr>
                @endforeach
                <tr><td style="{{ $tdl }}font-weight:800;border-bottom:0;">Total</td><td style="{{ $td }}border-bottom:0;"></td><td style="{{ $td }}font-weight:800;border-bottom:0;">{{ money(collect($r['methods'])->sum('amount')) }}</td></tr>
            </table>
            <div style="font-size:11.5px;color:#94a3b8;margin-top:4px;">Cash is shown after change given.</div>
        @else
            <p style="font-size:13px;color:#64748b;margin:0;">No payments in this period.</p>
        @endif

        {{-- cashiers --}}
        <h2 style="{{ $h2 }}">By cashier</h2>
        @if ($r['cashiers'])
            <div style="overflow-x:auto;-webkit-overflow-scrolling:touch;max-width:100%;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="min-width:520px;">
                <tr>
                    <th style="{{ $thl }}">Cashier</th><th style="{{ $th }}">Sales</th><th style="{{ $th }}">Total</th>
                    @foreach ($cols as $m)<th style="{{ $th }}">{{ $short[$m] ?? ucfirst($m) }}</th>@endforeach
                    <th style="{{ $th }}">Refunds</th><th style="{{ $th }}">Voids</th>
                </tr>
                @foreach ($r['cashiers'] as $c)
                    <tr>
                        <td style="{{ $tdl }}"><strong>{{ $c['name'] }}</strong>@if ($c['first_at'])<div style="font-size:11.5px;color:#94a3b8;">{{ $c['first_at']->format('H:i') }}–{{ $c['last_at']->format('H:i') }} · avg {{ money($c['average']) }}</div>@endif</td>
                        <td style="{{ $td }}">{{ $c['transactions'] }}</td>
                        <td style="{{ $td }}font-weight:800;">{{ money($c['net']) }}</td>
                        @foreach ($cols as $m)<td style="{{ $td }}">{{ isset($c['methods'][$m]) ? money($c['methods'][$m]) : '–' }}</td>@endforeach
                        <td style="{{ $td }}{{ $c['refunds'] > 0 ? 'color:#b45309;' : '' }}">{{ $c['refund_count'] ? money($c['refunds']) : '–' }}</td>
                        <td style="{{ $td }}{{ $c['voids'] > 0 ? 'color:#b91c1c;' : '' }}">{{ $c['voids'] ? $c['voids'] . ' · ' . money($c['void_value']) : '–' }}</td>
                    </tr>
                @endforeach
                <tr style="background:#f8fafc;">
                    <td style="{{ $tdl }}font-weight:800;">All cashiers</td>
                    <td style="{{ $td }}font-weight:800;">{{ $s['transactions'] }}</td>
                    <td style="{{ $td }}font-weight:800;">{{ money($s['net']) }}</td>
                    @foreach ($cols as $m)<td style="{{ $td }}font-weight:700;">{{ money(collect($r['methods'])->firstWhere('method', $m)['amount'] ?? 0) }}</td>@endforeach
                    <td style="{{ $td }}font-weight:700;">{{ $s['refund_count'] ? money($s['refunds']) : '–' }}</td>
                    <td style="{{ $td }}font-weight:700;">{{ $s['voids'] ? $s['voids'] . ' · ' . money($s['void_value']) : '–' }}</td>
                </tr>
            </table>
            </div>
        @else
            <p style="font-size:13px;color:#64748b;margin:0;">No cashier activity in this period.</p>
        @endif

        {{-- shifts --}}
        @if ($r['shifts'])
            <h2 style="{{ $h2 }}">Shifts &amp; cash count</h2>
            <div style="overflow-x:auto;-webkit-overflow-scrolling:touch;max-width:100%;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="min-width:520px;">
                <tr><th style="{{ $thl }}">Cashier</th><th style="{{ $thl }}">Time</th><th style="{{ $th }}">Expected</th><th style="{{ $th }}">Counted</th><th style="{{ $th }}">Over / short</th></tr>
                @foreach ($r['shifts'] as $h)
                    @php $v = $h['variance']; @endphp
                    <tr>
                        <td style="{{ $tdl }}"><strong>{{ $h['cashier'] }}</strong>@if ($h['register'])<div style="font-size:11.5px;color:#94a3b8;">{{ $h['register'] }}</div>@endif</td>
                        <td style="{{ $tdl }}font-size:12.5px;">{{ $h['opened_at']->format('D H:i') }} – {{ $h['closed_at'] ? $h['closed_at']->format('H:i') : 'still open' }}</td>
                        <td style="{{ $td }}">{{ $h['expected'] === null ? '–' : money($h['expected']) }}</td>
                        <td style="{{ $td }}">{{ $h['counted'] === null ? '–' : money($h['counted']) }}</td>
                        <td style="{{ $td }}font-weight:700;color:{{ $v === null ? '#94a3b8' : (abs($v) < 0.01 ? '#047857' : ($v < 0 ? '#b91c1c' : '#b45309')) }};">
                            {{ $v === null ? '–' : (abs($v) < 0.01 ? 'Balanced' : ($v < 0 ? 'Short ' . money(abs($v)) : 'Over ' . money($v))) }}</td>
                    </tr>
                @endforeach
            </table>
            </div>
        @endif

        {{-- branches --}}
        @if ($r['branches'])
            <h2 style="{{ $h2 }}">By branch</h2>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr><th style="{{ $thl }}">Branch</th><th style="{{ $th }}">Sales</th><th style="{{ $th }}">Total</th></tr>
                @foreach ($r['branches'] as $b)
                    <tr><td style="{{ $tdl }}">{{ $b['name'] }}</td><td style="{{ $td }}">{{ $b['transactions'] }}</td><td style="{{ $td }}font-weight:700;">{{ money($b['net']) }}</td></tr>
                @endforeach
            </table>
        @endif

        {{-- best sellers --}}
        @if ($r['products'])
            <h2 style="{{ $h2 }}">Best sellers</h2>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr><th style="{{ $thl }}">Product</th><th style="{{ $th }}">Qty</th><th style="{{ $th }}">Revenue</th></tr>
                @foreach ($r['products'] as $i => $p)
                    <tr><td style="{{ $tdl }}"><span style="color:#94a3b8;">{{ $i + 1 }}.</span> {{ $p['title'] }}</td><td style="{{ $td }}">{{ qty($p['qty']) }}</td><td style="{{ $td }}font-weight:700;">{{ money($p['revenue']) }}</td></tr>
                @endforeach
            </table>
        @endif

        {{-- refunds and voids --}}
        @if ($r['refund_list'] || $r['void_list'])
            <h2 style="{{ $h2 }}">Refunds &amp; voids</h2>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr><th style="{{ $thl }}">Receipt</th><th style="{{ $thl }}">By / reason</th><th style="{{ $th }}">Amount</th></tr>
                @foreach ($r['refund_list'] as $x)
                    <tr><td style="{{ $tdl }}"><span style="background:#fef3c7;color:#92400e;border-radius:6px;padding:1px 6px;font-size:11px;font-weight:700;">REFUND</span><br>{{ $x['order_id'] }} · {{ $x['at']->format('H:i') }}</td>
                        <td style="{{ $tdl }}font-size:12.5px;">{{ $x['by'] }}<div style="color:#64748b;">{{ \Illuminate\Support\Str::limit($x['reason'], 90) }}</div></td>
                        <td style="{{ $td }}font-weight:700;color:#b45309;">−{{ money($x['amount']) }}</td></tr>
                @endforeach
                @foreach ($r['void_list'] as $x)
                    <tr><td style="{{ $tdl }}"><span style="background:#fee2e2;color:#991b1b;border-radius:6px;padding:1px 6px;font-size:11px;font-weight:700;">VOID</span><br>{{ $x['order_id'] }} · {{ $x['at']->format('H:i') }}</td>
                        <td style="{{ $tdl }}font-size:12.5px;">{{ $x['cashier'] }}{{ $x['voided_by'] ? ' · approved by ' . $x['voided_by'] : '' }}<div style="color:#64748b;">{{ \Illuminate\Support\Str::limit($x['reason'], 90) }}</div></td>
                        <td style="{{ $td }}font-weight:700;color:#b91c1c;">{{ money($x['amount']) }}</td></tr>
                @endforeach
            </table>
        @endif

        {{-- every transaction, grouped by cashier --}}
        @if (!empty($o['include_transactions']))
            <h2 style="{{ $h2 }}">All transactions</h2>
            @if ($r['transactions_truncated'])
                <p style="font-size:13px;color:#475569;margin:0;">{{ number_format($s['transactions'] + $s['voids']) }} transactions – too many to list here. Every one is in the attached spreadsheet{{ $fileName ? ' (' . $fileName . ')' : '' }}, with the cashier, time, items and payment for each.</p>
            @elseif ($txByCashier->isEmpty())
                <p style="font-size:13px;color:#64748b;margin:0;">No transactions in this period.</p>
            @else
                @foreach ($txByCashier as $cashier => $rows)
                    <div style="margin:14px 0 6px;font-size:13px;font-weight:800;">{{ $cashier }} <span style="color:#94a3b8;font-weight:600;">· {{ $rows->where('status', '!=', 'cancelled')->count() }} sale(s) · {{ money($rows->where('status', '!=', 'cancelled')->sum('total')) }}</span></div>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                        @foreach ($rows as $t)
                            @php $void = $t['status'] === 'cancelled'; @endphp
                            <tr>
                                <td style="padding:6px 10px;font-size:12.5px;border-bottom:1px solid #f1f5f9;color:#64748b;white-space:nowrap;width:44px;">{{ $t['at']->format('H:i') }}</td>
                                <td style="padding:6px 10px;font-size:12.5px;border-bottom:1px solid #f1f5f9;{{ $void ? 'text-decoration:line-through;color:#94a3b8;' : '' }}">{{ $t['id'] }}<span style="color:#94a3b8;"> · {{ $t['lines'] }} item{{ $t['lines'] === 1 ? '' : 's' }}{{ $t['customer'] ? ' · ' . $t['customer'] : '' }}</span>
                                    <div style="font-size:11.5px;color:#94a3b8;">{{ collect($t['methods'])->map(fn ($a, $m) => ($short[$m] ?? ucfirst($m)) . ' ' . money($a))->implode(' + ') ?: '—' }}{{ $t['pending'] ? ' · awaiting confirmation' : '' }}{{ $void ? ' · VOIDED' : '' }}</div></td>
                                <td style="padding:6px 10px;font-size:13px;border-bottom:1px solid #f1f5f9;text-align:right;font-weight:700;white-space:nowrap;{{ $void ? 'text-decoration:line-through;color:#94a3b8;' : '' }}">{{ money($t['total']) }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endforeach
            @endif
        @endif

        @if ($fileName)
            <div style="margin-top:22px;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:10px;padding:12px 14px;font-size:13px;color:#334155;">
                📎 <strong>{{ $fileName }}</strong> is attached with every transaction, cashier totals, items sold, refunds, voids and shifts – open it in Excel or Google Sheets.
            </div>
        @endif
    </td></tr>

    <tr><td style="padding:18px 28px 24px;font-size:11.5px;color:#94a3b8;border-top:1px solid #eef2f7;">
        Generated {{ $r['generated_at']->format('D j M Y, H:i') }} by ViteMart{{ $appUrl ? ' · ' : '' }}@if ($appUrl)<a href="{{ $appUrl }}" style="color:#0f766e;">{{ $appUrl }}</a>@endif.
        {{ ($o['channels'] ?? 'pos') === 'all' ? 'Includes till and online orders.' : 'Till (POS) sales only.' }}
        Change who gets this report, or when, under Sales Report Emails.
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>

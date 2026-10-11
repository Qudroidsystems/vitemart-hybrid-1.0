<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $shift->status === 'open' ? 'X' : 'Z' }}-report #{{ $shift->id }}</title>
    <style>
        body { font-family: 'Courier New', monospace; font-size: 13px; margin: 0; background: #f1f1f1; }
        .paper { width: 80mm; margin: 12px auto; background: #fff; padding: 10px 12px; }
        h2, h3 { text-align: center; margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 0; vertical-align: top; }
        .r { text-align: right; }
        .hr { border-top: 1px dashed #000; margin: 6px 0; }
        .big { font-size: 15px; font-weight: bold; }
        .actions { text-align: center; margin: 10px; }
        @media print { body { background: #fff; } .paper { margin: 0; } .actions { display: none; } }
    </style>
</head>
<body>
<div class="actions"><button onclick="window.print()">Print</button></div>
<div class="paper">
    <h2>{{ $store->store_name ?? 'Store' }}</h2>
    <h3>{{ $shift->status === 'open' ? 'X-REPORT (shift still open)' : 'Z-REPORT' }}</h3>
    <div class="hr"></div>
    <table>
        <tr><td>Shift</td><td class="r">#{{ $shift->id }}</td></tr>
        <tr><td>Cashier</td><td class="r">{{ $shift->user->first_name ?? '' }} {{ $shift->user->last_name ?? '' }}</td></tr>
        <tr><td>Opened</td><td class="r">{{ $shift->opened_at->format('d/m/Y H:i') }}</td></tr>
        <tr><td>Closed</td><td class="r">{{ $shift->closed_at?->format('d/m/Y H:i') ?? '—' }}</td></tr>
    </table>
    <div class="hr"></div>
    <table>
        <tr><td>Sales</td><td class="r">{{ $summary['orders_count'] }}</td></tr>
        <tr><td>Gross sales</td><td class="r">{{ money($summary['gross_sales']) }}</td></tr>
        <tr><td>Discounts</td><td class="r">{{ money($summary['discounts']) }}</td></tr>
        <tr><td>Voided sales</td><td class="r">{{ $summary['voided_count'] }}</td></tr>
        <tr><td>Refunds</td><td class="r">-{{ money($summary['refunds']) }}</td></tr>
    </table>
    <div class="hr"></div>
    <strong>Payments</strong>
    <table>
        @forelse ($summary['by_method'] as $m => $v)
            <tr><td>{{ strtoupper($m) }} ({{ $v['count'] }})</td><td class="r">{{ money($v['total']) }}</td></tr>
        @empty
            <tr><td colspan="2">No payments</td></tr>
        @endforelse
    </table>
    <div class="hr"></div>
    <strong>Cash drawer</strong>
    <table>
        <tr><td>Opening float</td><td class="r">{{ money($shift->opening_float) }}</td></tr>
        <tr><td>+ Cash sales</td><td class="r">{{ money($summary['cash_sales']) }}</td></tr>
        <tr><td>+ Cash in</td><td class="r">{{ money($summary['cash_in']) }}</td></tr>
        <tr><td>- Cash out</td><td class="r">{{ money($summary['cash_out']) }}</td></tr>
        <tr><td>- Refunds</td><td class="r">{{ money($summary['refunds']) }}</td></tr>
        <tr class="big"><td>Expected</td><td class="r">{{ money($summary['expected_cash']) }}</td></tr>
        @if ($shift->counted_cash !== null)
            <tr class="big"><td>Counted</td><td class="r">{{ money($shift->counted_cash) }}</td></tr>
            <tr class="big"><td>Difference</td><td class="r">{{ (float) $shift->variance > 0 ? '+' : '' }}{{ money($shift->variance) }}</td></tr>
        @endif
    </table>
    @if ($shift->cashMovements->isNotEmpty())
        <div class="hr"></div>
        <strong>Cash in / out</strong>
        <table>
            @foreach ($shift->cashMovements as $c)
                <tr><td>{{ $c->created_at->format('H:i') }} {{ $c->type === 'in' ? 'IN' : 'OUT' }} {{ $c->reason }}</td><td class="r">{{ money($c->amount) }}</td></tr>
            @endforeach
        </table>
    @endif
    @if ($topItems->isNotEmpty())
        <div class="hr"></div>
        <strong>Top items</strong>
        <table>
            @foreach ($topItems as $t)
                <tr><td>{{ \Illuminate\Support\Str::limit($t->title, 22) }} x{{ qty($t->qty) }}</td><td class="r">{{ money($t->total) }}</td></tr>
            @endforeach
        </table>
    @endif
    @if ($shift->notes)<div class="hr"></div><div>Notes: {{ $shift->notes }}</div>@endif
    <div class="hr"></div>
    <div style="text-align:center">Printed {{ now()->format('d/m/Y H:i') }}<br><br>Cashier sign: ____________<br><br>Supervisor sign: __________</div>
</div>
</body>
</html>

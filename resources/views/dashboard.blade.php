@extends('layouts.master')

@section('content')
@php
    $arrow = function ($pct, $invert = false) {
        if ($pct === null) return '<span class="text-muted">new</span>';
        if ($pct == 0) return '<span class="text-muted">no change</span>';
        $good = $invert ? $pct < 0 : $pct > 0;
        return '<span class="' . ($good ? 'text-success' : 'text-danger') . '"><i class="bi bi-arrow-' . ($pct > 0 ? 'up' : 'down') . '-short"></i>' . abs($pct) . '%</span>';
    };
    $rangeLabel = $start->isSameDay($end) ? $start->format('D, d M Y') : $start->format('d M') . ' – ' . $end->format('d M Y');
@endphp
<style>
    .viz-root { --series-1: #2a78d6; --series-3: #1baf7a; --grid: #e8eaee; --ink-2: #52514e; }
    [data-bs-theme="dark"] .viz-root { --series-1: #3987e5; --series-3: #199e70; --grid: #2e3138; --ink-2: #c3c2b7; }
    .kpi .val { font-size: 1.6rem; font-weight: 700; line-height: 1.15; }
    .kpi .lbl { font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; color: var(--tb-secondary-color, #6c757d); }
    .kpi .cmp { font-size: .8rem; }
    .bar-row { display: grid; grid-template-columns: minmax(90px, 38%) 1fr auto; gap: 8px; align-items: center; padding: 5px 0; }
    .bar-row .track { height: 10px; background: var(--grid); border-radius: 4px; overflow: hidden; }
    .bar-row .fill { height: 100%; background: var(--series-1); border-radius: 0 4px 4px 0; }
    .rank { width: 22px; height: 22px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: .75rem; font-weight: 700; background: var(--tb-light, #f3f6f9); }
    .legend-dot { width: 10px; height: 10px; border-radius: 3px; display: inline-block; }
</style>
<div class="main-content viz-root">
<div class="page-content">
<div class="container-fluid">

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="me-auto">
            <h4 class="mb-0">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ auth()->user()->first_name }}</h4>
            <div class="text-muted fs-sm">{{ $rangeLabel }} · compared with {{ $prevStart->isSameDay($prevEnd) ? $prevStart->format('D, d M') : $prevStart->format('d M') . ' – ' . $prevEnd->format('d M') }} · <span id="updated">updated {{ now()->format('h:i A') }}</span></div>
        </div>
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-center" id="periodForm">
            <div class="btn-group btn-group-sm flex-wrap" role="group">
                @foreach ($periods as $k => $label)
                    <a href="{{ route('dashboard', ['period' => $k]) }}" class="btn {{ $period === $k ? 'btn-primary' : 'btn-light' }}">{{ $label }}</a>
                @endforeach
            </div>
            <input type="hidden" name="period" value="custom">
            <input type="date" name="from" class="form-control form-control-sm w-auto" value="{{ $period === 'custom' ? $start->toDateString() : '' }}" aria-label="From">
            <input type="date" name="to" class="form-control form-control-sm w-auto" value="{{ $period === 'custom' ? $end->toDateString() : '' }}" aria-label="To">
            <button class="btn btn-sm btn-light">Apply</button>
            <a href="{{ route('dashboard.export', request()->query()) }}" class="btn btn-sm btn-light" title="Download CSV"><i class="bi bi-download"></i></a>
            @can('View pos')<a href="{{ route('pos.index') }}" class="btn btn-sm btn-success"><i class="bi bi-cart3 me-1"></i>Open POS</a>@endcan
        </form>
    </div>

    {{-- ============ KPI ROW ============ --}}
    <div class="row g-3 mb-3">
        @php
            $tiles = [
                ['Sales revenue', money($kpi['revenue'], 0), $arrow($change['revenue']), 'bi-cash-coin', 'primary'],
                ['Transactions', number_format($kpi['orders']), $arrow($change['orders']), 'bi-receipt', 'info'],
                ['Average basket', money($kpi['basket'], 0), $arrow($change['basket']), 'bi-basket', 'warning'],
            ];
            if ($canProfit) {
                $tiles[] = ['Gross profit', money($kpi['profit'], 0), $arrow($change['profit']) . ' <span class="text-muted">· ' . $kpi['margin'] . '% margin</span>', 'bi-graph-up-arrow', 'success'];
            } else {
                $tiles[] = ['Items sold', qty($kpi['items']), $arrow($change['items']), 'bi-box-seam', 'success'];
            }
        @endphp
        @foreach ($tiles as [$label, $val, $cmp, $icon, $color])
            <div class="col-6 col-xl-3">
                <div class="card kpi h-100 mb-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="lbl">{{ $label }}</div>
                            <span class="avatar-xs"><span class="avatar-title rounded bg-{{ $color }}-subtle text-{{ $color }}"><i class="bi {{ $icon }}"></i></span></span>
                        </div>
                        <div class="val mt-2" data-kpi="{{ \Illuminate\Support\Str::slug($label) }}">{{ $val }}</div>
                        <div class="cmp mt-1">{!! $cmp !!} <span class="text-muted">vs previous</span></div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- secondary numbers --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="row text-center g-2">
                <div class="col-6 col-md-2"><div class="text-muted fs-xs text-uppercase">Items sold</div><div class="fw-semibold">{{ qty($kpi['items']) }}</div></div>
                <div class="col-6 col-md-2"><div class="text-muted fs-xs text-uppercase">Discounts given</div><div class="fw-semibold">{{ money($kpi['discounts'], 0) }}</div></div>
                <div class="col-6 col-md-2"><div class="text-muted fs-xs text-uppercase">Refunds</div><div class="fw-semibold {{ $kpi['refunds'] > 0 ? 'text-danger' : '' }}">{{ money($kpi['refunds'], 0) }}</div></div>
                <div class="col-6 col-md-2"><div class="text-muted fs-xs text-uppercase">Voided sales</div><div class="fw-semibold {{ $kpi['voided'] > 0 ? 'text-warning' : '' }}">{{ $kpi['voided'] }}</div></div>
                <div class="col-6 col-md-2"><div class="text-muted fs-xs text-uppercase">New customers</div><div class="fw-semibold">{{ $kpi['new_customers'] }}</div></div>
                <div class="col-6 col-md-2"><div class="text-muted fs-xs text-uppercase">Net after refunds</div><div class="fw-semibold">{{ money($kpi['net'], 0) }}</div></div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        {{-- ============ TREND ============ --}}
        <div class="col-xl-8">
            <div class="card h-100 mb-0">
                <div class="card-header d-flex align-items-center">
                    <h6 class="card-title mb-0 flex-grow-1">{{ $trend['hourly'] ? 'Sales by hour' : 'Sales by day' }}</h6>
                    <div class="d-flex gap-3 fs-sm">
                        <span><span class="legend-dot me-1" style="background:var(--series-1)"></span>Revenue</span>
                        @if ($canProfit)<span><span class="legend-dot me-1" style="background:var(--series-3)"></span>Gross profit</span>@endif
                    </div>
                </div>
                <div class="card-body">
                    @if (array_sum($trend['revenue']) == 0)
                        <div class="text-center text-muted py-5"><i class="bi bi-bar-chart fs-1 d-block mb-2"></i>No sales in this period yet.</div>
                    @else
                        <div id="trendChart" style="min-height:300px"></div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ============ PAYMENT MIX ============ --}}
        <div class="col-xl-4">
            <div class="card h-100 mb-0">
                <div class="card-header"><h6 class="card-title mb-0">How customers paid</h6></div>
                <div class="card-body">
                    @php $payTotal = array_sum($payments) ?: 1; $payMax = max($payments ?: [1]); @endphp
                    @forelse ($payments as $m => $amt)
                        <div class="bar-row" title="{{ $m }}: {{ money($amt) }}">
                            <div class="text-truncate">{{ $m }}</div>
                            <div class="track"><div class="fill" style="width: {{ max(2, $amt / $payMax * 100) }}%"></div></div>
                            <div class="text-end fs-sm"><strong>{{ money($amt, 0) }}</strong> <span class="text-muted">{{ round($amt / $payTotal * 100) }}%</span></div>
                        </div>
                    @empty
                        <div class="text-muted text-center py-4">No payments yet.</div>
                    @endforelse

                    <h6 class="mt-4 mb-2">Sales by category</h6>
                    @php $catMax = collect($categories)->max('revenue') ?: 1; @endphp
                    @forelse ($categories as $c)
                        <div class="bar-row" title="{{ $c['name'] }}: {{ money($c['revenue']) }}">
                            <div class="text-truncate">{{ $c['name'] }}</div>
                            <div class="track"><div class="fill" style="width: {{ max(2, $c['revenue'] / $catMax * 100) }}%"></div></div>
                            <div class="text-end fs-sm fw-semibold">{{ money($c['revenue'], 0) }}</div>
                        </div>
                    @empty
                        <div class="text-muted text-center py-3">—</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ============ TOP PRODUCTS ============ --}}
        <div class="col-xl-6">
            <div class="card h-100 mb-0">
                <div class="card-header d-flex align-items-center">
                    <h6 class="card-title mb-0 flex-grow-1">Top products</h6>
                    @can('View product')<a href="{{ route('products.index') }}" class="fs-sm">All products</a>@endcan
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>#</th><th>Product</th><th class="text-end">Qty</th><th class="text-end">Revenue</th>@if($canProfit)<th class="text-end">Profit</th>@endif</tr></thead>
                        <tbody>
                        @forelse ($topProducts as $i => $p)
                            <tr>
                                <td><span class="rank">{{ $i + 1 }}</span></td>
                                <td class="text-truncate" style="max-width:240px">{{ $p['title'] }}</td>
                                <td class="text-end">{{ qty($p['qty']) }}</td>
                                <td class="text-end fw-semibold">{{ money($p['revenue'], 0) }}</td>
                                @if($canProfit)<td class="text-end {{ $p['profit'] < 0 ? 'text-danger' : '' }}">{{ money($p['profit'], 0) }}</td>@endif
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No sales in this period.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ============ CASHIERS ============ --}}
        <div class="col-xl-6">
            <div class="card h-100 mb-0">
                <div class="card-header d-flex align-items-center">
                    <h6 class="card-title mb-0 flex-grow-1">Cashier performance</h6>
                    @can('View pos')<a href="{{ route('pos.transactions') }}" class="fs-sm">Transactions</a>@endcan
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>Cashier</th><th class="text-end">Sales</th><th class="text-end">Revenue</th><th class="text-end">Avg basket</th><th class="text-end">Discounts</th><th class="text-end">Voids</th></tr></thead>
                        <tbody>
                        @forelse ($cashiers as $c)
                            <tr>
                                <td>{{ trim($c['name']) ?: 'Unknown' }}</td>
                                <td class="text-end">{{ $c['orders'] }}</td>
                                <td class="text-end fw-semibold">{{ money($c['revenue'], 0) }}</td>
                                <td class="text-end">{{ money($c['basket'], 0) }}</td>
                                <td class="text-end">{{ money($c['discounts'], 0) }}</td>
                                <td class="text-end">@if($c['voids'])<span class="badge bg-warning-subtle text-warning">{{ $c['voids'] }}</span>@else 0 @endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No till sales in this period.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ============ INVENTORY ============ --}}
        <div class="col-xl-4">
            <div class="card h-100 mb-0">
                <div class="card-header d-flex align-items-center">
                    <h6 class="card-title mb-0 flex-grow-1">Stock health</h6>
                    @can('View inventory')<a href="{{ route('inventory.low-stock-alerts') }}" class="fs-sm">Low stock report</a>@endcan
                </div>
                <div class="card-body">
                    <div class="row g-2 text-center mb-3">
                        <div class="col-6"><div class="border rounded p-2"><div class="fs-xs text-muted text-uppercase">Stock at cost</div><div class="fw-bold">{{ money($inventory['cost_value'], 0) }}</div></div></div>
                        <div class="col-6"><div class="border rounded p-2"><div class="fs-xs text-muted text-uppercase">At selling price</div><div class="fw-bold">{{ money($inventory['retail_value'], 0) }}</div></div></div>
                        <div class="col-4"><a href="{{ route('products.index', ['stock' => 'low_stock']) }}" class="d-block border rounded p-2 text-reset"><div class="fs-xs text-muted text-uppercase">Low</div><div class="fw-bold text-warning"><i class="bi bi-exclamation-triangle me-1"></i>{{ $inventory['low_count'] }}</div></a></div>
                        <div class="col-4"><a href="{{ route('products.index', ['stock' => 'out_of_stock']) }}" class="d-block border rounded p-2 text-reset"><div class="fs-xs text-muted text-uppercase">Out</div><div class="fw-bold text-danger"><i class="bi bi-x-octagon me-1"></i>{{ $inventory['out_count'] }}</div></a></div>
                        <div class="col-4"><a href="{{ route('adjustments.index', ['status' => 'pending']) }}" class="d-block border rounded p-2 text-reset"><div class="fs-xs text-muted text-uppercase">To approve</div><div class="fw-bold {{ $inventory['pending_adjustments'] ? 'text-primary' : '' }}"><i class="bi bi-hourglass-split me-1"></i>{{ $inventory['pending_adjustments'] }}</div></a></div>
                    </div>
                    <h6 class="fs-sm text-muted text-uppercase">Reorder soon</h6>
                    @forelse ($inventory['low'] as $p)
                        <div class="d-flex justify-content-between border-bottom py-1 fs-sm">
                            <a href="{{ route('products.edit', $p->id) }}" class="text-reset text-truncate me-2">{{ $p->title }}</a>
                            <span class="{{ $p->stock <= 0 ? 'text-danger' : 'text-warning' }} fw-semibold text-nowrap">{{ qty($p->stock) }} <span class="text-muted fw-normal">/ {{ qty($p->reorder_level) }}</span></span>
                        </div>
                    @empty
                        <div class="text-muted fs-sm">Everything is above its reorder level.</div>
                    @endforelse
                    @if ($inventory['expiring']->isNotEmpty())
                        <h6 class="fs-sm text-muted text-uppercase mt-3">Expiring within 30 days</h6>
                        @foreach ($inventory['expiring'] as $e)
                            <div class="d-flex justify-content-between border-bottom py-1 fs-sm">
                                <span class="text-truncate me-2">{{ $e->title }}</span>
                                <span class="text-danger text-nowrap">{{ \Carbon\Carbon::parse($e->expiry)->format('d M') }}</span>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        {{-- ============ RECENT SALES ============ --}}
        <div class="col-xl-4">
            <div class="card h-100 mb-0">
                <div class="card-header"><h6 class="card-title mb-0">Latest sales</h6></div>
                <div class="list-group list-group-flush">
                    @forelse ($recent as $o)
                        <div class="list-group-item d-flex align-items-center gap-2">
                            <div class="flex-grow-1 min-w-0">
                                <div class="vm-mono text-truncate">{{ $o->id }}</div>
                                <div class="fs-xs text-muted">{{ \Carbon\Carbon::parse($o->order_date)->diffForHumans() }} · {{ $o->cashier ?? '—' }}{{ $o->customer ? ' · ' . $o->customer : '' }}</div>
                            </div>
                            <div class="text-end">
                                <div class="fw-semibold">{{ money($o->total_amount, 0) }}</div>
                                @if ($o->status === 'cancelled')<span class="badge bg-danger-subtle text-danger">voided</span>
                                @else<span class="fs-xs text-muted text-capitalize">{{ $o->payment_method }}</span>@endif
                            </div>
                        </div>
                    @empty
                        <div class="list-group-item text-muted text-center py-4">No sales yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ============ SECURITY ============ --}}
        <div class="col-xl-4">
            <div class="card h-100 mb-0">
                <div class="card-header d-flex align-items-center">
                    <h6 class="card-title mb-0 flex-grow-1">Activity & security</h6>
                    @can('View audit trail')<a href="{{ route('audit.index') }}" class="fs-sm">Audit trail</a>@endcan
                </div>
                <div class="card-body">
                    <div class="row g-2 text-center mb-3">
                        <div class="col-6"><a href="{{ route('security.index') }}" class="d-block border rounded p-2 text-reset"><div class="fs-xs text-muted text-uppercase">Online now</div><div class="fw-bold"><i class="bi bi-circle-fill text-success fs-xs me-1"></i>{{ $security['online'] }}</div></a></div>
                        <div class="col-6"><div class="border rounded p-2"><div class="fs-xs text-muted text-uppercase">Failed logins today</div><div class="fw-bold {{ $security['failed_logins'] ? 'text-danger' : '' }}">{{ $security['failed_logins'] }}</div></div></div>
                    </div>
                    @foreach ($security['events'] as $ev)
                        <div class="d-flex gap-2 border-bottom py-1 fs-sm">
                            <span class="badge bg-{{ $ev->event_color }}-subtle text-{{ $ev->event_color }} align-self-start">{{ str_replace('_', ' ', $ev->event) }}</span>
                            <div class="min-w-0">
                                <div class="text-truncate">{{ $ev->description }}</div>
                                <div class="fs-xs text-muted">{{ $ev->user_name ?: 'System' }} · {{ $ev->created_at->diffForHumans() }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
</div>
</div>
@endsection

@push('page-scripts')
<script>
(function () {
    const el = document.getElementById('trendChart');
    if (!el || !window.ApexCharts) return;
    const css = getComputedStyle(document.querySelector('.viz-root'));
    const c1 = css.getPropertyValue('--series-1').trim(), c3 = css.getPropertyValue('--series-3').trim();
    const grid = css.getPropertyValue('--grid').trim(), ink = css.getPropertyValue('--ink-2').trim();
    const trend = @json($trend);
    const canProfit = @json($canProfit);
    const series = [{name: 'Revenue', data: trend.revenue}];
    if (canProfit) series.push({name: 'Gross profit', data: trend.profit});
    const money = v => VM.money(v, 0);

    new ApexCharts(el, {
        chart: {type: trend.hourly ? 'bar' : 'area', height: 300, toolbar: {show: false}, fontFamily: 'inherit', zoom: {enabled: false}},
        series,
        colors: canProfit ? [c1, c3] : [c1],
        xaxis: {categories: trend.labels, labels: {style: {colors: ink}, rotate: -45, hideOverlappingLabels: true}, axisBorder: {show: false}, axisTicks: {show: false}, tickAmount: Math.min(trend.labels.length, 12)},
        yaxis: {labels: {style: {colors: ink}, formatter: v => VM.currency + (Math.abs(v) >= 1e6 ? (v / 1e6).toFixed(1) + 'm' : Math.abs(v) >= 1e3 ? (v / 1e3).toFixed(0) + 'k' : v.toFixed(0))}},
        grid: {borderColor: grid, strokeDashArray: 3, xaxis: {lines: {show: false}}},
        stroke: {width: trend.hourly ? 0 : 2, curve: 'smooth'},
        fill: trend.hourly ? {opacity: 1} : {type: 'gradient', gradient: {opacityFrom: .25, opacityTo: .02}},
        plotOptions: {bar: {columnWidth: '60%', borderRadius: 4, borderRadiusApplication: 'end'}},
        dataLabels: {enabled: false},
        legend: {show: false},
        markers: {size: 0, hover: {size: 5}},
        tooltip: {shared: true, intersect: false, y: {formatter: (v, o) => money(v) + (o && o.seriesIndex === 0 ? ' · ' + trend.orders[o.dataPointIndex] + ' sales' : '')}},
    }).render();

    // Refresh "today" figures every 2 minutes
    if (@json($period) === 'today') {
        setInterval(async () => {
            try {
                const r = await VM.api(location.href, {headers: {'X-Background-Poll': '1'}});
                document.querySelector('[data-kpi="sales-revenue"]').textContent = money(r.kpi.revenue);
                document.querySelector('[data-kpi="transactions"]').textContent = r.kpi.orders.toLocaleString();
                document.querySelector('[data-kpi="average-basket"]').textContent = money(r.kpi.basket);
                const gp = document.querySelector('[data-kpi="gross-profit"]'); if (gp) gp.textContent = money(r.kpi.profit);
                document.getElementById('updated').textContent = 'updated ' + r.updated;
            } catch (e) {}
        }, 120000);
    }
})();
</script>
@endpush

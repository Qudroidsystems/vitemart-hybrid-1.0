@extends('layouts.master')

@section('content')
@php
    // Branch colours follow the branch (by its number), never its rank – a filter never repaints a branch.
    $palette = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6', '#e34948'];
    $colorOf = fn ($b) => $palette[($b->number) % count($palette)];
    $fresh = [
        'live' => ['success', 'bi-check-circle-fill'],
        'local' => ['success', 'bi-hdd-fill'],
        'behind' => ['warning', 'bi-clock-history'],
        'stale' => ['danger', 'bi-exclamation-octagon-fill'],
    ];
    $ago = fn ($r) => $r['fresh']['state'] === 'local' ? 'live' : ($r['fresh']['as_of'] ? $r['fresh']['as_of']->diffForHumans() : 'never');
    $qs = request()->query();
@endphp
<div class="main-content">
<div class="page-content">
<div class="container-fluid hqd">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0">All branches</h4>
            <p class="text-muted mb-0 mt-1 fs-sm">{{ $start->format('d M Y H:i') }} – {{ $end->format('d M Y H:i') }} ·
                each branch's figures are as fresh as its last sync.</p>
        </div>
        <a class="btn btn-light btn-sm" href="{{ route('mb.hq-dashboard.export', $qs) }}"><i class="bi bi-download me-1"></i>CSV</a>
    </div>

    {{-- Filters: one row above everything they affect --}}
    <form class="card mb-3" method="GET">
        <div class="card-body py-2 d-flex flex-wrap gap-2 align-items-center">
            <div class="btn-group btn-group-sm flex-wrap" role="group">
                @foreach ($periods as $k => $l)
                    @if ($k !== 'custom')
                        <button name="period" value="{{ $k }}" class="btn {{ $period === $k ? 'btn-primary' : 'btn-light' }}">{{ $l }}</button>
                    @endif
                @endforeach
            </div>
            <input type="date" name="from" value="{{ request('from', $start->toDateString()) }}" class="form-control form-control-sm" style="width:auto">
            <span class="text-muted fs-sm">to</span>
            <input type="date" name="to" value="{{ request('to', $end->toDateString()) }}" class="form-control form-control-sm" style="width:auto">
            <button name="period" value="custom" class="btn btn-sm btn-light">Apply dates</button>
            <div class="ms-auto d-flex flex-wrap gap-2 align-items-center">
                @foreach ($allBranches as $b)
                    <label class="form-check form-check-inline mb-0 fs-sm">
                        <input class="form-check-input" type="checkbox" name="branches[]" value="{{ $b->id }}" @checked(in_array($b->id, $picked)) onchange="this.form.submit()">
                        <span class="legend-dot" style="background: {{ $colorOf($b) }}"></span>{{ $b->code }}
                    </label>
                @endforeach
                <input type="hidden" name="period" value="{{ $period }}">
            </div>
        </div>
    </form>

    @if ($totals['not_fresh'])
        <div class="alert alert-warning d-flex gap-2 align-items-start py-2">
            <i class="bi bi-clock-history fs-5"></i>
            <div><strong>{{ $totals['not_fresh'] }} branch{{ $totals['not_fresh'] > 1 ? 'es are' : ' is' }} behind.</strong>
                Their sales since their last sync are not in these totals yet. See the “Data up to” column.</div>
        </div>
    @endif

    {{-- Headline numbers --}}
    <div class="row g-3 mb-3">
        @foreach ([['Sales', money($totals['revenue']), number_format($totals['orders']) . ' receipts'],
                   ['Average basket', money($totals['basket']), 'per receipt'],
                   ['Gross profit', money($totals['profit']), $totals['margin'] !== null ? $totals['margin'] . '% margin' : '—'],
                   ['Stock value (cost)', money($totals['stock_value']), number_format($totals['low_stock']) . ' items low']] as [$label, $value, $sub])
            <div class="col-6 col-xl-3">
                <div class="card mb-0 h-100"><div class="card-body">
                    <div class="text-muted fs-sm">{{ $label }}</div>
                    <div class="fs-3 fw-semibold hqd-num">{{ $value }}</div>
                    <div class="fs-xs text-muted">{{ $sub }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    {{-- Branch comparison --}}
    <div class="card">
        <div class="card-header"><h6 class="card-title mb-0">Branch by branch</h6></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 hqd-table">
                <thead class="table-light"><tr>
                    <th>Branch</th><th>Data up to</th><th class="text-end">Sales</th><th class="text-end">Receipts</th><th class="text-end">Basket</th>
                    <th class="text-end">Gross profit</th><th class="text-end">Margin</th><th class="text-end">Refunds · voids</th>
                    <th class="text-end">Stock value</th><th class="text-end">Low · out</th><th class="text-end">Transfers</th>
                </tr></thead>
                <tbody>
                @forelse ($rows as $r)
                    @php [$fc, $fi] = $fresh[$r['fresh']['state']]; @endphp
                    <tr>
                        <td><span class="legend-dot" style="background: {{ $colorOf($r['branch']) }}"></span>
                            <a href="{{ route('mb.branches.show', $r['branch']) }}" class="fw-semibold">{{ $r['branch']->code }}</a>
                            <span class="text-muted fs-sm">{{ $r['branch']->name }}</span></td>
                        <td class="text-nowrap"><span class="badge bg-{{ $fc }}-subtle text-{{ $fc }}"><i class="bi {{ $fi }} me-1"></i>{{ $r['fresh']['label'] }}</span>
                            <div class="fs-xs text-muted">{{ $r['fresh']['state'] === 'local' ? 'this server' : ($r['fresh']['as_of'] ? $r['fresh']['as_of']->format('d M H:i') . ' · ' . $ago($r) : 'never') }}</div></td>
                        <td class="text-end fw-semibold">{{ money($r['revenue']) }}</td>
                        <td class="text-end">{{ number_format($r['orders']) }}</td>
                        <td class="text-end">{{ money($r['basket']) }}</td>
                        <td class="text-end">{{ money($r['profit']) }}</td>
                        <td class="text-end">{{ $r['margin'] !== null ? $r['margin'] . '%' : '—' }}</td>
                        <td class="text-end">{{ money($r['refunds']) }} · {{ $r['voids'] }}</td>
                        <td class="text-end">{{ money($r['stock_value']) }}</td>
                        <td class="text-end">{{ $r['low_stock'] }} · <span class="{{ $r['out_of_stock'] ? 'text-danger' : '' }}">{{ $r['out_of_stock'] }}</span></td>
                        <td class="text-end fs-sm">{{ $r['transfers_in'] }} in · {{ $r['transfers_out'] }} out
                            @if ($r['transfer_issues'])<div class="text-danger fs-xs"><i class="bi bi-exclamation-triangle"></i> {{ $r['transfer_issues'] }} with differences</div>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="text-center text-muted py-4">No branches.</td></tr>
                @endforelse
                </tbody>
                @if (count($rows) > 1)
                    <tfoot><tr class="fw-semibold"><td colspan="2">All selected</td><td class="text-end">{{ money($totals['revenue']) }}</td>
                        <td class="text-end">{{ number_format($totals['orders']) }}</td><td class="text-end">{{ money($totals['basket']) }}</td>
                        <td class="text-end">{{ money($totals['profit']) }}</td><td class="text-end">{{ $totals['margin'] !== null ? $totals['margin'] . '%' : '—' }}</td>
                        <td></td><td class="text-end">{{ money($totals['stock_value']) }}</td><td></td><td></td></tr></tfoot>
                @endif
            </table>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card h-100 mb-0">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0">Sales per day</h6>
                    <div class="d-flex flex-wrap gap-3 fs-sm" id="trendLegend">
                        @foreach ($branches as $b)<span><span class="legend-line" style="background: {{ $colorOf($b) }}"></span>{{ $b->code }}</span>@endforeach
                    </div>
                </div>
                <div class="card-body"><div style="height:300px"><canvas id="trendChart" aria-label="Sales per day by branch" role="img"></canvas></div></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card h-100 mb-0">
                <div class="card-header"><h6 class="card-title mb-0">Sales by branch</h6></div>
                <div class="card-body"><div style="height:300px"><canvas id="branchChart" aria-label="Sales by branch" role="img"></canvas></div></div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-0">
        <div class="col-xl-7">
            <div class="card">
                <div class="card-header"><h6 class="card-title mb-0">Best sellers across branches</h6></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>Product</th><th class="text-end">Qty</th><th class="text-end">Sales</th><th style="width:38%">Share by branch</th></tr></thead>
                        <tbody>
                        @forelse ($top as $t)
                            <tr>
                                <td>{{ $t['title'] }}</td>
                                <td class="text-end">{{ qty($t['qty']) }}</td>
                                <td class="text-end fw-semibold">{{ money($t['revenue']) }}</td>
                                <td>
                                    <div class="share-bar">
                                        @foreach ($branches as $b)
                                            @php $v = $t['by_branch'][$b->code] ?? 0; @endphp
                                            @if ($v > 0)
                                                <span style="width: {{ max(2, $t['revenue'] > 0 ? $v / $t['revenue'] * 100 : 0) }}%; background: {{ $colorOf($b) }}" title="{{ $b->code }}: {{ money($v) }}"></span>
                                            @endif
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">No sales in this period.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card">
                <div class="card-header"><h6 class="card-title mb-0">How customers paid</h6></div>
                <div class="table-responsive">
                    @php $methods = collect($mix)->flatMap(fn ($m) => array_keys($m))->unique()->values(); @endphp
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Branch</th>@foreach ($methods as $m)<th class="text-end text-capitalize">{{ $m }}</th>@endforeach</tr></thead>
                        <tbody>
                        @foreach ($mix as $code => $m)
                            <tr><td class="fw-semibold">{{ $code }}</td>@foreach ($methods as $k)<td class="text-end">{{ isset($m[$k]) ? money($m[$k]) : '—' }}</td>@endforeach</tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
</div>
<style>
    .hqd .legend-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 6px; vertical-align: baseline; }
    .hqd .legend-line { display: inline-block; width: 14px; height: 3px; border-radius: 2px; margin-right: 6px; vertical-align: middle; }
    .hqd .hqd-num { font-variant-numeric: tabular-nums; }
    .hqd .hqd-table td, .hqd .hqd-table th { font-variant-numeric: tabular-nums; white-space: nowrap; }
    .hqd .share-bar { display: flex; gap: 2px; height: 10px; }
    .hqd .share-bar span { display: block; height: 100%; border-radius: 4px; }
</style>
@endsection

@push('page-scripts')
<script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
<script>
(() => {
    if (!window.Chart) return;
    const css = getComputedStyle(document.body);
    const ink = css.getPropertyValue('--vz-secondary-color').trim() || '#6b7280';
    const grid = 'rgba(128,128,128,.15)';
    const money = v => VM.money(v, 0);
    const trend = @json($trend);
    const colors = @json($branches->map(fn ($b) => $colorOf($b))->values());
    const fmtDay = d => new Date(d + 'T00:00:00').toLocaleDateString(undefined, {day: 'numeric', month: 'short'});

    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {labels: trend.labels.map(fmtDay), datasets: trend.series.map((s, i) => ({
            label: s.code, data: s.values, borderColor: colors[i], backgroundColor: colors[i], borderWidth: 2,
            pointRadius: trend.labels.length > 31 ? 0 : 3, pointHoverRadius: 5, tension: 0.25, fill: false,
        }))},
        options: {
            maintainAspectRatio: false, interaction: {mode: 'index', intersect: false},
            plugins: {legend: {display: false}, tooltip: {callbacks: {label: c => ` ${c.dataset.label}: ${money(c.parsed.y)}`}}},
            scales: {x: {grid: {display: false}, ticks: {color: ink, maxTicksLimit: 10}}, y: {beginAtZero: true, grid: {color: grid}, ticks: {color: ink, callback: money}}},
        },
    });

    const rows = @json(collect($rows)->map(fn ($r) => ['code' => $r['branch']->code, 'revenue' => $r['revenue']])->values());
    new Chart(document.getElementById('branchChart'), {
        type: 'bar',
        data: {labels: rows.map(r => r.code), datasets: [{label: 'Sales', data: rows.map(r => r.revenue), backgroundColor: colors, borderRadius: 4, maxBarThickness: 36}]},
        options: {
            maintainAspectRatio: false,
            plugins: {legend: {display: false}, tooltip: {callbacks: {label: c => ' ' + money(c.parsed.y)}}},
            scales: {x: {grid: {display: false}, ticks: {color: ink}}, y: {beginAtZero: true, grid: {color: grid}, ticks: {color: ink, callback: money}}},
        },
    });
})();
</script>
@endpush

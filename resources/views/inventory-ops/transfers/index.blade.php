@extends('layouts.master')

@section('content')
@php $mb = !\App\Services\MultiBranch\Install::isStandalone() && !\App\Services\MultiBranch\Install::isCentral(); @endphp
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0">Stock transfers</h4>
            <p class="text-muted mb-0 mt-1 fs-sm">Move stock between branches: request → approve → dispatch (in transit) → receive. Differences are tracked until resolved.</p>
        </div>
        <div class="d-flex gap-2">
            @canany(['Request stock transfer', 'Allocate stock'])
                <a href="{{ route('transfers.create', ['direction' => 'request']) }}" class="btn btn-primary btn-sm"><i class="bi bi-box-arrow-in-down me-1"></i>Request stock</a>
                <a href="{{ route('transfers.create', ['direction' => 'send']) }}" class="btn btn-soft-primary btn-sm"><i class="bi bi-truck me-1"></i>Send stock</a>
            @endcanany
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card mb-0"><div class="card-body"><div class="text-muted fs-sm">On the way to us</div><div class="fs-3 fw-semibold">{{ $counts['incoming'] }}</div></div></div></div>
        <div class="col-md-4"><div class="card mb-0"><div class="card-body"><div class="text-muted fs-sm">For us to approve / dispatch</div><div class="fs-3 fw-semibold">{{ $counts['outgoing'] }}</div></div></div></div>
        <div class="col-md-4"><div class="card mb-0"><div class="card-body"><div class="text-muted fs-sm">With differences to resolve</div><div class="fs-3 fw-semibold {{ $counts['discrepancy'] ? 'text-danger' : '' }}">{{ $counts['discrepancy'] }}</div></div></div></div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <ul class="nav nav-pills nav-sm">
                @foreach (['incoming' => 'Coming in', 'outgoing' => 'Going out', 'all' => 'All'] as $k => $l)
                    <li class="nav-item"><a class="nav-link py-1 {{ $tab === $k ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => $k, 'page' => null]) }}">{{ $l }}</a></li>
                @endforeach
            </ul>
            <form class="d-flex gap-2" method="GET">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Transfer number">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Any status</option><option value="open" @selected(request('status') === 'open')>Open</option>
                    @foreach (\App\Models\StockTransfer::STATUSES as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach
                </select>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Number</th><th>From</th><th>To</th><th class="text-end">Items</th><th>Status</th><th>Requested</th><th>Dispatched</th><th>Received</th></tr></thead>
                <tbody>
                @forelse ($transfers as $t)
                    <tr onclick="location.href='{{ route('transfers.show', $t) }}'" style="cursor:pointer">
                        <td class="fw-semibold vm-mono">{{ $t->number }}</td>
                        <td>{{ $t->fromBranch->code ?? '?' }}</td>
                        <td>{{ $t->toBranch->code ?? '?' }}</td>
                        <td class="text-end">{{ $t->lines_count }}</td>
                        <td>@include('inventory-ops.partials.badge', ['status' => $t->status, 'label' => \App\Models\StockTransfer::STATUSES[$t->status] ?? $t->status])
                            @if ($t->has_discrepancy && $t->status === 'received')<span class="badge bg-danger-subtle text-danger">differences</span>@endif</td>
                        <td class="fs-sm">{{ $t->requested_at?->format('d M H:i') }}<div class="fs-xs text-muted">{{ $t->requested_by_name }}</div></td>
                        <td class="fs-sm">{{ $t->dispatched_at?->format('d M H:i') ?? '—' }}</td>
                        <td class="fs-sm">{{ $t->received_at?->format('d M H:i') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No transfers.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($transfers->hasPages())<div class="card-footer">{{ $transfers->links() }}</div>@endif
    </div>
</div>
</div>
</div>
@endsection

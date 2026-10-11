@extends('layouts.master')

@section('content')
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div><h4 class="mb-sm-0">Cash drawer shifts</h4><p class="text-muted mb-0 mt-1 fs-sm">Opening float, takings, cash counted at close, and the difference.</p></div>
    </div>
    <div class="card">
        <div class="card-body border-bottom">
            <form class="row g-2 align-items-end">
                <div class="col-6 col-md-3"><label class="form-label fs-xs text-muted mb-1">Cashier</label>
                    <select name="user_id" class="form-select"><option value="">All</option>@foreach ($cashiers as $c)<option value="{{ $c->id }}" @selected(request('user_id') == $c->id)>{{ $c->first_name }} {{ $c->last_name }}</option>@endforeach</select></div>
                <div class="col-6 col-md-2"><label class="form-label fs-xs text-muted mb-1">Status</label>
                    <select name="status" class="form-select"><option value="">All</option><option value="open" @selected(request('status') === 'open')>Open</option><option value="closed" @selected(request('status') === 'closed')>Closed</option></select></div>
                <div class="col-6 col-md-2"><label class="form-label fs-xs text-muted mb-1">From</label><input type="date" name="from" class="form-control" value="{{ request('from') }}"></div>
                <div class="col-6 col-md-2"><label class="form-label fs-xs text-muted mb-1">To</label><input type="date" name="to" class="form-control" value="{{ request('to') }}"></div>
                <div class="col-12 col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>#</th><th>Cashier</th><th>Opened</th><th>Closed</th><th class="text-end">Float</th><th class="text-end">Sales</th><th class="text-end">Expected cash</th><th class="text-end">Counted</th><th class="text-end">Difference</th><th></th></tr></thead>
                <tbody>
                @forelse ($shifts as $s)
                    <tr>
                        <td>{{ $s->id }}</td>
                        <td>{{ $s->user->first_name ?? '' }} {{ $s->user->last_name ?? '' }}</td>
                        <td class="fs-sm">{{ $s->opened_at->format('d M, h:i A') }}</td>
                        <td class="fs-sm">{!! $s->closed_at ? $s->closed_at->format('d M, h:i A') : '<span class="badge bg-success">open</span>' !!}</td>
                        <td class="text-end">{{ money($s->opening_float) }}</td>
                        <td class="text-end">{{ isset($s->summary['gross_sales']) ? money($s->summary['gross_sales']) : '—' }}</td>
                        <td class="text-end">{{ $s->expected_cash !== null ? money($s->expected_cash) : '—' }}</td>
                        <td class="text-end">{{ $s->counted_cash !== null ? money($s->counted_cash) : '—' }}</td>
                        <td class="text-end fw-semibold {{ $s->variance === null ? '' : ((float) $s->variance < 0 ? 'text-danger' : ((float) $s->variance > 0 ? 'text-warning' : 'text-success')) }}">
                            {{ $s->variance !== null ? ((float) $s->variance > 0 ? '+' : '') . money($s->variance) : '—' }}</td>
                        <td class="text-end"><a href="{{ route('pos.shifts.report', $s) }}" target="_blank" class="btn btn-sm btn-light">{{ $s->status === 'open' ? 'X-report' : 'Z-report' }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center text-muted py-5">No shifts yet. Cashiers open a shift from the POS menu.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{!! $shifts->links('pagination::bootstrap-5') !!}</div>
    </div>
</div>
</div>
</div>
@endsection

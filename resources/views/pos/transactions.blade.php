@extends('layouts.master')

@section('content')
@php $canProfit = auth()->user()->can('View profit figures'); @endphp
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0">Sales transactions</h4>
            <p class="text-muted mb-0 mt-1 fs-sm">{{ \Carbon\Carbon::parse($from)->format('d M Y') }}{{ $from !== $to ? ' – ' . \Carbon\Carbon::parse($to)->format('d M Y') : '' }}</p>
        </div>
        <div class="d-flex gap-2 mt-2 mt-sm-0">
            <a href="{{ route('pos.transactions.export', request()->query()) }}" class="btn btn-light btn-sm"><i class="bi bi-download me-1"></i>Export CSV</a>
            <a href="{{ route('pos.index') }}" class="btn btn-success btn-sm"><i class="bi bi-cart3 me-1"></i>POS</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        @php
            $t = [['Revenue', money($summary->revenue ?? 0), 'bi-cash-coin', 'primary'], ['Sales', number_format(($summary->orders ?? 0) - ($summary->voided ?? 0)), 'bi-receipt', 'info'],
                  ['Discounts', money($summary->discounts ?? 0), 'bi-percent', 'warning'], ['Refunds', money($refunds), 'bi-arrow-return-left', 'danger']];
            if ($canProfit) $t[] = ['Gross profit', money(($summary->revenue ?? 0) - ($summary->cost ?? 0)), 'bi-graph-up', 'success'];
            $t[] = ['Voided', number_format($summary->voided ?? 0), 'bi-x-octagon', 'secondary'];
        @endphp
        @foreach ($t as [$l, $v, $i, $c])
            <div class="col-6 col-md-4 col-xl-2">
                <div class="card vm-stat mb-0 h-100"><div class="card-body">
                    <div class="d-flex justify-content-between"><span class="vm-stat-label">{{ $l }}</span><i class="bi {{ $i }} text-{{ $c }}"></i></div>
                    <div class="vm-stat-value mt-1">{{ $v }}</div>
                </div></div>
            </div>
        @endforeach
    </div>
    @if ($byMethod->isNotEmpty())
        <div class="d-flex flex-wrap gap-2 mb-3">
            @foreach ($byMethod as $m)
                <a href="{{ route('pos.transactions', array_merge(request()->query(), ['method' => $m->method])) }}" class="vm-chip text-reset text-decoration-none py-1 px-3">
                    <span class="text-capitalize">{{ $m->method }}</span> <strong>{{ money($m->total) }}</strong> <span class="text-muted">({{ $m->c }})</span>
                </a>
            @endforeach
        </div>
    @endif

    <div class="card">
        <div class="card-body border-bottom">
            <form class="row g-2 align-items-end">
                <div class="col-md-3"><label class="form-label fs-xs text-muted mb-1">Search</label><input type="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Receipt no., customer, phone, item"></div>
                <div class="col-6 col-md-2"><label class="form-label fs-xs text-muted mb-1">From</label><input type="date" name="from" class="form-control" value="{{ $from }}"></div>
                <div class="col-6 col-md-2"><label class="form-label fs-xs text-muted mb-1">To</label><input type="date" name="to" class="form-control" value="{{ $to }}"></div>
                <div class="col-6 col-md-2"><label class="form-label fs-xs text-muted mb-1">Cashier</label>
                    <select name="user_id" class="form-select"><option value="">All</option>@foreach ($cashiers as $c)<option value="{{ $c->id }}" @selected(request('user_id') == $c->id)>{{ $c->first_name }} {{ $c->last_name }}</option>@endforeach</select></div>
                <div class="col-6 col-md-1"><label class="form-label fs-xs text-muted mb-1">Payment</label>
                    <select name="method" class="form-select"><option value="">All</option>@foreach (['cash', 'card', 'transfer', 'credit', 'split'] as $m)<option @selected(request('method') === $m)>{{ $m }}</option>@endforeach</select></div>
                <div class="col-6 col-md-1"><label class="form-label fs-xs text-muted mb-1">Status</label>
                    <select name="status" class="form-select"><option value="">All</option>@foreach (['completed' => 'Completed', 'cancelled' => 'Voided', 'refunded' => 'Refunded'] as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></div>
                <div class="col-12 col-md-1 d-flex gap-1"><button class="btn btn-primary flex-fill"><i class="bi bi-search"></i></button><a href="{{ route('pos.transactions') }}" class="btn btn-light"><i class="bi bi-x-lg"></i></a></div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Receipt</th><th>Time</th><th>Cashier</th><th>Customer</th><th class="text-end">Items</th><th class="text-end">Total</th>@if($canProfit)<th class="text-end">Profit</th>@endif<th>Payment</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($orders as $o)
                    <tr class="{{ $o->status === 'cancelled' ? 'text-muted' : '' }}">
                        <td class="vm-mono">{{ $o->id }}</td>
                        <td class="fs-sm text-nowrap">{{ $o->order_date?->format('d M, h:i A') }}</td>
                        <td class="fs-sm">{{ $o->user->first_name ?? '—' }}</td>
                        <td class="fs-sm">{{ $o->customer ? $o->customer->first_name . ' ' . $o->customer->last_name : 'Walk-in' }}</td>
                        <td class="text-end">{{ $o->items_count }}</td>
                        <td class="text-end fw-semibold">{{ money($o->total_amount) }}@if($o->discount_amount > 0)<div class="fs-xs text-danger">−{{ money($o->discount_amount) }} disc.</div>@endif</td>
                        @if($canProfit)<td class="text-end fs-sm">{{ $o->profit !== null ? money($o->profit) : '—' }}</td>@endif
                        <td class="fs-sm">
                            @forelse ($payments[$o->id] ?? [] as $p)
                                <div><span class="text-capitalize">{{ $p->method }}</span> {{ money($p->amount) }}</div>
                            @empty
                                <span class="text-capitalize">{{ $o->payment_method }}</span>
                            @endforelse
                        </td>
                        <td>
                            @php $sc = ['completed' => 'success', 'cancelled' => 'danger', 'refunded' => 'warning'][$o->status] ?? 'secondary'; @endphp
                            <span class="badge bg-{{ $sc }}-subtle text-{{ $sc }}">{{ $o->status === 'cancelled' ? 'voided' : $o->status }}</span>
                            @if($o->payment_status === 'partially_refunded')<div class="fs-xs text-warning">part refunded</div>@endif
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('pos.receipt', $o->id) }}" target="_blank" class="btn btn-sm btn-light" title="Reprint"><i class="bi bi-printer"></i></a>
                            <button class="btn btn-sm btn-light view-order" data-id="{{ $o->id }}" title="Details"><i class="bi bi-eye"></i></button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center text-muted py-5">No sales match these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{!! $orders->links('pagination::bootstrap-5') !!}</div>
    </div>
</div>
</div>
</div>
@endsection

@push('page-scripts')
<script>
document.querySelectorAll('.view-order').forEach(b => b.addEventListener('click', async () => {
    try {
        const o = await VM.api('/pos/order/' + encodeURIComponent(b.dataset.id));
        Swal.fire({width: 640, title: o.id, html: `<div class="text-start fs-sm">
            <div class="text-muted mb-2">${VM.esc(o.date)} · ${VM.esc(o.cashier || '')}${o.customer ? ' · ' + VM.esc(o.customer) : ''}</div>
            ${o.status === 'cancelled' ? `<div class="alert alert-danger py-1">Voided: ${VM.esc(o.void_reason || '')}</div>` : ''}
            <table class="table table-sm"><thead><tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Total</th></tr></thead><tbody>
            ${o.items.map(i => `<tr><td>${VM.esc(i.title)}<div class="text-muted">${VM.esc(i.unit || '')}${i.returned ? ' · ' + VM.qty(i.returned) + ' returned' : ''}</div></td><td class="text-end">${VM.qty(i.qty)}</td><td class="text-end">${VM.money(i.price)}</td><td class="text-end">${VM.money(i.total)}</td></tr>`).join('')}
            </tbody><tfoot><tr><td colspan="3">Subtotal</td><td class="text-end">${VM.money(o.subtotal)}</td></tr>
            ${o.discount ? `<tr><td colspan="3">Discount</td><td class="text-end text-danger">−${VM.money(o.discount)}</td></tr>` : ''}
            <tr class="fw-bold"><td colspan="3">Total</td><td class="text-end">${VM.money(o.total)}</td></tr>
            ${o.refunded ? `<tr><td colspan="3">Refunded</td><td class="text-end text-danger">−${VM.money(o.refunded)}</td></tr>` : ''}</tfoot></table>
            <div>${o.payments.map(p => `<span class="vm-chip me-1 text-capitalize">${VM.esc(p.method)} ${VM.money(p.amount)}</span>`).join('')}</div>
            <div class="mt-2 text-muted">To void or refund, use <strong>Receipts</strong> on the POS screen.</div></div>`});
    } catch (e) { VM.error(e); }
}));
</script>
@endpush

@extends('layouts.master')

@section('content')
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0">Purchase orders</h4>
            <p class="text-muted mb-0 mt-1 fs-sm">Order from suppliers, get approval, send, then receive against the order (part deliveries are fine).
                @if ($waiting) <span class="badge bg-warning-subtle text-warning">{{ $waiting }} waiting for approval</span>@endif</p>
        </div>
        @can('Create purchase order')<a href="{{ route('purchase-orders.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New order</a>@endcan
    </div>
    <div class="card">
        <div class="card-header">
            <form class="d-flex flex-wrap gap-2" method="GET">
                <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="max-width:200px" placeholder="PO number">
                <select name="supplier_id" class="form-select form-select-sm" style="max-width:240px" onchange="this.form.submit()"><option value="">All suppliers</option>
                    @foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected(request('supplier_id') == $s->id)>{{ $s->name }}</option>@endforeach</select>
                <select name="status" class="form-select form-select-sm" style="max-width:200px" onchange="this.form.submit()"><option value="">Any status</option><option value="open" @selected(request('status') === 'open')>Open</option>
                    @foreach (\App\Models\PurchaseOrder::STATUSES as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Number</th><th>Supplier</th>@unless (\App\Services\MultiBranch\Install::isBranch())<th>For</th>@endunless<th class="text-end">Lines</th><th class="text-end">Total</th><th>Expected</th><th>Status</th><th>Created</th></tr></thead>
                <tbody>
                @forelse ($orders as $o)
                    <tr onclick="location.href='{{ route('purchase-orders.show', $o) }}'" style="cursor:pointer">
                        <td class="vm-mono fw-semibold">{{ $o->number }}</td>
                        <td>{{ $o->supplier->name ?? '?' }}</td>
                        @unless (\App\Services\MultiBranch\Install::isBranch())<td>{{ $o->branch->code ?? '' }}</td>@endunless
                        <td class="text-end">{{ $o->lines_count }}</td>
                        <td class="text-end">{{ money($o->total) }}</td>
                        <td class="fs-sm">{{ $o->expected_date?->format('d M Y') ?? '—' }}</td>
                        <td>@include('inventory-ops.partials.badge', ['status' => $o->status, 'label' => \App\Models\PurchaseOrder::STATUSES[$o->status] ?? $o->status])</td>
                        <td class="fs-sm">{{ $o->created_at?->format('d M H:i') }}<div class="fs-xs text-muted">{{ $o->created_by_name }}</div></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No purchase orders.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($orders->hasPages())<div class="card-footer">{{ $orders->links() }}</div>@endif
    </div>
</div>
</div>
</div>
@endsection

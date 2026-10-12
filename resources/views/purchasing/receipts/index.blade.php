@extends('layouts.master')

@section('content')
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div><h4 class="mb-sm-0">Goods received</h4><p class="text-muted mb-0 mt-1 fs-sm">Every delivery booked in, with or without a purchase order.</p></div>
        @can('Post goods receipt')<a href="{{ route('goods-receipts.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-box-seam me-1"></i>Receive goods</a>@endcan
    </div>
    <div class="card">
        <div class="card-header">
            <form class="d-flex gap-2" method="GET">
                <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="max-width:220px" placeholder="GRN or invoice number">
                <select name="supplier_id" class="form-select form-select-sm" style="max-width:240px" onchange="this.form.submit()"><option value="">All suppliers</option>
                    @foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected(request('supplier_id') == $s->id)>{{ $s->name }}</option>@endforeach</select>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Number</th><th>Supplier</th><th>Order</th><th>Invoice</th><th class="text-end">Lines</th><th class="text-end">Total</th><th>Received</th></tr></thead>
                <tbody>
                @forelse ($receipts as $r)
                    <tr onclick="location.href='{{ route('goods-receipts.show', $r) }}'" style="cursor:pointer">
                        <td class="vm-mono fw-semibold">{{ $r->number }}</td><td>{{ $r->supplier->name ?? '—' }}</td><td class="vm-mono fs-sm">{{ $r->purchaseOrder->number ?? '—' }}</td>
                        <td>{{ $r->supplier_invoice ?: '—' }}</td><td class="text-end">{{ $r->lines_count }}</td><td class="text-end">{{ money($r->total) }}</td>
                        <td class="fs-sm">{{ $r->created_at?->format('d M H:i') }}<div class="fs-xs text-muted">{{ trim(($r->receiver->first_name ?? '') . ' ' . ($r->receiver->last_name ?? '')) }}</div></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No deliveries yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($receipts->hasPages())<div class="card-footer">{{ $receipts->links() }}</div>@endif
    </div>
</div>
</div>
</div>
@endsection

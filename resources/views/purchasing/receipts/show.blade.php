@extends('layouts.master')

@section('content')
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div><h4 class="mb-sm-0">Goods received <span class="vm-mono">{{ $grn->number }}</span></h4>
            <p class="text-muted mb-0 mt-1">{{ $grn->supplier->name ?? 'No supplier' }} · invoice {{ $grn->supplier_invoice ?: '—' }} {{ $grn->invoice_date ? '(' . $grn->invoice_date->format('d M Y') . ')' : '' }}
                · into {{ $grn->location->name ?? '' }} · {{ $grn->created_at?->format('d M Y H:i') }} by {{ trim(($grn->receiver->first_name ?? '') . ' ' . ($grn->receiver->last_name ?? '')) }}
                @if ($grn->purchaseOrder) · order <a href="{{ route('purchase-orders.show', $grn->purchaseOrder) }}">{{ $grn->purchaseOrder->number }}</a>@endif</p></div>
        <div class="d-flex gap-2">
            @can('Return to supplier')@if ($grn->supplier_id)<a href="{{ route('supplier-returns.create', ['grn' => $grn->id]) }}" class="btn btn-light btn-sm"><i class="bi bi-arrow-return-left me-1"></i>Return some</a>@endif @endcan
            <a href="{{ route('goods-receipts.index') }}" class="btn btn-light btn-sm">All deliveries</a>
        </div>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>Product</th><th>Pack</th><th class="text-end">Qty</th><th class="text-end">Free</th><th class="text-end">Cost / pack</th><th>Expiry</th><th>Batch</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                @foreach ($grn->lines as $l)
                    <tr><td>{{ $l->product->title ?? '?' }}{{ $l->variation ? ' – ' . $l->variation->label : '' }}</td><td>{{ $l->pack->unit->name ?? 'unit' }}{{ $l->qty_per_unit != 1 ? ' ×' . qty($l->qty_per_unit) : '' }}</td>
                        <td class="text-end">{{ qty($l->quantity) }}</td><td class="text-end">{{ $l->free_qty ? qty($l->free_qty) : '' }}</td><td class="text-end">{{ money($l->unit_cost) }}</td>
                        <td>{{ $l->expiry_date?->format('d M Y') }}</td><td>{{ $l->batch_number }}</td><td class="text-end">{{ money($l->quantity * $l->unit_cost) }}</td></tr>
                @endforeach
                </tbody>
                <tfoot><tr class="fw-semibold"><td colspan="7" class="text-end">Total</td><td class="text-end">{{ money($grn->total) }}</td></tr></tfoot>
            </table>
        </div>
        @if ($grn->notes)<div class="card-footer fs-sm">{{ $grn->notes }}</div>@endif
    </div>
</div>
</div>
</div>
@endsection

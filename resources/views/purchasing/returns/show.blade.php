@extends('layouts.master')

@section('content')
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div><h4 class="mb-sm-0">Return <span class="vm-mono">{{ $ret->number }}</span></h4>
            <p class="text-muted mb-0 mt-1">{{ $ret->supplier->name ?? '' }} · {{ $ret->reason }} · from {{ $ret->location->name ?? '' }} · credit note {{ $ret->credit_note ?: '—' }} · {{ $ret->created_at?->format('d M Y H:i') }}</p></div>
        <a href="{{ route('supplier-returns.index') }}" class="btn btn-light btn-sm">All returns</a>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>Product</th><th>Pack</th><th class="text-end">Qty</th><th class="text-end">Cost / pack</th><th class="text-end">Value</th></tr></thead>
                <tbody>
                @foreach ($ret->lines as $l)
                    <tr><td>{{ $l->product->title ?? '?' }}{{ $l->variation ? ' – ' . $l->variation->label : '' }}</td><td>{{ $l->pack->unit->name ?? 'unit' }}{{ $l->qty_per_unit != 1 ? ' ×' . qty($l->qty_per_unit) : '' }}</td>
                        <td class="text-end">{{ qty($l->quantity) }}</td><td class="text-end">{{ money($l->unit_cost) }}</td><td class="text-end">{{ money($l->quantity * $l->unit_cost) }}</td></tr>
                @endforeach
                </tbody>
                <tfoot><tr class="fw-semibold"><td colspan="4" class="text-end">Total</td><td class="text-end">{{ money($ret->total) }}</td></tr></tfoot>
            </table>
        </div>
    </div>
</div>
</div>
</div>
@endsection

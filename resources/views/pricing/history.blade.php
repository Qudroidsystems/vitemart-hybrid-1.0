@extends('layouts.master')

@section('content')
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0">Price history{{ $product ? ' – ' . $product->title : '' }}</h4>
            <p class="text-muted mb-0 mt-1 fs-sm">Every price change, who made it and why.</p>
        </div>
        <a href="{{ route('pricing.index') }}" class="btn btn-light btn-sm"><i class="bi bi-tags me-1"></i>Price control</a>
    </div>
    <div class="card">
        <div class="card-body border-bottom">
            <form class="row g-2 align-items-end">
                @if ($product)<input type="hidden" name="product_id" value="{{ $product->id }}">@endif
                <div class="col-6 col-md-2">
                    <label class="form-label fs-xs text-muted mb-1">Field</label>
                    <select name="field" class="form-select">
                        <option value="">All</option>
                        @foreach (['price' => 'Selling price', 'cost_price' => 'Cost price', 'sale_price' => 'Sale price', 'wholesale_price' => 'Wholesale', 'promo_price' => 'Promo', 'pack_price' => 'Pack price'] as $k => $v)
                            <option value="{{ $k }}" @selected(request('field') === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label fs-xs text-muted mb-1">Source</label>
                    <select name="source" class="form-select">
                        <option value="">All</option>
                        @foreach (['manual', 'bulk', 'promo', 'revert', 'receive', 'create'] as $s)<option @selected(request('source') === $s)>{{ $s }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2"><label class="form-label fs-xs text-muted mb-1">From</label><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
                <div class="col-6 col-md-2"><label class="form-label fs-xs text-muted mb-1">To</label><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
                <div class="col-12 col-md-2 d-flex gap-1"><button class="btn btn-primary flex-fill">Filter</button><a href="{{ route('pricing.history') }}" class="btn btn-light"><i class="bi bi-x-lg"></i></a></div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Date</th><th>Item</th><th>Field</th><th class="text-end">Old</th><th class="text-end">New</th><th class="text-end">Change</th><th>Source / reason</th><th>By</th></tr></thead>
                <tbody>
                @forelse ($histories as $h)
                    <tr>
                        <td class="text-nowrap fs-sm">{{ $h->created_at->format('d M Y, h:i A') }}</td>
                        <td>
                            <a href="{{ route('pricing.history', ['product_id' => $h->product_id]) }}" class="text-reset">{{ $h->product->title ?? 'Deleted' }}</a>
                            @if($h->variation)<div class="fs-xs text-muted">{{ $h->variation->label }}</div>@endif
                            @if($h->pack)<div class="fs-xs text-muted">{{ $h->pack->unit->name ?? 'Pack' }} ×{{ qty($h->pack->quantity_per_unit) }}</div>@endif
                        </td>
                        <td>{{ ucfirst(str_replace(['pack_', '_'], ['pack ', ' '], $h->field)) }}</td>
                        <td class="text-end text-muted">{{ $h->old_value !== null ? money($h->old_value) : '—' }}</td>
                        <td class="text-end fw-semibold">{{ $h->new_value !== null ? money($h->new_value) : '—' }}</td>
                        <td class="text-end">@if($h->change_percent !== null)<span class="{{ $h->change_percent > 0 ? 'text-success' : 'text-danger' }}">{{ $h->change_percent > 0 ? '+' : '' }}{{ $h->change_percent }}%</span>@endif</td>
                        <td><span class="badge bg-light text-body">{{ $h->source }}</span> <span class="fs-sm text-muted">{{ $h->reason }}</span></td>
                        <td class="fs-sm">{{ $h->user ? $h->user->first_name . ' ' . $h->user->last_name : 'System' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-5">No price changes found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{!! $histories->links('pagination::bootstrap-5') !!}</div>
    </div>
</div>
</div>
</div>
@endsection

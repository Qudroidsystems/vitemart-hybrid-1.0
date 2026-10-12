@extends('layouts.master')

@section('content')
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div><h4 class="mb-sm-0">Returns to suppliers</h4><p class="text-muted mb-0 mt-1 fs-sm">Damaged, expired or wrong goods sent back – stock goes down; note the supplier's credit note.</p></div>
        @can('Return to supplier')<a href="{{ route('supplier-returns.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-arrow-return-left me-1"></i>New return</a>@endcan
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Number</th><th>Supplier</th><th>Reason</th><th>Credit note</th><th class="text-end">Lines</th><th class="text-end">Value</th><th>Date</th></tr></thead>
                <tbody>
                @forelse ($returns as $r)
                    <tr onclick="location.href='{{ route('supplier-returns.show', $r) }}'" style="cursor:pointer">
                        <td class="vm-mono fw-semibold">{{ $r->number }}</td><td>{{ $r->supplier->name ?? '' }}</td><td class="fs-sm">{{ $r->reason }}</td><td>{{ $r->credit_note ?: '—' }}</td>
                        <td class="text-end">{{ $r->lines_count }}</td><td class="text-end">{{ money($r->total) }}</td><td class="fs-sm">{{ $r->created_at?->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No returns.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($returns->hasPages())<div class="card-footer">{{ $returns->links() }}</div>@endif
    </div>
</div>
</div>
</div>
@endsection

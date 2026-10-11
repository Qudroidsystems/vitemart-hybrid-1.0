@extends('layouts.master')

@section('content')
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0">Branch prices</h4>
            <p class="text-muted mb-0 mt-1 fs-sm">The catalogue price applies everywhere unless a price list below sets a different price.
                A branch's own list beats an all-branch list; higher priority wins.</p>
        </div>
        @if ($canManage)
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#listModal"><i class="bi bi-plus-lg me-1"></i>New price list</button>
        @endif
    </div>

    @include('layouts.partials.flash')

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Code</th><th>Name</th><th>Applies to</th><th class="text-end">Priority</th><th>Dates</th><th class="text-end">Items</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($lists as $l)
                    <tr>
                        <td><a href="{{ route('catalogue.price-lists.show', $l) }}" class="fw-semibold vm-mono">{{ $l->code }}</a></td>
                        <td>{{ $l->name }}<div class="fs-xs text-muted">{{ $l->notes }}</div></td>
                        <td>{{ $l->branch ? $l->branch->code . ' – ' . $l->branch->name : 'All branches' }}</td>
                        <td class="text-end">{{ $l->priority }}</td>
                        <td class="fs-sm">{{ $l->starts_at?->format('d M Y') ?? '—' }} → {{ $l->ends_at?->format('d M Y') ?? 'open' }}</td>
                        <td class="text-end">{{ number_format($l->items_count) }}</td>
                        <td>@if ($l->is_active)<span class="badge bg-success-subtle text-success">Active</span>@else<span class="badge bg-light text-muted">Off</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No price lists yet. Approving a branch's price request creates its list automatically.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
</div>

@if ($canManage)
<div class="modal fade" id="listModal" tabindex="-1">
    <div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('catalogue.price-lists.store') }}">
        @csrf
        <div class="modal-header"><h5 class="modal-title">New price list</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-5"><label class="form-label">Code</label><input name="code" class="form-control text-uppercase vm-mono" maxlength="30" required placeholder="LEKKI-XMAS"></div>
            <div class="col-7"><label class="form-label">Name</label><input name="name" class="form-control" maxlength="120" required placeholder="Lekki Christmas prices"></div>
            <div class="col-7"><label class="form-label">Applies to</label>
                <select name="branch_id" class="form-select"><option value="">All branches</option>
                    @foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->code }} – {{ $b->name }}</option>@endforeach</select></div>
            <div class="col-5"><label class="form-label">Priority</label><input type="number" name="priority" class="form-control" value="60" min="0" max="1000" required></div>
            <div class="col-6"><label class="form-label">From</label><input type="datetime-local" name="starts_at" class="form-control"></div>
            <div class="col-6"><label class="form-label">Until</label><input type="datetime-local" name="ends_at" class="form-control"></div>
            <div class="col-12"><label class="form-label">Notes</label><input name="notes" class="form-control" maxlength="255"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create</button></div>
    </form></div>
</div>
@endif
@endsection

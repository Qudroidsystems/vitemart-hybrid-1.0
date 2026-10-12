@extends('layouts.master')

@section('content')
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0">Stock counts</h4>
            <p class="text-muted mb-0 mt-1 fs-sm">Counters see the products but not what the system expects. Big differences are sent back for a recount; the result goes to a supervisor for approval.</p>
        </div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newCount"><i class="bi bi-plus-lg me-1"></i>Start a count</button>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Number</th><th>Title</th><th>Location</th><th class="text-end">Counted</th><th>Status</th><th>Started</th><th>Adjustment</th></tr></thead>
                <tbody>
                @forelse ($counts as $c)
                    <tr onclick="location.href='{{ route('counts.show', $c) }}'" style="cursor:pointer">
                        <td class="vm-mono fw-semibold">{{ $c->number }}</td>
                        <td>{{ $c->title }} @if ($c->blind)<span class="badge bg-light text-muted">blind</span>@endif</td>
                        <td>{{ $c->location->name ?? '' }}</td>
                        <td class="text-end">{{ $c->counted_count }} / {{ $c->lines_count }}</td>
                        <td>@include('inventory-ops.partials.badge', ['status' => $c->status])</td>
                        <td class="fs-sm">{{ $c->created_at?->format('d M H:i') }}</td>
                        <td class="fs-sm">{{ $c->stock_adjustment_id ? \App\Models\StockAdjustment::find($c->stock_adjustment_id)?->reference : '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No counts yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($counts->hasPages())<div class="card-footer">{{ $counts->links() }}</div>@endif
    </div>
</div>
</div>
</div>

<div class="modal fade" id="newCount" tabindex="-1">
    <div class="modal-dialog"><form class="modal-content" id="countForm">
        <div class="modal-header"><h5 class="modal-title">Start a stock count</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-12"><label class="form-label">Title</label><input name="title" class="form-control" maxlength="150" placeholder="e.g. Beverages aisle – month end"></div>
            <div class="col-12"><label class="form-label">Where</label><select name="location_id" class="form-select">@foreach ($locations as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach</select></div>
            <div class="col-12"><label class="form-label">What</label>
                <select name="scope" class="form-select" id="scope">
                    <option value="category">One category</option><option value="in_stock">Everything the system says we have</option><option value="all">Every product</option>
                </select></div>
            <div class="col-12" id="catBox"><select name="category_id" class="form-select">@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
            <div class="col-7"><div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="blind" value="1" id="blind" checked><label class="form-check-label" for="blind">Blind count (hide expected quantities)</label></div></div>
            <div class="col-5"><label class="form-label fs-sm">Recount if off by more than</label><div class="input-group input-group-sm"><input type="number" name="recount_threshold" class="form-control" value="{{ sys_setting('count_recount_threshold', 10) }}" min="0" max="100"><span class="input-group-text">%</span></div></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Start</button></div>
    </form></div>
</div>
@endsection

@push('page-scripts')
<script>
document.getElementById('scope').addEventListener('change', e => document.getElementById('catBox').style.display = e.target.value === 'category' ? '' : 'none');
document.getElementById('countForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(e.target); const body = Object.fromEntries(fd.entries()); body.blind = e.target.blind.checked ? 1 : 0;
    try { const r = await VM.api(@json(route('counts.store')), {method: 'POST', body}); location.href = r.redirect; } catch (err) { VM.error(err); }
});
</script>
@endpush

@extends('layouts.master')

@section('content')
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0">Stock adjustments</h4>
            <p class="text-muted mb-0 mt-1 fs-sm">Adjustments, stock counts and goods received. Stock only changes once an entry is approved.</p>
        </div>
        @canany(['Create stock adjustment', 'Adjust stock', 'Receive stock'])
        <div class="d-flex gap-2 mt-2 mt-sm-0">
            <a href="{{ route('adjustments.create', ['type' => 'receive']) }}" class="btn btn-light btn-sm"><i class="bi bi-truck me-1"></i>Receive goods</a>
            <a href="{{ route('adjustments.create', ['type' => 'count']) }}" class="btn btn-light btn-sm"><i class="bi bi-clipboard-check me-1"></i>Stock count</a>
            <a href="{{ route('adjustments.create', ['type' => 'damage']) }}" class="btn btn-light btn-sm"><i class="bi bi-trash3 me-1"></i>Write off damage</a>
            <a href="{{ route('adjustments.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New adjustment</a>
        </div>
        @endcanany
    </div>

    @include('layouts.partials.flash')

    <ul class="nav nav-tabs nav-tabs-custom mb-0">
        @foreach (['all' => 'All', 'pending' => 'Awaiting approval', 'approved' => 'Approved', 'rejected' => 'Rejected', 'draft' => 'Drafts', 'cancelled' => 'Cancelled'] as $k => $label)
            <li class="nav-item">
                <a class="nav-link {{ $status === $k ? 'active' : '' }}" href="{{ route('adjustments.index', array_merge(request()->except('page'), ['status' => $k])) }}">
                    {{ $label }}
                    @php $n = $k === 'all' ? $counts->sum() : ($counts[$k] ?? 0); @endphp
                    @if ($n)<span class="badge {{ $k === 'pending' ? 'bg-warning' : 'bg-light text-body' }} ms-1">{{ $n }}</span>@endif
                </a>
            </li>
        @endforeach
    </ul>
    <div class="card">
        <div class="card-body border-bottom">
            <form class="row g-2 align-items-end">
                <input type="hidden" name="status" value="{{ $status }}">
                <div class="col-md-4"><input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Reference, reason, supplier or product"></div>
                <div class="col-6 col-md-2">
                    <select name="type" class="form-select">
                        <option value="">All types</option>
                        @foreach (\App\Models\StockAdjustment::TYPES as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2"><input type="date" name="from" value="{{ request('from') }}" class="form-control" title="From"></div>
                <div class="col-6 col-md-2"><input type="date" name="to" value="{{ request('to') }}" class="form-control" title="To"></div>
                <div class="col-6 col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
            </form>
            @if ($status === 'pending' && $pendingValue)
                <div class="mt-2 fs-sm text-muted">Value of pending changes at cost: <strong class="{{ $pendingValue < 0 ? 'text-danger' : 'text-success' }}">{{ money($pendingValue) }}</strong></div>
            @endif
        </div>
        @can('Approve stock adjustment')
            <div class="vm-bulkbar align-items-center gap-2 px-3 py-2 bg-primary-subtle border-bottom" id="bulkBar">
                <strong><span id="selCount">0</span> selected</strong>
                <button class="btn btn-sm btn-success" id="bulkApprove"><i class="bi bi-check2-all me-1"></i>Approve selected</button>
            </div>
        @endcan
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr>
                    <th style="width:32px">@can('Approve stock adjustment')<input type="checkbox" class="form-check-input" id="selAll">@endcan</th>
                    <th>Reference</th><th>Type</th><th>Reason</th><th>Location</th><th class="text-end">Items</th><th class="text-end">Value change</th><th>Created</th><th>Status</th><th></th>
                </tr></thead>
                <tbody>
                @forelse ($adjustments as $a)
                    <tr>
                        <td>@if($a->isPending())@can('Approve stock adjustment')<input type="checkbox" class="form-check-input row-check" value="{{ $a->id }}">@endcan @endif</td>
                        <td><a href="{{ route('adjustments.show', $a) }}" class="fw-medium vm-mono">{{ $a->reference }}</a></td>
                        <td>{{ $a->type_label }}</td>
                        <td class="text-truncate" style="max-width:220px">{{ $a->reason }}@if($a->supplier)<div class="fs-xs text-muted">{{ $a->supplier }} {{ $a->supplier_invoice }}</div>@endif</td>
                        <td class="fs-sm">{{ $a->location->name ?? '—' }}</td>
                        <td class="text-end">{{ $a->items_count }}</td>
                        <td class="text-end {{ $a->total_value_change < 0 ? 'text-danger' : 'text-success' }}">{{ money($a->total_value_change) }}</td>
                        <td class="fs-sm">{{ $a->creator->first_name ?? '—' }}<div class="fs-xs text-muted">{{ $a->created_at->format('d M, h:i A') }}</div></td>
                        <td>
                            <span class="badge bg-{{ $a->status_color }}-subtle text-{{ $a->status_color }} text-capitalize">{{ $a->status }}</span>
                            @if($a->approver)<div class="fs-xs text-muted">by {{ $a->approver->first_name }}</div>@endif
                        </td>
                        <td class="text-end"><a href="{{ route('adjustments.show', $a) }}" class="btn btn-sm btn-light">{{ $a->isPending() && auth()->user()->can('Approve stock adjustment') ? 'Review' : 'Open' }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center text-muted py-5"><i class="bi bi-inboxes fs-1 d-block mb-2"></i>No adjustments here.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{!! $adjustments->links('pagination::bootstrap-5') !!}</div>
    </div>
</div>
</div>
</div>
@endsection

@push('page-scripts')
<script>
(function () {
    const checks = () => [...document.querySelectorAll('.row-check')];
    const bar = document.getElementById('bulkBar');
    function refresh() { const n = checks().filter(c => c.checked).length; if (bar) { bar.classList.toggle('show', n > 0); document.getElementById('selCount').textContent = n; } }
    document.getElementById('selAll')?.addEventListener('change', e => { checks().forEach(c => c.checked = e.target.checked); refresh(); });
    checks().forEach(c => c.addEventListener('change', refresh));
    document.getElementById('bulkApprove')?.addEventListener('click', async () => {
        const ids = checks().filter(c => c.checked).map(c => c.value);
        if (!await VM.confirm(`Approve ${ids.length} adjustment(s)?`, 'Stock will be updated immediately.', 'Approve')) return;
        try { const r = await VM.api(@json(route('adjustments.bulk-approve')), {method: 'POST', body: {ids}}); await Swal.fire({icon: 'success', title: r.message}); location.reload(); }
        catch (e) { VM.error(e); }
    });
})();
</script>
@endpush

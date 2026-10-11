@extends('layouts.master')

@section('content')
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0">Tills</h4>
            <p class="text-muted mb-0 mt-1 fs-sm">Each till PC picks its till once (top bar of the POS). A till can have one open shift at a time, and sells from its stock location.</p>
        </div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#regModal" data-new="1"><i class="bi bi-plus-lg me-1"></i>Add till</button>
    </div>

    @include('layouts.partials.flash')

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Code</th><th>Name</th><th>Sells from</th><th>Now</th><th>Last used</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($registers as $r)
                    @php $sh = $open[$r->id] ?? null; @endphp
                    <tr>
                        <td class="fw-semibold vm-mono">{{ $r->code }}</td>
                        <td>{{ $r->name }}<div class="fs-xs text-muted">{{ $r->receipt_printer }}</div></td>
                        <td>{{ $r->location->name ?? 'Default store location' }}</td>
                        <td>@if ($sh)<span class="badge bg-success-subtle text-success">Open · {{ $sh->user->first_name ?? '' }} since {{ $sh->opened_at?->format('H:i') }}</span>@else<span class="text-muted fs-sm">closed</span>@endif</td>
                        <td class="fs-sm">{{ $r->last_used_at?->diffForHumans() ?? 'never' }}</td>
                        <td>@if ($r->status === 'active')<span class="badge bg-success-subtle text-success">Active</span>@else<span class="badge bg-light text-muted">Off</span>@endif</td>
                        <td class="text-end"><button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#regModal"
                            data-r="{{ json_encode($r->only(['id', 'code', 'name', 'stock_location_id', 'status', 'receipt_printer'])) }}">Edit</button></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No tills yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
</div>

<div class="modal fade" id="regModal" tabindex="-1">
    <div class="modal-dialog"><form class="modal-content" method="POST" id="regForm" action="{{ route('pos.registers.store') }}">
        @csrf <input type="hidden" name="_method" value="POST" id="regMethod">
        <div class="modal-header"><h5 class="modal-title" id="regTitle">Add till</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-4"><label class="form-label">Code</label><input name="code" class="form-control text-uppercase vm-mono" maxlength="20" required placeholder="T2"></div>
            <div class="col-8"><label class="form-label">Name</label><input name="name" class="form-control" maxlength="100" required placeholder="Till 2 – by the exit"></div>
            <div class="col-12"><label class="form-label">Sells from</label>
                <select name="stock_location_id" class="form-select"><option value="">Default store location</option>
                    @foreach ($locations as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach</select></div>
            <div class="col-8"><label class="form-label">Receipt printer (note)</label><input name="receipt_printer" class="form-control" maxlength="120" placeholder="e.g. Xprinter 80mm USB"></div>
            <div class="col-4"><label class="form-label">Status</label><select name="status" class="form-select"><option value="active">Active</option><option value="inactive">Off</option></select></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    </form></div>
</div>
@endsection

@push('page-scripts')
<script>
document.getElementById('regModal').addEventListener('show.bs.modal', e => {
    const f = document.getElementById('regForm');
    const r = e.relatedTarget?.dataset.r ? JSON.parse(e.relatedTarget.dataset.r) : null;
    f.reset();
    document.getElementById('regTitle').textContent = r ? 'Edit till ' + r.code : 'Add till';
    f.action = r ? `{{ url('/pos/registers') }}/${r.id}` : @json(route('pos.registers.store'));
    document.getElementById('regMethod').value = r ? 'PUT' : 'POST';
    if (r) Object.entries(r).forEach(([k, v]) => { if (f.elements[k]) f.elements[k].value = v ?? ''; });
});
</script>
@endpush

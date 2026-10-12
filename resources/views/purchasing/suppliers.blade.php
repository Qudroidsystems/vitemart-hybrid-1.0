@extends('layouts.master')

@section('content')
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0">Suppliers</h4>
            <p class="text-muted mb-0 mt-1 fs-sm">@if (\App\Services\MultiBranch\Install::isBranch()) Head office manages this list; it reaches every branch with the sync. @else Who you buy from – used on purchase orders, goods received and returns. @endif</p>
        </div>
        <div class="d-flex gap-2">
            <form method="GET"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search"></form>
            @if ($canEdit)<button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#supModal"><i class="bi bi-plus-lg me-1"></i>Add supplier</button>@endif
        </div>
    </div>
    @include('layouts.partials.flash')
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Code</th><th>Name</th><th>Contact</th><th>Phone</th><th class="text-end">Terms</th><th class="text-end">Orders</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($suppliers as $s)
                    <tr>
                        <td class="vm-mono">{{ $s->code }}</td>
                        <td class="fw-semibold">{{ $s->name }}<div class="fs-xs text-muted">{{ $s->address }}</div></td>
                        <td>{{ $s->contact_person }}<div class="fs-xs text-muted">{{ $s->email }}</div></td>
                        <td>{{ $s->phone }}</td>
                        <td class="text-end">{{ $s->payment_terms_days ? $s->payment_terms_days . ' days' : 'cash' }}</td>
                        <td class="text-end">{{ $s->purchase_orders_count }}</td>
                        <td>@if ($s->is_active)<span class="badge bg-success-subtle text-success">Active</span>@else<span class="badge bg-light text-muted">Off</span>@endif</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('purchase-orders.index', ['supplier_id' => $s->id]) }}" class="btn btn-sm btn-light">Orders</a>
                            @if ($canEdit)<button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#supModal" data-s="{{ json_encode($s->only(['id', 'code', 'name', 'contact_person', 'phone', 'email', 'address', 'tax_id', 'payment_terms_days', 'lead_time_days', 'bank_details', 'notes', 'is_active'])) }}">Edit</button>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No suppliers yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($suppliers->hasPages())<div class="card-footer">{{ $suppliers->links() }}</div>@endif
    </div>
</div>
</div>
</div>

@if ($canEdit)
<div class="modal fade" id="supModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><form class="modal-content" method="POST" id="supForm" action="{{ route('suppliers.store') }}">
        @csrf <input type="hidden" name="_method" value="POST" id="supMethod">
        <div class="modal-header"><h5 class="modal-title" id="supTitle">Add supplier</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-md-3"><label class="form-label">Code</label><input name="code" class="form-control text-uppercase vm-mono" maxlength="20" placeholder="NESTLE"></div>
            <div class="col-md-9"><label class="form-label">Name *</label><input name="name" class="form-control" required maxlength="255"></div>
            <div class="col-md-4"><label class="form-label">Contact person</label><input name="contact_person" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Phone</label><input name="phone" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Email</label><input name="email" type="email" class="form-control"></div>
            <div class="col-md-8"><label class="form-label">Address</label><input name="address" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Tax ID</label><input name="tax_id" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">Pay within (days)</label><input name="payment_terms_days" type="number" min="0" class="form-control" value="0"></div>
            <div class="col-md-3"><label class="form-label">Delivers in (days)</label><input name="lead_time_days" type="number" min="0" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Bank details</label><input name="bank_details" class="form-control"></div>
            <div class="col-12"><label class="form-label">Notes</label><input name="notes" class="form-control"></div>
            <div class="col-12 form-check form-switch ms-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="supActive" checked><label class="form-check-label" for="supActive">Active</label></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    </form></div>
</div>
@endif
@endsection

@if ($canEdit)
@push('page-scripts')
<script>
document.getElementById('supModal').addEventListener('show.bs.modal', e => {
    const f = document.getElementById('supForm'); const s = e.relatedTarget?.dataset.s ? JSON.parse(e.relatedTarget.dataset.s) : null;
    f.reset(); document.getElementById('supTitle').textContent = s ? 'Edit ' + s.name : 'Add supplier';
    f.action = s ? `{{ url('/suppliers') }}/${s.id}` : @json(route('suppliers.store'));
    document.getElementById('supMethod').value = s ? 'PUT' : 'POST';
    if (s) { Object.entries(s).forEach(([k, v]) => { if (f.elements[k] && f.elements[k].type !== 'checkbox') f.elements[k].value = v ?? ''; }); f.elements.is_active.checked = !!s.is_active; }
});
</script>
@endpush
@endif

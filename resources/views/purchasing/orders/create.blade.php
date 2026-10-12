@extends('layouts.master')

@section('content')
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div><h4 class="mb-sm-0">New purchase order</h4><p class="text-muted mb-0 mt-1 fs-sm">Order in the packs the supplier sells (cartons…); the cost is per pack.</p></div>
        <a href="{{ route('purchase-orders.index') }}" class="btn btn-light btn-sm"><i class="bi bi-arrow-left me-1"></i>All orders</a>
    </div>
    <div class="row g-3">
        <div class="col-xl-9">
            <div class="card" id="editor">
                <div class="card-header position-relative">
                    <input type="text" class="form-control form-control-lg le-scan" placeholder="Scan a barcode or type a product name…" autocomplete="off">
                    <div class="list-group le-results shadow"></div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 le-table">
                        <thead class="table-light"><tr><th>Product</th><th class="text-end">In stock</th><th>Pack</th><th style="width:110px">Qty</th><th style="width:140px">Cost per pack</th><th></th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-3">
            <div class="card"><div class="card-body">
                <label class="form-label">Supplier *</label>
                <select id="supplier" class="form-select mb-3"><option value="">Choose…</option>@foreach ($suppliers as $s)<option value="{{ $s->id }}" data-lead="{{ $s->lead_time_days }}">{{ $s->name }}</option>@endforeach</select>
                @if ($branches->isNotEmpty())
                    <label class="form-label">Deliver to</label>
                    <select id="branch" class="form-select mb-3">@foreach ($branches as $b)<option value="{{ $b->id }}" @selected($b->id === \App\Services\MultiBranch\Install::branchId())>{{ $b->code }} – {{ $b->name }}</option>@endforeach</select>
                @endif
                <label class="form-label">Expected delivery</label>
                <input type="date" id="expected" class="form-control mb-3">
                <label class="form-label">Notes</label>
                <textarea id="notes" class="form-control mb-3" rows="2" maxlength="500"></textarea>
                <div class="d-flex justify-content-between mb-3"><span class="text-muted">Total</span><span class="fs-5 fw-semibold" id="total">0</span></div>
                <button class="btn btn-primary w-100 mb-2 save" data-action="submit">Save and submit</button>
                <button class="btn btn-light w-100 save" data-action="draft">Save as draft</button>
                @if (!\Illuminate\Support\Facades\Schema::hasTable('suppliers') || $suppliers->isEmpty())<div class="alert alert-warning mt-3 fs-sm mb-0">Add a supplier first.</div>@endif
            </div></div>
        </div>
    </div>
</div>
</div>
</div>
@include('layouts.partials.line-editor')
@endsection

@push('page-scripts')
<script>
(() => {
    const packCost = (it, packId) => { const p = (it.packs || []).find(x => String(x.id) === String(packId)) || {qty: 1}; return (Number(it.cost_price || 0) * Number(p.qty || 1)).toFixed(2); };
    const ed = VM.lineEditor({
        root: document.getElementById('editor'), lookup: @json(route('products.lookup')), showStock: true,
        fields: [{key: 'qty', type: 'number', default: 1}, {key: 'unit_cost', type: 'number', default: (it, pid) => packCost(it, pid)}],
        onPackChange: r => { r.unit_cost = packCost(r.item, r.pack_id); },
        onChange: e => document.getElementById('total').textContent = VM.money(e.rows.reduce((a, r) => a + Number(r.qty || 0) * Number(r.unit_cost || 0), 0)),
    });
    document.getElementById('supplier').addEventListener('change', e => {
        const lead = Number(e.target.selectedOptions[0]?.dataset.lead || 0);
        if (lead && !document.getElementById('expected').value) { const d = new Date(); d.setDate(d.getDate() + lead); document.getElementById('expected').value = d.toISOString().slice(0, 10); }
    });
    document.querySelectorAll('.save').forEach(b => b.addEventListener('click', async () => {
        const lines = ed.payload().filter(l => Number(l.qty) > 0);
        if (!document.getElementById('supplier').value) return VM.toast('Choose the supplier', 'warning');
        if (!lines.length) return VM.toast('Add at least one product', 'warning');
        b.disabled = true;
        try {
            const r = await VM.api(@json(route('purchase-orders.store')), {method: 'POST', body: {
                supplier_id: document.getElementById('supplier').value, branch_id: document.getElementById('branch')?.value || null,
                expected_date: document.getElementById('expected').value || null, notes: document.getElementById('notes').value, action: b.dataset.action, lines}});
            VM.toast(r.message); location.href = r.redirect;
        } catch (err) { VM.error(err); b.disabled = false; }
    }));
})();
</script>
@endpush

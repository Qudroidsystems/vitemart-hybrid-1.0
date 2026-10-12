@extends('layouts.master')

@section('content')
@php
    $prefill = [];
    if ($grn) {
        foreach ($grn->lines as $l) {
            $packs = \App\Models\ProductUnit::with('unit')->where('product_id', $l->product_id)->get()
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->unit->name ?? 'unit', 'qty' => (float) $u->quantity_per_unit, 'is_base' => (bool) $u->is_base])->values();
            $prefill[] = ['item' => ['key' => $l->product_id . ':' . ($l->product_variation_id ?? 0), 'product_id' => $l->product_id, 'variation_id' => $l->product_variation_id,
                'title' => ($l->product->title ?? '?') . ($l->variation ? ' – ' . $l->variation->label : ''), 'sku' => $l->product->sku ?? '', 'barcode' => $l->product->barcode ?? '',
                'packs' => $packs, 'unit' => 'unit', 'stock' => 0], 'pack_id' => $l->product_unit_id, 'unit_cost' => $l->unit_cost, 'note' => 'received ' . qty($l->quantity)];
        }
    }
@endphp
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div><h4 class="mb-sm-0">Return to supplier {{ $grn ? '– from ' . $grn->number : '' }}</h4><p class="text-muted mb-0 mt-1 fs-sm">Stock leaves the chosen location when you post.</p></div>
        <a href="{{ route('supplier-returns.index') }}" class="btn btn-light btn-sm"><i class="bi bi-arrow-left me-1"></i>All returns</a>
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
                        <thead class="table-light"><tr><th>Product</th><th class="text-end">In stock</th><th>Pack</th><th style="width:110px">Qty</th><th style="width:130px">Cost / pack</th><th></th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-3">
            <div class="card"><div class="card-body">
                <label class="form-label">Supplier *</label>
                <select id="supplier" class="form-select mb-3"><option value="">Choose…</option>
                    @foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected($grn && $grn->supplier_id === $s->id)>{{ $s->name }}</option>@endforeach</select>
                <label class="form-label">Take from</label>
                <select id="location" class="form-select mb-3">@foreach ($locations as $l)<option value="{{ $l->id }}">{{ $l->name }}{{ $l->type === 'quarantine' ? ' (quarantine)' : '' }}</option>@endforeach</select>
                <label class="form-label">Reason *</label>
                <input id="reason" class="form-control mb-3" maxlength="255" placeholder="e.g. expired on arrival, wrong item">
                <label class="form-label">Supplier credit note</label>
                <input id="credit" class="form-control mb-3" maxlength="80">
                <div class="d-flex justify-content-between mb-3"><span class="text-muted">Value</span><span class="fs-5 fw-semibold" id="total">0</span></div>
                <button class="btn btn-danger w-100" id="post">Post return</button>
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
    const packCost = (it, pid) => { const p = (it.packs || []).find(x => String(x.id) === String(pid)) || {qty: 1}; return (Number(it.cost_price || 0) * Number(p.qty || 1)).toFixed(2); };
    const ed = VM.lineEditor({
        root: document.getElementById('editor'), lookup: @json(route('products.lookup')), showStock: true,
        lookupParams: () => 'location_id=' + document.getElementById('location').value,
        fields: [{key: 'qty', type: 'number', default: 1}, {key: 'unit_cost', type: 'number', default: (it, pid) => packCost(it, pid)}],
        onChange: e => document.getElementById('total').textContent = VM.money(e.rows.reduce((a, r) => a + Number(r.qty || 0) * Number(r.unit_cost || 0), 0)),
    });
    @json($prefill).reverse().forEach(p => ed.add(p.item, {qty: '', unit_cost: p.unit_cost, note: p.note}, p.pack_id));
    document.getElementById('post').addEventListener('click', async e => {
        const lines = ed.payload().filter(l => Number(l.qty) > 0);
        if (!document.getElementById('supplier').value) return VM.toast('Choose the supplier', 'warning');
        if (!document.getElementById('reason').value.trim()) return VM.toast('Enter the reason', 'warning');
        if (!lines.length) return VM.toast('Enter what is going back', 'warning');
        if (!await VM.confirm('Post this return?', 'Stock goes down now.', 'Post')) return;
        e.currentTarget.disabled = true;
        try {
            const r = await VM.api(@json(route('supplier-returns.store')), {method: 'POST', body: {
                supplier_id: document.getElementById('supplier').value, location_id: document.getElementById('location').value,
                goods_receipt_id: @json($grn?->id), reason: document.getElementById('reason').value, credit_note: document.getElementById('credit').value, lines}});
            VM.toast(r.message); location.href = r.redirect;
        } catch (err) { VM.error(err); e.currentTarget.disabled = false; }
    });
})();
</script>
@endpush

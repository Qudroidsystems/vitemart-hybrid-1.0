@extends('layouts.master')

@section('content')
@php
    $prefill = [];
    if ($po) {
        foreach ($po->lines as $l) {
            if ($l->outstanding() <= 0) continue;
            $packs = $l->product ? \App\Models\ProductUnit::with('unit')->where('product_id', $l->product_id)->get()
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->unit->name ?? 'unit', 'qty' => (float) $u->quantity_per_unit, 'is_base' => (bool) $u->is_base])->values() : [];
            $prefill[] = [
                'item' => ['key' => $l->product_id . ':' . ($l->product_variation_id ?? 0) . ':' . $l->id, 'product_id' => $l->product_id, 'variation_id' => $l->product_variation_id,
                    'title' => ($l->product->title ?? '?') . ($l->variation ? ' – ' . $l->variation->label : ''), 'sku' => $l->product->sku ?? '', 'barcode' => $l->product->barcode ?? '',
                    'packs' => $packs, 'unit' => 'unit', 'stock' => 0],
                'pack_id' => $l->product_unit_id, 'qty' => $l->outstanding(), 'free_qty' => '', 'unit_cost' => $l->unit_cost,
                'note' => 'ordered ' . qty($l->ordered_qty) . ', still due ' . qty($l->outstanding()), 'extra' => ['po_line_id' => $l->id],
            ];
        }
    }
@endphp
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div><h4 class="mb-sm-0">Receive goods {{ $po ? 'on ' . $po->number : '' }}</h4>
            <p class="text-muted mb-0 mt-1 fs-sm">Count what arrived. Quantities and costs are per pack; free / bonus goods go in “Free”. Stock and cost prices update when you post.</p></div>
        <a href="{{ route('goods-receipts.index') }}" class="btn btn-light btn-sm"><i class="bi bi-arrow-left me-1"></i>All deliveries</a>
    </div>
    <div class="row g-3">
        <div class="col-xl-9">
            <div class="card" id="editor">
                <div class="card-header position-relative">
                    <input type="text" class="form-control form-control-lg le-scan" placeholder="Scan a barcode or type a product name to add it…" autocomplete="off">
                    <div class="list-group le-results shadow"></div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 le-table">
                        <thead class="table-light"><tr><th>Product</th><th>Pack</th><th style="width:90px">Qty</th><th style="width:80px">Free</th><th style="width:120px">Cost / pack</th><th style="width:150px">Expiry</th><th style="width:120px">Batch</th><th></th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-3">
            <div class="card"><div class="card-body">
                <label class="form-label">Order</label>
                <select id="po" class="form-select mb-3" onchange="location.href='{{ route('goods-receipts.create') }}' + (this.value ? '?po=' + this.value : '')">
                    <option value="">No order (delivery without a PO)</option>
                    @foreach ($openOrders as $o)<option value="{{ $o->id }}" @selected($po && $po->id === $o->id)>{{ $o->number }} – {{ $o->supplier->name ?? '' }}</option>@endforeach
                </select>
                <label class="form-label">Supplier</label>
                <select id="supplier" class="form-select mb-3" @disabled($po)>
                    <option value="">Choose…</option>
                    @foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected($po && $po->supplier_id === $s->id)>{{ $s->name }}</option>@endforeach
                </select>
                <label class="form-label">Into</label>
                <select id="location" class="form-select mb-3">@foreach ($locations as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach</select>
                <div class="row g-2 mb-3">
                    <div class="col-7"><label class="form-label">Supplier invoice</label><input id="invoice" class="form-control" maxlength="80"></div>
                    <div class="col-5"><label class="form-label">Date</label><input type="date" id="invdate" class="form-control" value="{{ now()->toDateString() }}"></div>
                </div>
                <label class="form-label">Notes</label>
                <textarea id="notes" class="form-control mb-3" rows="2" maxlength="500"></textarea>
                <div class="d-flex justify-content-between mb-3"><span class="text-muted">Invoice total</span><span class="fs-5 fw-semibold" id="total">0</span></div>
                <button class="btn btn-success w-100" id="post"><i class="bi bi-box-seam me-1"></i>Post delivery</button>
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
        root: document.getElementById('editor'), lookup: @json(route('products.lookup')), allowDuplicates: true,
        fields: [{key: 'qty', type: 'number', default: 1}, {key: 'free_qty', type: 'number', placeholder: '0'}, {key: 'unit_cost', type: 'number', default: (it, pid) => packCost(it, pid)},
            {key: 'expiry_date', type: 'date'}, {key: 'batch_number', type: 'text'}],
        onChange: e => document.getElementById('total').textContent = VM.money(e.rows.reduce((a, r) => a + Number(r.qty || 0) * Number(r.unit_cost || 0), 0)),
    });
    @json($prefill).reverse().forEach(p => ed.add(p.item, {qty: p.qty, unit_cost: p.unit_cost, note: p.note, extra: p.extra}, p.pack_id));
    document.getElementById('post').addEventListener('click', async e => {
        const lines = ed.payload().filter(l => Number(l.qty) > 0 || Number(l.free_qty) > 0);
        const po = document.getElementById('po').value;
        if (!po && !document.getElementById('supplier').value) return VM.toast('Choose the supplier', 'warning');
        if (!lines.length) return VM.toast('Enter what arrived', 'warning');
        if (!await VM.confirm('Post this delivery?', lines.length + ' line(s) – stock goes up now.', 'Post')) return;
        e.currentTarget.disabled = true;
        try {
            const r = await VM.api(@json(route('goods-receipts.store')), {method: 'POST', body: {
                purchase_order_id: po || null, supplier_id: document.getElementById('supplier').value || null, location_id: document.getElementById('location').value,
                supplier_invoice: document.getElementById('invoice').value, invoice_date: document.getElementById('invdate').value, notes: document.getElementById('notes').value, lines}});
            VM.toast(r.message); location.href = r.redirect;
        } catch (err) { VM.error(err); e.currentTarget.disabled = false; }
    });
})();
</script>
@endpush

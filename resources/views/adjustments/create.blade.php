@extends('layouts.master')

@section('content')
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0" id="pageTitle">{{ $pagetitle }}</h4>
            <p class="text-muted mb-0 mt-1 fs-sm" id="typeHelp"></p>
        </div>
        <a href="{{ route('adjustments.index') }}" class="btn btn-light btn-sm mt-2 mt-sm-0"><i class="bi bi-arrow-left me-1"></i>All adjustments</a>
    </div>

    <form id="adjForm" novalidate>
        <div class="card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Type</label>
                        <select name="type" id="type" class="form-select">
                            @foreach ($types as $k => $v)<option value="{{ $k }}" @selected($type === $k)>{{ $v }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Store / location</label>
                        <select name="location_id" id="location" class="form-select">
                            @foreach ($locations as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Reason <span class="text-danger">*</span></label>
                        <input name="reason" id="reason" class="form-control" list="reasons" required>
                        <datalist id="reasons">@foreach ($reasons as $r)<option value="{{ $r }}">@endforeach</datalist>
                    </div>
                    <div class="col-md-3 receive-only">
                        <label class="form-label">Supplier</label>
                        <input name="supplier" class="form-control" placeholder="Supplier name">
                    </div>
                    <div class="col-md-3 receive-only">
                        <label class="form-label">Supplier invoice / waybill no.</label>
                        <input name="supplier_invoice" class="form-control">
                    </div>
                    <div class="col">
                        <label class="form-label">Notes</label>
                        <input name="notes" class="form-control" placeholder="Optional">
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="position-relative">
                    <i class="bi bi-upc-scan position-absolute top-50 translate-middle-y ms-3 text-muted fs-5"></i>
                    <input id="scan" class="form-control form-control-lg ps-5" placeholder="Scan a barcode or type a product name to add it…" autocomplete="off" autofocus>
                    <div id="results" class="list-group position-absolute w-100 shadow" style="z-index:20;max-height:320px;overflow:auto;display:none"></div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0" id="itemsTable">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th class="text-end" style="width:110px">In stock</th>
                            <th style="width:140px" class="mode-col">Action</th>
                            <th style="width:120px" id="qtyHead">Quantity</th>
                            <th style="width:150px">Unit</th>
                            <th style="width:130px" class="receive-only">Cost / unit</th>
                            <th style="width:150px" class="receive-only">Expiry · batch</th>
                            <th class="text-end" style="width:120px">Change</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="text-center text-muted py-5" id="emptyMsg"><i class="bi bi-upc-scan fs-1 d-block mb-2"></i>Scan or search to add products.</div>
            <div class="card-footer d-flex flex-wrap gap-2 align-items-center vm-sticky-actions">
                <div class="me-auto fs-sm">
                    <strong id="sumItems">0</strong> products ·
                    value change at cost <strong id="sumValue">0</strong>
                </div>
                <button type="button" class="btn btn-light" data-action="draft">Save draft</button>
                @if ($canApprove)
                    <button type="button" class="btn btn-outline-primary" data-action="submit">Submit for approval</button>
                    <button type="button" class="btn btn-success" data-action="approve"><i class="bi bi-check-lg me-1"></i>Approve & update stock</button>
                @else
                    <button type="button" class="btn btn-primary" data-action="submit"><i class="bi bi-send me-1"></i>{{ $needsApproval ? 'Submit for approval' : 'Save & update stock' }}</button>
                @endif
            </div>
        </div>
    </form>
</div>
</div>
</div>
@endsection

@push('page-scripts')
<script>
(function () {
    const LOOKUP = @json(route('products.lookup'));
    const HELP = {
        adjustment: 'Add or remove stock with a reason (found stock, internal use, correction…).',
        count: 'Enter what you physically counted. The difference from the system quantity is posted when approved – sales made while you count are not lost.',
        receive: 'Record a delivery from a supplier. Enter cost per unit to keep cost prices up to date.',
        damage: 'Write off broken or damaged items.',
        expired: 'Write off expired items.',
    };
    const DEFAULT_REASON = {adjustment: '', count: 'Stock count correction', receive: 'Goods received', damage: 'Damaged', expired: 'Expired'};
    const $ = (s, el = document) => el.querySelector(s);
    const $$ = (s, el = document) => [...el.querySelectorAll(s)];
    const typeSel = $('#type'), body = $('#itemsTable tbody');
    let rows = [];

    function applyType() {
        const t = typeSel.value;
        $('#pageTitle').textContent = typeSel.selectedOptions[0].text;
        $('#typeHelp').textContent = HELP[t] || '';
        $$('.receive-only').forEach(e => e.style.display = t === 'receive' ? '' : 'none');
        $$('.mode-col').forEach(e => e.style.display = t === 'adjustment' ? '' : 'none');
        $('#qtyHead').textContent = t === 'count' ? 'Counted' : 'Quantity';
        if (!$('#reason').value || Object.values(DEFAULT_REASON).includes($('#reason').value)) $('#reason').value = DEFAULT_REASON[t] || '';
        render();
    }
    typeSel.addEventListener('change', applyType);

    function modeFor(r) {
        const t = typeSel.value;
        return t === 'count' ? 'set' : t === 'receive' ? 'add' : (t === 'damage' || t === 'expired') ? 'remove' : r.mode;
    }
    function change(r) {
        const pack = r.item.packs.find(p => String(p.id) === String(r.pack_id)) || {qty: 1};
        const base = Number(r.qty || 0) * Number(pack.qty || 1);
        const m = modeFor(r);
        return m === 'set' ? base - Number(r.item.stock) : (m === 'remove' ? -base : base);
    }

    function render() {
        $('#emptyMsg').style.display = rows.length ? 'none' : '';
        const t = typeSel.value;
        body.innerHTML = rows.map((r, i) => {
            const c = change(r);
            const packs = r.item.packs.length ? r.item.packs : [{id: '', name: r.item.unit, qty: 1}];
            return `<tr data-i="${i}">
                <td><div class="fw-medium">${VM.esc(r.item.title)}</div><div class="fs-xs text-muted vm-mono">${VM.esc(r.item.sku || '')} ${VM.esc(r.item.barcode || '')}</div></td>
                <td class="text-end">${VM.qty(r.item.stock)} <span class="text-muted fs-xs">${VM.esc(r.item.unit)}</span></td>
                <td class="mode-col" style="${t === 'adjustment' ? '' : 'display:none'}">
                    <select class="form-select form-select-sm" data-f="mode"><option value="add" ${r.mode === 'add' ? 'selected' : ''}>Add</option><option value="remove" ${r.mode === 'remove' ? 'selected' : ''}>Remove</option><option value="set" ${r.mode === 'set' ? 'selected' : ''}>Set to</option></select></td>
                <td><input type="number" step="any" min="0" class="form-control form-control-sm" data-f="qty" value="${r.qty ?? ''}"></td>
                <td><select class="form-select form-select-sm" data-f="pack_id">${packs.map(p => `<option value="${p.id}" ${String(p.id) === String(r.pack_id) ? 'selected' : ''}>${VM.esc(p.name)}${p.qty != 1 ? ' (×' + VM.qty(p.qty) + ')' : ''}</option>`).join('')}</select></td>
                <td class="receive-only" style="${t === 'receive' ? '' : 'display:none'}"><input type="number" step="0.01" min="0" class="form-control form-control-sm" data-f="unit_cost" value="${r.unit_cost ?? ''}" placeholder="${VM.esc(r.item.cost_price || '')}"></td>
                <td class="receive-only" style="${t === 'receive' ? '' : 'display:none'}"><input type="date" class="form-control form-control-sm mb-1" data-f="expiry_date" value="${r.expiry_date || ''}"><input class="form-control form-control-sm" data-f="batch_number" placeholder="Batch" value="${VM.esc(r.batch_number || '')}"></td>
                <td class="text-end fw-semibold ${c < 0 ? 'text-danger' : (c > 0 ? 'text-success' : 'text-muted')}">${c > 0 ? '+' : ''}${VM.qty(c)}<div class="fs-xs text-muted fw-normal">→ ${VM.qty(Number(r.item.stock) + c)}</div></td>
                <td><button type="button" class="btn btn-sm btn-link text-danger" data-rm><i class="bi bi-x-lg"></i></button></td></tr>`;
        }).join('');
        const value = rows.reduce((a, r) => {
            const pack = r.item.packs.find(p => String(p.id) === String(r.pack_id)) || {qty: 1};
            const unitCost = r.unit_cost !== undefined && r.unit_cost !== '' && r.unit_cost !== null ? Number(r.unit_cost) / Number(pack.qty || 1) : Number(r.item.cost_price || 0);
            return a + change(r) * unitCost;
        }, 0);
        $('#sumItems').textContent = rows.length;
        $('#sumValue').textContent = VM.money(value);
        $('#sumValue').className = value < 0 ? 'text-danger' : 'text-success';
    }

    body.addEventListener('input', e => {
        const tr = e.target.closest('tr'); const f = e.target.dataset.f; if (!tr || !f) return;
        rows[tr.dataset.i][f] = e.target.value;
        if (f !== 'qty' && f !== 'unit_cost' && f !== 'batch_number') render();
        else { const pos = e.target.selectionStart; render(); const el = $(`tr[data-i="${tr.dataset.i}"] [data-f="${f}"]`); el.focus(); try { el.setSelectionRange(pos, pos); } catch (x) {} }
    });
    body.addEventListener('change', e => { const tr = e.target.closest('tr'); if (tr && e.target.dataset.f) { rows[tr.dataset.i][e.target.dataset.f] = e.target.value; render(); } });
    body.addEventListener('click', e => { if (e.target.closest('[data-rm]')) { rows.splice(e.target.closest('tr').dataset.i, 1); render(); } });
    body.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); $('#scan').focus(); } });

    function addItem(item, qty = null, packId = null) {
        const existing = rows.find(r => r.item.key === item.key && (!packId || String(r.pack_id) === String(packId)));
        if (existing) { existing.qty = Number(existing.qty || 0) + 1; render(); return; }
        const base = item.packs.find(p => p.is_base) || item.packs[0] || {id: ''};
        rows.unshift({item, qty: qty ?? (typeSel.value === 'count' ? '' : 1), pack_id: packId || base.id, mode: 'add'});
        render();
        const q = $('tr[data-i="0"] [data-f=qty]'); q && (q.focus(), q.select());
    }

    const scan = $('#scan'), results = $('#results');
    let found = [];
    async function search(exactOnEnter) {
        const q = scan.value.trim(); if (!q) return;
        try {
            found = await VM.api(LOOKUP + '?q=' + encodeURIComponent(q) + '&location_id=' + $('#location').value);
            const byBarcode = found.filter(i => i.barcode === q || i.packs.some(p => p.barcode === q));
            if (exactOnEnter && (byBarcode.length === 1 || found.length === 1)) {
                const it = byBarcode[0] || found[0];
                const pk = it.packs.find(p => p.barcode === q);
                addItem(it, null, pk ? pk.id : null); scan.value = ''; results.style.display = 'none'; return;
            }
            results.innerHTML = found.map((it, i) => `<button type="button" class="list-group-item list-group-item-action d-flex justify-content-between" data-i="${i}"><span>${VM.esc(it.title)} <span class="fs-xs text-muted vm-mono">${VM.esc(it.sku || '')}</span></span><span class="text-muted">${VM.qty(it.stock)} ${VM.esc(it.unit)}</span></button>`).join('')
                || '<div class="list-group-item text-muted">No products found</div>';
            results.style.display = 'block';
        } catch (e) { VM.error(e); }
    }
    scan.addEventListener('input', VM.debounce(() => { if (scan.value.trim().length >= 2 && !/^\d{8,}$/.test(scan.value.trim())) search(false); }, 250));
    scan.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); search(true); } if (e.key === 'Escape') results.style.display = 'none'; });
    results.addEventListener('click', e => { const b = e.target.closest('[data-i]'); if (!b) return; addItem(found[b.dataset.i]); scan.value = ''; results.style.display = 'none'; });
    document.addEventListener('click', e => { if (!e.target.closest('#results') && e.target !== scan) results.style.display = 'none'; });

    $('#location').addEventListener('change', async () => {
        if (!rows.length) return;
        const ids = [...new Set(rows.map(r => r.item.product_id))].join(',');
        const fresh = await VM.api(LOOKUP + '?ids=' + ids + '&location_id=' + $('#location').value);
        rows.forEach(r => { const f = fresh.find(x => x.key === r.item.key); if (f) r.item.stock = f.stock; });
        render();
    });

    $$('[data-action]').forEach(btn => btn.addEventListener('click', async () => {
        const action = btn.dataset.action;
        if (!rows.length) return VM.toast('Add at least one product', 'warning');
        if (!$('#reason').value.trim()) { $('#reason').classList.add('is-invalid'); $('#reason').focus(); return; }
        const missing = rows.filter(r => r.qty === '' || r.qty === null || r.qty === undefined);
        if (missing.length) return VM.error(new Error('Enter a quantity for: ' + missing.map(r => r.item.title).join(', ')));
        const fd = new FormData($('#adjForm'));
        const payload = Object.fromEntries([...fd.entries()].filter(([k]) => !k.startsWith('_')));
        payload.action = action;
        payload.items = rows.map(r => ({
            product_id: r.item.product_id, variation_id: r.item.variation_id, pack_id: r.pack_id || null, mode: modeFor(r),
            quantity: Number(r.qty || 0), unit_cost: r.unit_cost === '' || r.unit_cost === undefined ? null : Number(r.unit_cost),
            expiry_date: r.expiry_date || null, batch_number: r.batch_number || null,
        }));
        if (action === 'approve' && !await VM.confirm('Approve and update stock now?', rows.length + ' product(s) will change.', 'Approve')) return;
        btn.disabled = true;
        try {
            const r = await VM.api(@json(route('adjustments.store')), {method: 'POST', body: payload});
            await Swal.fire({icon: r.status === 'approved' ? 'success' : 'info', title: r.message, timer: 1800, showConfirmButton: false});
            location.href = r.redirect;
        } catch (e) { VM.error(e); } finally { btn.disabled = false; }
    }));

    // Pre-load products chosen on the product list
    const preIds = @json($ids);
    if (preIds) {
        VM.api(LOOKUP + '?ids=' + preIds + '&location_id=' + $('#location').value).then(items => { items.forEach(it => addItem(it)); }).catch(() => {});
    }
    applyType();
})();
</script>
@endpush

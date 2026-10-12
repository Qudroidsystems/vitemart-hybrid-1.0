{{--
    Reusable product-lines editor (transfers, purchase orders, goods received, supplier returns).
    Markup expected inside `root`:  input.le-scan, div.le-results (list-group), table.le-table > tbody
    Usage:
        const ed = VM.lineEditor({root: el, lookup: url, fields: [{key: 'qty', label: 'Qty', type: 'number'}], showStock: true});
        ed.add(item, {qty: 2});  ed.payload();  ed.rows
--}}
<style>
    .le-results { position: absolute; z-index: 1050; max-height: 320px; overflow: auto; width: 100%; display: none; }
    .le-table td { vertical-align: middle; }
    .le-table input.form-control-sm, .le-table select.form-select-sm { min-width: 80px; }
</style>
<script>
(function (VM) {
    VM.lineEditor = function (opts) {
        const root = opts.root;
        const scan = root.querySelector('.le-scan');
        const results = root.querySelector('.le-results');
        const body = root.querySelector('.le-table tbody');
        const fields = opts.fields || [{key: 'qty', label: 'Qty', type: 'number'}];
        const ed = {rows: [], onChange: opts.onChange || (() => {})};
        let found = [];

        const packOf = r => (r.item.packs || []).find(p => String(p.id) === String(r.pack_id)) || {qty: 1, name: r.item.unit || 'unit'};

        ed.render = function () {
            if (!ed.rows.length) {
                body.innerHTML = `<tr><td colspan="${fields.length + 4}" class="text-center text-muted py-4">${opts.empty || 'Scan or search for products to add them.'}</td></tr>`;
                ed.onChange(ed);
                return;
            }
            body.innerHTML = ed.rows.map((r, i) => {
                const packs = (r.item.packs && r.item.packs.length) ? r.item.packs : [{id: '', name: r.item.unit || 'unit', qty: 1}];
                return `<tr data-i="${i}">
                    <td><div class="fw-medium">${VM.esc(r.item.title)}</div><div class="fs-xs text-muted vm-mono">${VM.esc(r.item.sku || '')} ${VM.esc(r.item.barcode || '')}${r.note ? ' · <span class="text-primary">' + VM.esc(r.note) + '</span>' : ''}</div></td>
                    ${opts.showStock ? `<td class="text-end text-nowrap">${VM.qty(r.item.stock)} <span class="fs-xs text-muted">${VM.esc(r.item.unit || '')}</span></td>` : ''}
                    <td><select class="form-select form-select-sm" data-f="pack_id" ${opts.lockPack ? 'disabled' : ''}>${packs.map(p => `<option value="${p.id}" ${String(p.id) === String(r.pack_id) ? 'selected' : ''}>${VM.esc(p.name)}${Number(p.qty) !== 1 ? ' (×' + VM.qty(p.qty) + ')' : ''}</option>`).join('')}</select></td>
                    ${fields.map(f => `<td style="${f.width ? 'width:' + f.width : ''}">${f.type === 'text' || f.type === 'date'
                        ? `<input type="${f.type}" class="form-control form-control-sm" data-f="${f.key}" value="${VM.esc(r[f.key] ?? '')}" placeholder="${VM.esc(f.placeholder || '')}">`
                        : `<input type="number" step="any" min="0" class="form-control form-control-sm" data-f="${f.key}" value="${r[f.key] ?? ''}" placeholder="${VM.esc(typeof f.placeholder === 'function' ? f.placeholder(r) : (f.placeholder || ''))}">`}</td>`).join('')}
                    <td class="text-end"><button type="button" class="btn btn-sm btn-link text-danger" data-rm title="Remove"><i class="bi bi-x-lg"></i></button></td></tr>`;
            }).join('');
            ed.onChange(ed);
        };

        ed.add = function (item, values = {}, packId = null) {
            const base = (item.packs || []).find(p => p.is_base) || (item.packs || [])[0] || {id: ''};
            const pid = packId || values.pack_id || base.id;
            const existing = ed.rows.find(r => r.item.key === item.key && String(r.pack_id) === String(pid));
            if (existing && !opts.allowDuplicates) {
                existing.qty = Number(existing.qty || 0) + 1;
                ed.render();
                return existing;
            }
            const row = Object.assign({item, pack_id: pid}, Object.fromEntries(fields.map(f => [f.key, typeof f.default === 'function' ? f.default(item, pid) : (f.default ?? '')])), values);
            ed.rows.unshift(row);
            ed.render();
            const q = body.querySelector(`tr[data-i="0"] [data-f="${fields[0].key}"]`);
            q && (q.focus(), q.select && q.select());
            return row;
        };

        ed.payload = function () {
            return ed.rows.map(r => Object.assign({product_id: r.item.product_id, variation_id: r.item.variation_id || null, pack_id: r.pack_id || null},
                r.extra || {}, Object.fromEntries(fields.map(f => [f.key, r[f.key] === '' ? null : r[f.key]]))));
        };
        ed.packOf = packOf;

        body.addEventListener('input', e => {
            const tr = e.target.closest('tr[data-i]'); const f = e.target.dataset.f; if (!tr || !f) return;
            ed.rows[tr.dataset.i][f] = e.target.value; ed.onChange(ed);
        });
        body.addEventListener('change', e => {
            const tr = e.target.closest('tr[data-i]'); const f = e.target.dataset.f; if (!tr || !f) return;
            ed.rows[tr.dataset.i][f] = e.target.value;
            if (f === 'pack_id' && opts.onPackChange) opts.onPackChange(ed.rows[tr.dataset.i]);
            ed.render();
        });
        body.addEventListener('click', e => { if (e.target.closest('[data-rm]')) { ed.rows.splice(e.target.closest('tr').dataset.i, 1); ed.render(); } });
        body.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); scan && scan.focus(); } });

        async function search(exact) {
            const q = scan.value.trim(); if (!q) return;
            try {
                const extra = typeof opts.lookupParams === 'function' ? opts.lookupParams() : '';
                found = await VM.api(opts.lookup + '?q=' + encodeURIComponent(q) + (extra ? '&' + extra : ''));
                if (opts.filter) found = found.filter(opts.filter);
                const byBarcode = found.filter(i => i.barcode === q || (i.packs || []).some(p => p.barcode === q));
                if (exact && (byBarcode.length === 1 || found.length === 1)) {
                    const it = byBarcode[0] || found[0];
                    const pk = (it.packs || []).find(p => p.barcode === q);
                    ed.add(it, {}, pk ? pk.id : null); scan.value = ''; results.style.display = 'none'; return;
                }
                results.innerHTML = found.map((it, i) => `<button type="button" class="list-group-item list-group-item-action d-flex justify-content-between" data-i="${i}"><span>${VM.esc(it.title)} <span class="fs-xs text-muted vm-mono">${VM.esc(it.sku || '')}</span></span><span class="text-muted">${opts.showStock ? VM.qty(it.stock) + ' ' + VM.esc(it.unit || '') : ''}</span></button>`).join('')
                    || `<div class="list-group-item text-muted">${opts.noMatch || 'No products found'}</div>`;
                results.style.display = 'block';
            } catch (err) { VM.error(err); }
        }
        if (scan) {
            scan.addEventListener('input', VM.debounce(() => { if (scan.value.trim().length >= 2 && !/^\d{8,}$/.test(scan.value.trim())) search(false); }, 250));
            scan.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); search(true); } if (e.key === 'Escape') results.style.display = 'none'; });
            results.addEventListener('click', e => { const b = e.target.closest('[data-i]'); if (!b) return; ed.add(found[b.dataset.i]); scan.value = ''; results.style.display = 'none'; });
            document.addEventListener('click', e => { if (!e.target.closest('.le-results') && e.target !== scan) results.style.display = 'none'; });
        }
        ed.render();
        return ed;
    };
})(window.VM = window.VM || {});
</script>

@extends('layouts.master')

@section('content')
<div class="main-content">
<div class="page-content">
<div class="container-fluid">

    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0">Price control</h4>
            <p class="text-muted mb-0 mt-1 fs-sm">Change many prices at once, preview the result, and undo if needed. Every change is logged.</p>
        </div>
        <a href="{{ route('pricing.history') }}" class="btn btn-light btn-sm mt-2 mt-sm-0"><i class="bi bi-clock-history me-1"></i>Full price history</a>
    </div>

    <div class="row g-3 mb-3">
        @foreach ([['Price changes today', $stats['changes_today'], 'bi-pencil-square', 'primary'], ['Last 30 days', $stats['changes_30d'], 'bi-calendar3', 'info'],
                   ['Promotions running', $stats['promos_active'], 'bi-megaphone', 'success'], ['Priced below cost', $stats['below_cost'], 'bi-exclamation-triangle', 'danger']] as [$l, $v, $i, $c])
            <div class="col-6 col-xl-3">
                <div class="card vm-stat mb-0 h-100"><div class="card-body d-flex gap-3 align-items-center">
                    <span class="avatar-sm"><span class="avatar-title rounded bg-{{ $c }}-subtle text-{{ $c }} fs-3"><i class="bi {{ $i }}"></i></span></span>
                    <div><div class="vm-stat-label">{{ $l }}</div><div class="vm-stat-value">{{ number_format($v) }}</div>
                        @if ($l === 'Priced below cost' && $v)<a class="fs-xs" href="{{ route('products.index', ['below_cost' => 1]) }}">Show products</a>@endif</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-xl-5">
            <div class="card">
                <div class="card-header"><h6 class="card-title mb-0">Bulk price change</h6></div>
                <form class="card-body" id="bulkForm">
                    <label class="form-label fw-semibold">1. Which products?</label>
                    @if ($preselected)
                        <div class="alert alert-primary py-2 d-flex align-items-center">
                            <i class="bi bi-check2-square me-2"></i><span><strong>{{ count($preselected) }}</strong> products selected from the product list</span>
                            <a href="{{ route('pricing.index') }}" class="ms-auto fs-sm">Clear</a>
                        </div>
                        @foreach ($preselected as $id)<input type="hidden" name="product_ids[]" value="{{ $id }}">@endforeach
                    @else
                        <div class="row g-2 mb-1">
                            <div class="col-6">
                                <select name="category_id" class="form-select">
                                    <option value="">Any category</option>
                                    @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <select name="brand_id" class="form-select">
                                    <option value="">Any brand</option>
                                    @foreach ($brands as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-12"><input name="search" class="form-control" placeholder="…or name / SKU contains"></div>
                        </div>
                        <div class="form-text mb-2">Tip: select products on the product list and press “Change prices”.</div>
                    @endif
                    <div class="d-flex gap-3 mb-3">
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="include_variations" value="1" id="iv" checked><label class="form-check-label" for="iv">Include variations</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="include_packs" value="1" id="ip" checked><label class="form-check-label" for="ip">Include pack / carton prices</label></div>
                    </div>

                    <label class="form-label fw-semibold">2. What change?</label>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <select name="field" class="form-select">
                                <option value="price">Selling price</option>
                                <option value="cost_price">Cost price</option>
                                <option value="wholesale_price">Wholesale price</option>
                                <option value="sale_price">Sale price</option>
                                <option value="promo_price">Promo price</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <select name="method" class="form-select" id="method">
                                <option value="percent">By percentage</option>
                                <option value="amount">By fixed amount</option>
                                <option value="set">Set to exact price</option>
                                <option value="markup">Cost + markup %</option>
                            </select>
                        </div>
                        <div class="col-5" id="dirWrap">
                            <select name="direction" class="form-select">
                                <option value="increase">Increase</option>
                                <option value="decrease">Decrease</option>
                            </select>
                        </div>
                        <div class="col-7">
                            <div class="input-group">
                                <span class="input-group-text" id="valPrefix">%</span>
                                <input type="number" step="0.01" min="0" name="value" class="form-control" required placeholder="10">
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fs-sm mb-1">Round up to nearest</label>
                            <select name="rounding" class="form-select">
                                <option value="0">No rounding</option>
                                @foreach ([5, 10, 50, 100, 500] as $r)<option value="{{ $r }}">{{ currency_symbol() }}{{ $r }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fs-sm mb-1">Reason</label>
                            <input name="reason" class="form-control" placeholder="e.g. Supplier increase">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-eye me-1"></i>Preview changes</button>
                </form>
            </div>

            @can('Manage price control')
            <div class="card">
                <div class="card-header"><h6 class="card-title mb-0">Promotion</h6></div>
                <div class="card-body">
                    <p class="text-muted fs-sm">Uses the same product selection above. Packs get the same % off automatically.</p>
                    <div class="row g-2">
                        <div class="col-4"><div class="input-group"><input type="number" id="promoPct" class="form-control" placeholder="10"><span class="input-group-text">% off</span></div></div>
                        <div class="col-4"><input type="datetime-local" id="promoStart" class="form-control" title="Starts"></div>
                        <div class="col-4"><input type="datetime-local" id="promoEnd" class="form-control" title="Ends"></div>
                        <div class="col-6"><button class="btn btn-success w-100" id="startPromo" type="button"><i class="bi bi-megaphone me-1"></i>Start promo</button></div>
                        <div class="col-6"><button class="btn btn-light w-100" id="endPromo" type="button">End promo</button></div>
                    </div>
                </div>
            </div>
            @endcan
        </div>

        <div class="col-xl-7">
            <div class="card" id="previewCard" style="display:none">
                <div class="card-header d-flex align-items-center gap-2">
                    <div class="flex-grow-1"><h6 class="card-title mb-0" id="pvTitle">Preview</h6><div class="fs-sm text-muted" id="pvSub"></div></div>
                    <button class="btn btn-sm btn-light" id="pvCancel">Cancel</button>
                    <button class="btn btn-sm btn-success" id="pvApply"><i class="bi bi-check-lg me-1"></i>Apply <span id="pvCount"></span> changes</button>
                </div>
                <div class="table-responsive" style="max-height:520px">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light" style="position:sticky;top:0"><tr>
                            <th style="width:30px"><input type="checkbox" class="form-check-input" id="pvAll" checked></th>
                            <th>Item</th><th class="text-end">Cost</th><th class="text-end">Now</th><th class="text-end">New</th><th class="text-end">Change</th></tr></thead>
                        <tbody id="pvBody"></tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h6 class="card-title mb-0">Bulk changes (undo available)</h6></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>When</th><th>Change</th><th class="text-end">Items</th><th>By</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($batches as $b)
                            <tr>
                                <td class="text-nowrap fs-sm">{{ $b->created_at->format('d M, h:i A') }}</td>
                                <td>{{ $b->description }} @if($b->reverted_at)<span class="badge bg-secondary-subtle text-secondary">undone {{ $b->reverted_at->diffForHumans() }}</span>@endif</td>
                                <td class="text-end">{{ $b->items_count }}</td>
                                <td class="fs-sm">{{ $b->user->first_name ?? '—' }}</td>
                                <td class="text-end text-nowrap">
                                    <button class="btn btn-sm btn-light view-batch" data-id="{{ $b->id }}">View</button>
                                    @if (!$b->reverted_at)
                                        @canany(['Revert price change', 'Manage price control'])
                                            <button class="btn btn-sm btn-outline-danger undo-batch" data-id="{{ $b->id }}" data-desc="{{ $b->description }}"><i class="bi bi-arrow-counterclockwise"></i> Undo</button>
                                        @endcanany
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No bulk changes yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex"><h6 class="card-title mb-0 flex-grow-1">Latest price changes</h6><a href="{{ route('pricing.history') }}" class="fs-sm">See all</a></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>When</th><th>Item</th><th>Field</th><th class="text-end">Old</th><th class="text-end">New</th><th>By</th></tr></thead>
                        <tbody>
                        @forelse ($recent as $h)
                            <tr>
                                <td class="fs-sm text-nowrap">{{ $h->created_at->diffForHumans() }}</td>
                                <td class="text-truncate" style="max-width:220px">{{ $h->product->title ?? '—' }}@if($h->variation) – {{ $h->variation->label }}@endif @if($h->pack) – {{ $h->pack->unit->name ?? 'Pack' }} ×{{ qty($h->pack->quantity_per_unit) }}@endif</td>
                                <td class="fs-sm">{{ str_replace(['pack_', '_'], ['pack ', ' '], $h->field) }} <span class="badge bg-light text-muted">{{ $h->source }}</span></td>
                                <td class="text-end text-muted">{{ $h->old_value !== null ? money($h->old_value) : '—' }}</td>
                                <td class="text-end fw-semibold">{{ $h->new_value !== null ? money($h->new_value) : '—' }}
                                    @if ($h->change_percent !== null)<span class="fs-xs {{ $h->change_percent > 0 ? 'text-success' : 'text-danger' }}">{{ $h->change_percent > 0 ? '+' : '' }}{{ $h->change_percent }}%</span>@endif</td>
                                <td class="fs-sm">{{ $h->user->first_name ?? 'System' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No price changes yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
</div>
@endsection

@push('page-scripts')
<script>
(function () {
    const form = document.getElementById('bulkForm');
    const method = document.getElementById('method');
    let lastPayload = null, lastRows = [];

    function syncMethod() {
        const m = method.value;
        document.getElementById('dirWrap').style.display = (m === 'percent' || m === 'amount') ? '' : 'none';
        document.getElementById('valPrefix').textContent = (m === 'percent' || m === 'markup') ? '%' : VM.currency;
    }
    method.addEventListener('change', syncMethod); syncMethod();

    function payload() {
        const fd = new FormData(form), body = {};
        for (const [k, v] of fd.entries()) {
            if (k === 'product_ids[]') (body.product_ids = body.product_ids || []).push(v);
            else if (v !== '') body[k] = v;
        }
        body.include_variations = !!fd.get('include_variations');
        body.include_packs = !!fd.get('include_packs');
        return body;
    }

    form.addEventListener('submit', async e => {
        e.preventDefault();
        lastPayload = payload();
        try {
            const r = await VM.api(@json(route('pricing.preview')), {method: 'POST', body: lastPayload});
            lastRows = r.rows;
            document.getElementById('pvTitle').textContent = r.description;
            document.getElementById('pvSub').innerHTML = `${r.count} price(s) will change` + (r.below_cost ? ` · <span class="text-danger"><i class="bi bi-exclamation-triangle"></i> ${r.below_cost} below cost</span>` : '');
            document.getElementById('pvBody').innerHTML = r.rows.map(row => {
                const diff = row.old ? ((row.new - row.old) / row.old * 100) : null;
                return `<tr class="${row.below_cost ? 'table-danger' : ''}">
                    <td><input type="checkbox" class="form-check-input pv-row" value="${VM.esc(row.key)}" checked></td>
                    <td>${VM.esc(row.label)}<div class="fs-xs text-muted">${VM.esc(row.sku || '')} ${row.kind !== 'product' ? '· ' + row.kind : ''}</div></td>
                    <td class="text-end text-muted">${row.cost ? VM.money(row.cost) : '—'}</td>
                    <td class="text-end"><span class="vm-diff-old px-1 rounded">${row.old !== null ? VM.money(row.old) : '—'}</span></td>
                    <td class="text-end fw-semibold"><span class="vm-diff-new px-1 rounded">${VM.money(row.new)}</span></td>
                    <td class="text-end ${diff > 0 ? 'text-success' : 'text-danger'}">${diff === null ? 'new' : (diff > 0 ? '+' : '') + diff.toFixed(1) + '%'}</td></tr>`;
            }).join('') || '<tr><td colspan="6" class="text-center text-muted py-4">Nothing would change.</td></tr>';
            document.getElementById('pvCount').textContent = r.count;
            document.getElementById('previewCard').style.display = '';
            document.getElementById('previewCard').scrollIntoView({behavior: 'smooth'});
        } catch (err) { VM.error(err); }
    });
    document.getElementById('pvAll').addEventListener('change', e => document.querySelectorAll('.pv-row').forEach(c => c.checked = e.target.checked));
    document.getElementById('pvCancel').addEventListener('click', () => document.getElementById('previewCard').style.display = 'none');
    document.getElementById('pvApply').addEventListener('click', async () => {
        const keys = [...document.querySelectorAll('.pv-row:checked')].map(c => c.value);
        if (!keys.length) return VM.toast('Nothing selected', 'warning');
        if (!await VM.confirm(`Apply ${keys.length} price change(s)?`, 'Prices at the till change immediately. You can undo from the list below.', 'Apply')) return;
        const send = async extra => VM.api(@json(route('pricing.apply')), {method: 'POST', body: Object.assign({}, lastPayload, {keys}, extra || {})});
        try {
            const r = await send();
            await Swal.fire({icon: 'success', title: 'Done', text: r.message}); location.reload();
        } catch (err) {
            if (err.data && err.data.needs_confirmation && await VM.confirm('Some prices are below cost', err.data.message + ' Apply anyway?', 'Apply anyway', 'error')) {
                try { const r = await send({confirm_below_cost: 1}); await Swal.fire({icon: 'success', title: 'Done', text: r.message}); location.reload(); } catch (e2) { VM.error(e2); }
            } else VM.error(err);
        }
    });

    document.querySelectorAll('.undo-batch').forEach(b => b.addEventListener('click', async () => {
        if (!await VM.confirm('Undo “' + b.dataset.desc + '”?', 'Every price in this change goes back to what it was before.', 'Undo')) return;
        try { const r = await VM.api(`/price-control/batches/${b.dataset.id}/revert`, {method: 'POST'}); await Swal.fire({icon: 'success', title: r.message}); location.reload(); }
        catch (err) { VM.error(err); }
    }));
    document.querySelectorAll('.view-batch').forEach(b => b.addEventListener('click', async () => {
        try {
            const r = await VM.api(`/price-control/batches/${b.dataset.id}`);
            Swal.fire({title: r.description, width: 700, html: `<div style="max-height:400px;overflow:auto"><table class="table table-sm text-start"><thead><tr><th>Item</th><th class="text-end">Old</th><th class="text-end">New</th></tr></thead><tbody>` +
                r.rows.map(x => `<tr><td>${VM.esc(x.item)}</td><td class="text-end">${x.old !== null ? VM.money(x.old) : '—'}</td><td class="text-end">${x.new !== null ? VM.money(x.new) : '—'}</td></tr>`).join('') + '</tbody></table></div>'});
        } catch (err) { VM.error(err); }
    }));

    async function promo(action) {
        const ids = payload().product_ids;
        if (!ids || !ids.length) return VM.error(new Error('Select products on the product list first, then press “Change prices”.'));
        const body = {product_ids: ids, action};
        if (action === 'start') {
            body.percent = document.getElementById('promoPct').value;
            body.starts_at = document.getElementById('promoStart').value || null;
            body.ends_at = document.getElementById('promoEnd').value || null;
            if (!body.percent) return VM.toast('Enter the % off', 'warning');
        }
        try { const r = await VM.api(@json(route('pricing.promo')), {method: 'POST', body}); await Swal.fire({icon: 'success', title: r.message}); location.reload(); }
        catch (err) { VM.error(err); }
    }
    document.getElementById('startPromo')?.addEventListener('click', () => promo('start'));
    document.getElementById('endPromo')?.addEventListener('click', () => promo('end'));
})();
</script>
@endpush

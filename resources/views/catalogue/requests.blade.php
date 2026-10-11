@extends('layouts.master')

@section('content')
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0">{{ $pagetitle }}</h4>
            <p class="text-muted mb-0 mt-1 fs-sm">
                @if ($isCentral)
                    Price changes branches have asked for, and products they want added to the catalogue.
                @else
                    Price changes and products this branch has sent to head office.
                    @if ($unsent)
                        <span class="badge bg-warning-subtle text-warning ms-1"><i class="bi bi-cloud-slash me-1"></i>{{ $unsent }} waiting to be sent – head office not reachable</span>
                    @endif
                @endif
            </p>
        </div>
        <form class="d-flex gap-2" method="GET">
            @if ($isCentral)
                <select name="branch_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All branches</option>
                    @foreach ($branches as $b)<option value="{{ $b->id }}" @selected(request('branch_id') == $b->id)>{{ $b->code }} – {{ $b->name }}</option>@endforeach
                </select>
            @endif
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach (['pending' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'all' => 'All'] as $k => $l)
                    <option value="{{ $k }}" @selected($status === $k)>{{ $l }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @include('layouts.partials.flash')

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabPrices">Price changes @if ($counts['price'])<span class="badge bg-warning ms-1">{{ $counts['price'] }}</span>@endif</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabProducts">New products @if ($counts['products'])<span class="badge bg-warning ms-1">{{ $counts['products'] }}</span>@endif</a></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tabPrices">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light"><tr>
                            @if ($isCentral)<th>Branch</th>@endif
                            <th>Item</th><th>Price</th><th class="text-end">Now</th><th class="text-end">Asked</th><th class="text-end">Change</th>
                            <th>Reason</th><th>Asked by</th><th>Status</th><th></th>
                        </tr></thead>
                        <tbody>
                        @forelse ($priceRequests as $r)
                            @php
                                $diff = $r->current_value ? (($r->requested_value - $r->current_value) / max(0.01, $r->current_value)) * 100 : null;
                                $cost = $r->product?->cost_price;
                                $below = $cost && $r->field !== 'wholesale_price' && !$r->variation_uid && !$r->pack_uid && (float) $r->requested_value < (float) $cost;
                            @endphp
                            <tr data-id="{{ $r->id }}">
                                @if ($isCentral)<td><span class="fw-semibold">{{ $r->branch->code ?? '?' }}</span></td>@endif
                                <td>{{ $r->item_label }}
                                    @unless ($r->product_id)<div class="fs-xs text-danger">Not in the catalogue here</div>@endunless</td>
                                <td class="fs-sm">{{ \App\Models\PriceChangeRequest::FIELDS[$r->field] ?? $r->field }}
                                    @if ($r->field === 'promo_price' && ($r->promo_starts_at || $r->promo_ends_at))
                                        <div class="fs-xs text-muted">{{ $r->promo_starts_at?->format('d M') ?? 'now' }} – {{ $r->promo_ends_at?->format('d M') ?? 'open' }}</div>
                                    @endif
                                </td>
                                <td class="text-end">{{ $r->current_value !== null ? money($r->current_value) : '—' }}</td>
                                <td class="text-end fw-semibold">{{ money($r->requested_value) }}
                                    @if ($below)<div class="fs-xs text-danger"><i class="bi bi-exclamation-triangle"></i> below cost {{ money($cost) }}</div>@endif</td>
                                <td class="text-end {{ $diff === null ? '' : ($diff < 0 ? 'text-danger' : 'text-success') }}">{{ $diff === null ? '—' : (($diff > 0 ? '+' : '') . number_format($diff, 1) . '%') }}</td>
                                <td class="fs-sm" style="max-width:240px">{{ $r->reason }}</td>
                                <td class="fs-sm">{{ $r->requested_by_name }}<div class="fs-xs text-muted">{{ $r->created_at?->diffForHumans() }}</div></td>
                                <td>
                                    @include('catalogue.partials.status', ['s' => $r->status])
                                    @if ($r->status === 'approved')
                                        <div class="fs-xs text-muted">{{ money($r->approved_value) }} · {{ $r->scope === 'all' ? 'all branches' : 'this branch' }}</div>
                                    @endif
                                    @if (!$isCentral && $r->status === 'pending')
                                        <div class="fs-xs {{ $r->sent ? 'text-muted' : 'text-warning' }}">{{ $r->sent ? 'with head office' : 'not sent yet' }}</div>
                                    @endif
                                    @if ($r->decision_note)<div class="fs-xs text-muted">“{{ $r->decision_note }}”</div>@endif
                                </td>
                                <td class="text-end text-nowrap">
                                    @if ($isCentral && $r->status === 'pending' && $r->product_id)
                                        @can('Approve price change')
                                            <button class="btn btn-sm btn-success pr-approve" data-id="{{ $r->id }}" data-value="{{ $r->requested_value }}" data-label="{{ $r->item_label }}" data-branch="{{ $r->branch->code ?? '' }}">Approve</button>
                                            <button class="btn btn-sm btn-light text-danger pr-reject" data-id="{{ $r->id }}">Reject</button>
                                        @endcan
                                    @elseif (!$isCentral && $r->status === 'pending' && !$r->sent)
                                        <button class="btn btn-sm btn-light pr-cancel" data-id="{{ $r->id }}">Cancel</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center text-muted py-4">Nothing here.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tabProducts">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light"><tr>
                            @if ($isCentral)<th>Branch</th>@endif
                            <th>Product</th><th>Barcode</th><th class="text-end">Price</th><th>Note</th><th>Sent by</th><th>Status</th><th></th>
                        </tr></thead>
                        <tbody>
                        @forelse ($submissions as $s)
                            <tr>
                                @if ($isCentral)<td class="fw-semibold">{{ $s->branch->code ?? '?' }}</td>@endif
                                <td>{{ $s->title }}<div class="fs-xs text-muted">{{ $s->payload['category']['name'] ?? '' }} {{ !empty($s->payload['brand']['name']) ? '· ' . $s->payload['brand']['name'] : '' }}
                                    {{ count($s->payload['packs'] ?? []) ? '· ' . count($s->payload['packs']) . ' pack(s)' : '' }} {{ count($s->payload['variations'] ?? []) ? '· ' . count($s->payload['variations']) . ' variation(s)' : '' }}</div></td>
                                <td class="vm-mono fs-sm">{{ $s->barcode }}</td>
                                <td class="text-end">{{ money($s->payload['price'] ?? 0) }}</td>
                                <td class="fs-sm">{{ $s->note }}</td>
                                <td class="fs-sm">{{ $s->submitted_by_name }}<div class="fs-xs text-muted">{{ $s->created_at?->diffForHumans() }}</div></td>
                                <td>@include('catalogue.partials.status', ['s' => $s->status])
                                    @if ($s->decision_note)<div class="fs-xs text-muted">“{{ $s->decision_note }}”</div>@endif</td>
                                <td class="text-end text-nowrap">
                                    @if ($isCentral && $s->status === 'pending')
                                        @can('Approve product submission')
                                            <button class="btn btn-sm btn-success ps-review" data-id="{{ $s->id }}">Review</button>
                                        @endcan
                                    @elseif ($s->status === 'approved' && $s->central_product_id && $isCentral)
                                        <a class="btn btn-sm btn-light" href="{{ route('products.edit', $s->central_product_id) }}">Open</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">Nothing here.</td></tr>
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

{{-- Approve price --}}
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Approve price change</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <p class="mb-2" id="apLabel"></p>
            <label class="form-label">Approved price</label>
            <input type="number" step="0.01" min="0" class="form-control mb-3" id="apValue">
            <label class="form-label">Apply to</label>
            <div class="form-check"><input class="form-check-input" type="radio" name="apScope" id="apScopeBranch" value="branch" checked>
                <label class="form-check-label" for="apScopeBranch">Only <span id="apBranch"></span> (goes into its branch price list)</label></div>
            <div class="form-check mb-3"><input class="form-check-input" type="radio" name="apScope" id="apScopeAll" value="all">
                <label class="form-check-label" for="apScopeAll">All branches (changes the catalogue price)</label></div>
            <label class="form-label">Note to the branch</label>
            <input type="text" class="form-control" id="apNote" maxlength="255" placeholder="optional">
        </div>
        <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success" id="apGo">Approve</button></div>
    </div></div>
</div>

{{-- Review product --}}
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">New product from a branch</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" id="rvBody">Loading…</div>
        <div class="modal-footer">
            <input type="text" class="form-control me-auto" id="rvNote" maxlength="255" placeholder="Note to the branch (optional)" style="max-width:320px">
            <button class="btn btn-light text-danger" id="rvReject">Reject</button>
            <button class="btn btn-success" id="rvApprove">Add to catalogue</button>
        </div>
    </div></div>
</div>
@endsection

@push('page-scripts')
<script>
(() => {
    const $ = (s, r = document) => r.querySelector(s);
    let current = null;
    const apModal = new bootstrap.Modal('#approveModal');
    const rvModal = new bootstrap.Modal('#reviewModal');

    document.addEventListener('click', async e => {
        const a = e.target.closest('.pr-approve');
        if (a) {
            current = a.dataset.id;
            $('#apLabel').textContent = a.dataset.label;
            $('#apValue').value = a.dataset.value;
            $('#apBranch').textContent = a.dataset.branch;
            $('#apNote').value = '';
            $('#apScopeBranch').checked = true;
            apModal.show();
            return;
        }
        const r = e.target.closest('.pr-reject');
        if (r) {
            const note = await VM.prompt('Reject this price change?', 'Tell the branch why', false);
            if (note === null) return;
            try { const res = await VM.api(`/catalogue/price-requests/${r.dataset.id}/reject`, {method: 'POST', body: {note}}); VM.toast(res.message); setTimeout(() => location.reload(), 600); } catch (err) { VM.error(err); }
            return;
        }
        const c = e.target.closest('.pr-cancel');
        if (c) {
            try { await VM.api(`/catalogue/price-requests/${c.dataset.id}/cancel`, {method: 'POST'}); location.reload(); } catch (err) { VM.error(err); }
            return;
        }
        const v = e.target.closest('.ps-review');
        if (v) {
            current = v.dataset.id;
            $('#rvBody').innerHTML = 'Loading…';
            rvModal.show();
            try {
                const d = await VM.api(`/catalogue/submissions/${current}`);
                const p = d.payload, m = VM.money;
                $('#rvBody').innerHTML = `
                    ${d.conflicts.length ? `<div class="alert alert-danger">These barcodes already belong to another product here: <b class="vm-mono">${d.conflicts.map(VM.esc).join(', ')}</b>. Reject it, or fix the other product first.</div>` : ''}
                    <h5 class="mb-1">${VM.esc(p.title)}</h5>
                    <div class="text-muted fs-sm mb-3">From ${VM.esc(d.branch?.code || '')} · ${VM.esc(d.submission.submitted_by_name || '')}${d.submission.note ? ' · “' + VM.esc(d.submission.note) + '”' : ''}</div>
                    <div class="row g-2 fs-sm mb-3">
                        <div class="col-md-4"><span class="text-muted">Barcode</span><div class="vm-mono">${VM.esc(p.barcode || '—')}</div></div>
                        <div class="col-md-4"><span class="text-muted">SKU</span><div class="vm-mono">${VM.esc(p.sku || '—')}</div></div>
                        <div class="col-md-4"><span class="text-muted">Category / brand</span><div>${VM.esc(p.category?.name || '—')} / ${VM.esc(p.brand?.name || '—')}</div></div>
                        <div class="col-md-4"><span class="text-muted">Price</span><div class="fw-semibold">${m(p.price)}</div></div>
                        <div class="col-md-4"><span class="text-muted">Cost</span><div>${p.cost_price != null ? m(p.cost_price) : '—'}</div></div>
                        <div class="col-md-4"><span class="text-muted">Sold in</span><div>${VM.esc(p.base_unit || 'Piece')}${p.is_weighted ? ' (by weight)' : ''}</div></div>
                    </div>
                    ${(p.packs || []).length ? `<table class="table table-sm"><thead><tr><th>Pack</th><th class="text-end">Qty</th><th class="text-end">Price</th><th>Barcode</th></tr></thead><tbody>${p.packs.map(k => `<tr><td>${VM.esc(k.unit)}</td><td class="text-end">${k.quantity_per_unit}</td><td class="text-end">${k.price != null ? m(k.price) : '—'}</td><td class="vm-mono">${VM.esc(k.barcode || '')}</td></tr>`).join('')}</tbody></table>` : ''}
                    ${(p.variations || []).length ? `<table class="table table-sm"><thead><tr><th>Variation</th><th class="text-end">Price</th><th>Barcode</th></tr></thead><tbody>${p.variations.map(k => `<tr><td>${VM.esc(k.name || '')}</td><td class="text-end">${k.price != null ? m(k.price) : '—'}</td><td class="vm-mono">${VM.esc(k.barcode || '')}</td></tr>`).join('')}</tbody></table>` : ''}
                    <p class="text-muted fs-sm mb-0">Approving adds it to the head-office catalogue under the same identity, so the branch's sales and stock history stay linked. Other branches receive it with the next catalogue sync.</p>`;
                $('#rvApprove').disabled = d.conflicts.length > 0;
            } catch (err) { VM.error(err); }
        }
    });

    $('#apGo').addEventListener('click', async () => {
        try {
            const res = await VM.api(`/catalogue/price-requests/${current}/approve`, {method: 'POST', body: {
                value: $('#apValue').value, scope: document.querySelector('input[name=apScope]:checked').value, note: $('#apNote').value}});
            apModal.hide(); VM.toast(res.message); setTimeout(() => location.reload(), 700);
        } catch (err) { VM.error(err); }
    });
    $('#rvApprove').addEventListener('click', async () => {
        try { const res = await VM.api(`/catalogue/submissions/${current}/approve`, {method: 'POST', body: {note: $('#rvNote').value}}); rvModal.hide(); VM.toast(res.message); setTimeout(() => location.reload(), 700); } catch (err) { VM.error(err); }
    });
    $('#rvReject').addEventListener('click', async () => {
        try { const res = await VM.api(`/catalogue/submissions/${current}/reject`, {method: 'POST', body: {note: $('#rvNote').value}}); rvModal.hide(); VM.toast(res.message); setTimeout(() => location.reload(), 700); } catch (err) { VM.error(err); }
    });
})();
</script>
@endpush

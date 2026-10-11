@extends('layouts.master')

@section('content')
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0">{{ $priceList->name }} <span class="vm-mono text-muted fs-base">{{ $priceList->code }}</span></h4>
            <p class="text-muted mb-0 mt-1 fs-sm">{{ $priceList->branch ? 'Only ' . $priceList->branch->code . ' – ' . $priceList->branch->name : 'All branches' }}
                · priority {{ $priceList->priority }} · {{ $priceList->is_active ? 'active' : 'switched off' }}
                @if ($priceList->starts_at || $priceList->ends_at) · {{ $priceList->starts_at?->format('d M Y H:i') ?? 'now' }} → {{ $priceList->ends_at?->format('d M Y H:i') ?? 'open' }}@endif</p>
        </div>
        <a href="{{ route('catalogue.price-lists') }}" class="btn btn-light btn-sm"><i class="bi bi-arrow-left me-1"></i>All lists</a>
    </div>

    @include('layouts.partials.flash')

    <div class="row g-3">
        <div class="{{ $canManage ? 'col-xl-8' : 'col-12' }}">
            <div class="card">
                <div class="card-header">
                    <form class="d-flex gap-2" method="GET"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Find a product in this list"><button class="btn btn-sm btn-light">Search</button></form>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>Item</th><th class="text-end">Catalogue</th><th class="text-end">This list</th><th class="text-end">Wholesale</th><th class="text-end">Promo</th><th>Updated</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($items as $i)
                            @php
                                $base = $i->pack ? $i->pack->price : ($i->variation ? ($i->variation->price ?? $i->product?->price) : $i->product?->price);
                                $label = ($i->product->title ?? '?') . ($i->variation ? ' – ' . $i->variation->label : '') . ($i->pack ? ' – ' . ($i->pack->unit->name ?? 'Pack') . ' of ' . qty($i->pack->quantity_per_unit) : '');
                            @endphp
                            <tr>
                                <td>{{ $label }}<div class="fs-xs text-muted vm-mono">{{ $i->product->sku ?? '' }}</div></td>
                                <td class="text-end text-muted">{{ $base !== null ? money($base) : '—' }}</td>
                                <td class="text-end fw-semibold">{{ $i->price !== null ? money($i->price) : '—' }}</td>
                                <td class="text-end">{{ $i->wholesale_price !== null ? money($i->wholesale_price) : '—' }}</td>
                                <td class="text-end">{{ $i->promo_price !== null ? money($i->promo_price) : '—' }}
                                    @if ($i->promo_price !== null)<div class="fs-xs text-muted">{{ $i->promo_starts_at?->format('d M') ?? 'now' }}–{{ $i->promo_ends_at?->format('d M') ?? 'open' }}</div>@endif</td>
                                <td class="fs-sm text-muted">{{ $i->updated_at?->diffForHumans() }} · v{{ $i->version }}</td>
                                <td class="text-end">
                                    @if ($canManage)
                                        <button class="btn btn-sm btn-link text-danger pl-rm" data-id="{{ $i->id }}" title="Remove – back to catalogue price"><i class="bi bi-x-lg"></i></button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No prices in this list yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($items->hasPages())<div class="card-footer">{{ $items->links() }}</div>@endif
            </div>
        </div>

        @if ($canManage)
            <div class="col-xl-4">
                <div class="card mb-3">
                    <div class="card-header"><h6 class="card-title mb-0">Set a price</h6></div>
                    <div class="card-body">
                        <label class="form-label">Product</label>
                        <input type="text" class="form-control mb-1" id="plSearch" placeholder="Scan or type a name" autocomplete="off">
                        <div class="list-group mb-2" id="plResults" style="max-height:220px;overflow:auto"></div>
                        <div id="plForm" class="d-none">
                            <div class="p-2 bg-light rounded mb-2 fs-sm" id="plPicked"></div>
                            <select class="form-select form-select-sm mb-2" id="plPack"></select>
                            <div class="row g-2">
                                <div class="col-6"><label class="form-label fs-sm">Price</label><input type="number" step="0.01" min="0" class="form-control" id="plPrice"></div>
                                <div class="col-6"><label class="form-label fs-sm">Wholesale</label><input type="number" step="0.01" min="0" class="form-control" id="plWholesale"></div>
                                <div class="col-12"><label class="form-label fs-sm">Promo price (optional)</label><input type="number" step="0.01" min="0" class="form-control" id="plPromo"></div>
                                <div class="col-6"><input type="datetime-local" class="form-control form-control-sm" id="plPromoFrom" title="Promo from"></div>
                                <div class="col-6"><input type="datetime-local" class="form-control form-control-sm" id="plPromoTo" title="Promo until"></div>
                            </div>
                            <button class="btn btn-primary w-100 mt-3" id="plSave">Save price</button>
                        </div>
                    </div>
                </div>

                <form class="card" method="POST" action="{{ route('catalogue.price-lists.update', $priceList) }}">
                    @csrf @method('PUT')
                    <div class="card-header"><h6 class="card-title mb-0">List settings</h6></div>
                    <div class="card-body row g-2">
                        <div class="col-5"><label class="form-label fs-sm">Code</label><input name="code" class="form-control form-control-sm vm-mono" value="{{ $priceList->code }}" required></div>
                        <div class="col-7"><label class="form-label fs-sm">Name</label><input name="name" class="form-control form-control-sm" value="{{ $priceList->name }}" required></div>
                        <div class="col-7"><label class="form-label fs-sm">Applies to</label>
                            <select name="branch_id" class="form-select form-select-sm"><option value="">All branches</option>
                                @foreach (\App\Models\Branch::orderBy('number')->get() as $b)<option value="{{ $b->id }}" @selected($priceList->branch_id === $b->id)>{{ $b->code }}</option>@endforeach</select></div>
                        <div class="col-5"><label class="form-label fs-sm">Priority</label><input type="number" name="priority" class="form-control form-control-sm" value="{{ $priceList->priority }}" required></div>
                        <div class="col-6"><label class="form-label fs-sm">From</label><input type="datetime-local" name="starts_at" class="form-control form-control-sm" value="{{ $priceList->starts_at?->format('Y-m-d\TH:i') }}"></div>
                        <div class="col-6"><label class="form-label fs-sm">Until</label><input type="datetime-local" name="ends_at" class="form-control form-control-sm" value="{{ $priceList->ends_at?->format('Y-m-d\TH:i') }}"></div>
                        <div class="col-12"><label class="form-label fs-sm">Notes</label><input name="notes" class="form-control form-control-sm" value="{{ $priceList->notes }}"></div>
                        <div class="col-12 form-check form-switch ms-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="plActive" @checked($priceList->is_active)><label class="form-check-label" for="plActive">Active</label></div>
                    </div>
                    <div class="card-footer text-end"><button class="btn btn-sm btn-primary">Save</button></div>
                </form>
            </div>
        @endif
    </div>
</div>
</div>
</div>
@endsection

@if ($canManage)
@push('page-scripts')
<script>
(() => {
    const $ = s => document.querySelector(s);
    const listId = {{ $priceList->id }};
    let pick = null;
    const search = VM.debounce(async () => {
        const q = $('#plSearch').value.trim();
        if (q.length < 2) { $('#plResults').innerHTML = ''; return; }
        try {
            const res = await VM.api(`{{ route('products.lookup') }}?q=${encodeURIComponent(q)}&with_packs=1`);
            const rows = Array.isArray(res) ? res : (res.items || []);
            $('#plResults').innerHTML = rows.slice(0, 15).map((r, i) => `<button type="button" class="list-group-item list-group-item-action py-1 fs-sm" data-i="${i}">${VM.esc(r.label || r.title || r.name)} <span class="text-muted float-end">${r.price != null ? VM.money(r.price) : ''}</span></button>`).join('') || '<div class="text-muted fs-sm p-2">No match</div>';
            $('#plResults').onclick = e => {
                const b = e.target.closest('[data-i]'); if (!b) return;
                pick = rows[+b.dataset.i];
                $('#plPicked').innerHTML = `<b>${VM.esc(pick.label || pick.title || pick.name)}</b><br>Catalogue price ${pick.price != null ? VM.money(pick.price) : '—'}`;
                const packs = (pick.packs || []).filter(k => !k.is_base);
                $('#plPack').innerHTML = `<option value="">Single (${VM.esc(pick.unit || 'piece')})</option>` + packs.map(k => `<option value="${k.id}">${VM.esc(k.name)} of ${k.qty}${k.price != null ? ' – ' + VM.money(k.price) : ''}</option>`).join('');
                $('#plPack').classList.toggle('d-none', !packs.length || !!pick.variation_id);
                $('#plPrice').value = ''; $('#plWholesale').value = ''; $('#plPromo').value = '';
                $('#plForm').classList.remove('d-none'); $('#plResults').innerHTML = ''; $('#plPrice').focus();
            };
        } catch (e) { VM.error(e); }
    }, 250);
    $('#plSearch').addEventListener('input', search);
    $('#plSave').addEventListener('click', async () => {
        if (!pick) return;
        try {
            const res = await VM.api(`/catalogue/price-lists/${listId}/items`, {method: 'POST', body: {
                product_id: pick.product_id || pick.id, variation_id: pick.variation_id || null, pack_id: $('#plPack').value || null,
                price: $('#plPrice').value || null, wholesale_price: $('#plWholesale').value || null, promo_price: $('#plPromo').value || null,
                promo_starts_at: $('#plPromoFrom').value || null, promo_ends_at: $('#plPromoTo').value || null}});
            if (res.below_cost) await Swal.fire({icon: 'warning', title: 'Saved – but below cost', text: 'This price is lower than the cost price.'});
            location.reload();
        } catch (e) { VM.error(e); }
    });
    document.addEventListener('click', async e => {
        const b = e.target.closest('.pl-rm'); if (!b) return;
        if (!await VM.confirm('Remove this price?', 'The item goes back to the catalogue price.')) return;
        try { await VM.api(`/catalogue/price-lists/${listId}/items/${b.dataset.id}`, {method: 'DELETE'}); location.reload(); } catch (err) { VM.error(err); }
    });
})();
</script>
@endpush
@endif

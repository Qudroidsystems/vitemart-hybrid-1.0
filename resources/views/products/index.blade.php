@extends('layouts.master')

@section('content')
<div class="main-content">
<div class="page-content">
<div class="container-fluid">

    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0">Products</h4>
            <p class="text-muted mb-0 mt-1 fs-sm">Every product, its packs, prices and live stock.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap mt-2 mt-sm-0">
            <a href="{{ route('products.spreadsheet.index') }}" class="btn btn-light btn-sm"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Import / Export Excel</a>
            @canany(['Manage price control', 'View price history'])
                <a href="{{ route('pricing.index') }}" class="btn btn-light btn-sm"><i class="bi bi-tags me-1"></i>Price control</a>
            @endcanany
            @can('Create product')
                <a href="{{ route('products.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add product</a>
            @endcan
        </div>
    </div>

    @include('layouts.partials.flash')

    {{-- Summary --}}
    <div class="row g-3 mb-3">
        @php
            $cards = [
                ['Products', number_format($stats->total ?? 0), number_format($stats->active ?? 0) . ' active', 'bi-box-seam', 'primary', null],
                ['Low stock', number_format($stats->low_stock ?? 0), 'At or below reorder level', 'bi-exclamation-triangle', 'warning', 'low_stock'],
                ['Out of stock', number_format($stats->out_of_stock ?? 0), 'Zero or negative', 'bi-x-octagon', 'danger', 'out_of_stock'],
                ['Stock value (cost)', money($stats->stock_cost ?? 0, 0), 'Retail value ' . money($stats->stock_retail ?? 0, 0), 'bi-cash-stack', 'success', null],
            ];
        @endphp
        @foreach ($cards as [$label, $value, $sub, $icon, $color, $filter])
            <div class="col-6 col-xl-3">
                <a @if($filter) href="{{ route('products.index', ['stock' => $filter]) }}" @endif class="card vm-stat h-100 mb-0 text-reset {{ $filter ? '' : 'pe-none' }}">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-{{ $color }}-subtle text-{{ $color }} rounded fs-3"><i class="bi {{ $icon }}"></i></span>
                        </div>
                        <div class="min-w-0">
                            <div class="vm-stat-label">{{ $label }}</div>
                            <div class="vm-stat-value">{{ $value }}</div>
                            <div class="vm-stat-sub text-muted text-truncate">{{ $sub }}</div>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-body border-bottom">
            <form method="GET" class="row g-2 align-items-end" id="filterForm">
                <div class="col-12 col-lg-3">
                    <label class="form-label fs-xs text-muted mb-1">Search</label>
                    <div class="position-relative">
                        <input type="search" name="search" value="{{ request('search') }}" class="form-control ps-5" placeholder="Name, SKU or scan a barcode" autofocus>
                        <i class="bi bi-upc-scan position-absolute top-50 translate-middle-y ms-3 text-muted"></i>
                    </div>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label fs-xs text-muted mb-1">Category</label>
                    <select name="category" class="form-select" onchange="this.form.submit()">
                        <option value="">All categories</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label fs-xs text-muted mb-1">Brand</label>
                    <select name="brand" class="form-select" onchange="this.form.submit()">
                        <option value="">All brands</option>
                        @foreach ($brands as $b)
                            <option value="{{ $b->id }}" @selected(request('brand') == $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label fs-xs text-muted mb-1">Stock</label>
                    <select name="stock" class="form-select" onchange="this.form.submit()">
                        <option value="">Any stock</option>
                        <option value="in_stock" @selected(request('stock') === 'in_stock')>In stock</option>
                        <option value="low_stock" @selected(request('stock') === 'low_stock')>Low stock</option>
                        <option value="out_of_stock" @selected(request('stock') === 'out_of_stock')>Out of stock</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-1">
                    <label class="form-label fs-xs text-muted mb-1">Status</label>
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">All</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-1">
                    <label class="form-label fs-xs text-muted mb-1">Sort</label>
                    <select name="sort" class="form-select" onchange="this.form.submit()">
                        <option value="latest">Newest</option>
                        <option value="name" @selected(request('sort') === 'name')>Name</option>
                        <option value="stock_low" @selected(request('sort') === 'stock_low')>Stock ↑</option>
                        <option value="stock_high" @selected(request('sort') === 'stock_high')>Stock ↓</option>
                        <option value="price_high" @selected(request('sort') === 'price_high')>Price ↓</option>
                        <option value="price_low" @selected(request('sort') === 'price_low')>Price ↑</option>
                    </select>
                </div>
                <div class="col-12 col-lg-1 d-flex gap-1">
                    <button class="btn btn-primary flex-fill" type="submit"><i class="bi bi-search"></i></button>
                    <a href="{{ route('products.index') }}" class="btn btn-light" title="Clear filters"><i class="bi bi-x-lg"></i></a>
                </div>
                <div class="col-12 d-flex flex-wrap gap-3 pt-1">
                    @foreach (['promo' => 'On promotion', 'below_cost' => 'Priced below cost', 'no_barcode' => 'Missing barcode'] as $k => $label)
                        <div class="form-check form-check-inline m-0">
                            <input class="form-check-input" type="checkbox" name="{{ $k }}" value="1" id="f_{{ $k }}" @checked(request()->boolean($k)) onchange="this.form.submit()">
                            <label class="form-check-label fs-sm" for="f_{{ $k }}">{{ $label }}</label>
                        </div>
                    @endforeach
                </div>
            </form>
        </div>

        {{-- Bulk action bar (shows when rows are selected) --}}
        <div class="vm-bulkbar align-items-center gap-2 flex-wrap px-3 py-2 bg-primary-subtle border-bottom" id="bulkBar">
            <strong><span id="selCount">0</span> selected</strong>
            <span class="text-muted">·</span>
            @can('Print barcode labels')
                <button class="btn btn-sm btn-light" data-bulk="labels"><i class="bi bi-upc me-1"></i>Print labels</button>
            @endcan
            @canany(['Create stock adjustment', 'Adjust stock'])
                <button class="btn btn-sm btn-light" data-bulk="adjust"><i class="bi bi-sliders me-1"></i>Adjust stock</button>
                <button class="btn btn-sm btn-light" data-bulk="count"><i class="bi bi-clipboard-check me-1"></i>Stock count</button>
            @endcanany
            @can('Manage price control')
                <button class="btn btn-sm btn-light" data-bulk="price"><i class="bi bi-tags me-1"></i>Change prices</button>
            @endcan
            @can('Update product')
                <div class="dropdown">
                    <button class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown">More</button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" data-bulk-action="activate">Activate</a></li>
                        <li><a class="dropdown-item" href="#" data-bulk-action="deactivate">Deactivate</a></li>
                        <li><a class="dropdown-item" href="#" data-bulk-action="generate_barcodes">Generate missing barcodes</a></li>
                        <li><a class="dropdown-item" href="#" data-bulk-action="reorder_level">Set reorder level…</a></li>
                        <li><a class="dropdown-item" href="#" data-bulk-action="category">Move to category…</a></li>
                    </ul>
                </div>
            @endcan
            <button class="btn btn-sm btn-link ms-auto" id="clearSel">Clear selection</button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 vm-table">
                <thead class="table-light">
                    <tr>
                        <th style="width:36px"><input class="form-check-input" type="checkbox" id="selAll"></th>
                        <th>Product</th>
                        <th>Barcode / SKU</th>
                        <th class="text-end">Cost</th>
                        <th class="text-end">Selling price</th>
                        <th class="text-end">Margin</th>
                        <th>Packs</th>
                        <th class="text-end">Stock</th>
                        <th>Status</th>
                        <th style="width:60px"></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($products as $p)
                    @php
                        $sell = $p->selling_price;
                        $margin = $p->cost_price > 0 ? ($sell - $p->cost_price) / $sell * 100 : null;
                        $unit = $p->baseUnitName();
                        $stock = (float) $p->stock;
                        $reorder = (float) ($p->reorder_level ?? 10);
                    @endphp
                    <tr data-id="{{ $p->id }}">
                        <td><input class="form-check-input row-check" type="checkbox" value="{{ $p->id }}"></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-sm bg-light rounded p-1 flex-shrink-0">
                                    @if ($p->thumbnail)
                                        <img src="{{ asset('storage/' . $p->thumbnail) }}" alt="" class="img-fluid d-block h-100 w-100" style="object-fit:cover">
                                    @else
                                        <span class="avatar-title bg-light text-muted rounded"><i class="bi bi-image"></i></span>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <a href="{{ route('products.show', $p->id) }}" class="fw-medium text-reset d-block text-truncate" style="max-width:260px">{{ $p->title }}</a>
                                    <div class="fs-xs text-muted">
                                        {{ $p->category->name ?? 'Uncategorised' }}
                                        @if ($p->brand) · {{ $p->brand->name }} @endif
                                        @if ($p->product_type === 'variable') · <span class="badge bg-info-subtle text-info">{{ $p->active_variations_count }} variations</span> @endif
                                        @if ($p->is_weighted) · <span class="badge bg-secondary-subtle text-secondary">by weight</span> @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if ($p->barcode)
                                <span class="vm-mono">{{ $p->barcode }}</span>
                            @else
                                <span class="badge bg-danger-subtle text-danger">No barcode</span>
                            @endif
                            <div class="fs-xs text-muted vm-mono">{{ $p->sku }}</div>
                        </td>
                        <td class="text-end">{{ $p->cost_price ? money($p->cost_price) : '—' }}</td>
                        <td class="text-end">
                            @if ($p->isPromoActive())
                                <del class="text-muted fs-xs">{{ money($p->price) }}</del>
                                <div class="fw-semibold text-danger">{{ money($sell) }} <span class="badge bg-danger">Promo</span></div>
                            @elseif ($p->sale_price && $p->sale_price < $p->price)
                                <del class="text-muted fs-xs">{{ money($p->price) }}</del>
                                <div class="fw-semibold">{{ money($sell) }}</div>
                            @else
                                <span class="fw-semibold">{{ money($sell) }}</span>
                            @endif
                            @if ($p->wholesale_price)
                                <div class="fs-xs text-muted">Wholesale {{ money($p->wholesale_price) }}</div>
                            @endif
                        </td>
                        <td class="text-end">
                            @if ($margin === null)
                                <span class="text-muted">—</span>
                            @else
                                <span class="badge {{ $margin < 0 ? 'bg-danger' : ($margin < 10 ? 'bg-warning-subtle text-warning' : 'bg-success-subtle text-success') }}">{{ number_format($margin, 1) }}%</span>
                            @endif
                        </td>
                        <td>
                            @foreach ($p->packs->where('is_base', false)->take(3) as $pack)
                                <span class="vm-chip" title="{{ $pack->barcode ? 'Barcode ' . $pack->barcode : 'No barcode' }}">
                                    {{ $pack->unit->name ?? 'Pack' }} ×{{ qty($pack->quantity_per_unit) }}
                                    @if ($pack->price) · {{ money($pack->price, 0) }} @endif
                                </span>
                            @endforeach
                            @if ($p->packs->where('is_base', false)->isEmpty())
                                <span class="text-muted fs-xs">{{ ucfirst($unit) }} only</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <span class="fw-semibold {{ $stock <= 0 ? 'text-danger' : ($stock <= $reorder ? 'text-warning' : '') }}">{{ qty($stock) }}</span>
                            <span class="text-muted fs-xs">{{ $unit }}</span>
                            @if ($stock <= 0)
                                <div><span class="badge bg-danger-subtle text-danger">Out</span></div>
                            @elseif ($stock <= $reorder)
                                <div><span class="badge bg-warning-subtle text-warning">Low</span></div>
                            @endif
                        </td>
                        <td>
                            @if ($p->is_active)
                                <span class="badge bg-success-subtle text-success">Active</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-subtle-secondary btn-sm btn-icon" data-bs-toggle="dropdown" aria-label="Actions"><i class="bi bi-three-dots-vertical"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="{{ route('products.show', $p->id) }}"><i class="bi bi-eye me-2"></i>View</a></li>
                                    @can('Update product')
                                        <li><a class="dropdown-item" href="{{ route('products.edit', $p->id) }}"><i class="bi bi-pencil me-2"></i>Edit</a></li>
                                    @endcan
                                    @can('Create product')
                                        <li><a class="dropdown-item" href="{{ route('products.create', ['copy' => $p->id]) }}"><i class="bi bi-copy me-2"></i>Duplicate</a></li>
                                    @endcan
                                    @canany(['Create stock adjustment', 'Adjust stock'])
                                        <li><a class="dropdown-item quick-adjust" href="#" data-id="{{ $p->id }}" data-title="{{ $p->title }}" data-stock="{{ $stock }}" data-unit="{{ $unit }}" data-variable="{{ $p->product_type === 'variable' ? 1 : 0 }}"><i class="bi bi-sliders me-2"></i>Adjust stock</a></li>
                                    @endcanany
                                    @can('Print barcode labels')
                                        <li><a class="dropdown-item" target="_blank" href="{{ route('products.labels', ['ids' => $p->id, 'include_packs' => 1]) }}"><i class="bi bi-upc me-2"></i>Print labels</a></li>
                                    @endcan
                                    @canany(['Manage price control', 'View price history'])
                                        <li><a class="dropdown-item" href="{{ route('pricing.history', ['product_id' => $p->id]) }}"><i class="bi bi-clock-history me-2"></i>Price history</a></li>
                                    @endcanany
                                    @can('View audit trail')
                                        <li><a class="dropdown-item" href="{{ route('audit.index', ['subject_type' => \App\Models\Product::class, 'subject_id' => $p->id]) }}"><i class="bi bi-journal-text me-2"></i>Audit trail</a></li>
                                    @endcan
                                    @can('Delete product')
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger delete-product" href="#" data-id="{{ $p->id }}" data-title="{{ $p->title }}"><i class="bi bi-trash me-2"></i>Delete</a></li>
                                    @endcan
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center text-muted py-5">
                        <i class="bi bi-box-seam fs-1 d-block mb-2"></i>No products match these filters.
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="text-muted fs-sm">
                Showing {{ $products->firstItem() ?? 0 }}–{{ $products->lastItem() ?? 0 }} of {{ $products->total() }}
                <select class="form-select form-select-sm d-inline-block w-auto ms-2" onchange="const u=new URL(location);u.searchParams.set('per_page',this.value);location=u">
                    @foreach ([25, 50, 100, 200] as $n)<option @selected(request('per_page', 25) == $n)>{{ $n }}</option>@endforeach
                </select> per page
            </div>
            {!! $products->links('pagination::bootstrap-5') !!}
        </div>
    </div>

</div>
</div>
</div>

{{-- Quick stock adjust --}}
<div class="modal fade" id="adjustModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="adjustForm">
            <div class="modal-header">
                <h5 class="modal-title">Adjust stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="product_id">
                <div class="mb-3">
                    <div class="fw-semibold" id="adjTitle"></div>
                    <div class="text-muted fs-sm">Current stock: <span id="adjStock"></span></div>
                </div>
                <div class="mb-3" id="adjVariationWrap" style="display:none">
                    <label class="form-label">Variation</label>
                    <select class="form-select" name="variation_id" id="adjVariation"></select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-5">
                        <label class="form-label">Action</label>
                        <select class="form-select" name="mode" id="adjMode">
                            <option value="add">Add stock</option>
                            <option value="remove">Remove stock</option>
                            <option value="set">Set counted qty</option>
                        </select>
                    </div>
                    <div class="col-3">
                        <label class="form-label">Qty</label>
                        <input type="number" step="any" min="0" class="form-control" name="quantity" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label">In</label>
                        <select class="form-select" name="pack_id" id="adjPack"></select>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-7">
                        <label class="form-label">Reason</label>
                        <select class="form-select" name="reason" required>
                            @foreach (\App\Models\StockAdjustment::REASONS as $r)<option>{{ $r }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-5">
                        <label class="form-label">Location</label>
                        <select class="form-select" name="location_id">
                            @foreach ($locations as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <label class="form-label">Notes</label>
                <textarea class="form-control" name="notes" rows="2" placeholder="Optional"></textarea>
                <div class="alert alert-info fs-sm mt-3 mb-0" id="adjApprovalNote">
                    <i class="bi bi-shield-check me-1"></i>
                    @if (auth()->user()->can('Approve stock adjustment') || !sys_setting('adjustment_requires_approval', true))
                        You can approve adjustments, so stock will update immediately.
                    @else
                        This adjustment will wait for a supervisor's approval before stock changes.
                    @endif
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save adjustment</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('page-scripts')
<script>
(function () {
    const checks = () => [...document.querySelectorAll('.row-check')];
    const selected = () => checks().filter(c => c.checked).map(c => c.value);
    const bar = document.getElementById('bulkBar');

    function refresh() {
        const ids = selected();
        document.getElementById('selCount').textContent = ids.length;
        bar.classList.toggle('show', ids.length > 0);
        checks().forEach(c => c.closest('tr').classList.toggle('vm-selected', c.checked));
    }
    document.getElementById('selAll')?.addEventListener('change', e => { checks().forEach(c => c.checked = e.target.checked); refresh(); });
    checks().forEach(c => c.addEventListener('change', refresh));
    document.getElementById('clearSel')?.addEventListener('click', () => { checks().forEach(c => c.checked = false); document.getElementById('selAll').checked = false; refresh(); });

    document.querySelectorAll('[data-bulk]').forEach(btn => btn.addEventListener('click', () => {
        const ids = selected().join(',');
        const map = {
            labels: @json(route('products.labels')) + '?include_packs=1&ids=' + ids,
            adjust: @json(route('adjustments.create')) + '?type=adjustment&ids=' + ids,
            count: @json(route('adjustments.create')) + '?type=count&ids=' + ids,
            price: @json(route('pricing.index')) + '?ids=' + ids,
        };
        if (btn.dataset.bulk === 'labels') window.open(map.labels, '_blank'); else location.href = map[btn.dataset.bulk];
    }));

    document.querySelectorAll('[data-bulk-action]').forEach(a => a.addEventListener('click', async e => {
        e.preventDefault();
        const action = a.dataset.bulkAction;
        let value = null;
        if (action === 'reorder_level') {
            value = await VM.prompt('Reorder level', 'Alert me when stock falls to', true, 'number');
            if (value === null) return;
        }
        if (action === 'category') {
            const opts = {!! json_encode($categories->pluck('name', 'id')) !!};
            const r = await Swal.fire({title: 'Move to category', input: 'select', inputOptions: opts, showCancelButton: true});
            if (!r.isConfirmed) return; value = r.value;
        }
        try {
            const res = await VM.api(@json(route('products.bulk-action')), {method: 'POST', body: {ids: selected(), action, value}});
            VM.toast(res.message); setTimeout(() => location.reload(), 700);
        } catch (err) { VM.error(err); }
    }));

    // Delete (archives products that have history)
    document.querySelectorAll('.delete-product').forEach(a => a.addEventListener('click', async e => {
        e.preventDefault();
        if (!await VM.confirm('Delete ' + a.dataset.title + '?', 'Products with sales or stock history are deactivated instead, so reports stay correct.', 'Delete')) return;
        try {
            const res = await VM.api('/products/' + a.dataset.id, {method: 'DELETE'});
            VM.toast(res.message); setTimeout(() => location.reload(), 800);
        } catch (err) { VM.error(err); }
    }));

    // Quick adjust
    const modalEl = document.getElementById('adjustModal');
    const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
    const form = document.getElementById('adjustForm');
    document.querySelectorAll('.quick-adjust').forEach(a => a.addEventListener('click', async e => {
        e.preventDefault();
        form.reset();
        form.product_id.value = a.dataset.id;
        document.getElementById('adjTitle').textContent = a.dataset.title;
        document.getElementById('adjStock').textContent = VM.qty(a.dataset.stock) + ' ' + a.dataset.unit;
        const packSel = document.getElementById('adjPack');
        const varSel = document.getElementById('adjVariation');
        packSel.innerHTML = ''; varSel.innerHTML = '';
        try {
            const items = await VM.api(@json(route('products.lookup')) + '?ids=' + a.dataset.id);
            const first = items[0];
            (first?.packs || []).forEach(p => packSel.insertAdjacentHTML('beforeend', `<option value="${p.id}">${VM.esc(p.name)}${p.qty != 1 ? ' (×' + VM.qty(p.qty) + ')' : ''}</option>`));
            const isVar = items.length > 1 || (first && first.variation_id);
            document.getElementById('adjVariationWrap').style.display = isVar ? '' : 'none';
            if (isVar) items.forEach(i => varSel.insertAdjacentHTML('beforeend', `<option value="${i.variation_id}">${VM.esc(i.title)} — ${VM.qty(i.stock)} in stock</option>`));
        } catch (err) { /* packs optional */ }
        modal.show();
    }));
    form?.addEventListener('submit', async e => {
        e.preventDefault();
        const body = Object.fromEntries(new FormData(form).entries());
        if (!body.variation_id) delete body.variation_id;
        try {
            const res = await VM.api(@json(route('adjustments.quick')), {method: 'POST', body});
            modal.hide();
            Swal.fire({icon: res.status === 'approved' ? 'success' : 'info', title: res.status === 'approved' ? 'Stock updated' : 'Sent for approval', text: res.message})
                .then(() => location.reload());
        } catch (err) { VM.error(err); }
    });
})();
</script>
@endpush

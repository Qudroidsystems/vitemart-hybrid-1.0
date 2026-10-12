@extends('layouts.master')

@section('content')
@php
    $central = \App\Services\MultiBranch\Install::isCentral();
    $standalone = \App\Services\MultiBranch\Install::isStandalone();
    $mb = !$standalone;
@endphp
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0">{{ $direction === 'send' ? 'Send stock to another branch' : 'Request stock from another branch' }}</h4>
            <p class="text-muted mb-0 mt-1 fs-sm">Quantities can be in packs (cartons, packs…); stock moves in single units.
                @if ($mb) Only head-office catalogue items can move between branches. @endif</p>
        </div>
        <a href="{{ route('transfers.index') }}" class="btn btn-light btn-sm"><i class="bi bi-arrow-left me-1"></i>All transfers</a>
    </div>

    <div class="row g-3">
        <div class="col-xl-9">
            <div class="card" id="editor">
                <div class="card-header position-relative">
                    <input type="text" class="form-control form-control-lg le-scan" placeholder="Scan a barcode or type a product name…" autocomplete="off" autofocus>
                    <div class="list-group le-results shadow"></div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 le-table">
                        <thead class="table-light"><tr><th>Product</th><th class="text-end">Stock here</th><th>In</th><th style="width:140px">Quantity</th><th></th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-3">
            <div class="card">
                <div class="card-body">
                    <label class="form-label">From</label>
                    <select id="from" class="form-select mb-3">
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" @selected($direction === 'send' ? $b->id === $here : ($b->id !== $here && $b->type === 'warehouse'))>{{ $b->code }} – {{ $b->name }}</option>
                        @endforeach
                    </select>
                    <label class="form-label">To</label>
                    <select id="to" class="form-select mb-3">
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" @selected($direction === 'send' ? false : $b->id === $here)>{{ $b->code }} – {{ $b->name }}</option>
                        @endforeach
                    </select>
                    <label class="form-label">Note</label>
                    <textarea id="notes" class="form-control mb-3" rows="2" maxlength="500" placeholder="e.g. for the weekend promo"></textarea>
                    <div class="d-flex justify-content-between fs-sm mb-3"><span class="text-muted">Lines</span><span class="fw-semibold" id="nLines">0</span></div>
                    <button class="btn btn-primary w-100" id="save"><span class="spinner-border spinner-border-sm d-none me-1"></span>{{ $direction === 'send' ? 'Create transfer' : 'Send request' }}</button>
                </div>
            </div>
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
    const mb = @json($mb);
    const ed = VM.lineEditor({
        root: document.getElementById('editor'), lookup: @json(route('products.lookup')), showStock: true,
        filter: it => !mb || it.owner === 'central',
        noMatch: mb ? 'No head-office catalogue item matches' : 'No products found',
        fields: [{key: 'qty', label: 'Qty', type: 'number', default: 1}],
        onChange: e => document.getElementById('nLines').textContent = e.rows.length,
    });
    document.getElementById('save').addEventListener('click', async e => {
        const btn = e.currentTarget;
        const lines = ed.payload().filter(l => Number(l.qty) > 0);
        if (!lines.length) return VM.toast('Add at least one product with a quantity', 'warning');
        btn.disabled = true; btn.querySelector('.spinner-border').classList.remove('d-none');
        try {
            const r = await VM.api(@json(route('transfers.store')), {method: 'POST', body: {
                from_branch_id: document.getElementById('from').value, to_branch_id: document.getElementById('to').value,
                notes: document.getElementById('notes').value, lines}});
            VM.toast(r.message); location.href = r.redirect;
        } catch (err) { VM.error(err); btn.disabled = false; btn.querySelector('.spinner-border').classList.add('d-none'); }
    });
})();
</script>
@endpush

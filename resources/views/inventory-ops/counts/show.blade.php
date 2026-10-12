@extends('layouts.master')

@section('content')
@php $open = $count->status === 'open'; $done = $lines->whereNotNull('counted_qty')->count(); @endphp
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0">{{ $count->title }} <span class="vm-mono text-muted fs-base">{{ $count->number }}</span> @include('inventory-ops.partials.badge', ['status' => $count->status])</h4>
            <p class="text-muted mb-0 mt-1 fs-sm">{{ $count->location->name ?? '' }} · {{ $count->blind ? 'blind count' : 'expected quantities shown' }} ·
                <span id="progress">{{ $done }}</span> of {{ $lines->count() }} counted
                @if ($count->adjustment) · <a href="{{ route('adjustments.show', $count->adjustment) }}">{{ $count->adjustment->reference }}</a> ({{ $count->adjustment->status }}) @endif</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('counts.index') }}" class="btn btn-light btn-sm">All counts</a>
            @if ($open)
                <button class="btn btn-light btn-sm text-danger" id="cancel">Cancel count</button>
                <button class="btn btn-success btn-sm" id="submit"><i class="bi bi-send-check me-1"></i>Submit count</button>
            @endif
        </div>
    </div>

    @if ($open && $lines->where('recount', true)->count())
        <div class="alert alert-warning"><i class="bi bi-arrow-repeat me-1"></i><strong>{{ $lines->where('recount', true)->count() }} item(s) need a recount</strong> – they are highlighted. Count them again carefully, then submit.</div>
    @endif

    <div class="card">
        @if ($open)
            <div class="card-header"><input type="text" class="form-control form-control-lg" id="find" placeholder="Scan a barcode or type to jump to a product…" autocomplete="off" autofocus></div>
        @endif
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" id="sheet">
                <thead class="table-light"><tr><th>Category</th><th>Product</th><th>Barcode</th>
                    @if ($showSystem)<th class="text-end">Expected</th>@endif
                    <th style="width:140px">Counted</th>
                    @if (!$open)<th class="text-end">Difference</th>@endif
                </tr></thead>
                <tbody>
                @foreach ($lines as $l)
                    @php $bc = $l->variation->barcode ?? $l->product->barcode ?? ''; @endphp
                    <tr data-id="{{ $l->id }}" data-search="{{ strtolower(($l->product->title ?? '') . ' ' . ($l->variation->label ?? '') . ' ' . ($l->product->sku ?? '')) }}" data-bc="{{ $bc }}"
                        class="{{ $l->recount ? 'table-warning' : '' }}">
                        <td class="fs-sm text-muted">{{ $l->product->category->name ?? '' }}</td>
                        <td>{{ $l->product->title ?? '?' }}{{ $l->variation ? ' – ' . $l->variation->label : '' }}</td>
                        <td class="vm-mono fs-xs">{{ $bc }}</td>
                        @if ($showSystem)<td class="text-end">{{ qty($open ? ($system[$l->id] ?? 0) : $l->system_qty) }}</td>@endif
                        <td>
                            @if ($open)
                                <input type="number" step="any" min="0" class="form-control form-control-sm cnt" value="{{ $l->counted_qty !== null ? (float) $l->counted_qty : '' }}">
                            @else
                                {{ $l->counted_qty !== null ? qty($l->counted_qty) : '—' }}
                            @endif
                        </td>
                        @if (!$open)
                            <td class="text-end fw-semibold {{ $l->variance < 0 ? 'text-danger' : ($l->variance > 0 ? 'text-success' : 'text-muted') }}">{{ $l->variance !== null ? ($l->variance > 0 ? '+' : '') . qty($l->variance) : '' }}</td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
</div>
@endsection

@if ($open)
@push('page-scripts')
<script>
(() => {
    const base = @json(url('/stock-counts/' . $count->id));
    const rows = [...document.querySelectorAll('#sheet tbody tr')];
    let pending = {};
    const save = async () => {
        const entries = Object.entries(pending).map(([line_id, counted_qty]) => ({line_id: Number(line_id), counted_qty}));
        if (!entries.length) return;
        pending = {};
        try { await VM.api(`${base}/record`, {method: 'POST', body: {entries}}); } catch (e) { VM.error(e); }
        document.getElementById('progress').textContent = rows.filter(r => r.querySelector('.cnt').value !== '').length;
    };
    const flush = VM.debounce(save, 600);
    rows.forEach(r => r.querySelector('.cnt').addEventListener('input', e => { pending[r.dataset.id] = e.target.value === '' ? null : e.target.value; r.classList.remove('table-warning'); flush(); }));
    rows.forEach(r => r.querySelector('.cnt').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); document.getElementById('find').focus(); document.getElementById('find').select(); } }));

    const find = document.getElementById('find');
    find.addEventListener('keydown', e => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const q = find.value.trim().toLowerCase(); if (!q) return;
        const r = rows.find(x => x.dataset.bc === q) || rows.find(x => x.dataset.search.includes(q));
        if (!r) { VM.toast('Not on this count sheet', 'warning'); return; }
        r.scrollIntoView({block: 'center'}); const i = r.querySelector('.cnt');
        // scanning the same item again adds one
        if (r.dataset.bc === q && i.value !== '') { i.value = Number(i.value) + 1; i.dispatchEvent(new Event('input')); }
        i.focus(); i.select();
    });
    find.addEventListener('input', () => {
        const q = find.value.trim().toLowerCase();
        rows.forEach(r => r.style.display = !q || /^\d{6,}$/.test(q) || r.dataset.search.includes(q) ? '' : 'none');
    });

    document.getElementById('submit').addEventListener('click', async () => {
        await save();
        const empty = rows.filter(r => r.querySelector('.cnt').value === '').length;
        const r = await Swal.fire({title: 'Submit this count?', icon: 'question', html: empty ? `${empty} item(s) have not been counted.<div class="form-check mt-2 text-start d-inline-block"><input class="form-check-input" type="checkbox" id="zero"><label class="form-check-label" for="zero">Treat them as zero (nothing on the shelf)</label></div>` : 'Every item has been counted.',
            showCancelButton: true, confirmButtonText: 'Submit', preConfirm: () => ({uncounted_as_zero: document.getElementById('zero')?.checked ? 1 : 0})});
        if (!r.isConfirmed) return;
        try {
            const res = await VM.api(`${base}/submit`, {method: 'POST', body: r.value});
            if (res.recount) { await Swal.fire({icon: 'warning', title: 'Recount needed', text: res.message}); location.reload(); return; }
            VM.toast(res.message); setTimeout(() => location.href = res.redirect, 900);
        } catch (e) { VM.error(e); }
    });
    document.getElementById('cancel').addEventListener('click', async () => {
        if (!await VM.confirm('Cancel this count?', 'The counts entered so far are discarded. Stock does not change.')) return;
        try { await VM.api(`${base}/cancel`, {method: 'POST'}); location.href = @json(route('counts.index')); } catch (e) { VM.error(e); }
    });
})();
</script>
@endpush
@endif

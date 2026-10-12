@extends('layouts.master')

@section('content')
@php
    $t = $transfer;
    $steps = ['requested' => 'Requested', 'approved' => 'Approved', 'dispatched' => 'On the way', 'received' => 'Received', 'closed' => 'Closed'];
    $rank = \App\Models\StockTransfer::RANK[$t->status] ?? 0;
    $fmt = fn ($l, $q) => $q === null ? '—' : ($l->qty_per_unit != 1 && fmod($q, $l->qty_per_unit) == 0 ? qty($q / $l->qty_per_unit) . ' × ' . ($l->pack->unit->name ?? 'pack') : qty($q));
@endphp
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0">Transfer <span class="vm-mono">{{ $t->number }}</span>
                @include('inventory-ops.partials.badge', ['status' => $t->status, 'label' => \App\Models\StockTransfer::STATUSES[$t->status] ?? $t->status])</h4>
            <p class="text-muted mb-0 mt-1"><strong>{{ $t->fromBranch->code }}</strong> {{ $t->fromBranch->name }} <i class="bi bi-arrow-right mx-1"></i> <strong>{{ $t->toBranch->code }}</strong> {{ $t->toBranch->name }}
                @if ($t->carrier) · <i class="bi bi-truck"></i> {{ $t->carrier }} @endif</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('transfers.print', $t) }}" target="_blank" class="btn btn-light btn-sm"><i class="bi bi-printer me-1"></i>Delivery note</a>
            <a href="{{ route('transfers.index') }}" class="btn btn-light btn-sm">All transfers</a>
        </div>
    </div>

    @if (!in_array($t->status, ['cancelled', 'rejected']))
        <div class="card"><div class="card-body py-3">
            <div class="d-flex justify-content-between flex-wrap gap-2">
                @foreach ($steps as $k => $label)
                    @php $done = (\App\Models\StockTransfer::RANK[$k]) <= $rank; $at = $t->{$k . '_at'} ?? null; $by = $t->{$k . '_by_name'} ?? null; @endphp
                    <div class="text-center flex-fill">
                        <div class="rounded-circle mx-auto mb-1 d-flex align-items-center justify-content-center {{ $done ? 'bg-success text-white' : 'bg-light text-muted' }}" style="width:30px;height:30px"><i class="bi {{ $done ? 'bi-check-lg' : 'bi-circle' }}"></i></div>
                        <div class="fs-sm fw-semibold">{{ $label }}</div>
                        <div class="fs-xs text-muted">{{ $at?->format('d M H:i') }}{{ $by ? ' · ' . $by : '' }}</div>
                    </div>
                @endforeach
            </div>
        </div></div>
    @else
        <div class="alert alert-secondary">{{ ucfirst($t->status) }}{{ $t->cancel_reason ? ': ' . $t->cancel_reason : '' }} · {{ $t->closed_by_name }} {{ $t->closed_at?->diffForHumans() }}</div>
    @endif

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="card-title mb-0">Items</h6>
            <div class="d-flex gap-2">
                @if ($can['approve'])
                    @canany(['Approve stock transfer', 'Allocate stock'])
                        <button class="btn btn-sm btn-success act" data-act="approve" data-col="approved">Approve</button>
                        <button class="btn btn-sm btn-light text-danger act" data-act="reject">Reject</button>
                    @endcanany
                @endif
                @if ($can['dispatch'])
                    @can('Dispatch stock transfer')<button class="btn btn-sm btn-primary act" data-act="dispatch" data-col="dispatched"><i class="bi bi-truck me-1"></i>Dispatch</button>@endcan
                @endif
                @if ($can['receive'])
                    @can('Receive stock transfer')<button class="btn btn-sm btn-success act" data-act="receive" data-col="received"><i class="bi bi-box-seam me-1"></i>Receive</button>@endcan
                @endif
                @if ($can['cancel'])
                    <button class="btn btn-sm btn-light act" data-act="cancel">Cancel transfer</button>
                @endif
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0" id="lines">
                <thead class="table-light"><tr><th>Product</th><th class="text-end">Asked</th><th class="text-end">Approved</th><th class="text-end">Sent</th><th class="text-end">Received</th><th class="text-end">Difference</th><th class="text-end">Value</th><th></th></tr></thead>
                <tbody>
                @foreach ($t->lines as $l)
                    @php $val = ((float) ($l->received_qty ?? $l->dispatched_qty ?? $l->approved_qty ?? $l->requested_qty)) * (float) $l->unit_cost; @endphp
                    <tr data-uid="{{ $l->uid }}" data-per="{{ $l->qty_per_unit }}"
                        data-approved="{{ ($l->approved_qty ?? $l->requested_qty) / $l->qty_per_unit }}" data-dispatched="{{ ($l->dispatched_qty ?? $l->approved_qty ?? 0) / $l->qty_per_unit }}" data-received="{{ ($l->dispatched_qty ?? 0) / $l->qty_per_unit }}">
                        <td><div class="fw-medium">{{ $l->label() }}</div><div class="fs-xs text-muted vm-mono">{{ $l->product->sku ?? '' }} · {{ $l->pack->unit->name ?? 'unit' }}{{ $l->qty_per_unit != 1 ? ' of ' . qty($l->qty_per_unit) : '' }}</div></td>
                        <td class="text-end">{{ $fmt($l, $l->requested_qty) }}</td>
                        <td class="text-end">{{ $fmt($l, $l->approved_qty) }}</td>
                        <td class="text-end">{{ $fmt($l, $l->dispatched_qty) }}</td>
                        <td class="text-end">{{ $fmt($l, $l->received_qty) }}</td>
                        <td class="text-end {{ $l->discrepancy_qty < 0 ? 'text-danger' : ($l->discrepancy_qty > 0 ? 'text-warning' : '') }}">
                            {{ $l->discrepancy_qty != 0 ? ($l->discrepancy_qty > 0 ? '+' : '') . qty($l->discrepancy_qty) : '' }}
                            @if ($l->discrepancy_status !== 'none')
                                <div class="fs-xs">{{ ['open' => 'to resolve', 'written_off' => 'written off', 'charged' => 'charged', 'found' => 'found'][$l->discrepancy_status] ?? $l->discrepancy_status }}</div>
                                @if ($l->discrepancy_note)<div class="fs-xs text-muted">{{ $l->discrepancy_note }}</div>@endif
                            @endif
                        </td>
                        <td class="text-end">{{ money($val) }}</td>
                        <td class="text-end">
                            @if ($can['resolve'] && $l->discrepancy_status === 'open')
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-soft-danger dropdown-toggle" data-bs-toggle="dropdown">Resolve</button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item resolve" href="#" data-line="{{ $l->id }}" data-res="written_off">Write off the difference</a></li>
                                        <li><a class="dropdown-item resolve" href="#" data-line="{{ $l->id }}" data-res="charged">Charge to someone…</a></li>
                                        @if ($can['found'] && $l->discrepancy_qty < 0)<li><a class="dropdown-item resolve" href="#" data-line="{{ $l->id }}" data-res="found">More turned up…</a></li>@endif
                                    </ul>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if ($t->notes)<div class="card-footer fs-sm"><i class="bi bi-chat-left-text me-1"></i>{{ $t->notes }}</div>@endif
    </div>
</div>
</div>
</div>
@endsection

@push('page-scripts')
<script>
(() => {
    const base = @json(url('/transfers/' . $t->id));
    const titles = {approve: 'Approve – quantities to send', dispatch: 'Dispatch – quantities loaded', receive: 'Receive – count what arrived'};
    document.querySelectorAll('.act').forEach(b => b.addEventListener('click', async () => {
        const act = b.dataset.act;
        let body = {};
        if (act === 'reject' || act === 'cancel') {
            const reason = await VM.prompt(act === 'reject' ? 'Reject this request?' : 'Cancel this transfer?', 'Reason');
            if (!reason) return;
            body.reason = reason;
        } else {
            const col = b.dataset.col;
            const rows = [...document.querySelectorAll('#lines tbody tr')];
            const html = `<div class="text-start" style="max-height:50vh;overflow:auto"><table class="table table-sm align-middle"><tbody>${rows.map(r => `<tr><td class="fs-sm">${VM.esc(r.querySelector('td .fw-medium').textContent)}<div class="fs-xs text-muted">${VM.esc(r.querySelector('td .fs-xs').textContent)}</div></td>
                <td style="width:110px"><input type="number" step="any" min="0" class="form-control form-control-sm q" data-uid="${r.dataset.uid}" value="${Number(r.dataset[col === 'approved' ? 'approved' : (col === 'dispatched' ? 'dispatched' : 'received')])}"></td></tr>`).join('')}</tbody></table></div>
                ${act === 'dispatch' ? '<input class="form-control mt-2" id="carrier" placeholder="Driver / vehicle / waybill (optional)">' : ''}
                ${act === 'receive' ? '<input class="form-control mt-2" id="rnote" placeholder="Note (optional) – e.g. 2 cartons damp">' : ''}
                <div class="fs-xs text-muted mt-2 text-start">Quantities are in the pack shown for each line.</div>`;
            const r = await Swal.fire({title: titles[act], html, width: 640, showCancelButton: true, confirmButtonText: act === 'approve' ? 'Approve' : (act === 'dispatch' ? 'Dispatch' : 'Receive'),
                preConfirm: () => ({qty: Object.fromEntries([...document.querySelectorAll('.swal2-html-container .q')].map(i => [i.dataset.uid, i.value])),
                    carrier: document.getElementById('carrier')?.value, note: document.getElementById('rnote')?.value})});
            if (!r.isConfirmed) return;
            body = r.value;
        }
        try { const res = await VM.api(`${base}/${act}`, {method: 'POST', body}); VM.toast(res.message); setTimeout(() => location.reload(), 900); }
        catch (err) { VM.error(err); }
    }));
    document.querySelectorAll('.resolve').forEach(a => a.addEventListener('click', async e => {
        e.preventDefault();
        const res = a.dataset.res; const body = {resolution: res};
        if (res === 'charged') { const n = await VM.prompt('Charge the difference to…', 'Name and reason'); if (!n) return; body.note = n; }
        if (res === 'found') { const q = await VM.prompt('How many turned up?', 'Quantity (in the pack shown)', true, 'number'); if (!q) return; body.found_qty = q; }
        if (res === 'written_off') { const n = await VM.prompt('Write off the difference?', 'Reason (optional)', false); if (n === null) return; body.note = n || 'Written off'; }
        try { const r = await VM.api(`${base}/lines/${a.dataset.line}/resolve`, {method: 'POST', body}); VM.toast(r.message); setTimeout(() => location.reload(), 700); }
        catch (err) { VM.error(err); }
    }));
})();
</script>
@endpush

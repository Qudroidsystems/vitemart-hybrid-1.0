@extends('layouts.master')

@section('content')
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0">Payments to confirm</h4>
            <p class="text-muted mb-0 mt-1 fs-sm">Transfers the till accepted before anyone saw the money arrive. Check the bank app, then confirm or mark as not received.</p>
        </div>
        <form class="d-flex gap-2" method="GET">
            <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Receipt or reference">
            <select name="method" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All methods</option>
                @foreach (['transfer' => 'Transfer', 'card' => 'Card'] as $k => $l)<option value="{{ $k }}" @selected(request('method') === $k)>{{ $l }}</option>@endforeach
            </select>
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach (['pending' => 'Waiting', 'confirmed' => 'Confirmed', 'failed' => 'Not received', 'all' => 'All'] as $k => $l)<option value="{{ $k }}" @selected($status === $k)>{{ $l }}</option>@endforeach
            </select>
        </form>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-sm-4"><div class="card mb-0"><div class="card-body"><div class="text-muted fs-sm">Waiting</div><div class="fs-3 fw-semibold">{{ number_format($totals->c ?? 0) }}</div></div></div></div>
        <div class="col-sm-4"><div class="card mb-0"><div class="card-body"><div class="text-muted fs-sm">Amount waiting</div><div class="fs-3 fw-semibold">{{ money($totals->t ?? 0) }}</div></div></div></div>
        <div class="col-sm-4"><div class="card mb-0"><div class="card-body"><div class="text-muted fs-sm">Oldest</div><div class="fs-3 fw-semibold {{ $oldest && \Carbon\Carbon::parse($oldest)->lt(now()->subHours(2)) ? 'text-danger' : '' }}">{{ $oldest ? \Carbon\Carbon::parse($oldest)->diffForHumans(null, true) : '—' }}</div></div></div></div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Time</th><th>Receipt</th><th>Method</th><th class="text-end">Amount</th><th>Reference</th><th>Customer</th><th>Cashier · till</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($payments as $p)
                    <tr>
                        <td class="fs-sm">{{ $p->created_at?->format('d M H:i') }}<div class="fs-xs text-muted">{{ $p->created_at?->diffForHumans() }}</div></td>
                        <td class="vm-mono fs-sm">{{ $p->order_id }}</td>
                        <td class="text-capitalize">{{ $p->method }}</td>
                        <td class="text-end fw-semibold">{{ money($p->amount) }}</td>
                        <td class="vm-mono fs-sm">{{ $p->reference ?: '—' }}</td>
                        <td class="fs-sm">{{ $p->customer ?: 'Walk-in' }}</td>
                        <td class="fs-sm">{{ $p->cashier }}{{ $p->till ? ' · ' . $p->till : '' }}</td>
                        <td>
                            @php $c = ['pending' => 'warning', 'confirmed' => 'success', 'failed' => 'danger', 'reversed' => 'secondary'][$p->status] ?? 'light'; @endphp
                            <span class="badge bg-{{ $c }}-subtle text-{{ $c }}">{{ ['pending' => 'Waiting', 'confirmed' => 'Confirmed', 'failed' => 'Not received', 'reversed' => 'Reversed'][$p->status] ?? $p->status }}</span>
                            @if ($p->status_note)<div class="fs-xs text-muted">{{ $p->status_note }}</div>@endif
                        </td>
                        <td class="text-end text-nowrap">
                            @if ($p->status === 'pending')
                                <button class="btn btn-sm btn-success pay-ok" data-id="{{ $p->id }}" data-ref="{{ $p->reference }}">Received</button>
                                <button class="btn btn-sm btn-light text-danger pay-fail" data-id="{{ $p->id }}">Not received</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">Nothing waiting. 👍</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($payments->hasPages())<div class="card-footer">{{ $payments->links() }}</div>@endif
    </div>
</div>
</div>
</div>
@endsection

@push('page-scripts')
<script>
document.addEventListener('click', async e => {
    const ok = e.target.closest('.pay-ok');
    if (ok) {
        const r = await Swal.fire({title: 'Money received?', input: 'text', inputLabel: 'Bank reference / session ID (optional)', inputValue: ok.dataset.ref || '', showCancelButton: true, confirmButtonText: 'Yes, received'});
        if (!r.isConfirmed) return;
        try { await VM.api(`/pos/payments/${ok.dataset.id}/confirm`, {method: 'POST', body: {reference: r.value || null}}); location.reload(); } catch (err) { VM.error(err); }
        return;
    }
    const bad = e.target.closest('.pay-fail');
    if (bad) {
        const reason = await VM.prompt('Not received', 'What happened? (e.g. fake alert, reversed by bank)');
        if (!reason) return;
        try { const res = await VM.api(`/pos/payments/${bad.dataset.id}/fail`, {method: 'POST', body: {reason}}); VM.toast(res.message, 'info'); setTimeout(() => location.reload(), 900); } catch (err) { VM.error(err); }
    }
});
</script>
@endpush

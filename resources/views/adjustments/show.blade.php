@extends('layouts.master')

@section('content')
@php $a = $adjustment; @endphp
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0"><span class="vm-mono">{{ $a->reference }}</span> <span class="badge bg-{{ $a->status_color }} text-capitalize align-middle fs-sm">{{ $a->status }}</span></h4>
            <p class="text-muted mb-0 mt-1 fs-sm">{{ $a->type_label }} · {{ $a->location->name ?? '' }} · created by {{ $a->creator->first_name ?? '—' }} {{ $a->created_at->format('d M Y, h:i A') }}</p>
        </div>
        <div class="d-flex gap-2 mt-2 mt-sm-0">
            <a href="{{ route('adjustments.index') }}" class="btn btn-light btn-sm"><i class="bi bi-arrow-left me-1"></i>All adjustments</a>
            <button class="btn btn-light btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
        </div>
    </div>

    @if ($a->status === 'approved')
        <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i>Approved by <strong>{{ $a->approver->first_name ?? '' }} {{ $a->approver->last_name ?? '' }}</strong> on {{ $a->approved_at?->format('d M Y, h:i A') }}. Stock has been updated.</div>
    @elseif ($a->status === 'rejected')
        <div class="alert alert-danger"><i class="bi bi-x-circle me-1"></i>Rejected by <strong>{{ $a->rejecter->first_name ?? '' }}</strong> on {{ $a->rejected_at?->format('d M Y, h:i A') }}: {{ $a->rejection_reason }}</div>
    @elseif ($a->isPending())
        <div class="alert alert-warning d-flex align-items-center flex-wrap gap-2">
            <i class="bi bi-hourglass-split"></i>
            <span class="me-auto">Waiting for approval – stock has <strong>not</strong> changed yet.</span>
            @if ($canApprove)
                <button class="btn btn-sm btn-danger" id="rejectBtn"><i class="bi bi-x-lg me-1"></i>Reject</button>
                <button class="btn btn-sm btn-success" id="approveBtn"><i class="bi bi-check-lg me-1"></i>Approve & update stock</button>
            @endif
            @if ($a->created_by === auth()->id() || $canApprove)
                <button class="btn btn-sm btn-light" id="cancelBtn">Cancel it</button>
            @endif
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-9">
            <div class="card">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light"><tr>
                            <th>Product</th><th>Entered</th><th class="text-end">{{ $a->isPending() ? 'System qty (at entry)' : 'Before' }}</th>
                            <th class="text-end">Change</th><th class="text-end">{{ $a->status === 'approved' ? 'After' : 'Expected after' }}</th>
                            @if ($a->isPending())<th class="text-end">Stock now</th>@endif
                            <th class="text-end">Value</th>
                        </tr></thead>
                        <tbody>
                        @foreach ($a->items as $i)
                            <tr>
                                <td>{{ $i->display_name }}<div class="fs-xs text-muted vm-mono">{{ $i->variation?->sku ?? $i->product->sku ?? '' }}</div>
                                    @if($i->expiry_date)<div class="fs-xs text-danger">Expires {{ $i->expiry_date->format('d M Y') }}</div>@endif
                                    @if($i->batch_number)<div class="fs-xs text-muted">Batch {{ $i->batch_number }}</div>@endif</td>
                                <td class="fs-sm">{{ ['add' => 'Add', 'remove' => 'Remove', 'set' => 'Counted'][$i->mode] ?? $i->mode }} {{ qty($i->entered_quantity) }} {{ $i->pack?->unit?->name ?? '' }}@if($i->quantity_per_unit != 1) <span class="text-muted">(×{{ qty($i->quantity_per_unit) }})</span>@endif</td>
                                <td class="text-end">{{ qty($i->system_quantity) }}</td>
                                <td class="text-end fw-semibold {{ $i->quantity_change < 0 ? 'text-danger' : 'text-success' }}">{{ $i->quantity_change > 0 ? '+' : '' }}{{ qty($i->quantity_change) }}</td>
                                <td class="text-end">{{ qty($i->balance_after ?? $i->projected_balance) }}</td>
                                @if ($a->isPending())
                                    @php $now = $currentBalances[$i->id] ?? 0; @endphp
                                    <td class="text-end {{ abs($now - $i->system_quantity) > 0.001 ? 'text-warning fw-semibold' : 'text-muted' }}" title="{{ abs($now - $i->system_quantity) > 0.001 ? 'Stock has moved since this was entered (sales etc.). The change is applied on top of the current stock.' : '' }}">{{ qty($now) }}</td>
                                @endif
                                <td class="text-end {{ $i->quantity_change * $i->unit_cost < 0 ? 'text-danger' : '' }}">{{ money($i->quantity_change * (float) $i->unit_cost) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot class="table-light"><tr><th colspan="{{ $a->isPending() ? 6 : 5 }}" class="text-end">Total value change at cost</th><th class="text-end">{{ money($a->total_value_change) }}</th></tr></tfoot>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="card"><div class="card-body">
                <dl class="mb-0">
                    <dt class="fs-xs text-muted text-uppercase">Reason</dt><dd>{{ $a->reason ?: '—' }}</dd>
                    @if($a->supplier)<dt class="fs-xs text-muted text-uppercase">Supplier</dt><dd>{{ $a->supplier }} {{ $a->supplier_invoice ? '· ' . $a->supplier_invoice : '' }}</dd>@endif
                    <dt class="fs-xs text-muted text-uppercase">Notes</dt><dd class="mb-0">{{ $a->notes ?: '—' }}</dd>
                </dl>
            </div></div>
            @can('View audit trail')
                <a href="{{ route('audit.index', ['subject_type' => \App\Models\StockAdjustment::class, 'subject_id' => $a->id]) }}" class="btn btn-light w-100"><i class="bi bi-journal-text me-1"></i>Audit trail</a>
            @endcan
        </div>
    </div>
</div>
</div>
</div>
@endsection

@push('page-scripts')
<script>
(function () {
    const id = @json($a->id);
    document.getElementById('approveBtn')?.addEventListener('click', async () => {
        if (!await VM.confirm('Approve {{ $a->reference }}?', 'Stock will be updated now.', 'Approve')) return;
        try { const r = await VM.api(`/stock-adjustments/${id}/approve`, {method: 'POST'}); await Swal.fire({icon: 'success', title: r.message}); location.reload(); } catch (e) { VM.error(e); }
    });
    document.getElementById('rejectBtn')?.addEventListener('click', async () => {
        const reason = await VM.prompt('Reject {{ $a->reference }}', 'Reason (the person who entered it will see this)');
        if (!reason) return;
        try { const r = await VM.api(`/stock-adjustments/${id}/reject`, {method: 'POST', body: {reason}}); await Swal.fire({icon: 'info', title: r.message}); location.reload(); } catch (e) { VM.error(e); }
    });
    document.getElementById('cancelBtn')?.addEventListener('click', async () => {
        if (!await VM.confirm('Cancel this adjustment?', 'Nothing will change in stock.')) return;
        try { await VM.api(`/stock-adjustments/${id}/cancel`, {method: 'POST'}); location.reload(); } catch (e) { VM.error(e); }
    });
})();
</script>
@endpush

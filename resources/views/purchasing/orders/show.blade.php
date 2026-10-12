@extends('layouts.master')

@section('content')
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0">Purchase order <span class="vm-mono">{{ $po->number }}</span>
                @include('inventory-ops.partials.badge', ['status' => $po->status, 'label' => \App\Models\PurchaseOrder::STATUSES[$po->status] ?? $po->status])</h4>
            <p class="text-muted mb-0 mt-1"><strong>{{ $po->supplier->name }}</strong> · for {{ $po->branch->code ?? '' }} {{ $po->branch->name ?? '' }}
                · expected {{ $po->expected_date?->format('d M Y') ?? '—' }} · by {{ $po->created_by_name }}
                @if ($po->approved_by_name) · approved by {{ $po->approved_by_name }} @endif</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('purchase-orders.print', $po) }}" target="_blank" class="btn btn-light btn-sm"><i class="bi bi-printer me-1"></i>Print / PDF</a>
            @if ($po->status === 'draft')@can('Create purchase order')<button class="btn btn-primary btn-sm act" data-act="submit">Submit</button>@endcan @endif
            @if ($po->status === 'submitted')@can('Approve purchase order')<button class="btn btn-success btn-sm act" data-act="approve">Approve</button>@endcan @endif
            @if ($po->status === 'approved')@can('Create purchase order')<button class="btn btn-primary btn-sm act" data-act="send">Mark as sent</button>@endcan @endif
            @if (in_array($po->status, ['approved', 'sent', 'partial']) && $here)
                @can('Post goods receipt')<a href="{{ route('goods-receipts.create', ['po' => $po->id]) }}" class="btn btn-success btn-sm"><i class="bi bi-box-seam me-1"></i>Receive goods</a>@endcan
            @endif
            @if (in_array($po->status, ['partial', 'sent', 'approved']))@can('Create purchase order')<button class="btn btn-light btn-sm act" data-act="close">Close</button>@endcan @endif
            @if (in_array($po->status, ['draft', 'submitted', 'approved', 'sent']))@can('Create purchase order')<button class="btn btn-light btn-sm text-danger act" data-act="cancel">Cancel</button>@endcan @endif
        </div>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>Product</th><th>Pack</th><th class="text-end">Ordered</th><th class="text-end">Received</th><th class="text-end">Outstanding</th><th class="text-end">Cost / pack</th><th class="text-end">Line total</th></tr></thead>
                <tbody>
                @foreach ($po->lines as $l)
                    <tr>
                        <td>{{ $l->product->title ?? '?' }}{{ $l->variation ? ' – ' . $l->variation->label : '' }}<div class="fs-xs text-muted vm-mono">{{ $l->product->sku ?? '' }}</div></td>
                        <td>{{ $l->pack->unit->name ?? 'unit' }}{{ $l->qty_per_unit != 1 ? ' ×' . qty($l->qty_per_unit) : '' }}</td>
                        <td class="text-end">{{ qty($l->ordered_qty) }}</td>
                        <td class="text-end">{{ qty($l->received_qty) }}</td>
                        <td class="text-end {{ $l->outstanding() > 0 ? 'fw-semibold' : 'text-muted' }}">{{ qty($l->outstanding()) }}</td>
                        <td class="text-end">{{ money($l->unit_cost) }}</td>
                        <td class="text-end">{{ money($l->ordered_qty * $l->unit_cost) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr class="fw-semibold"><td colspan="6" class="text-end">Total</td><td class="text-end">{{ money($po->total) }}</td></tr></tfoot>
            </table>
        </div>
        @if ($po->notes || $po->cancel_reason)<div class="card-footer fs-sm">{{ $po->notes }} {{ $po->cancel_reason ? '· Cancelled: ' . $po->cancel_reason : '' }}</div>@endif
    </div>
    @if ($po->receipts->isNotEmpty())
        <div class="card">
            <div class="card-header"><h6 class="card-title mb-0">Deliveries</h6></div>
            <ul class="list-group list-group-flush">
                @foreach ($po->receipts as $r)
                    <li class="list-group-item d-flex justify-content-between"><a href="{{ route('goods-receipts.show', $r) }}" class="vm-mono">{{ $r->number }}</a><span>{{ $r->created_at?->format('d M Y H:i') }} · invoice {{ $r->supplier_invoice ?: '—' }}</span><span>{{ money($r->total) }}</span></li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
</div>
</div>
@endsection

@push('page-scripts')
<script>
document.querySelectorAll('.act').forEach(b => b.addEventListener('click', async () => {
    const act = b.dataset.act; const body = {};
    if (act === 'cancel') { const r = await VM.prompt('Cancel this order?', 'Reason'); if (!r) return; body.reason = r; }
    if (act === 'close' && !await VM.confirm('Close this order?', 'Anything not yet delivered will no longer be expected.')) return;
    try { const res = await VM.api(`{{ url('/purchase-orders/' . $po->id) }}/${act}`, {method: 'POST', body}); VM.toast(res.message); setTimeout(() => location.reload(), 700); } catch (e) { VM.error(e); }
}));
</script>
@endpush

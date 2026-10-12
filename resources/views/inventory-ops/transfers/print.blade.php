<!doctype html>
<html><head><meta charset="utf-8"><title>Delivery note {{ $transfer->number }}</title>
<style>
    body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; font-size: 13px; color: #111; margin: 24px; }
    h1 { font-size: 20px; margin: 0 0 4px; } table { width: 100%; border-collapse: collapse; margin-top: 16px; }
    th, td { border-bottom: 1px solid #ddd; padding: 6px 4px; text-align: left; } th { background: #f4f4f4; } .r { text-align: right; }
    .grid { display: flex; gap: 32px; margin-top: 12px; } .sig { margin-top: 48px; display: flex; gap: 48px; } .sig div { flex: 1; border-top: 1px solid #333; padding-top: 4px; }
    @media print { .noprint { display: none; } body { margin: 0; } }
</style></head>
<body>
<button class="noprint" onclick="print()">Print</button>
<h1>Stock transfer – delivery note</h1>
<div><strong>{{ $transfer->number }}</strong> · {{ \App\Models\StockTransfer::STATUSES[$transfer->status] ?? $transfer->status }}</div>
<div class="grid">
    <div><div style="color:#666">From</div><strong>{{ $transfer->fromBranch->code }}</strong> {{ $transfer->fromBranch->name }}<br>{{ $transfer->fromBranch->address }}</div>
    <div><div style="color:#666">To</div><strong>{{ $transfer->toBranch->code }}</strong> {{ $transfer->toBranch->name }}<br>{{ $transfer->toBranch->address }}</div>
    <div><div style="color:#666">Dispatched</div>{{ $transfer->dispatched_at?->format('d M Y H:i') ?? '—' }}<br>{{ $transfer->carrier }}</div>
</div>
<table>
    <thead><tr><th>#</th><th>Product</th><th>Barcode</th><th>Pack</th><th class="r">Sent</th><th class="r">Received</th></tr></thead>
    <tbody>
    @foreach ($transfer->lines as $i => $l)
        @php $per = $l->qty_per_unit ?: 1; $sent = $l->dispatched_qty ?? $l->approved_qty ?? $l->requested_qty; @endphp
        <tr><td>{{ $i + 1 }}</td><td>{{ $l->label() }}</td><td>{{ $l->product->barcode ?? '' }}</td><td>{{ $l->pack->unit->name ?? 'unit' }}{{ $per != 1 ? ' ×' . qty($per) : '' }}</td>
            <td class="r">{{ qty($sent / $per) }}</td><td class="r">{{ $l->received_qty !== null ? qty($l->received_qty / $per) : '' }}</td></tr>
    @endforeach
    </tbody>
</table>
<div class="sig"><div>Dispatched by</div><div>Driver</div><div>Received by</div></div>
</body></html>

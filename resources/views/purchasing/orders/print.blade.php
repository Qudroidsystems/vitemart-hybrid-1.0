<!doctype html>
<html><head><meta charset="utf-8"><title>{{ $po->number }}</title>
<style>
    body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; font-size: 13px; color: #111; margin: 32px; }
    h1 { font-size: 22px; margin: 0; } table { width: 100%; border-collapse: collapse; margin-top: 18px; }
    th, td { border-bottom: 1px solid #ddd; padding: 7px 4px; text-align: left; } th { background: #f4f4f4; } .r { text-align: right; }
    .top { display: flex; justify-content: space-between; } .box { margin-top: 16px; display: flex; gap: 40px; } .muted { color: #666; }
    @media print { .noprint { display: none; } body { margin: 0; } }
</style></head>
<body>
<button class="noprint" onclick="print()">Print / save as PDF</button>
<div class="top">
    <div><h1>{{ $store->store_name ?? config('app.name') }}</h1><div class="muted">{{ $po->branch->address ?? $store->address ?? '' }} {{ $store->phone ?? '' }}</div></div>
    <div style="text-align:right"><h1>PURCHASE ORDER</h1><div><strong>{{ $po->number }}</strong></div><div class="muted">{{ $po->created_at?->format('d M Y') }}</div></div>
</div>
<div class="box">
    <div><div class="muted">Supplier</div><strong>{{ $po->supplier->name }}</strong><br>{{ $po->supplier->address }}<br>{{ $po->supplier->phone }} {{ $po->supplier->email }}</div>
    <div><div class="muted">Deliver to</div><strong>{{ $po->branch->name ?? '' }}</strong><br>{{ $po->branch->address ?? '' }}</div>
    <div><div class="muted">Expected</div>{{ $po->expected_date?->format('d M Y') ?? '—' }}<br><span class="muted">Terms:</span> {{ $po->supplier->payment_terms_days ? $po->supplier->payment_terms_days . ' days' : 'cash' }}</div>
</div>
<table>
    <thead><tr><th>#</th><th>Item</th><th>Barcode</th><th>Pack</th><th class="r">Qty</th><th class="r">Unit cost</th><th class="r">Amount</th></tr></thead>
    <tbody>
    @foreach ($po->lines as $i => $l)
        <tr><td>{{ $i + 1 }}</td><td>{{ $l->product->title ?? '' }}{{ $l->variation ? ' – ' . $l->variation->label : '' }}</td><td>{{ $l->pack->barcode ?? $l->product->barcode ?? '' }}</td>
            <td>{{ $l->pack->unit->name ?? 'unit' }}{{ $l->qty_per_unit != 1 ? ' ×' . qty($l->qty_per_unit) : '' }}</td><td class="r">{{ qty($l->ordered_qty) }}</td>
            <td class="r">{{ money($l->unit_cost) }}</td><td class="r">{{ money($l->ordered_qty * $l->unit_cost) }}</td></tr>
    @endforeach
    </tbody>
    <tfoot><tr><th colspan="6" class="r">Total</th><th class="r">{{ money($po->total) }}</th></tr></tfoot>
</table>
@if ($po->notes)<p><strong>Notes:</strong> {{ $po->notes }}</p>@endif
<p class="muted" style="margin-top:40px">Approved by: {{ $po->approved_by_name ?? '________________' }}</p>
</body></html>

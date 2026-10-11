{{-- Receipt email (inline styles – email apps ignore most CSS) --}}
@php
    $storeName = $store?->store_name ?: config('app.name');
    $sub = $order->subtotal ?? $order->items->sum('total_price');
    $paidTotal = $payments->sum('amount');
    $cashier = $order->user ? trim($order->user->first_name . ' ' . $order->user->last_name) : null;
    $customerName = $order->customer ? trim($order->customer->first_name . ' ' . $order->customer->last_name) : null;
    $td = 'padding:8px 0;border-bottom:1px solid #eef1f6;font-size:14px;color:#1d2433;vertical-align:top;';
@endphp
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Receipt {{ $order->id }}</title></head>
<body style="margin:0;padding:0;background:#eef1f6;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef1f6;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:14px;overflow:hidden;">

    {{-- Header --}}
    <tr><td style="background:#0f766e;padding:22px 26px;text-align:center;">
        @if ($store?->logo)
            <img src="{{ $store->getLogoUrlAttribute() }}" alt="{{ $storeName }}" style="max-height:56px;max-width:180px;margin-bottom:8px;border:0;">
        @endif
        <div style="font-size:20px;font-weight:bold;color:#ffffff;">{{ $storeName }}</div>
        @if ($store?->motto)<div style="font-size:12px;color:#ccfbf1;margin-top:2px;">{{ $store->motto }}</div>@endif
    </td></tr>

    <tr><td style="padding:22px 26px 6px;">
        <div style="font-size:16px;color:#1d2433;margin-bottom:4px;">{{ $customerName ? 'Hello ' . $customerName . ',' : 'Hello,' }}</div>
        <div style="font-size:14px;color:#6b7385;">Thank you for shopping with us. Here is your receipt.</div>
        @if ($order->status === 'cancelled')
            <div style="margin-top:12px;padding:10px;border:2px solid #c0392b;color:#c0392b;font-weight:bold;text-align:center;border-radius:8px;">THIS SALE WAS VOIDED</div>
        @endif
    </td></tr>

    {{-- Receipt details --}}
    <tr><td style="padding:12px 26px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border-radius:10px;">
            <tr>
                <td style="padding:12px 14px;font-size:12px;color:#6b7385;">Receipt no.<br><span style="font-size:14px;color:#1d2433;font-weight:bold;font-family:Consolas,monospace;">{{ $order->id }}</span></td>
                <td style="padding:12px 14px;font-size:12px;color:#6b7385;text-align:right;">Date<br><span style="font-size:14px;color:#1d2433;">{{ optional($order->order_date ?? $order->created_at)->format('d M Y, h:i A') }}</span></td>
            </tr>
            @if ($cashier)
                <tr><td colspan="2" style="padding:0 14px 12px;font-size:12px;color:#6b7385;">Served by {{ $cashier }}</td></tr>
            @endif
        </table>
    </td></tr>

    {{-- Items --}}
    <tr><td style="padding:6px 26px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="padding:6px 0;font-size:11px;color:#6b7385;text-transform:uppercase;border-bottom:2px solid #e3e7ef;">Item</td>
                <td style="padding:6px 0;font-size:11px;color:#6b7385;text-transform:uppercase;border-bottom:2px solid #e3e7ef;text-align:right;">Total</td>
            </tr>
            @foreach ($order->items as $item)
                <tr>
                    <td style="{{ $td }}">
                        <strong>{{ $item->title }}</strong><br>
                        <span style="font-size:12px;color:#6b7385;">{{ qty($item->quantity) }} {{ $item->unit_name ? strtolower($item->unit_name) : '' }} × {{ money($item->unit_price) }}</span>
                        @if (($item->discount_amount ?? 0) > 0)
                            <br><span style="font-size:12px;color:#c0392b;">Discount −{{ money($item->discount_amount) }}</span>
                        @endif
                    </td>
                    <td style="{{ $td }}text-align:right;white-space:nowrap;"><strong>{{ money($item->total_price) }}</strong></td>
                </tr>
            @endforeach
        </table>
    </td></tr>

    {{-- Totals --}}
    <tr><td style="padding:6px 26px 4px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr><td style="padding:4px 0;font-size:14px;color:#6b7385;">Subtotal</td><td style="padding:4px 0;font-size:14px;text-align:right;color:#1d2433;">{{ money($sub) }}</td></tr>
            @if (($order->discount_amount ?? 0) > 0)
                <tr><td style="padding:4px 0;font-size:14px;color:#6b7385;">Discount</td><td style="padding:4px 0;font-size:14px;text-align:right;color:#c0392b;">−{{ money($order->discount_amount) }}</td></tr>
            @endif
            <tr>
                <td style="padding:10px 0 4px;font-size:18px;font-weight:bold;color:#1d2433;border-top:2px solid #1d2433;">Total</td>
                <td style="padding:10px 0 4px;font-size:22px;font-weight:bold;color:#0f766e;text-align:right;border-top:2px solid #1d2433;">{{ money($order->total_amount) }}</td>
            </tr>
            @foreach ($payments as $pay)
                <tr><td style="padding:3px 0;font-size:13px;color:#6b7385;">Paid – {{ ucfirst($pay->method) }}{{ $pay->reference ? ' (' . $pay->reference . ')' : '' }}</td><td style="padding:3px 0;font-size:13px;text-align:right;color:#1d2433;">{{ money($pay->amount) }}</td></tr>
            @endforeach
            @if ($payments->isEmpty() && $order->payment_method)
                <tr><td style="padding:3px 0;font-size:13px;color:#6b7385;">Paid – {{ ucfirst($order->payment_method) }}</td><td style="padding:3px 0;font-size:13px;text-align:right;color:#1d2433;">{{ money($order->amount_paid ?? $order->total_amount) }}</td></tr>
            @endif
            @if (($order->change_due ?? 0) > 0)
                <tr><td style="padding:3px 0;font-size:13px;color:#6b7385;">Change</td><td style="padding:3px 0;font-size:13px;text-align:right;color:#1d2433;">{{ money($order->change_due) }}</td></tr>
            @endif
        </table>
    </td></tr>

    {{-- Footer --}}
    <tr><td style="padding:18px 26px 24px;text-align:center;">
        @if ($footer || $store?->footer_note)
            <div style="font-size:13px;color:#1d2433;margin-bottom:10px;">{{ $footer ?: $store->footer_note }}</div>
        @endif
        <div style="font-size:12px;color:#6b7385;line-height:1.6;">
            {{ $storeName }}@if ($store?->address) · {{ $store->address }}@endif
            @if ($store?->phone)<br>Phone: {{ $store->phone }}@endif
            @if ($store?->email)<br>{{ $store->email }}@endif
            @if ($store?->tax_id)<br>Tax ID: {{ $store->tax_id }}@endif
        </div>
    </td></tr>

</table>
</td></tr>
</table>
</body>
</html>

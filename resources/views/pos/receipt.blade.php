<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - {{ $order->id }}</title>

    <!-- JsBarcode -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.12.1/JsBarcode.all.min.js"></script>

    <style>
        /* PRINT-OPTIMIZED STYLES */
        body {
            font-family: 'Courier New', monospace !important;
            font-size: 13px !important;
            line-height: 1.3 !important;
            margin: 0 !important;
            padding: 0 !important;
            background: white !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* Hide all non-print elements */
        .no-print, .printer-slot {
            display: none !important;
        }

        /* Receipt Container */
        .receipt-container {
            width: 80mm !important;
            max-width: 80mm !important;
            margin: 0 auto !important;
            padding: 15px !important;
            background: white !important;
            color: #000 !important;
            font-weight: 500 !important; /* Slightly bolder for better print */
        }

        /* DARKER TEXT FOR BETTER PRINT QUALITY */
        .receipt-container * {
            color: #000 !important;
            text-shadow: none !important;
        }

        .header h1 {
            font-size: 18px !important;
            margin: 8px 0 !important;
            font-weight: 900 !important; /* Extra bold for store name */
            letter-spacing: 0.5px !important;
        }

        .header p, .motto {
            font-size: 11px !important;
            margin: 3px 0 !important;
            font-weight: 600 !important;
        }

        .order-info {
            margin: 12px 0 !important;
        }

        .order-info p {
            margin: 4px 0 !important;
            font-weight: 600 !important;
            font-size: 11px !important;
        }

        .order-info strong {
            font-weight: 800 !important;
        }

        /* Tables with darker borders */
        table {
            width: 100% !important;
            border-collapse: collapse !important;
            margin: 12px 0 !important;
            font-size: 11px !important;
        }

        table th {
            text-align: left !important;
            padding: 6px 0 !important;
            border-bottom: 2px solid #000 !important; /* Thicker border */
            font-weight: 800 !important;
            font-size: 12px !important;
        }

        table td {
            padding: 5px 0 !important;
            vertical-align: top !important;
        }

        .text-right {
            text-align: right !important;
        }

        .text-center {
            text-align: center !important;
        }

        .text-left {
            text-align: left !important;
        }

        /* Divider lines */
        .divider {
            border-top: 2px dashed #000 !important; /* Darker and thicker */
            margin: 12px 0 !important;
            height: 0 !important;
        }

        /* Item details */
        .item-details {
            font-size: 10px !important;
            color: #333 !important;
        }

        /* Totals section */
        .total-row {
            font-weight: 900 !important; /* Extra bold for totals */
            font-size: 14px !important;
            border-top: 2px solid #000 !important; /* Thicker border */
            padding-top: 10px !important;
            margin-top: 10px !important;
        }

        .subtotal-row {
            font-weight: 700 !important;
        }

        /* Barcode */
        .barcode {
            margin: 25px 0 !important;
            text-align: center !important;
        }

        .barcode svg {
            width: 100% !important;
            height: 65px !important;
        }

        .barcode-text {
            font-size: 10px !important;
            margin-top: 5px !important;
            font-weight: 700 !important;
            letter-spacing: 1px !important;
        }

        /* Footer */
        .footer {
            margin-top: 25px !important;
            font-size: 10px !important;
            font-weight: 600 !important;
        }

        .footer p {
            margin: 4px 0 !important;
        }

        /* Important notice */
        .important-notice {
            font-weight: 900 !important;
            font-size: 11px !important;
            margin: 15px 0 !important;
            text-align: center !important;
        }

        /* Unit display in items */
        .unit-display {
            font-size: 10px !important;
            font-weight: 700 !important;
            color: #000 !important;
            margin-top: 2px !important;
        }

        /* Ensure no page breaks inside important elements */
        .no-break {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        /* Print-specific optimizations */
        @media print {
            body {
                margin: 0 !important;
                padding: 0 !important;
                font-size: 13px !important;
            }

            .receipt-container {
                width: 80mm !important;
                max-width: 80mm !important;
                padding: 10px !important;
                box-shadow: none !important;
                border: none !important;
            }

            /* Force black text on print */
            * {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
                color: #000000 !important;
            }

            /* Prevent page breaks */
            .receipt-container {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
        }

        /* On-screen preview styles */
        @media screen {
            body {
                background: #f5f5f5 !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                min-height: 100vh !important;
                padding: 20px !important;
            }

            .receipt-container {
                box-shadow: 0 10px 40px rgba(0,0,0,0.3) !important;
                border-radius: 8px !important;
            }
        }
    </style>
</head>
<body onload="window.print(); setTimeout(() => window.close(), 1000);">

@php
    $store = \App\Models\StoreSetting::getSettings();
    $currency = $store?->currency_symbol ?? '₦';
@endphp

<div class="receipt-container no-break">
    <!-- Store Header -->
    <div class="header text-center">
        @if($store?->logo)
            <div class="logo mb-3">
                <img src="{{ $store->getLogoUrlAttribute() }}" alt="Store Logo" style="max-width: 60mm; max-height: 30mm; filter: contrast(1.2);">
            </div>
        @endif
        <h1>{{ strtoupper($store?->store_name ?? 'MY SUPERMARKET') }}</h1>
        @if($store?->motto)
            <p class="motto">{{ $store->motto }}</p>
        @endif
        @if($store?->address)
            <p><strong>{{ $store->address }}</strong></p>
        @endif
        @if($store?->phone)
            <p><strong>Phone:</strong> {{ $store->phone }}</p>
        @endif
        @if($store?->email)
            <p><strong>Email:</strong> {{ $store->email }}</p>
        @endif
        @if($store?->tax_id)
            <p><strong>Tax ID:</strong> {{ $store->tax_id }}</p>
        @endif
    </div>

    <div class="divider"></div>

    <!-- Order Info -->
    <div class="order-info">
        <p><strong>RECEIPT NO:</strong> {{ $order->id }}</p>
        <p><strong>DATE:</strong> {{ $order->order_date->format('d M Y, h:i A') }}</p>
        <p><strong>CASHIER:</strong> {{ auth()->user()->name ?? 'Admin' }}</p>
        @if($order->customer)
            <p><strong>CUSTOMER:</strong> {{ strtoupper($order->customer->first_name . ' ' . $order->customer->last_name) }}</p>
            @if($order->customer->phone_number)
                <p><strong>PHONE:</strong> {{ $order->customer->phone_number }}</p>
            @endif
        @else
            <p><strong>CUSTOMER:</strong> WALK-IN CUSTOMER</p>
        @endif
        <p><strong>PAYMENT:</strong> {{ strtoupper($order->payment_method) }}</p>
    </div>

    <div class="divider"></div>

    <!-- Items Table -->
    <table>
        <thead>
            <tr>
                <th class="text-left">ITEM</th>
                <th class="text-center">QTY</th>
                <th class="text-center">UNIT</th>
                <th class="text-right">PRICE</th>
                <th class="text-right">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td class="text-left">
                        <strong>{{ $item->title }}</strong>
                        @if($item->sku)
                            <br><small class="item-details">SKU: {{ $item->sku }}</small>
                        @endif
                    </td>
                    <td class="text-center">
                        <strong>{{ number_format($item->quantity, $item->is_unit_mode ? 3 : 0) }}</strong>
                    </td>
                    <td class="text-center">
                        @if($item->unit_name)
                            <span class="unit-display">{{ strtoupper($item->unit_name) }}</span>
                        @else
                            <span class="unit-display">PCS</span>
                        @endif
                    </td>
                    <td class="text-right">
                        <strong>{{ $currency }}{{ number_format($item->unit_price, 2) }}</strong>
                    </td>
                    <td class="text-right">
                        <strong>{{ $currency }}{{ number_format($item->total_price, 2) }}</strong>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    <!-- Totals -->
    <table>
        <tr class="subtotal-row">
            <td class="text-left"><strong>SUBTOTAL:</strong></td>
            <td class="text-right"><strong>{{ $currency }}{{ number_format($order->subtotal ?? ($order->items->sum('total_price')), 2) }}</strong></td>
        </tr>
        @if($order->discount_amount > 0)
            <tr class="subtotal-row">
                <td class="text-left"><strong>DISCOUNT:</strong></td>
                <td class="text-right"><strong>-{{ $currency }}{{ number_format($order->discount_amount, 2) }}</strong></td>
            </tr>
        @endif
        <tr class="total-row">
            <td class="text-left"><strong>GRAND TOTAL:</strong></td>
            <td class="text-right"><strong>{{ $currency }}{{ number_format($order->total_amount, 2) }}</strong></td>
        </tr>
        @if($order->payment_method == 'cash' && isset($order->cash_received))
            <tr class="subtotal-row">
                <td class="text-left"><strong>CASH RECEIVED:</strong></td>
                <td class="text-right"><strong>{{ $currency }}{{ number_format($order->cash_received, 2) }}</strong></td>
            </tr>
            <tr class="subtotal-row">
                <td class="text-left"><strong>CHANGE:</strong></td>
                <td class="text-right"><strong>{{ $currency }}{{ number_format($order->cash_received - $order->total_amount, 2) }}</strong></td>
            </tr>
        @endif
    </table>

    <div class="divider"></div>

    <!-- Important Notice -->
    <div class="important-notice">
        <p>GOODS SOLD ARE NON-RETURNABLE</p>
        <p>RECEIPT MUST BE PRESENTED FOR ANY COMPLAINTS</p>
    </div>

    <!-- Barcode -->
    <div class="barcode">
        <svg id="receipt-barcode"></svg>
        <div class="barcode-text">{{ $order->id }}</div>
    </div>

    <!-- Footer -->
    <div class="footer text-center">
        <p><strong>{{ $store?->footer_note ?? 'THANK YOU FOR SHOPPING WITH US!' }}</strong></p>
        <p>** This is a computer-generated receipt **</p>
        <p><strong>Printed:</strong> {{ now()->format('d M Y h:i A') }}</p>
        <p>Powered by ViteMart 2.0 | Developed by Qudroid System</p>
    </div>
</div>

<script>
    JsBarcode("#receipt-barcode", "{{ $order->id }}", {
        format: "CODE128",
        width: 2.5,
        height: 70,
        displayValue: false,
        margin: 10,
        background: "transparent",
        lineColor: "#000000",
        flat: true
    });

    // Auto-close after print
    window.onafterprint = function() {
        setTimeout(function() {
            window.close();
        }, 500);
    };

    // Fallback close in case onafterprint doesn't fire
    setTimeout(function() {
        window.close();
    }, 5000);
</script>

</body>
</html>

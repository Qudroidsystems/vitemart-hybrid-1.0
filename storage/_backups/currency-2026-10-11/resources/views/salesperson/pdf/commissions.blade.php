<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commission Statement - {{ $user->name ?? $user->first_name . ' ' . $user->last_name }}</title>
    <style>
        @page { margin: 20px; size: A4 landscape; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; line-height: 1.5; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 15px; }
        .store-name { font-size: 22px; font-weight: bold; margin-bottom: 5px; }
        .report-title { font-size: 16px; font-weight: bold; margin: 15px 0; text-align: center; color: #2c3e50; }
        .summary { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; padding: 15px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background-color: #2c3e50; color: white; padding: 8px; text-align: left; font-size: 10px; font-weight: bold; }
        td { padding: 6px 8px; border-bottom: 1px solid #dee2e6; font-size: 10px; }
        .total-row { background-color: #2c3e50 !important; color: white; font-weight: bold; }
        .text-right { text-align: right; }
        .footer { margin-top: 30px; padding-top: 10px; border-top: 1px solid #dee2e6; text-align: center; font-size: 9px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <div class="store-name">{{ $settings->store_name ?? 'Store Management System' }}</div>
        <div style="font-size: 10px; color: #666;">
            @if($settings->address){{ $settings->address }}<br>@endif
            @if($settings->phone)Phone: {{ $settings->phone }} | @endif
            @if($settings->email)Email: {{ $settings->email }}@endif
        </div>
    </div>

    <div class="report-title">
        COMMISSION STATEMENT - {{ strtoupper($user->name ?? $user->first_name . ' ' . $user->last_name) }}
    </div>

    @php
        $totalCommission = $commissions->sum('commission_amount');
        $paidCommission = $commissions->where('commission_paid', true)->sum('commission_amount');
        $pendingCommission = $commissions->where('commission_paid', false)->sum('commission_amount');
    @endphp

    <div class="summary">
        <div style="display: flex; justify-content: space-around; text-align: center;">
            <div>
                <h3 style="margin: 0; color: #28a745;">{{ $settings->currency_symbol ?? '₦' }}{{ number_format($paidCommission, 2) }}</h3>
                <p style="margin: 5px 0 0 0; font-size: 10px;">Paid Commission</p>
            </div>
            <div>
                <h3 style="margin: 0; color: #ffc107;">{{ $settings->currency_symbol ?? '₦' }}{{ number_format($pendingCommission, 2) }}</h3>
                <p style="margin: 5px 0 0 0; font-size: 10px;">Pending Commission</p>
            </div>
            <div>
                <h3 style="margin: 0; color: #007bff;">{{ $settings->currency_symbol ?? '₦' }}{{ number_format($totalCommission, 2) }}</h3>
                <p style="margin: 5px 0 0 0; font-size: 10px;">Total Commission</p>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Date</th>
                <th>Customer</th>
                <th class="text-right">Order Amount</th>
                <th class="text-right">Commission</th>
                <th>Status</th>
                <th>Paid Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach($commissions as $commission)
            <tr>
                <td>#{{ $commission->id }}</td>
                <td>{{ $commission->order_date->format('M d, Y') }}</td>
                <td>{{ $commission->customer->name ?? 'Guest' }}</td>
                <td class="text-right">{{ $settings->currency_symbol ?? '₦' }}{{ number_format($commission->total_amount, 2) }}</td>
                <td class="text-right">{{ $settings->currency_symbol ?? '₦' }}{{ number_format($commission->commission_amount, 2) }}</td>
                <td>
                    @if($commission->commission_paid)
                        <span style="background-color: #28a745; color: white; padding: 2px 6px; border-radius: 3px; font-size: 9px;">PAID</span>
                    @else
                        <span style="background-color: #ffc107; color: #212529; padding: 2px 6px; border-radius: 3px; font-size: 9px;">PENDING</span>
                    @endif
                </td>
                <td>
                    @if($commission->paid_date)
                        {{ $commission->paid_date->format('M d, Y') }}
                    @else
                        -
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="3"><strong>TOTAL</strong></td>
                <td class="text-right"><strong>{{ $settings->currency_symbol ?? '₦' }}{{ number_format($commissions->sum('total_amount'), 2) }}</strong></td>
                <td class="text-right"><strong>{{ $settings->currency_symbol ?? '₦' }}{{ number_format($totalCommission, 2) }}</strong></td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        {{ $settings->footer_note ?? 'Confidential - For Personal Use Only' }}<br>
        Generated on: {{ $generatedAt }}
    </div>
</body>
</html>

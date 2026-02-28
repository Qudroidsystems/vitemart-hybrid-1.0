@extends('layouts.master')

@section('title', 'My Sales Dashboard')

@section('content')
<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                        <h4 class="mb-sm-0">{{ $pagetitle }}</h4>
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                                <li class="breadcrumb-item active">My Sales</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- WELCOME MESSAGE -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="mb-1">Welcome back, {{ $user->name ?? $user->first_name . ' ' . $user->last_name }}!</h4>
                                    <p class="mb-0 opacity-75">Track your sales performance and commissions</p>
                                </div>
                                <div class="avatar-xl">
                                    <div class="avatar-title bg-light text-primary rounded-circle display-4">
                                        {{ strtoupper(substr($user->name ?? $user->first_name, 0, 1)) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- QUICK STATS -->
            <div class="row">
                <div class="col-xl-3 col-md-6">
                    <div class="card card-animate">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <p class="text-uppercase fw-medium text-muted mb-0">Today's Revenue</p>
                                    <h4 class="fs-22 fw-semibold mb-0">₦{{ number_format($todayPerformance->total_revenue ?? 0, 2) }}</h4>
                                    <small class="text-muted">{{ $todayPerformance->order_count ?? 0 }} sales</small>
                                </div>
                                <div class="avatar-sm flex-shrink-0">
                                    <span class="avatar-title bg-success rounded-circle fs-3">
                                        <i class="bi bi-sun"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card card-animate">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <p class="text-uppercase fw-medium text-muted mb-0">This Week</p>
                                    <h4 class="fs-22 fw-semibold mb-0">₦{{ number_format($weekPerformance->total_revenue ?? 0, 2) }}</h4>
                                    <small class="text-muted">{{ $weekPerformance->order_count ?? 0 }} sales</small>
                                </div>
                                <div class="avatar-sm flex-shrink-0">
                                    <span class="avatar-title bg-info rounded-circle fs-3">
                                        <i class="bi bi-calendar-week"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card card-animate">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <p class="text-uppercase fw-medium text-muted mb-0">Pending Commission</p>
                                    <h4 class="fs-22 fw-semibold mb-0">₦{{ number_format($commissionSummary['pending'], 2) }}</h4>
                                    <small class="text-muted">Total: ₦{{ number_format($commissionSummary['total'], 2) }}</small>
                                </div>
                                <div class="avatar-sm flex-shrink-0">
                                    <span class="avatar-title bg-warning rounded-circle fs-3">
                                        <i class="bi bi-cash-stack"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card card-animate">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <p class="text-uppercase fw-medium text-muted mb-0">Daily Average</p>
                                    <h4 class="fs-22 fw-semibold mb-0">₦{{ number_format($summary['daily_average'], 2) }}</h4>
                                    <small class="text-muted">{{ $daysInPeriod }} days period</small>
                                </div>
                                <div class="avatar-sm flex-shrink-0">
                                    <span class="avatar-title bg-primary rounded-circle fs-3">
                                        <i class="bi bi-graph-up"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DATE RANGE SUMMARY -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Sales Period: {{ date('M d, Y', strtotime($dateFrom)) }} - {{ date('M d, Y', strtotime($dateTo)) }}</h6>
                                    <div class="row mt-2">
                                        <div class="col-md-3">
                                            <small class="text-muted">Total Revenue:</small>
                                            <div class="fw-bold">₦{{ number_format($summary['total_revenue'], 2) }}</div>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted">Total Sales:</small>
                                            <div class="fw-bold">{{ number_format($summary['total_sales']) }}</div>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted">Total Commission:</small>
                                            <div class="fw-bold">₦{{ number_format($summary['total_commission'], 2) }}</div>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted">Average Sale:</small>
                                            <div class="fw-bold">₦{{ number_format($summary['average_sale'], 2) }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <button class="btn btn-primary" onclick="generatePDF()">
                                        <i class="bi bi-file-pdf"></i> Export PDF
                                    </button>
                                    <a href="{{ route('salesperson.commissions') }}" class="btn btn-warning ms-2">
                                        <i class="bi bi-cash-stack"></i> Commissions
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FILTERS -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Date From</label>
                                    <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Date To</label>
                                    <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Payment Method</label>
                                    <select name="payment_method" class="form-select">
                                        <option value="">All Methods</option>
                                        <option value="cash" {{ request('payment_method') == 'cash' ? 'selected' : '' }}>Cash</option>
                                        <option value="card" {{ request('payment_method') == 'card' ? 'selected' : '' }}>Card</option>
                                        <option value="transfer" {{ request('payment_method') == 'transfer' ? 'selected' : '' }}>Transfer</option>
                                        <option value="pos" {{ request('payment_method') == 'pos' ? 'selected' : '' }}>POS</option>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="bi bi-filter"></i> Apply Filters
                                    </button>
                                    <a href="{{ route('salesperson.dashboard') }}" class="btn btn-secondary">
                                        <i class="bi bi-arrow-clockwise"></i> Reset
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PERFORMANCE CHARTS & METRICS -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Monthly Performance (Last 6 Months)</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th class="text-end">Orders</th>
                                            <th class="text-end">Revenue</th>
                                            <th class="text-end">Commission</th>
                                            <th class="text-end">Avg. Order</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($monthlyPerformance as $month)
                                        <tr>
                                            <td>{{ date('M Y', strtotime($month->month . '-01')) }}</td>
                                            <td class="text-end">{{ number_format($month->order_count) }}</td>
                                            <td class="text-end">₦{{ number_format($month->total_revenue, 2) }}</td>
                                            <td class="text-end">₦{{ number_format($month->total_commission, 2) }}</td>
                                            <td class="text-end">₦{{ number_format($month->order_count > 0 ? $month->total_revenue / $month->order_count : 0, 2) }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- TOP PRODUCTS -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">Top Selling Products (Last 30 Days)</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th class="text-end">Quantity</th>
                                            <th class="text-end">Revenue</th>
                                            <th class="text-end">Avg. Price</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($topProducts as $product)
                                        <tr>
                                            <td>{{ $product->product_name }}</td>
                                            <td class="text-end">{{ number_format($product->total_quantity) }}</td>
                                            <td class="text-end">₦{{ number_format($product->total_revenue, 2) }}</td>
                                            <td class="text-end">₦{{ number_format($product->total_quantity > 0 ? $product->total_revenue / $product->total_quantity : 0, 2) }}</td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="4" class="text-center">No product data available</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- PAYMENT METHOD BREAKDOWN -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Payment Methods</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="paymentChart" height="250"></canvas>
                        </div>
                    </div>

                    <!-- QUICK LINKS -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="{{ route('salesperson.performance') }}" class="btn btn-outline-primary text-start">
                                    <i class="bi bi-graph-up me-2"></i> Performance Analytics
                                </a>
                                <a href="{{ route('salesperson.commissions') }}" class="btn btn-outline-warning text-start">
                                    <i class="bi bi-cash-stack me-2"></i> Commission Statement
                                </a>
                                <button class="btn btn-outline-success text-start" onclick="generatePDF()">
                                    <i class="bi bi-file-pdf me-2"></i> Export Sales Report
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- COMMISSION SUMMARY -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">Commission Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    <div class="text-center">
                                        <h3 class="text-success">₦{{ number_format($commissionSummary['paid'], 2) }}</h3>
                                        <p class="text-muted mb-0">Paid</p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <h3 class="text-warning">₦{{ number_format($commissionSummary['pending'], 2) }}</h3>
                                        <p class="text-muted mb-0">Pending</p>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3 text-center">
                                <small class="text-muted">Total: ₦{{ number_format($commissionSummary['total'], 2) }}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SALES HISTORY -->
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">My Sales History ({{ $sales->total() }} records)</h5>
                    <div>
                        <span class="badge bg-primary">Total: ₦{{ number_format($summary['total_revenue'], 2) }}</span>
                        <span class="badge bg-success ms-2">Sales: {{ number_format($summary['total_sales']) }}</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Commission</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($sales as $sale)
                                <tr>
                                    <td>#{{ $sale->id }}</td>
                                    <td>{{ $sale->order_date->format('M d, Y') }}</td>
                                    <td>{{ $sale->customer->name ?? 'Guest' }}</td>
                                    <td>{{ $sale->items->count() }}</td>
                                    <td>₦{{ number_format($sale->total_amount, 2) }}</td>
                                    <td>₦{{ number_format($sale->commission_amount ?? 0, 2) }}</td>
                                    <td><span class="badge bg-{{ $sale->payment_method == 'cash' ? 'success' : ($sale->payment_method == 'card' ? 'info' : ($sale->payment_method == 'transfer' ? 'warning' : 'danger')) }}">{{ ucfirst($sale->payment_method) }}</span></td>
                                    <td><span class="badge bg-{{ $sale->status == 'completed' ? 'success' : ($sale->status == 'pending' ? 'warning' : 'info') }}">{{ ucfirst($sale->status) }}</span></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary view-order-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#orderModal"
                                                data-id="{{ $sale->id }}">
                                            <i class="bi bi-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="9" class="text-center">No sales recorded for selected period</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    {!! $sales->links() !!}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Order Details Modal -->
<div class="modal fade" id="orderModal" tabindex="-1" aria-labelledby="orderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="orderModalLabel">Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="orderDetails">
                <!-- Content will be loaded via AJAX -->
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading order details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Initialize payment chart
    initPaymentChart();

    // Order Modal AJAX
    document.querySelectorAll('.view-order-btn').forEach(button => {
        button.addEventListener('click', function() {
            const orderId = this.getAttribute('data-id');
            loadOrderDetails(orderId);
        });
    });

    // Load order details when modal is shown
    const orderModal = document.getElementById('orderModal');
    orderModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const orderId = button.getAttribute('data-id');
        loadOrderDetails(orderId);
    });
});

function initPaymentChart() {
    const paymentCtx = document.getElementById('paymentChart');
    const paymentMethods = @json($paymentBreakdown->pluck('payment_method'));
    const paymentTotals = @json($paymentBreakdown->pluck('total'));

    if (paymentCtx && paymentMethods.length > 0) {
        new Chart(paymentCtx, {
            type: 'doughnut',
            data: {
                labels: paymentMethods,
                datasets: [{
                    data: paymentTotals,
                    backgroundColor: [
                        '#198754', // Cash - Green
                        '#0d6efd', // Card - Blue
                        '#ffc107', // Transfer - Yellow
                        '#dc3545', // POS - Red
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${context.label}: ₦${value.toLocaleString()} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    } else {
        // Display message if no payment data
        paymentCtx.parentElement.innerHTML = `
            <div class="text-center py-4">
                <i class="bi bi-pie-chart display-4 text-muted mb-3"></i>
                <p class="text-muted mb-0">No payment data available for the selected period</p>
            </div>
        `;
    }
}

function loadOrderDetails(orderId) {
    const orderDetailsDiv = document.getElementById('orderDetails');

    // Show loading
    orderDetailsDiv.innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading order details...</p>
        </div>
    `;

    // Update modal title
    document.getElementById('orderModalLabel').textContent = `Order #${orderId} Details`;

    // Fetch order details via AJAX
    fetch(`/sales/${orderId}/details`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const order = data.order;

                // Format date
                const orderDate = new Date(order.order_date);
                const formattedDate = orderDate.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });

                // Build HTML content
                let html = `
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0">Order Information</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm">
                                        <tr>
                                            <th>Order ID:</th>
                                            <td>#${order.id}</td>
                                        </tr>
                                        <tr>
                                            <th>Date & Time:</th>
                                            <td>${formattedDate}</td>
                                        </tr>
                                        <tr>
                                            <th>Status:</th>
                                            <td><span class="badge bg-${order.status === 'completed' ? 'success' : (order.status === 'pending' ? 'warning' : 'info')}">${order.status.toUpperCase()}</span></td>
                                        </tr>
                                        <tr>
                                            <th>Payment Method:</th>
                                            <td><span class="badge bg-${order.payment_method === 'cash' ? 'success' : (order.payment_method === 'card' ? 'info' : (order.payment_method === 'transfer' ? 'warning' : 'danger'))}">${order.payment_method.toUpperCase()}</span></td>
                                        </tr>
                                        <tr>
                                            <th>Total Amount:</th>
                                            <td class="fw-bold">₦${parseFloat(order.total_amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                        </tr>
                                        <tr>
                                            <th>Commission:</th>
                                            <td class="fw-bold text-success">₦${parseFloat(order.commission_amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0">Customer Information</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm">
                                        <tr>
                                            <th>Customer:</th>
                                            <td>${order.customer ? order.customer.name : 'Guest'}</td>
                                        </tr>
                                        <tr>
                                            <th>Customer Phone:</th>
                                            <td>${order.customer ? (order.customer.phone || 'N/A') : 'N/A'}</td>
                                        </tr>
                                        <tr>
                                            <th>Customer Email:</th>
                                            <td>${order.customer ? (order.customer.email || 'N/A') : 'N/A'}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                // Add items table if items exist
                if (order.items && order.items.length > 0) {
                    html += `
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Order Items (${order.items.length})</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Product</th>
                                                <th>Quantity</th>
                                                <th>Unit Price</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                    `;

                    let itemsTotal = 0;

                    order.items.forEach((item, index) => {
                        // Safely get product name - check multiple possible field names
                        const productName = item.product ?
                            (item.product.name || item.product.title || `Product #${item.product_id || 'N/A'}`) :
                            `Product #${item.product_id || 'N/A'}`;

                        const quantity = parseInt(item.quantity) || 0;
                        // Use unit_price if available, otherwise use price or total/quantity
                        let unitPrice = 0;
                        if (item.unit_price !== undefined && item.unit_price !== null) {
                            unitPrice = parseFloat(item.unit_price);
                        } else if (item.price !== undefined && item.price !== null) {
                            unitPrice = parseFloat(item.price);
                        } else if (item.total !== undefined && item.total !== null && quantity > 0) {
                            unitPrice = parseFloat(item.total) / quantity;
                        }

                        const itemTotal = parseFloat(item.total) || (quantity * unitPrice);
                        itemsTotal += itemTotal;

                        html += `
                            <tr>
                                <td>${index + 1}</td>
                                <td>${productName}</td>
                                <td>${quantity}</td>
                                <td>₦${unitPrice.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                <td>₦${itemTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                            </tr>
                        `;
                    });

                    const taxAmount = parseFloat(order.tax_amount) || 0;
                    const grandTotal = itemsTotal + taxAmount;

                    html += `
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="4" class="text-end fw-bold">Items Total:</td>
                                                <td class="fw-bold">₦${itemsTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                            </tr>
                    `;

                    if (taxAmount > 0) {
                        html += `
                                            <tr>
                                                <td colspan="4" class="text-end">Tax/VAT:</td>
                                                <td>₦${taxAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                            </tr>
                        `;
                    }

                    html += `
                                            <tr>
                                                <td colspan="4" class="text-end fw-bold">Grand Total:</td>
                                                <td class="fw-bold text-success">₦${grandTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    html += `
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> No items found for this order.
                        </div>
                    `;
                }

                // Add notes if available
                if (order.notes) {
                    html += `
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0">Notes</h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-0">${order.notes}</p>
                            </div>
                        </div>
                    `;
                }

                orderDetailsDiv.innerHTML = html;
            } else {
                orderDetailsDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> ${data.message || 'Failed to load order details'}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading order details:', error);
            orderDetailsDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> An error occurred while loading order details. Please try again.
                    <br><small>Error: ${error.message}</small>
                </div>
            `;
        });
}

function generatePDF() {
    // Build URL with current filters
    let url = '/salesperson/export/pdf?';
    const params = new URLSearchParams(window.location.search);

    // Add date range if not in params
    if (!params.has('date_from')) {
        params.append('date_from', '{{ $dateFrom }}');
    }
    if (!params.has('date_to')) {
        params.append('date_to', '{{ $dateTo }}');
    }

    url += params.toString();

    // Open in new tab
    window.open(url, '_blank');
}

// Handle enter key in filter inputs
document.querySelectorAll('.form-control, .form-select').forEach(input => {
    input.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            this.closest('form').submit();
        }
    });
});
</script>
@endsection

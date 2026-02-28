@extends('layouts.master')

@section('title', 'My Performance Analytics')

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
                                <li class="breadcrumb-item"><a href="{{ route('salesperson.dashboard') }}">My Sales</a></li>
                                <li class="breadcrumb-item active">Performance</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CUSTOMER STATISTICS -->
            <div class="row">
                <div class="col-xl-3 col-md-6">
                    <div class="card card-animate">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <p class="text-uppercase fw-medium text-muted mb-0">Total Orders</p>
                                    <h4 class="fs-22 fw-semibold mb-0">{{ number_format($customerStats->total_orders ?? 0) }}</h4>
                                </div>
                                <div class="avatar-sm flex-shrink-0">
                                    <span class="avatar-title bg-primary rounded-circle fs-3">
                                        <i class="bi bi-cart"></i>
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
                                    <p class="text-uppercase fw-medium text-muted mb-0">Unique Customers</p>
                                    <h4 class="fs-22 fw-semibold mb-0">{{ number_format($customerStats->unique_customers ?? 0) }}</h4>
                                </div>
                                <div class="avatar-sm flex-shrink-0">
                                    <span class="avatar-title bg-success rounded-circle fs-3">
                                        <i class="bi bi-people"></i>
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
                                    <p class="text-uppercase fw-medium text-muted mb-0">Repeat Customers</p>
                                    <h4 class="fs-22 fw-semibold mb-0">{{ number_format($repeatCustomers) }}</h4>
                                </div>
                                <div class="avatar-sm flex-shrink-0">
                                    <span class="avatar-title bg-info rounded-circle fs-3">
                                        <i class="bi bi-arrow-repeat"></i>
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
                                    <p class="text-uppercase fw-medium text-muted mb-0">Avg. Order Value</p>
                                    <h4 class="fs-22 fw-semibold mb-0">₦{{ number_format($customerStats->avg_order_value ?? 0, 2) }}</h4>
                                </div>
                                <div class="avatar-sm flex-shrink-0">
                                    <span class="avatar-title bg-warning rounded-circle fs-3">
                                        <i class="bi bi-graph-up"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MONTHLY PERFORMANCE CHART -->
            <div class="row mt-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Monthly Performance (Last 12 Months)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="monthlyChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Daily Performance by Day</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Day</th>
                                            <th class="text-end">Avg. Orders</th>
                                            <th class="text-end">Avg. Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($dailyPerformance as $day)
                                            <tr>
                                                <td>{{ $day->day_name }}</td>
                                                <td class="text-end">{{ number_format($day->order_count, 1) }}</td>
                                                <td class="text-end">₦{{ number_format($day->avg_revenue, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DETAILED PERFORMANCE TABLES -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Monthly Performance Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th class="text-end">Orders</th>
                                            <th class="text-end">Revenue</th>
                                            <th class="text-end">Commission</th>
                                            <th class="text-end">Avg. Order Value</th>
                                            <th class="text-end">Orders/Day</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($monthlyPerformance as $month)
                                            @php
                                                $daysInMonth = \Carbon\Carbon::parse($month->month . '-01')->daysInMonth;
                                            @endphp
                                            <tr>
                                                <td>{{ date('M Y', strtotime($month->month . '-01')) }}</td>
                                                <td class="text-end">{{ number_format($month->order_count) }}</td>
                                                <td class="text-end">₦{{ number_format($month->total_revenue, 2) }}</td>
                                                <td class="text-end">₦{{ number_format($month->total_commission, 2) }}</td>
                                                <td class="text-end">₦{{ number_format($month->avg_order_value, 2) }}</td>
                                                <td class="text-end">{{ number_format($month->order_count / $daysInMonth, 1) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    initMonthlyChart();
});

function initMonthlyChart() {
    const monthlyCtx = document.getElementById('monthlyChart');

    const months = @json($monthlyPerformance->pluck('month')->map(function($month) {
        return date('M Y', strtotime($month . '-01'));
    }));

    const revenues = @json($monthlyPerformance->pluck('total_revenue'));
    const commissions = @json($monthlyPerformance->pluck('total_commission'));

    if (monthlyCtx) {
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: months,
                datasets: [
                    {
                        label: 'Revenue',
                        data: revenues,
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Commission',
                        data: commissions,
                        backgroundColor: 'rgba(255, 206, 86, 0.6)',
                        borderColor: 'rgba(255, 206, 86, 1)',
                        borderWidth: 1,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Revenue (₦)'
                        },
                        ticks: {
                            callback: function(value) {
                                return '₦' + value.toLocaleString();
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Commission (₦)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                        ticks: {
                            callback: function(value) {
                                return '₦' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += '₦' + context.parsed.y.toLocaleString();
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
}
</script>
@endsection

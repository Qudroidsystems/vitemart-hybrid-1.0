@extends('layouts.master')

@section('title', $pagetitle)

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
                                <li class="breadcrumb-item"><a href="#">Reports</a></li>
                                <li class="breadcrumb-item active">Loyalty Points</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row">
                <div class="col-md-4">
                    <div class="card mini-stats-wid">
                        <div class="card-body">
                            <div class="d-flex">
                                <div class="flex-grow-1">
                                    <p class="text-muted fw-medium">Active Members</p>
                                    <h4 class="mb-0">{{ number_format($totalCustomers) }}</h4>
                                </div>
                                <div class="avatar-sm rounded-circle bg-primary align-self-center mini-stat-icon">
                                    <span class="avatar-title rounded-circle bg-primary">
                                        <i class="bx bx-user-circle font-size-24"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card mini-stats-wid">
                        <div class="card-body">
                            <div class="d-flex">
                                <div class="flex-grow-1">
                                    <p class="text-muted fw-medium">Total Points</p>
                                    <h4 class="mb-0">{{ number_format($totalPoints) }}</h4>
                                </div>
                                <div class="avatar-sm rounded-circle bg-success align-self-center mini-stat-icon">
                                    <span class="avatar-title rounded-circle bg-success">
                                        <i class="bx bx-star font-size-24"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card mini-stats-wid">
                        <div class="card-body">
                            <div class="d-flex">
                                <div class="flex-grow-1">
                                    <p class="text-muted fw-medium">Total Value</p>
                                    <h4 class="mb-0">₦{{ number_format($totalValue, 2) }}</h4>
                                </div>
                                <div class="avatar-sm rounded-circle bg-info align-self-center mini-stat-icon">
                                    <span class="avatar-title rounded-circle bg-info">
                                        <i class="bx bx-money font-size-24"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="row mb-4">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Top 10 Customers by Points</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="topCustomersChart" height="300"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Points Earned (Last 12 Months)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="monthlyPointsChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Points Distribution</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="distributionChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters & Export -->
            <div class="card">
                <div class="card-body">
                    <form method="GET">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <input type="text" name="search" class="form-control" placeholder="Search customer..." value="{{ request('search') }}">
                            </div>
                            <div class="col-md-2">
                                <input type="number" name="min_points" class="form-control" placeholder="Min points" value="{{ request('min_points') }}">
                            </div>
                            <div class="col-md-2">
                                <input type="number" name="max_points" class="form-control" placeholder="Max points" value="{{ request('max_points') }}">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                            <div class="col-md-2">
                                <a href="{{ route('reports.loyalty-points.export') }}?{{ request()->query() }}" class="btn btn-success w-100">
                                    Export CSV
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Customers Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Customer</th>
                                    <th>Phone</th>
                                    <th>Email</th>
                                    <th class="text-center">Points</th>
                                    <th class="text-center">Value</th>
                                    <th class="text-center">Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($customers as $customer)
                                    <tr>
                                        <td>
                                            <strong>{{ $customer->first_name }} {{ $customer->last_name }}</strong>
                                        </td>
                                        <td>{{ $customer->phone_number ?? '-' }}</td>
                                        <td>{{ $customer->email ?? '-' }}</td>
                                        <td class="text-center fw-bold text-primary">
                                            {{ number_format($customer->points->points ?? 0) }}
                                        </td>
                                        <td class="text-center fw-bold text-success">
                                            ₦{{ number_format(($customer->points->points ?? 0) / config('loyalty.redeem_rate', 100), 2) }}
                                        </td>
                                        <td class="text-center">{{ $customer->created_at->format('d M Y') }}</td>
                                        <td>
                                            <a href="{{ route('reports.loyalty-points.history', $customer->id) }}" class="btn btn-sm btn-info">
                                                View History
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            No customers with loyalty points found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $customers->links() }}
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Top 10 Customers - Horizontal Bar
    const ctx1 = document.getElementById('topCustomersChart').getContext('2d');
    new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: {!! json_encode($topCustomers->map(fn($c) => $c->first_name . ' ' . substr($c->last_name ?? '', 0, 1) . '.') ->toArray()) !!},
            datasets: [{
                label: 'Points',
                data: {!! json_encode($topCustomers->pluck('points')->toArray()) !!},
                backgroundColor: 'rgba(54, 162, 235, 0.8)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.parsed.x + ' points';
                        }
                    }
                }
            },
            scales: {
                x: { beginAtZero: true }
            }
        }
    });

    // Monthly Points Earned - Line Chart
    const ctx2 = document.getElementById('monthlyPointsChart').getContext('2d');
    new Chart(ctx2, {
        type: 'line',
        data: {
            labels: {!! json_encode($months) !!},
            datasets: [{
                label: 'Points Earned',
                data: {!! json_encode($pointsData) !!},
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#28a745',
                pointRadius: 5
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top' },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.parsed.y + ' points';
                        }
                    }
                }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    // Points Distribution - Doughnut Chart
    const ctx3 = document.getElementById('distributionChart').getContext('2d');
    new Chart(ctx3, {
        type: 'doughnut',
        data: {
            labels: {!! json_encode($distribution->keys()->toArray()) !!},
            datasets: [{
                data: {!! json_encode($distribution->values()->toArray()) !!},
                backgroundColor: [
                    '#6c757d', '#ffc107', '#fd7e14', '#20c997', '#0d6efd'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'right' },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' + context.parsed + ' customers';
                        }
                    }
                }
            }
        }
    });
});
</script>
@endsection

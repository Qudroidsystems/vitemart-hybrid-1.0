@extends('layouts.master')

@section('title', 'My Commission Statement')

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
                                <li class="breadcrumb-item active">Commissions</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- COMMISSION SUMMARY -->
            <div class="row">
                <div class="col-xl-4 col-md-6">
                    <div class="card card-animate">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <p class="text-uppercase fw-medium text-muted mb-0">Total Commission</p>
                                    <h4 class="fs-22 fw-semibold mb-0">₦{{ number_format($summary['total'], 2) }}</h4>
                                </div>
                                <div class="avatar-sm flex-shrink-0">
                                    <span class="avatar-title bg-success rounded-circle fs-3">
                                        <i class="bi bi-cash-stack"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6">
                    <div class="card card-animate">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <p class="text-uppercase fw-medium text-muted mb-0">Paid Commission</p>
                                    <h4 class="fs-22 fw-semibold mb-0">₦{{ number_format($summary['paid'], 2) }}</h4>
                                </div>
                                <div class="avatar-sm flex-shrink-0">
                                    <span class="avatar-title bg-info rounded-circle fs-3">
                                        <i class="bi bi-check-circle"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6">
                    <div class="card card-animate">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <p class="text-uppercase fw-medium text-muted mb-0">Pending Commission</p>
                                    <h4 class="fs-22 fw-semibold mb-0">₦{{ number_format($summary['pending'], 2) }}</h4>
                                </div>
                                <div class="avatar-sm flex-shrink-0">
                                    <span class="avatar-title bg-warning rounded-circle fs-3">
                                        <i class="bi bi-clock-history"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- COMMISSION STATEMENT -->
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Commission Statement</h5>
                    <button class="btn btn-danger" onclick="exportCommissionPDF()">
                        <i class="bi bi-file-pdf"></i> Export PDF
                    </button>
                </div>
                <div class="card-body">
                    @if($commissions->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Date</th>
                                        <th>Customer</th>
                                        <th class="text-end">Order Amount</th>
                                        <th class="text-end">Commission</th>
                                        <th>Payment Method</th>
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
                                            <td class="text-end">₦{{ number_format($commission->total_amount, 2) }}</td>
                                            <td class="text-end fw-bold text-success">
                                                ₦{{ number_format($commission->commission_amount, 2) }}
                                            </td>
                                            <td>{{ ucfirst($commission->payment_method) }}</td>
                                            <td>
                                                @if($commission->commission_paid)
                                                    <span class="badge bg-success">Paid</span>
                                                @else
                                                    <span class="badge bg-warning">Pending</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($commission->paid_date)
                                                    {{ $commission->paid_date->format('M d, Y') }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="table-light">
                                        <td colspan="3" class="text-end fw-bold">Totals:</td>
                                        <td class="text-end fw-bold">₦{{ number_format($commissions->sum('total_amount'), 2) }}</td>
                                        <td class="text-end fw-bold">₦{{ number_format($commissions->sum('commission_amount'), 2) }}</td>
                                        <td colspan="3"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        {!! $commissions->links() !!}
                    @else
                        <div class="text-center py-5">
                            <i class="bi bi-cash-stack display-1 text-muted"></i>
                            <h4 class="mt-3">No Commission Records</h4>
                            <p class="text-muted">You don't have any commission records yet.</p>
                            <a href="{{ route('salesperson.dashboard') }}" class="btn btn-primary">
                                <i class="bi bi-arrow-left"></i> Back to Sales
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function exportCommissionPDF() {
    window.open('/salesperson/commissions/export/pdf', '_blank');
}
</script>
@endsection

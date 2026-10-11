@extends('layouts.master')

@section('title', 'Points History - ' . $customer->first_name . ' ' . $customer->last_name)

@section('content')
<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                        <h4 class="mb-sm-0">Points History</h4>
                        <a href="{{ route('reports.loyalty-points') }}" class="btn btn-secondary">Back to Report</a>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <h5>{{ $customer->first_name }} {{ $customer->last_name }}</h5>
                            <p class="text-muted">{{ $customer->phone_number ?? 'No phone' }}</p>
                            <div class="mt-3">
                                <h2 class="text-primary">{{ number_format($customer->points->points ?? 0) }}</h2>
                                <p class="mb-0">Total Points</p>
                                <p class="fs-4 text-success">₦{{ number_format(($customer->points->points ?? 0) / config('loyalty.redeem_rate', 100), 2) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Transaction History</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Points</th>
                                            <th>Order</th>
                                            <th>Description</th>
                                            <th>By</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($transactions as $t)
                                            <tr>
                                                <td>{{ $t->created_at->format('d M Y H:i') }}</td>
                                                <td>
                                                    @if($t->points_earned > 0)
                                                        <span class="badge bg-success">Earned</span>
                                                    @elseif($t->points_redeemed > 0)
                                                        <span class="badge bg-warning">Redeemed</span>
                                                    @endif
                                                </td>
                                                <td class="{{ $t->points_earned > 0 ? 'text-success' : 'text-warning' }}">
                                                    {{ $t->points_earned > 0 ? '+' : '-' }}{{ abs($t->points_earned ?: $t->points_redeemed) }}
                                                </td>
                                                <td>
                                                    @if($t->order)
                                                        <a href="{{ route('orders.show', $t->order->id) }}">#{{ $t->order->id }}</a>
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td>{{ $t->description }}</td>
                                                <td>{{ $t->createdBy->name ?? 'System' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center py-4">No transactions yet</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            {{ $transactions->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

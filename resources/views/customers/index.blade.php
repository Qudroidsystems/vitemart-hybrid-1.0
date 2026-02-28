@extends('layouts.master')
@section('title', 'Customer Management')

@section('content')
<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">

            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                        <h4 class="mb-sm-0">Customer Management</h4>
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                                <li class="breadcrumb-item active">Customers</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="row">
                <div class="col-xl-3 col-md-6">
                    <div class="card card-animate bg-primary-subtle">
                        <div class="card-body">
                            <p class="text-uppercase fw-medium text-primary mb-0">Total Customers</p>
                            <h4 class="fs-22 fw-semibold mb-0">{{ number_format($stats['total']) }}</h4>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card card-animate bg-success-subtle">
                        <div class="card-body">
                            <p class="text-uppercase fw-medium text-success mb-0">Active</p>
                            <h4 class="fs-22 fw-semibold mb-0">{{ number_format($stats['active']) }}</h4>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card card-animate bg-warning-subtle">
                        <div class="card-body">
                            <p class="text-uppercase fw-medium text-warning mb-0">Inactive</p>
                            <h4 class="fs-22 fw-semibold mb-0">{{ number_format($stats['inactive']) }}</h4>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card card-animate bg-info-subtle">
                        <div class="card-body">
                            <p class="text-uppercase fw-medium text-info mb-0">Total Revenue</p>
                            <h4 class="fs-22 fw-semibold mb-0">₦{{ number_format($stats['total_spent'], 2) }}</h4>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search & Actions -->
            <div class="row mt-4">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <form action="{{ route('customers.index') }}" method="GET" class="d-flex gap-2">
                                    <input type="text" name="search" class="form-control"
                                           placeholder="Search by name, email, phone..."
                                           value="{{ request('search') }}" style="min-width: 300px;">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i> Search
                                    </button>
                                    @if(request('search'))
                                        <a href="{{ route('customers.index') }}" class="btn btn-secondary">
                                            Clear
                                        </a>
                                    @endif
                                </form>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('customers.export') }}" class="btn btn-success">
                                        <i class="bi bi-file-earmark-excel"></i> Export
                                    </a>
                                    @can('Manage customer')
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCustomerModal">
                                            <i class="bi bi-plus-circle"></i> Add Customer
                                        </button>
                                    @endcan
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customers Table -->
            <div class="row mt-4">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">All Customers</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Contact</th>
                                            <th>Type</th>
                                            <th>Orders</th>
                                            <th>Total Spent</th>
                                            <th>Credit Limit</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($customers as $customer)
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-xs me-3">
                                                        <div class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                            {{ Str::substr($customer->first_name, 0, 1) }}
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0">{{ $customer->first_name }} {{ $customer->last_name }}</h6>
                                                        @if($customer->company_name)
                                                            <small class="text-muted">{{ $customer->company_name }}</small>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <small>{{ $customer->email ?? 'No email' }}</small>
                                                    <small class="text-muted">{{ $customer->phone_number }}</small>
                                                    @if($customer->phone_number_2)
                                                        <small class="text-muted">{{ $customer->phone_number_2 }}</small>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $customer->customer_type == 'wholesale' ? 'warning' : ($customer->customer_type == 'corporate' ? 'info' : 'secondary') }}-subtle text-{{ $customer->customer_type == 'wholesale' ? 'warning' : ($customer->customer_type == 'corporate' ? 'info' : 'secondary') }}">
                                                    {{ ucfirst($customer->customer_type) }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="fw-semibold">{{ $customer->orders_count }}</span>
                                            </td>
                                            <td>
                                                <span class="fw-semibold">₦{{ number_format($customer->orders_sum_total_amount ?? 0, 2) }}</span>
                                            </td>
                                            <td>
                                                @if($customer->credit_limit > 0)
                                                    <div class="d-flex flex-column">
                                                        <small class="text-muted">Limit: ₦{{ number_format($customer->credit_limit, 2) }}</small>
                                                        <small class="text-muted">Balance: ₦{{ number_format($customer->credit_balance, 2) }}</small>
                                                    </div>
                                                @else
                                                    <span class="text-muted">No credit</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge {{ $customer->status == 'active' ? 'bg-success-subtle text-success' : ($customer->status == 'suspended' ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning') }}">
                                                    {{ ucfirst($customer->status) }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <button type="button" class="btn btn-sm btn-info view-customer-btn"
                                                            data-bs-toggle="modal" data-bs-target="#viewCustomerModal"
                                                            data-customer="{{ json_encode($customer) }}"
                                                            data-stats="{{ json_encode([
                                                                'total_orders' => $customer->orders_count,
                                                                'total_spent' => $customer->orders_sum_total_amount ?? 0,
                                                                'avg_order_value' => $customer->orders_count > 0 ? ($customer->orders_sum_total_amount / $customer->orders_count) : 0,
                                                                'last_order_date' => $customer->orders->last()->created_at ?? null
                                                            ]) }}">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    @can('Manage customer')
                                                        <button type="button" class="btn btn-sm btn-primary edit-customer-btn"
                                                                data-bs-toggle="modal" data-bs-target="#editCustomerModal"
                                                                data-customer="{{ json_encode($customer) }}">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <form action="{{ route('customers.destroy', $customer) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this customer?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-danger">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    @endcan
                                                </div>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="8" class="text-center py-5">
                                                <div class="text-muted">
                                                    <i class="bi bi-people display-1 text-light"></i>
                                                    <h5>No customers found</h5>
                                                    @if(request('search'))
                                                        <p>No results for "{{ request('search') }}"</p>
                                                        <a href="{{ route('customers.index') }}" class="btn btn-primary">View all customers</a>
                                                    @else
                                                        <p>Start by adding your first customer</p>
                                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCustomerModal">
                                                            <i class="bi bi-plus-circle"></i> Add Customer
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            @if($customers->hasPages())
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div class="text-muted">
                                        Showing {{ $customers->firstItem() }} to {{ $customers->lastItem() }} of {{ $customers->total() }} entries
                                    </div>
                                    <nav aria-label="Page navigation">
                                        {{ $customers->withQueryString()->links() }}
                                    </nav>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Customer Modal -->
<div class="modal fade" id="createCustomerModal" tabindex="-1" aria-labelledby="createCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="createCustomerModalLabel">
                    <i class="bi bi-person-plus me-2"></i> Add New Customer
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createCustomerForm" action="{{ route('customers.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Personal Information -->
                        <div class="col-md-6">
                            <label for="first_name" class="form-label required">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="last_name" class="form-label required">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>

                        <div class="col-md-6">
                            <label for="gender" class="form-label required">Gender</label>
                            <select class="form-select" id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>

                        <!-- Contact Information -->
                        <div class="col-md-6">
                            <label for="phone_number" class="form-label required">Phone Number</label>
                            <input type="tel" class="form-control" id="phone_number" name="phone_number" required>
                        </div>
                        <div class="col-md-6">
                            <label for="phone_number_2" class="form-label">Alternate Phone Number</label>
                            <input type="tel" class="form-control" id="phone_number_2" name="phone_number_2">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>

                        <!-- Address Information -->
                        <div class="col-md-12">
                            <label for="home_address" class="form-label">Home Address</label>
                            <textarea class="form-control" id="home_address" name="home_address" rows="2"></textarea>
                        </div>
                        <div class="col-md-12">
                            <label for="office_address" class="form-label">Office Address</label>
                            <textarea class="form-control" id="office_address" name="office_address" rows="2"></textarea>
                        </div>

                        <!-- Customer Type & Business Information -->
                        <div class="col-md-6">
                            <label for="customer_type" class="form-label required">Customer Type</label>
                            <select class="form-select" id="customer_type" name="customer_type" required>
                                <option value="">Select Type</option>
                                <option value="regular">Regular</option>
                                <option value="wholesale">Wholesale</option>
                                <option value="corporate">Corporate</option>
                                <option value="retail">Retail</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label required">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active" selected>Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="company_name" class="form-label">Company Name</label>
                            <input type="text" class="form-control" id="company_name" name="company_name">
                        </div>
                        <div class="col-md-6">
                            <label for="tax_id_number" class="form-label">Tax ID Number</label>
                            <input type="text" class="form-control" id="tax_id_number" name="tax_id_number">
                        </div>

                        <!-- Credit & Loyalty -->
                        <div class="col-md-6">
                            <label for="credit_limit" class="form-label">Credit Limit (₦)</label>
                            <input type="number" class="form-control" id="credit_limit" name="credit_limit" min="0" step="0.01" value="0">
                        </div>
                        <div class="col-md-6">
                            <label for="loyalty_points" class="form-label">Loyalty Points</label>
                            <input type="number" class="form-control" id="loyalty_points" name="loyalty_points" min="0" value="0">
                        </div>

                        <div class="col-md-6">
                            <label for="loyalty_card_number" class="form-label">Loyalty Card Number</label>
                            <input type="text" class="form-control" id="loyalty_card_number" name="loyalty_card_number">
                        </div>

                        <!-- Additional Information -->
                        <div class="col-md-12">
                            <label for="contact_person" class="form-label">Contact Person (for corporate)</label>
                            <input type="text" class="form-control" id="contact_person" name="contact_person">
                        </div>
                        <div class="col-md-12">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Any additional notes about this customer..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Save Customer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Customer Modal -->
<div class="modal fade" id="editCustomerModal" tabindex="-1" aria-labelledby="editCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editCustomerModalLabel">
                    <i class="bi bi-person-check me-2"></i> Edit Customer
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editCustomerForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Personal Information -->
                        <div class="col-md-6">
                            <label for="edit_first_name" class="form-label required">First Name</label>
                            <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_last_name" class="form-label required">Last Name</label>
                            <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                        </div>

                        <div class="col-md-6">
                            <label for="edit_gender" class="form-label required">Gender</label>
                            <select class="form-select" id="edit_gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>

                        <!-- Contact Information -->
                        <div class="col-md-6">
                            <label for="edit_phone_number" class="form-label required">Phone Number</label>
                            <input type="tel" class="form-control" id="edit_phone_number" name="phone_number" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_phone_number_2" class="form-label">Alternate Phone Number</label>
                            <input type="tel" class="form-control" id="edit_phone_number_2" name="phone_number_2">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="edit_email" name="email">
                        </div>

                        <!-- Address Information -->
                        <div class="col-md-12">
                            <label for="edit_home_address" class="form-label">Home Address</label>
                            <textarea class="form-control" id="edit_home_address" name="home_address" rows="2"></textarea>
                        </div>
                        <div class="col-md-12">
                            <label for="edit_office_address" class="form-label">Office Address</label>
                            <textarea class="form-control" id="edit_office_address" name="office_address" rows="2"></textarea>
                        </div>

                        <!-- Customer Type & Business Information -->
                        <div class="col-md-6">
                            <label for="edit_customer_type" class="form-label required">Customer Type</label>
                            <select class="form-select" id="edit_customer_type" name="customer_type" required>
                                <option value="">Select Type</option>
                                <option value="regular">Regular</option>
                                <option value="wholesale">Wholesale</option>
                                <option value="corporate">Corporate</option>
                                <option value="retail">Retail</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_status" class="form-label required">Status</label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="edit_company_name" class="form-label">Company Name</label>
                            <input type="text" class="form-control" id="edit_company_name" name="company_name">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_tax_id_number" class="form-label">Tax ID Number</label>
                            <input type="text" class="form-control" id="edit_tax_id_number" name="tax_id_number">
                        </div>

                        <!-- Credit & Loyalty -->
                        <div class="col-md-6">
                            <label for="edit_credit_limit" class="form-label">Credit Limit (₦)</label>
                            <input type="number" class="form-control" id="edit_credit_limit" name="credit_limit" min="0" step="0.01" value="0">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_loyalty_points" class="form-label">Loyalty Points</label>
                            <input type="number" class="form-control" id="edit_loyalty_points" name="loyalty_points" min="0" value="0">
                        </div>

                        <div class="col-md-6">
                            <label for="edit_loyalty_card_number" class="form-label">Loyalty Card Number</label>
                            <input type="text" class="form-control" id="edit_loyalty_card_number" name="loyalty_card_number">
                        </div>

                        <!-- Additional Information -->
                        <div class="col-md-12">
                            <label for="edit_contact_person" class="form-label">Contact Person (for corporate)</label>
                            <input type="text" class="form-control" id="edit_contact_person" name="contact_person">
                        </div>
                        <div class="col-md-12">
                            <label for="edit_notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="edit_notes" name="notes" rows="3" placeholder="Any additional notes about this customer..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Update Customer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Customer Modal -->
<div class="modal fade" id="viewCustomerModal" tabindex="-1" aria-labelledby="viewCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="viewCustomerModalLabel">
                    <i class="bi bi-person-badge me-2"></i> Customer Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <!-- Customer Profile Header -->
                    <div class="col-12 mb-4">
                        <div class="d-flex align-items-center">
                            <div class="avatar-xxl me-4">
                                <div class="avatar-title bg-info-subtle text-info rounded-circle display-4">
                                    <i class="bi bi-person"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <h3 id="view_full_name" class="mb-1"></h3>
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <span id="view_customer_type" class="badge"></span>
                                    <span id="view_status" class="badge"></span>
                                </div>
                                <div class="text-muted">
                                    <i class="bi bi-telephone me-1"></i>
                                    <span id="view_phone_number"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Personal Information -->
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted mb-3">Personal Information</h6>
                        <div class="mb-2">
                            <span class="text-muted">Full Name:</span>
                            <p class="fw-semibold mb-0" id="view_name"></p>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted">Gender:</span>
                            <p class="fw-semibold mb-0" id="view_gender"></p>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted">Email:</span>
                            <p class="fw-semibold mb-0" id="view_email"></p>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted mb-3">Contact Information</h6>
                        <div class="mb-2">
                            <span class="text-muted">Primary Phone:</span>
                            <p class="fw-semibold mb-0" id="view_phone_primary"></p>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted">Alternate Phone:</span>
                            <p class="fw-semibold mb-0" id="view_phone_secondary"></p>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted">Contact Person:</span>
                            <p class="fw-semibold mb-0" id="view_contact_person"></p>
                        </div>
                    </div>

                    <!-- Address Information -->
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted mb-3">Address Information</h6>
                        <div class="mb-2">
                            <span class="text-muted">Home Address:</span>
                            <p class="fw-semibold mb-0" id="view_home_address"></p>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted">Office Address:</span>
                            <p class="fw-semibold mb-0" id="view_office_address"></p>
                        </div>
                    </div>

                    <!-- Business Information -->
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted mb-3">Business Information</h6>
                        <div class="mb-2">
                            <span class="text-muted">Company:</span>
                            <p class="fw-semibold mb-0" id="view_company_name"></p>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted">Tax ID:</span>
                            <p class="fw-semibold mb-0" id="view_tax_id"></p>
                        </div>
                    </div>

                    <!-- Financial Information -->
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted mb-3">Financial Information</h6>
                        <div class="mb-2">
                            <span class="text-muted">Credit Limit:</span>
                            <p class="fw-semibold mb-0" id="view_credit_limit"></p>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted">Credit Balance:</span>
                            <p class="fw-semibold mb-0" id="view_credit_balance"></p>
                        </div>
                    </div>

                    <!-- Loyalty Information -->
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted mb-3">Loyalty Information</h6>
                        <div class="mb-2">
                            <span class="text-muted">Loyalty Points:</span>
                            <p class="fw-semibold mb-0" id="view_loyalty_points"></p>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted">Loyalty Card:</span>
                            <p class="fw-semibold mb-0" id="view_loyalty_card"></p>
                        </div>
                    </div>

                    <!-- Statistics -->
                    <div class="col-12 mt-4">
                        <h6 class="text-muted mb-3">Customer Statistics</h6>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5 class="card-title mb-1" id="view_total_orders">0</h5>
                                        <p class="text-muted mb-0">Total Orders</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5 class="card-title mb-1" id="view_total_spent">₦0.00</h5>
                                        <p class="text-muted mb-0">Total Spent</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5 class="card-title mb-1" id="view_avg_order">₦0.00</h5>
                                        <p class="text-muted mb-0">Avg. Order</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5 class="card-title mb-1" id="view_last_order">N/A</h5>
                                        <p class="text-muted mb-0">Last Order</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="col-12 mt-4">
                        <h6 class="text-muted mb-3">Notes</h6>
                        <div class="card">
                            <div class="card-body">
                                <p class="mb-0" id="view_notes"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary edit-from-view-btn">
                    <i class="bi bi-pencil me-1"></i> Edit Customer
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Format currency
    function formatCurrency(amount) {
        if (!amount) amount = 0;
        return new Intl.NumberFormat('en-NG', {
            style: 'currency',
            currency: 'NGN',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount);
    }

    // Format date
    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-NG', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    // Handle edit customer button clicks
    document.querySelectorAll('.edit-customer-btn').forEach(button => {
        button.addEventListener('click', function() {
            const customerData = JSON.parse(this.dataset.customer);

            // Set form action
            document.getElementById('editCustomerForm').action = `/customers/${customerData.id}`;

            // Populate form fields
            document.getElementById('edit_first_name').value = customerData.first_name || '';
            document.getElementById('edit_last_name').value = customerData.last_name || '';
            document.getElementById('edit_gender').value = customerData.gender || '';
            document.getElementById('edit_phone_number').value = customerData.phone_number || '';
            document.getElementById('edit_phone_number_2').value = customerData.phone_number_2 || '';
            document.getElementById('edit_email').value = customerData.email || '';
            document.getElementById('edit_home_address').value = customerData.home_address || '';
            document.getElementById('edit_office_address').value = customerData.office_address || '';
            document.getElementById('edit_customer_type').value = customerData.customer_type || 'regular';
            document.getElementById('edit_status').value = customerData.status || 'active';
            document.getElementById('edit_company_name').value = customerData.company_name || '';
            document.getElementById('edit_tax_id_number').value = customerData.tax_id_number || '';
            document.getElementById('edit_credit_limit').value = customerData.credit_limit || 0;
            document.getElementById('edit_loyalty_points').value = customerData.loyalty_points || 0;
            document.getElementById('edit_loyalty_card_number').value = customerData.loyalty_card_number || '';
            document.getElementById('edit_contact_person').value = customerData.contact_person || '';
            document.getElementById('edit_notes').value = customerData.notes || '';
        });
    });

    // View customer button handler
    document.querySelectorAll('.view-customer-btn').forEach(button => {
        button.addEventListener('click', function() {
            const customerData = JSON.parse(this.dataset.customer);
            const statsData = JSON.parse(this.dataset.stats);

            // Populate customer information
            const fullName = `${customerData.first_name || ''} ${customerData.last_name || ''}`.trim();
            document.getElementById('view_full_name').textContent = fullName || 'N/A';
            document.getElementById('view_name').textContent = fullName || 'N/A';

            const gender = customerData.gender ?
                customerData.gender.charAt(0).toUpperCase() + customerData.gender.slice(1) : 'N/A';
            document.getElementById('view_gender').textContent = gender;

            document.getElementById('view_email').textContent = customerData.email || 'N/A';
            document.getElementById('view_phone_primary').textContent = customerData.phone_number || 'N/A';
            document.getElementById('view_phone_secondary').textContent = customerData.phone_number_2 || 'N/A';
            document.getElementById('view_phone_number').textContent = customerData.phone_number || 'N/A';
            document.getElementById('view_contact_person').textContent = customerData.contact_person || 'N/A';
            document.getElementById('view_home_address').textContent = customerData.home_address || 'N/A';
            document.getElementById('view_office_address').textContent = customerData.office_address || 'N/A';
            document.getElementById('view_company_name').textContent = customerData.company_name || 'N/A';
            document.getElementById('view_tax_id').textContent = customerData.tax_id_number || 'N/A';

            document.getElementById('view_credit_limit').textContent = formatCurrency(customerData.credit_limit);
            document.getElementById('view_credit_balance').textContent = formatCurrency(customerData.credit_balance);
            document.getElementById('view_loyalty_points').textContent = customerData.loyalty_points || 0;
            document.getElementById('view_loyalty_card').textContent = customerData.loyalty_card_number || 'N/A';
            document.getElementById('view_notes').textContent = customerData.notes || 'No notes available';

            // Set customer type badge
            const typeBadge = document.getElementById('view_customer_type');
            const customerType = customerData.customer_type || 'regular';
            const typeText = customerType.charAt(0).toUpperCase() + customerType.slice(1);
            typeBadge.textContent = typeText;

            const typeColorMap = {
                'wholesale': 'warning',
                'corporate': 'info',
                'retail': 'primary',
                'regular': 'secondary'
            };
            const typeColor = typeColorMap[customerType] || 'secondary';
            typeBadge.className = `badge bg-${typeColor}-subtle text-${typeColor}`;

            // Set status badge
            const statusBadge = document.getElementById('view_status');
            const status = customerData.status || 'active';
            const statusText = status.charAt(0).toUpperCase() + status.slice(1);
            statusBadge.textContent = statusText;

            const statusColorMap = {
                'active': 'success',
                'suspended': 'danger',
                'inactive': 'warning'
            };
            const statusColor = statusColorMap[status] || 'secondary';
            statusBadge.className = `badge bg-${statusColor}-subtle text-${statusColor}`;

            // Populate statistics
            document.getElementById('view_total_orders').textContent = statsData.total_orders || 0;
            document.getElementById('view_total_spent').textContent = formatCurrency(statsData.total_spent);
            document.getElementById('view_avg_order').textContent = formatCurrency(statsData.avg_order_value);
            document.getElementById('view_last_order').textContent = formatDate(statsData.last_order_date);

            // Store customer ID for edit button
            const editBtn = document.querySelector('.edit-from-view-btn');
            if (editBtn) {
                editBtn.dataset.customerId = customerData.id;
                // Find and store the edit button for this customer
                const editButton = document.querySelector(`.edit-customer-btn[data-customer*='"id":${customerData.id}']`);
                editBtn.dataset.editButton = editButton ? 'true' : 'false';
            }
        });
    });

    // Edit button in view modal
    const editFromViewBtn = document.querySelector('.edit-from-view-btn');
    if (editFromViewBtn) {
        editFromViewBtn.addEventListener('click', function() {
            const customerId = this.dataset.customerId;
            if (!customerId) return;

            // Find the edit button for this customer
            const editButton = document.querySelector(`.edit-customer-btn[data-customer*='"id":${customerId}']`);

            if (editButton) {
                // Close view modal
                const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewCustomerModal'));
                if (viewModal) viewModal.hide();

                // Trigger edit modal after a short delay
                setTimeout(() => {
                    editButton.click();
                }, 300);
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Permission Denied',
                    text: 'You do not have permission to edit this customer.',
                    confirmButtonColor: '#3085d6',
                });
            }
        });
    }

    // Form validation for create customer
    const createForm = document.getElementById('createCustomerForm');
    if (createForm) {
        createForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Basic validation
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const phoneNumber = document.getElementById('phone_number').value.trim();
            const gender = document.getElementById('gender').value;
            const customerType = document.getElementById('customer_type').value;
            const status = document.getElementById('status').value;

            if (!firstName || !lastName || !phoneNumber || !gender || !customerType || !status) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Information',
                    text: 'Please fill in all required fields (*)',
                    confirmButtonColor: '#3085d6',
                });
                return;
            }

            // Set default values for loyalty points
            const loyaltyPoints = document.getElementById('loyalty_points');
            if (!loyaltyPoints.value || loyaltyPoints.value === '') {
                loyaltyPoints.value = 0;
            }

            // Submit the form
            this.submit();
        });
    }

    // Form validation for edit customer
    const editForm = document.getElementById('editCustomerForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Basic validation
            const firstName = document.getElementById('edit_first_name').value.trim();
            const lastName = document.getElementById('edit_last_name').value.trim();
            const phoneNumber = document.getElementById('edit_phone_number').value.trim();
            const gender = document.getElementById('edit_gender').value;
            const customerType = document.getElementById('edit_customer_type').value;
            const status = document.getElementById('edit_status').value;

            if (!firstName || !lastName || !phoneNumber || !gender || !customerType || !status) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Information',
                    text: 'Please fill in all required fields (*)',
                    confirmButtonColor: '#3085d6',
                });
                return;
            }

            // Ensure loyalty points has a value
            const loyaltyPoints = document.getElementById('edit_loyalty_points');
            if (!loyaltyPoints.value || loyaltyPoints.value === '') {
                loyaltyPoints.value = 0;
            }

            // Submit the form
            this.submit();
        });
    }

    // Phone number formatting
    function formatPhoneNumber(input) {
        let value = input.value.replace(/\D/g, '');
        if (value.length > 10) {
            value = value.substring(0, 10);
        }

        if (value.length >= 4) {
            value = value.substring(0, 3) + '-' + value.substring(3);
        }
        if (value.length >= 8) {
            value = value.substring(0, 7) + '-' + value.substring(7);
        }

        input.value = value;
    }

    // Apply phone number formatting
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function() {
            formatPhoneNumber(this);
        });

        // Initial formatting
        if (input.value) {
            formatPhoneNumber(input);
        }
    });

    // Clear form when create modal is hidden
    const createModal = document.getElementById('createCustomerModal');
    if (createModal) {
        createModal.addEventListener('hidden.bs.modal', function() {
            document.getElementById('createCustomerForm').reset();
            // Reset loyalty points to default
            document.getElementById('loyalty_points').value = 0;
        });
    }

    // Clear view modal data when hidden
    const viewModal = document.getElementById('viewCustomerModal');
    if (viewModal) {
        viewModal.addEventListener('hidden.bs.modal', function() {
            // Clear all view fields
            const viewFields = document.querySelectorAll('#viewCustomerModal [id^="view_"]');
            viewFields.forEach(field => {
                if (field.tagName === 'P' || field.tagName === 'SPAN' || field.tagName === 'H3') {
                    field.textContent = field.id === 'view_total_orders' ? '0' :
                                      field.id === 'view_total_spent' ? '₦0.00' :
                                      field.id === 'view_avg_order' ? '₦0.00' :
                                      field.id === 'view_last_order' ? 'N/A' : '';
                }
            });
        });
    }

    // Success/error message handling
    @if(session('success'))
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: '{{ session('success') }}',
            timer: 3000,
            showConfirmButton: false
        });
    @endif

    @if($errors->any())
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            html: `{!! implode('<br>', $errors->all()) !!}`,
            confirmButtonColor: '#3085d6',
        });
    @endif
});
</script>

<style>
/* Modal styling */
.modal-lg {
    max-width: 800px;
}

.modal-header {
    border-bottom: 2px solid rgba(255,255,255,0.1);
}

.form-label.required::after {
    content: ' *';
    color: #dc3545;
}

/* View Modal Styling */
#viewCustomerModal .avatar-xxl {
    width: 100px;
    height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
}

#viewCustomerModal .avatar-xxl i {
    font-size: 3rem;
}

#viewCustomerModal .modal-body p {
    min-height: 24px;
    word-break: break-word;
}

#viewCustomerModal .card {
    border: 1px solid #e9ecef;
    transition: transform 0.2s;
    height: 100%;
}

#viewCustomerModal .card:hover {
    transform: translateY(-2px);
}

#viewCustomerModal .modal-body h6 {
    color: #6c757d;
    font-weight: 600;
    border-bottom: 1px solid #e9ecef;
    padding-bottom: 0.5rem;
    margin-bottom: 1rem;
}

/* Phone number formatting */
input[type="tel"] {
    font-family: 'Courier New', monospace;
    letter-spacing: 1px;
}

/* Form validation */
.form-control:invalid, .form-select:invalid {
    border-color: #dc3545;
}

/* Loading state for form submission */
button[type="submit"]:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Badge styling */
.badge.bg-success-subtle {
    background-color: rgba(25, 135, 84, 0.1) !important;
}

.badge.bg-danger-subtle {
    background-color: rgba(220, 53, 69, 0.1) !important;
}

.badge.bg-warning-subtle {
    background-color: rgba(255, 193, 7, 0.1) !important;
}

.badge.bg-info-subtle {
    background-color: rgba(13, 202, 240, 0.1) !important;
}

.badge.bg-primary-subtle {
    background-color: rgba(13, 110, 253, 0.1) !important;
}

.badge.bg-secondary-subtle {
    background-color: rgba(108, 117, 125, 0.1) !important;
}

/* Table styling */
.table-hover tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.02);
}

.avatar-title {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .modal-lg {
        margin: 0.5rem;
    }

    #viewCustomerModal .d-flex {
        flex-direction: column;
        text-align: center;
    }

    #viewCustomerModal .avatar-xxl {
        margin: 0 auto 1rem auto;
    }
}
</style>
@endsection

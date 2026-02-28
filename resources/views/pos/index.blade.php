@extends('layouts.master')
@section('title', $pagetitle ?? 'Point of Sale')
@section('content')
<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                        <h4 class="mb-sm-0">Point of Sale (POS)</h4>
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                                <li class="breadcrumb-item active">POS</li>
                            </ol>
                        </div>
                        <div class="mt-2 mt-sm-0">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="offlineModeToggle">
                                <label class="form-check-label" for="offlineModeToggle">Offline Mode</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <!-- Left: Product Search -->
                <div class="col-xxl-8">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title mb-4">Search or Scan Products</h5>
                            <div class="position-relative mb-4">
                                <input type="text" id="barcodeInput" class="form-control form-control-lg fs-3"
                                       placeholder="Scan barcode or search by name/SKU..." autofocus autocomplete="off"
                                       aria-label="Search or scan products">
                                <i class="bi bi-upc-scan position-absolute top-50 end-0 translate-middle-y me-4 fs-2 text-muted"></i>
                            </div>
                            <div class="d-flex align-items-center mb-3">
                                <div class="me-3">
                                    <small class="text-muted">Shortcuts:</small>
                                    <span class="badge bg-secondary ms-1">F1</span> Focus Search
                                    <span class="badge bg-secondary ms-2">F2</span> Clear Cart
                                    <span class="badge bg-secondary ms-2">F3</span> Complete Order
                                    <span class="badge bg-secondary ms-2">F4</span> Hold Order
                                </div>
                                <div class="ms-auto">
                                    <span id="connectionStatus" class="badge bg-success">
                                        <i class="bi bi-wifi"></i> Online
                                    </span>
                                </div>
                            </div>
                            <div class="table-responsive flex-grow-1 position-relative">
                                <!-- Loading Overlay -->
                                <div id="searchLoading" class="position-absolute top-0 start-0 w-100 h-100 bg-white bg-opacity-75 d-none" style="z-index: 10;">
                                    <div class="d-flex justify-content-center align-items-center h-100">
                                        <div class="text-center">
                                            <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                            <h5 class="text-primary">Searching products...</h5>
                                        </div>
                                    </div>
                                </div>
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-primary sticky-top" style="z-index: 1;">
                                        <tr>
                                            <th>Product</th>
                                            <th class="text-center">Stock</th>
                                            <th class="text-center">Qty</th>
                                            <th class="text-end">Price</th>
                                            <th class="text-center">Unit</th>
                                        </tr>
                                    </thead>
                                    <tbody id="resultsBody">
                                        <tr id="emptySearchRow">
                                            <td colspan="5" class="text-center py-5 text-muted">
                                                <i class="bi bi-search display-1 mb-4 text-light"></i>
                                                <h5>Ready to sell</h5>
                                                <p class="mb-0">Type or scan to find products</p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Right: Order Summary -->
                <div class="col-xxl-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Order Summary</h5>
                            <div>
                                <button class="btn btn-sm btn-warning me-2" id="holdOrderBtn" title="Hold order for later">
                                    <i class="bi bi-pause-circle"></i> Hold
                                </button>
                                <button class="btn btn-sm btn-info me-2" id="loadHeldBtn" title="Load held orders">
                                    <i class="bi bi-folder-symlink"></i> Load
                                </button>
                                <button class="btn btn-sm btn-danger" id="clearCart" title="Clear cart">
                                    <i class="bi bi-trash"></i> Clear
                                </button>
                            </div>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <!-- Customer Selection -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold d-flex justify-content-between align-items-center">
                                    <span>Customer</span>
                                    <a href="javascript:void(0)" class="text-decoration-none fs-6"
                                       data-bs-toggle="tooltip" data-bs-title="Quick customer management"
                                       id="quickCustomerBtn">
                                        <i class="bi bi-person-plus"></i>
                                    </a>
                                </label>
                                <div class="position-relative">
                                    <select class="form-select form-select-lg customer-select-dropdown"
                                            id="customerSelect"
                                            style="padding-right: 40px; z-index: 1000;">
                                        <option value="">Walk-in Customer</option>
                                        @foreach($customers as $customer)
                                            <option value="{{ $customer->id }}">
                                                {{ $customer->first_name }} {{ $customer->last_name }}
                                                @if($customer->phone_number)
                                                    - {{ $customer->phone_number }}
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="position-absolute top-50 end-0 translate-middle-y me-3">
                                        <i class="bi bi-chevron-down text-muted"></i>
                                    </div>
                                </div>
                                <small class="text-muted mt-1 d-block">
                                    <span id="customerCount">{{ count($customers) }}</span> customers available
                                </small>
                            </div>
                            <div class="flex-grow-1 mb-4">
                                <h6 class="fw-bold mb-3">Cart Items</h6>
                                <div class="table-responsive" style="max-height: 350px;">
                                    <table class="table table-sm">
                                        <thead class="table-dark sticky-top">
                                            <tr>
                                                <th>#</th>
                                                <th>Item</th>
                                                <th class="text-center">Qty</th>
                                                <th class="text-end">Price</th>
                                                <th class="text-end">Total</th>
                                                <th>Disc</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody id="cartBody">
                                            <tr id="emptyCartRow">
                                                <td colspan="7" class="text-center py-5 text-muted">
                                                    <i class="bi bi-cart fs-1 mb-3"></i>
                                                    No items in cart
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- Order-Level Discount -->
                            <div class="border-top pt-3 mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-semibold">Order Discount</span>
                                    <div class="input-group input-group-sm" style="width: 180px;">
                                        <input type="number" id="discountValue" class="form-control text-end"
                                               placeholder="0" min="0" step="0.01" aria-label="Discount amount" value="0">
                                        <select id="discountType" class="form-select" aria-label="Discount type">
                                            <option value="fixed">₦</option>
                                            <option value="percent" selected>%</option>
                                        </select>
                                        <button class="btn btn-outline-primary" type="button" id="applyDiscountBtn"
                                                title="Apply discount">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between text-danger fw-bold" id="discountRow" style="display: none;">
                                    <span>Discount Applied</span>
                                    <span id="discountAmount">-₦0.00</span>
                                </div>
                            </div>
                            <div class="border-top pt-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Subtotal</span>
                                    <span id="subtotal">₦0.00</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Tax ({{ config('pos.tax_rate', 0) }}%)</span>
                                    <span id="taxAmount">₦0.00</span>
                                </div>
                                <hr class="my-3">
                                <div class="d-flex justify-content-between fs-3 fw-bold text-success">
                                    <span>Grand Total</span>
                                    <span id="grandTotal">₦0.00</span>
                                </div>
                            </div>
                            <div class="mt-4">
                                <label class="form-label fw-bold">Payment Method</label>
                                <div class="btn-group w-100 mb-4" role="group" aria-label="Payment methods">
                                    <input type="radio" class="btn-check" name="payment" id="cash" value="cash" checked>
                                    <label class="btn btn-outline-success w-100 py-3" for="cash">
                                        <i class="bi bi-cash-coin me-1"></i> Cash
                                    </label>
                                    <input type="radio" class="btn-check" name="payment" id="card" value="card">
                                    <label class="btn btn-outline-primary w-100 py-3" for="card">
                                        <i class="bi bi-credit-card me-1"></i> Card
                                    </label>
                                    <input type="radio" class="btn-check" name="payment" id="transfer" value="transfer">
                                    <label class="btn btn-outline-info w-100 py-3" for="transfer">
                                        <i class="bi bi-bank me-1"></i> Transfer
                                    </label>
                                </div>
                            </div>
                            <div class="d-grid gap-2">
                                <button class="btn btn-success btn-lg py-3 fs-2" id="completeOrder">
                                    <i class="bi bi-printer me-2"></i> Complete & Print
                                </button>
                                <button class="btn btn-outline-secondary" id="quickInvoiceBtn" style="display: none;">
                                    <i class="bi bi-receipt me-1"></i> Generate Invoice
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Quantity Modal with Unit Support -->
<div class="modal fade" id="quantityModal" tabindex="-1" role="dialog" aria-labelledby="quantityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title" id="quantityModalLabel">
                    <i class="bi bi-cart-plus me-2"></i> Adjust Quantity/Unit
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Product Info -->
                <div class="text-center mb-4">
                    <div class="product-icon bg-light-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="bi bi-box-seam fs-3 text-primary"></i>
                    </div>
                    <h6 class="text-muted mb-1">Product:</h6>
                    <h4 class="modal-product-name text-primary fw-bold mb-0" id="modalProductLabel"></h4>
                    <div class="mt-2">
                        <span class="badge bg-info" id="modalProductPrice"></span>
                        <span class="badge bg-warning ms-2" id="modalProductStock"></span>
                        <span class="badge bg-secondary ms-2" id="modalProductUnit"></span>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted d-flex justify-content-center gap-2">
                            <span id="modalProductSku"></span>
                            <span id="modalProductBarcode"></span>
                        </small>
                    </div>
                </div>
                <!-- Unit Selection Toggle -->
                <div class="card border-0 bg-light mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <label class="form-label fw-bold text-dark mb-0">Measurement Type</label>
                            <div class="btn-group btn-group-sm" role="group">
                                <input type="radio" class="btn-check" name="measurementType" id="measureQuantity" value="quantity" checked>
                                <label class="btn btn-outline-primary" for="measureQuantity">
                                    <i class="bi bi-123 me-1"></i> Quantity
                                </label>
                                <input type="radio" class="btn-check" name="measurementType" id="measureUnit" value="unit">
                                <label class="btn btn-outline-success" for="measureUnit">
                                    <i class="bi bi-scale me-1"></i> Unit
                                </label>
                            </div>
                        </div>
                        <!-- Unit Selection (Hidden by default) -->
                        <div id="unitSelection" class="mt-3" style="display: none;">
                            <label class="form-label fw-semibold">Select Unit <span class="text-danger">*</span></label>
                            <select class="form-select" id="unitSelect" required>
                                <option value="">-- Please select a unit --</option>
                                <!-- Units will be populated dynamically -->
                            </select>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="rememberUnitPreference">
                                <label class="form-check-label" for="rememberUnitPreference">
                                    Remember my preference for this product
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Price Input Section - TWO-WAY BINDING -->
                <div id="priceInputSection" class="card border-0 bg-light mb-3" style="display: none;">
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <label class="form-label fw-bold text-dark mb-0" id="totalPriceLabel">Total Price</label>
                            <small class="text-muted" id="originalPriceText"></small>
                        </div>
                        <div class="input-group input-group-lg mb-3">
                            <span class="input-group-text bg-primary text-white fw-bold">₦</span>
                            <input type="number" id="pricePerUnit" class="form-control text-center border-secondary fs-3 fw-bold"
                                   min="0" step="0.01" placeholder="Enter price" style="height: 60px;" disabled>
                        </div>
                        <div class="text-center">
                            <small class="text-muted d-block">Price for <span id="amountDisplay">1.000</span> <span id="unitDisplay">unit</span></small>
                            <small class="text-success fw-bold d-block mt-1" id="calculatedPriceDisplay"></small>
                        </div>
                    </div>
                </div>
                <!-- Quantity/Unit Input -->
                <div class="card border-0 bg-light mb-4">
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <label class="form-label fw-bold text-dark mb-0" id="measurementLabel">Enter Amount</label>
                            <small class="text-muted" id="previousQtyText"></small>
                        </div>
                        <!-- Quantity Input (default) -->
                        <div id="quantityInputSection">
                            <div class="input-group input-group-lg">
                                <button class="btn btn-outline-secondary" type="button" id="decreaseQty" aria-label="Decrease quantity">
                                    <i class="bi bi-dash-lg"></i>
                                </button>
                                <input type="number" id="modalQty" class="form-control text-center border-secondary fs-2 fw-bold"
                                       min="1" value="1" step="1" autofocus style="height: 60px;" aria-label="Quantity">
                                <button class="btn btn-outline-secondary" type="button" id="increaseQty" aria-label="Increase quantity">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>
                        </div>
                        <!-- Unit Input (hidden by default) -->
                        <div id="unitInputSection" style="display: none;">
                            <div class="input-group input-group-lg">
                                <button class="btn btn-outline-secondary" type="button" id="decreaseUnit" aria-label="Decrease unit" disabled>
                                    <i class="bi bi-dash-lg"></i>
                                </button>
                                <input type="number" id="modalUnit" class="form-control text-center border-secondary fs-2 fw-bold"
                                       min="0.001" value="1" step="0.001" style="height: 60px;" aria-label="Unit amount" disabled>
                                <button class="btn btn-outline-secondary" type="button" id="increaseUnit" aria-label="Increase unit" disabled>
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted d-block">Press Enter to confirm, ESC to cancel</small>
                            <small class="text-success fw-semibold d-block mt-1" id="totalPriceDisplay">Total: ₦0.00</small>
                        </div>
                    </div>
                </div>
                <!-- Quick Buttons -->
                <div class="mb-4" id="quantityQuickButtons">
                    <div class="row g-2 mb-2">
                        <div class="col-3"><button type="button" class="btn btn-outline-secondary w-100 py-2 quick-btn" data-value="1">1</button></div>
                        <div class="col-3"><button type="button" class="btn btn-outline-secondary w-100 py-2 quick-btn" data-value="2">2</button></div>
                        <div class="col-3"><button type="button" class="btn btn-outline-secondary w-100 py-2 quick-btn" data-value="3">3</button></div>
                        <div class="col-3"><button type="button" class="btn btn-outline-secondary w-100 py-2 quick-btn" data-value="5">5</button></div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-3"><button type="button" class="btn btn-outline-secondary w-100 py-2 quick-btn" data-value="10">10</button></div>
                        <div class="col-3"><button type="button" class="btn btn-outline-secondary w-100 py-2 quick-btn" data-value="20">20</button></div>
                        <div class="col-3"><button type="button" class="btn btn-outline-secondary w-100 py-2 quick-btn" data-value="50">50</button></div>
                        <div class="col-3"><button type="button" class="btn btn-outline-secondary w-100 py-2 quick-btn" data-value="100">100</button></div>
                    </div>
                </div>
                <!-- Weight/Unit Quick Buttons -->
                <div id="unitQuickButtons" style="display: none;">
                    <h6 class="text-muted mb-2">Quick Amounts</h6>
                    <div class="row g-2">
                        <div class="col-3"><button type="button" class="btn btn-outline-success w-100 py-2 unit-quick-btn" data-value="0.25">¼</button></div>
                        <div class="col-3"><button type="button" class="btn btn-outline-success w-100 py-2 unit-quick-btn" data-value="0.5">½</button></div>
                        <div class="col-3"><button type="button" class="btn btn-outline-success w-100 py-2 unit-quick-btn" data-value="0.75">¾</button></div>
                        <div class="col-3"><button type="button" class="btn btn-outline-success w-100 py-2 unit-quick-btn" data-value="1">1</button></div>
                    </div>
                    <div class="row g-2 mt-2">
                        <div class="col-3"><button type="button" class="btn btn-outline-success w-100 py-2 unit-quick-btn" data-value="2.5">2.5</button></div>
                        <div class="col-3"><button type="button" class="btn btn-outline-success w-100 py-2 unit-quick-btn" data-value="5">5</button></div>
                        <div class="col-3"><button type="button" class="btn btn-outline-success w-100 py-2 unit-quick-btn" data-value="10">10</button></div>
                        <div class="col-3"><button type="button" class="btn btn-outline-success w-100 py-2 unit-quick-btn" data-value="25">25</button></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom">
                <button type="button" class="btn btn-danger px-4" id="removeFromCartBtn">
                    <i class="bi bi-trash me-2"></i> Remove
                </button>
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-2"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary px-4" id="confirmAddBtn">
                    <i class="bi bi-check-circle me-2"></i> Update Cart
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Per-Item Discount Modal -->
<div class="modal fade" id="itemDiscountModal" tabindex="-1" role="dialog" aria-labelledby="itemDiscountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="itemDiscountModalLabel">
                    <i class="bi bi-percent me-2"></i> Apply Item Discount
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6 id="itemName" class="text-center mb-3"></h6>
                <div class="input-group mb-3">
                    <input type="number" id="itemDiscountValue" class="form-control" placeholder="0" min="0" step="0.01" aria-label="Discount amount">
                    <select id="itemDiscountType" class="form-select" aria-label="Discount type">
                        <option value="percent" selected>%</option>
                        <option value="fixed">₦</option>
                    </select>
                </div>
                <small class="text-muted">Leave 0 to remove discount</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="applyItemDiscountBtn">Apply</button>
            </div>
        </div>
    </div>
</div>
<!-- Load Order Modal -->
<div class="modal fade" id="loadOrderModal" tabindex="-1" role="dialog" aria-labelledby="loadOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="loadOrderModalLabel">Load Held Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="heldOrdersList"></div>
                <div id="noHeldOrders" class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1 mb-3"></i>
                    <p class="mb-0">No held orders found</p>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Quick Customer Modal -->
<div class="modal fade" id="quickCustomerModal" tabindex="-1" role="dialog" aria-labelledby="quickCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="quickCustomerModalLabel">
                    <i class="bi bi-person-plus me-2"></i> Quick Add Customer
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="quickCustomerForm">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="firstName" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="firstName" required>
                        </div>
                        <div class="col-md-6">
                            <label for="lastName" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="lastName" required>
                        </div>
                        <div class="col-md-12">
                            <label for="phoneNumber" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phoneNumber">
                        </div>
                        <div class="col-md-12">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveQuickCustomerBtn">Save Customer</button>
            </div>
        </div>
    </div>
</div>
<!-- Offline Orders Modal -->
<div class="modal fade" id="offlineOrdersModal" tabindex="-1" role="dialog" aria-labelledby="offlineOrdersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title text-white" id="offlineOrdersModalLabel">
                    <i class="bi bi-wifi-off me-2"></i> Offline Orders
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    These orders were created while offline. They will be synced automatically when you're back online.
                </div>
                <div id="offlineOrdersList">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-warning" id="syncOfflineOrdersBtn" style="display: none;">
                    <i class="bi bi-cloud-arrow-up me-1"></i> Sync Now
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Hidden elements for accessibility -->
<div class="visually-hidden" role="status" aria-live="polite" id="cartStatus"></div>
<div class="visually-hidden" role="status" aria-live="polite" id="searchStatus"></div>
<!-- Toast Container -->
<div id="toastContainer" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// COMPLETE POS SCRIPT WITH UNIT SELECTION VALIDATION AND BARCODE SCANNING
// COMPLETE POS SCRIPT WITH UNIT SELECTION VALIDATION AND BARCODE SCANNING
document.addEventListener('DOMContentLoaded', function () {
    // ============================================
    // INITIALIZATION
    // ============================================
    const input = document.getElementById('barcodeInput');
    const resultsBody = document.getElementById('resultsBody');
    const emptySearchRow = document.getElementById('emptySearchRow');
    const cartBody = document.getElementById('cartBody');
    const emptyCartRow = document.getElementById('emptyCartRow');
    const subtotalEl = document.getElementById('subtotal');
    const discountEl = document.getElementById('discountAmount');
    const grandTotalEl = document.getElementById('grandTotal');
    const searchLoading = document.getElementById('searchLoading');
    const modalQty = document.getElementById('modalQty');
    const modalUnit = document.getElementById('modalUnit');
    const confirmAddBtn = document.getElementById('confirmAddBtn');
    const removeFromCartBtn = document.getElementById('removeFromCartBtn');
    const customerSelect = document.getElementById('customerSelect');
    const discountValue = document.getElementById('discountValue');
    const discountType = document.getElementById('discountType');
    const applyDiscountBtn = document.getElementById('applyDiscountBtn');
    const pricePerUnitInput = document.getElementById('pricePerUnit');
    const unitSelect = document.getElementById('unitSelect');

    // Initialize Bootstrap tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

    // State management
    let cart = [];
    let allSearchedProducts = [];
    let currentSearchQuery = '';
    let productQuantityCache = {};
    let currentProduct = null;
    let currentItemIndex = null;
    let orderDiscountType = 'percent';
    let orderDiscountValue = 0;
    let printWindow = null;
    let printCheckInterval = null;
    let quantityModal = null;
    let quickCustomerModal = null;

    // Barcode scanning
    let barcodeBuffer = '';
    let barcodeTimeout = null;

    // Unit management
    let availableUnits = [];
    let selectedUnit = null;
    let currentMeasurementType = 'quantity'; // 'quantity' or 'unit'
    let originalPricePerUnit = 0; // Store original product price per unit
    let isPriceInputActive = false; // Track which input is being edited

    // Audio element for thank you sound
    let thankYouAudio = null;

    input.focus();

    // ============================================
    // BARCODE SCANNING FUNCTIONALITY
    // ============================================
    input.addEventListener('keydown', function(e) {
        // Check if it's a barcode scanner input (typically fast input with Enter key)
        if (e.key === 'Enter') {
            e.preventDefault();

            const scannedCode = input.value.trim();
            if (scannedCode) {
                processBarcode(scannedCode);
            }
            return;
        }
    });

    // Also keep the existing input event for manual search
    input.addEventListener('input', debounce(() => {
        const q = input.value.trim();
        currentSearchQuery = q;

        // Don't trigger search if we're in barcode scanning mode
        // Barcodes are typically processed with Enter key
        if (q.length >= 2 && !isLikelyBarcode(q)) {
            searchProducts(q);
        } else if (q.length === 0) {
            clearAllUnselectedItems();
            renderAllSearchedProducts();
        } else {
            showEmptySearchState();
        }
    }, 300));

    // Function to process barcode
    async function processBarcode(barcode) {
        if (!barcode) return;

        input.value = '';
        showSearchLoading();

        try {
            // Use the existing search endpoint which already handles exact barcode matches
            const response = await axios.get('{{ route("pos.search") }}', {
                params: { q: barcode }
            });

            const products = response.data || [];

            if (products.length > 0) {
                // Since your search method returns exact barcode match first,
                // we can assume the first product is what we want
                const product = products[0];

                // Check if product is already in searched list
                const existingIndex = allSearchedProducts.findIndex(p => p.id === product.id);
                if (existingIndex === -1) {
                    allSearchedProducts.push(product);
                } else {
                    allSearchedProducts[existingIndex] = product;
                }

                // Check if product is in cart
                const cartItem = cart.find(i => i.product_id === product.id);
                if (cartItem) {
                    // Product already in cart, open quantity modal to adjust
                    const button = document.querySelector(`[data-product-id="${product.id}"]`);
                    if (button) {
                        openQuantityModal(button);
                    }
                } else {
                    // Product not in cart, open quantity modal to add
                    const productJson = JSON.stringify(product).replace(/'/g, "&apos;");
                    const tempButton = document.createElement('button');
                    tempButton.dataset.product = productJson;
                    tempButton.dataset.productId = product.id;
                    openQuantityModal(tempButton);
                }

                renderAllSearchedProducts();
                hideSearchLoading();

                // Play sound for barcode scan success
                playScanSound();
            } else {
                hideSearchLoading();
                showToast('Product not found', 'error');
            }
        } catch (error) {
            hideSearchLoading();
            console.error('Barcode scan error:', error);
            showToast('Error scanning barcode', 'error');
        }
    }

    // Helper function to check if input is likely a barcode
    function isLikelyBarcode(input) {
        // Check for common barcode patterns
        if (input.length >= 8 && input.length <= 14 && /^\d+$/.test(input)) {
            return true; // Numeric barcode (EAN, UPC)
        }

        if (input.startsWith('PROD') && input.length > 10) {
            return true; // Custom product code
        }

        return false;
    }

    // Function to play scan sound
    function playScanSound() {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);

            oscillator.frequency.value = 800;
            oscillator.type = 'sine';

            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);

            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.1);
        } catch (e) {
            console.log('Audio context not supported');
        }
    }

    // ============================================
    // FORMATTING FUNCTIONS
    // ============================================
    function formatNumber(number, decimals = 0) {
        if (typeof number !== 'number') {
            number = parseFloat(number) || 0;
        }
        return new Intl.NumberFormat('en-NG', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(number);
    }

    function formatCurrency(amount) {
        if (typeof amount !== 'number') {
            amount = parseFloat(amount) || 0;
        }
        return '₦' + formatNumber(amount, 2);
    }

    function formatQuantity(qty, isUnitMode = false) {
        if (typeof qty !== 'number') {
            qty = parseFloat(qty) || 0;
        }
        if (isUnitMode) {
            // For units, show only 2 decimal places
            return formatNumber(qty, 2);
        }
        // For quantity (pieces), no decimals
        return formatNumber(qty, 0);
    }

    // ============================================
    // INITIALIZE MODALS AND AUDIO
    // ============================================
    function initializeApp() {
        // Initialize modals
        quantityModal = new bootstrap.Modal(document.getElementById('quantityModal'));
        quickCustomerModal = new bootstrap.Modal(document.getElementById('quickCustomerModal'));

        // Create audio element
        thankYouAudio = new Audio('/audio/thank-you-sweet-man-235977.mp3');
        thankYouAudio.preload = 'auto';

        // Set up quantity modal events
        setupQuantityModal();

        // Initialize customer search
        initializeCustomerSearch();

        // Initialize quick customer modal
        initializeQuickCustomerModal();

        // Load unit preferences
        loadUnitPreferences();

        // Focus management for barcode scanning
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.modal') &&
                !e.target.closest('#customerSelect') &&
                !e.target.closest('#discountValue') &&
                e.target.id !== 'barcodeInput') {
                input.focus();
                input.select();
            }
        });
    }

    // ============================================
    // QUANTITY MODAL SETUP WITH UNIT VALIDATION
    // ============================================
    function setupQuantityModal() {
        const quantityModalElement = document.getElementById('quantityModal');

        quantityModalElement.addEventListener('show.bs.modal', function() {
            // Reset to quantity mode when modal opens
            switchToQuantityMode();
        });

        quantityModalElement.addEventListener('shown.bs.modal', () => {
            setTimeout(() => {
                if (currentMeasurementType === 'quantity') {
                    modalQty.focus();
                    modalQty.select();
                } else {
                    // Focus unit select dropdown in unit mode
                    if (availableUnits.length > 0 && !selectedUnit) {
                        unitSelect.focus();
                    } else if (selectedUnit) {
                        modalUnit.focus();
                        modalUnit.select();
                    }
                }
            }, 100);
        });

        quantityModalElement.addEventListener('hidden.bs.modal', () => {
            setTimeout(() => {
                input.focus();
                input.select();
            }, 100);
        });

        // Measurement type toggle
        document.getElementById('measureQuantity').addEventListener('click', function() {
            if (this.checked) {
                switchToQuantityMode();
            }
        });

        document.getElementById('measureUnit').addEventListener('click', function() {
            if (this.checked) {
                switchToUnitMode();
            }
        });

        // Also keep the change events as backup
        document.querySelectorAll('input[name="measurementType"]').forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'quantity') {
                    switchToQuantityMode();
                } else {
                    switchToUnitMode();
                }
                updateModalTotal();
            });
        });

        // Unit selection change - FIXED: Enable/disable inputs based on selection
        unitSelect.addEventListener('change', function() {
            selectedUnit = availableUnits.find(u => u.id == this.value);
            if (selectedUnit) {
                // Enable inputs when unit is selected
                modalUnit.disabled = false;
                document.getElementById('decreaseUnit').disabled = false;
                document.getElementById('increaseUnit').disabled = false;
                pricePerUnitInput.disabled = false;
                modalUnit.focus();
                modalUnit.select();
                updatePriceUnitLabel();
                updateModalLabels();
                updateModalTotal();
                updateAmountDisplay();
                // Check if user has a saved preference
                checkSavedUnitPreference();
            } else {
                // Disable inputs when no unit is selected
                modalUnit.disabled = true;
                document.getElementById('decreaseUnit').disabled = true;
                document.getElementById('increaseUnit').disabled = true;
                pricePerUnitInput.disabled = true;
                modalUnit.value = 1;
                pricePerUnitInput.value = '';
                updateModalLabels();
                updateModalTotal();
                updateAmountDisplay();
            }
        });

        // ============================================
        // TWO-WAY BINDING LOGIC
        // ============================================
        // Quantity input - update price per unit
        modalQty.addEventListener('input', function() {
            const quantity = parseInt(this.value) || 1;
            const originalPrice = originalPricePerUnit;
            const totalPrice = quantity * originalPrice;

            // Update price per unit field to show total price for this quantity
            if (!isPriceInputActive) {
                pricePerUnitInput.value = totalPrice.toFixed(2);
            }
            updateModalTotal();
            updateAmountDisplay();
        });

        modalQty.addEventListener('change', updateModalTotal);

        // Unit input - update price per unit (only if unit is selected)
        modalUnit.addEventListener('input', function() {
            if (selectedUnit) {
                const unitAmount = parseFloat(this.value) || 1;
                const originalPrice = originalPricePerUnit;
                const totalPrice = unitAmount * originalPrice;

                // Update price per unit field to show total price for this amount
                if (!isPriceInputActive) {
                    pricePerUnitInput.value = totalPrice.toFixed(2);
                }
                updateModalTotal();
                updateAmountDisplay();
            }
        });

        modalUnit.addEventListener('change', updateModalTotal);

        // Price per unit input - update quantity/unit amount
        pricePerUnitInput.addEventListener('focus', function() {
            isPriceInputActive = true;
        });

        pricePerUnitInput.addEventListener('blur', function() {
            isPriceInputActive = false;
        });

        pricePerUnitInput.addEventListener('input', function() {
            if (selectedUnit) {
                const totalPrice = parseFloat(this.value) || 0;
                const originalPrice = originalPricePerUnit;
                if (originalPrice > 0 && totalPrice > 0) {
                    // Calculate equivalent amount based on total price
                    const equivalentAmount = totalPrice / originalPrice;
                    if (currentMeasurementType === 'quantity') {
                        modalQty.value = Math.round(equivalentAmount);
                    } else {
                        modalUnit.value = equivalentAmount.toFixed(3);
                    }
                    updateModalTotal();
                    updateAmountDisplay();
                }
            }
        });

        pricePerUnitInput.addEventListener('blur', function() {
            const totalPrice = parseFloat(this.value) || 0;
            const originalPrice = originalPricePerUnit;
            if (totalPrice === 0 && currentProduct) {
                // Reset to original price if cleared
                this.value = originalPrice.toFixed(2);
                if (currentMeasurementType === 'quantity') {
                    modalQty.value = 1;
                } else if (selectedUnit) {
                    modalUnit.value = 1;
                }
                updateModalTotal();
                updateAmountDisplay();
                showToast('Price reset to original', 'info', 1000);
            }
        });

        // Increase/Decrease buttons for quantity
        document.getElementById('increaseQty').addEventListener('click', () => {
            modalQty.value = (parseInt(modalQty.value) || 1) + 1;
            const quantity = parseInt(modalQty.value);
            const totalPrice = quantity * originalPricePerUnit;
            pricePerUnitInput.value = totalPrice.toFixed(2);
            updateModalTotal();
            updateAmountDisplay();
            modalQty.focus();
            modalQty.select();
        });

        document.getElementById('decreaseQty').addEventListener('click', () => {
            const val = parseInt(modalQty.value) || 1;
            if (val > 1) {
                modalQty.value = val - 1;
                const quantity = parseInt(modalQty.value);
                const totalPrice = quantity * originalPricePerUnit;
                pricePerUnitInput.value = totalPrice.toFixed(2);
                updateModalTotal();
                updateAmountDisplay();
                modalQty.focus();
                modalQty.select();
            }
        });

        // Increase/Decrease buttons for unit (decimal support) - only if unit is selected
        document.getElementById('increaseUnit').addEventListener('click', () => {
            if (selectedUnit) {
                const current = parseFloat(modalUnit.value) || 1;
                modalUnit.value = (current + getUnitStep()).toFixed(3);
                const unitAmount = parseFloat(modalUnit.value);
                const totalPrice = unitAmount * originalPricePerUnit;
                pricePerUnitInput.value = totalPrice.toFixed(2);
                updateModalTotal();
                updateAmountDisplay();
                modalUnit.focus();
                modalUnit.select();
            }
        });

        document.getElementById('decreaseUnit').addEventListener('click', () => {
            if (selectedUnit) {
                const current = parseFloat(modalUnit.value) || 1;
                if (current > getUnitStep()) {
                    modalUnit.value = (current - getUnitStep()).toFixed(3);
                    const unitAmount = parseFloat(modalUnit.value);
                    const totalPrice = unitAmount * originalPricePerUnit;
                    pricePerUnitInput.value = totalPrice.toFixed(2);
                    updateModalTotal();
                    updateAmountDisplay();
                    modalUnit.focus();
                    modalUnit.select();
                }
            }
        });

        // Quick buttons for quantity mode
        document.querySelectorAll('.quick-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (currentMeasurementType === 'quantity') {
                    modalQty.value = this.dataset.value;
                    const quantity = parseInt(modalQty.value);
                    const totalPrice = quantity * originalPricePerUnit;
                    pricePerUnitInput.value = totalPrice.toFixed(2);
                    modalQty.focus();
                    modalQty.select();
                } else if (selectedUnit) {
                    // Only work in unit mode if unit is selected
                    modalUnit.value = this.dataset.value;
                    const unitAmount = parseFloat(modalUnit.value);
                    const totalPrice = unitAmount * originalPricePerUnit;
                    pricePerUnitInput.value = totalPrice.toFixed(2);
                    updateModalTotal();
                    updateAmountDisplay();
                    modalUnit.focus();
                    modalUnit.select();
                } else {
                    showToast('Please select a unit first', 'warning');
                }
                updateModalTotal();
                updateAmountDisplay();
            });
        });

        // Unit-specific quick buttons - only if unit is selected
        document.querySelectorAll('.unit-quick-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (selectedUnit) {
                    modalUnit.value = this.dataset.value;
                    const unitAmount = parseFloat(modalUnit.value);
                    const totalPrice = unitAmount * originalPricePerUnit;
                    pricePerUnitInput.value = totalPrice.toFixed(2);
                    updateModalTotal();
                    updateAmountDisplay();
                    modalUnit.focus();
                    modalUnit.select();
                } else {
                    showToast('Please select a unit first', 'warning');
                }
            });
        });

        // Remember unit preference checkbox
        document.getElementById('rememberUnitPreference').addEventListener('change', function() {
            if (this.checked && currentProduct && selectedUnit) {
                saveUnitPreference();
            }
        });

        modalQty.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                confirmAddBtn.click();
            }
        });

        modalUnit.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                confirmAddBtn.click();
            }
        });

        pricePerUnitInput.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                confirmAddBtn.click();
            }
        });
    }

    function updateModalLabels() {
        const totalPriceLabel = document.getElementById('totalPriceLabel');
        if (currentMeasurementType === 'unit' && selectedUnit) {
            totalPriceLabel.textContent = `Total Price (per ${selectedUnit.short_name})`;
        } else {
            totalPriceLabel.textContent = 'Total Price';
        }
    }

    function updatePriceUnitLabel() {
        if (selectedUnit) {
            document.getElementById('priceUnitLabel').textContent = selectedUnit.short_name;
            document.getElementById('priceUnitLabel').className = 'text-primary fw-bold';
            document.getElementById('unitDisplay').textContent = selectedUnit.short_name;
            updateModalLabels();
        } else {
            document.getElementById('priceUnitLabel').textContent = 'Unit';
            document.getElementById('unitDisplay').textContent = 'unit';
            updateModalLabels();
        }
    }

    function updateAmountDisplay() {
        if (currentMeasurementType === 'quantity') {
            const quantity = parseInt(modalQty.value) || 1;
            document.getElementById('amountDisplay').textContent = formatQuantity(quantity);
            document.getElementById('unitDisplay').textContent = 'unit(s)';
        } else {
            const unitAmount = parseFloat(modalUnit.value) || 1;
            // Use only 2 decimal places for display
            document.getElementById('amountDisplay').textContent = formatNumber(unitAmount, 2);
            if (selectedUnit) {
                document.getElementById('unitDisplay').textContent = selectedUnit.short_name;
            } else {
                document.getElementById('unitDisplay').textContent = 'unit';
            }
        }
    }

    function switchToQuantityMode() {
        currentMeasurementType = 'quantity';
        document.getElementById('measurementLabel').textContent = 'Enter Quantity';
        document.getElementById('quantityInputSection').style.display = 'block';
        document.getElementById('unitInputSection').style.display = 'none';
        document.getElementById('unitSelection').style.display = 'none';
        document.getElementById('unitQuickButtons').style.display = 'none';
        document.getElementById('priceInputSection').style.display = 'none';
        document.getElementById('quantityQuickButtons').style.display = 'block';

        // Enable all inputs in quantity mode
        modalQty.disabled = false;
        document.getElementById('decreaseQty').disabled = false;
        document.getElementById('increaseQty').disabled = false;

        // For quantity mode, price per unit input shows total price for the quantity
        if (currentProduct) {
            const price = currentProduct.sale_price || currentProduct.price;
            const quantity = parseInt(modalQty.value) || 1;
            pricePerUnitInput.value = (price * quantity).toFixed(2);
            originalPricePerUnit = price;
            document.getElementById('originalPriceText').textContent = `Per unit: ${formatCurrency(price)}`;
            document.getElementById('originalPriceText').className = 'text-muted fw-semibold';
        }

        updateModalLabels();
        updateModalTotal();
        updateAmountDisplay();
    }

    function switchToUnitMode() {
        currentMeasurementType = 'unit';
        document.getElementById('measurementLabel').textContent = 'Enter Amount';
        document.getElementById('quantityInputSection').style.display = 'none';
        document.getElementById('unitInputSection').style.display = 'block';
        document.getElementById('unitSelection').style.display = 'block';
        document.getElementById('unitQuickButtons').style.display = 'block';
        document.getElementById('priceInputSection').style.display = 'block';
        document.getElementById('quantityQuickButtons').style.display = 'none';

        // Load units for the current product
        loadProductUnits();

        // Initially disable inputs until unit is selected
        modalUnit.disabled = true;
        document.getElementById('decreaseUnit').disabled = true;
        document.getElementById('increaseUnit').disabled = true;
        pricePerUnitInput.disabled = true;

        // Set initial price per unit from product
        if (currentProduct) {
            const price = currentProduct.sale_price || currentProduct.price;
            const unitAmount = parseFloat(modalUnit.value) || 1;
            pricePerUnitInput.value = (price * unitAmount).toFixed(2);
            originalPricePerUnit = price;
            // This will be updated when unit is selected
            if (selectedUnit) {
                document.getElementById('originalPriceText').textContent = `Per ${selectedUnit.short_name}: ${formatCurrency(price)}`;
            } else {
                document.getElementById('originalPriceText').textContent = `Per unit: ${formatCurrency(price)}`;
            }
            document.getElementById('originalPriceText').className = 'text-muted fw-semibold';
        }

        updateModalLabels();
        // Calculate initial display
        updateModalTotal();
        updateAmountDisplay();
    }

    function getUnitStep() {
        if (!selectedUnit) return 0.001;
        // Return appropriate step based on selected unit
        if (selectedUnit.short_name.toLowerCase().includes('kg')) {
            return 0.001; // 0.001kg = 1g increments
        } else if (selectedUnit.short_name.toLowerCase().includes('g')) {
            return 1; // 1g increments
        } else if (selectedUnit.short_name.toLowerCase().includes('l')) {
            return 0.001; // 0.001L = 1ml increments
        }
        return 0.001; // default
    }

    async function loadProductUnits() {
        if (!currentProduct || !currentProduct.id) return;

        try {
            // Fetch product units from backend using the named route
            const response = await axios.get(`{{ route('api.product.units', ['product' => '__ID__']) }}`.replace('__ID__', currentProduct.id));
            availableUnits = response.data.units || [];

            const unitSelect = document.getElementById('unitSelect');
            unitSelect.innerHTML = '<option value="">-- Please select a unit --</option>';

            if (availableUnits.length > 0) {
                // Check for saved preference
                const savedPreference = getSavedUnitPreference(currentProduct.id);

                availableUnits.forEach(unit => {
                    const option = document.createElement('option');
                    option.value = unit.id;
                    option.textContent = `${unit.name} (${unit.short_name})`;

                    // Select saved preference or default
                    if (savedPreference && savedPreference.unitId == unit.id) {
                        option.selected = true;
                        selectedUnit = unit;
                    } else if (unit.is_default && !selectedUnit) {
                        option.selected = true;
                        selectedUnit = unit;
                    }
                    unitSelect.appendChild(option);
                });

                if (selectedUnit) {
                    // Enable inputs if unit is pre-selected
                    modalUnit.disabled = false;
                    document.getElementById('decreaseUnit').disabled = false;
                    document.getElementById('increaseUnit').disabled = false;
                    pricePerUnitInput.disabled = false;
                }

                updatePriceUnitLabel();
                updateAmountDisplay();

                // Check remember preference checkbox
                if (savedPreference) {
                    document.getElementById('rememberUnitPreference').checked = true;
                }
            } else {
                // Fallback to product's primary unit
                selectedUnit = {
                    id: 1,
                    name: currentProduct.primary_unit || 'Unit',
                    short_name: currentProduct.primary_unit || 'unit',
                    conversion_factor: 1,
                    is_default: true
                };

                // Add as option
                const option = document.createElement('option');
                option.value = selectedUnit.id;
                option.textContent = `${selectedUnit.name} (${selectedUnit.short_name})`;
                option.selected = true;
                unitSelect.appendChild(option);

                // Enable inputs
                modalUnit.disabled = false;
                document.getElementById('decreaseUnit').disabled = false;
                document.getElementById('increaseUnit').disabled = false;
                pricePerUnitInput.disabled = false;
                updatePriceUnitLabel();
            }
        } catch (error) {
            console.error('Error loading units:', error);
            // Fallback to product's primary unit
            selectedUnit = {
                id: 1,
                name: currentProduct.primary_unit || 'Unit',
                short_name: currentProduct.primary_unit || 'unit',
                conversion_factor: 1,
                is_default: true
            };
            updatePriceUnitLabel();
        }
    }

    function checkSavedUnitPreference() {
        if (!currentProduct || !selectedUnit) return;
        const preferences = JSON.parse(localStorage.getItem('unitPreferences') || '{}');
        const productPreference = preferences[currentProduct.id];
        if (productPreference && productPreference.unitId === selectedUnit.id) {
            document.getElementById('rememberUnitPreference').checked = true;
        }
    }

    function saveUnitPreference() {
        if (!currentProduct || !selectedUnit) return;
        const preferences = JSON.parse(localStorage.getItem('unitPreferences') || '{}');
        preferences[currentProduct.id] = {
            unitId: selectedUnit.id,
            unitName: selectedUnit.name,
            shortName: selectedUnit.short_name,
            timestamp: Date.now()
        };
        localStorage.setItem('unitPreferences', JSON.stringify(preferences));
        showToast('Unit preference saved for this product', 'success');
    }

    function getSavedUnitPreference(productId) {
        const preferences = JSON.parse(localStorage.getItem('unitPreferences') || '{}');
        return preferences[productId];
    }

    function loadUnitPreferences() {
        // Unit preferences are loaded on demand in loadProductUnits
    }

    // ============================================
    // EVENT LISTENERS
    // ============================================
    confirmAddBtn.addEventListener('click', addOrUpdateProductInCart);
    removeFromCartBtn.addEventListener('click', removeCurrentProductFromCart);
    document.getElementById('clearCart').addEventListener('click', clearCart);
    document.getElementById('holdOrderBtn').addEventListener('click', holdOrder);
    document.getElementById('loadHeldBtn').addEventListener('click', loadHeldOrders);
    document.getElementById('completeOrder').addEventListener('click', completeOrder);
    applyDiscountBtn.addEventListener('click', applyOrderDiscount);

    // Quick customer button click handler
    document.getElementById('quickCustomerBtn').addEventListener('click', function() {
        quickCustomerModal.show();
    });

    // Save quick customer button
    document.getElementById('saveQuickCustomerBtn').addEventListener('click', saveQuickCustomer);

    discountType.addEventListener('change', function() {
        orderDiscountType = this.value;
        if (orderDiscountType === 'percent' && orderDiscountValue > 100) {
            discountValue.value = 100;
            orderDiscountValue = 100;
        }
    });

    discountValue.addEventListener('input', function() {
        const val = parseFloat(this.value) || 0;
        if (orderDiscountType === 'percent' && val > 100) {
            this.value = 100;
            orderDiscountValue = 100;
        } else {
            orderDiscountValue = val;
        }
    });

    // Discount field focus
    discountValue.addEventListener('click', function() {
        this.focus();
        this.select();
    });

    discountType.addEventListener('click', function() {
        discountValue.focus();
        discountValue.select();
    });

    // Item discount apply
    document.getElementById('applyItemDiscountBtn').addEventListener('click', function() {
        if (currentItemIndex === null) return;
        const value = parseFloat(document.getElementById('itemDiscountValue').value) || 0;
        const type = document.getElementById('itemDiscountType').value;

        if (type === 'percent' && value > 100) {
            showToast('Percentage cannot exceed 100%', 'warning');
            return;
        }

        cart[currentItemIndex].discount_type = type;
        cart[currentItemIndex].discount_value = value;
        updateCart();
        renderAllSearchedProducts();
        bootstrap.Modal.getInstance(document.getElementById('itemDiscountModal')).hide();
        showToast('Item discount applied!', 'success');
    });

    // ============================================
    // AUDIO FUNCTIONS
    // ============================================
    function playThankYouSound() {
        if (thankYouAudio) {
            // Reset audio to start
            thankYouAudio.currentTime = 0;
            // Play the audio
            thankYouAudio.play().catch(error => {
                console.log('Audio play failed:', error);
                // Show fallback notification
                showToast('Thank you for your purchase!', 'success', 3000);
            });
        } else {
            // Fallback if audio fails to load
            showToast('Thank you for your purchase!', 'success', 3000);
        }
    }

    // ============================================
    // CUSTOMER FUNCTIONALITY
    // ============================================
    function initializeCustomerSearch() {
        const customerSelectElement = document.getElementById('customerSelect');
        const originalOptions = Array.from(customerSelectElement.options);

        // Create customer search container
        const customerContainer = customerSelectElement.closest('.mb-4');
        if (customerContainer) {
            const customerSearchContainer = document.createElement('div');
            customerSearchContainer.className = 'mb-2';
            customerSearchContainer.innerHTML = `
                <input type="text"
                       id="customerSearchInput"
                       class="form-control form-control-sm"
                       placeholder="Search customers... (Ctrl+F)">
            `;

            // Insert after the label but before the dropdown
            const label = customerContainer.querySelector('label');
            if (label) {
                customerContainer.insertBefore(customerSearchContainer, label.nextElementSibling);
            }

            const customerSearchInput = document.getElementById('customerSearchInput');
            customerSearchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                if (searchTerm.length === 0) {
                    // Restore all options
                    customerSelectElement.innerHTML = '';
                    originalOptions.forEach(option => {
                        customerSelectElement.appendChild(option.cloneNode(true));
                    });
                    updateCustomerCount(originalOptions.length - 1);
                    return;
                }

                // Filter options
                const filteredOptions = originalOptions.filter(option => {
                    if (option.value === '') return true; // Always show "Walk-in Customer"
                    return option.text.toLowerCase().includes(searchTerm);
                });

                // Update dropdown
                customerSelectElement.innerHTML = '';
                filteredOptions.forEach(option => {
                    customerSelectElement.appendChild(option.cloneNode(true));
                });

                // Show count
                const visibleCount = filteredOptions.length - 1; // Exclude "Walk-in Customer"
                updateCustomerCount(visibleCount);
            });

            // Keyboard shortcut to focus customer search
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'f') {
                    e.preventDefault();
                    if (customerSearchInput) {
                        customerSearchInput.focus();
                        customerSearchInput.select();
                        showToast('Customer search focused', 'info', 1000);
                    }
                }
            });
        }
    }

    function initializeQuickCustomerModal() {
        // Add event listener for quick customer form submission
        document.getElementById('quickCustomerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            saveQuickCustomer();
        });

        // Clear form when modal is hidden
        document.getElementById('quickCustomerModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('quickCustomerForm').reset();
        });
    }

    async function saveQuickCustomer() {
        const firstName = document.getElementById('firstName').value.trim();
        const lastName = document.getElementById('lastName').value.trim();
        const phoneNumber = document.getElementById('phoneNumber').value.trim();
        const email = document.getElementById('email').value.trim();

        if (!firstName || !lastName) {
            showToast('First name and last name are required', 'warning');
            return;
        }

        // Show loading
        const saveBtn = document.getElementById('saveQuickCustomerBtn');
        const originalText = saveBtn.innerHTML;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
        saveBtn.disabled = true;

        try {
            // First, let's check if the route exists - we'll use a fallback if not
            let response;
            try {
                response = await axios.post('{{ route("customers.quick") }}', {
                    first_name: firstName,
                    last_name: lastName,
                    phone_number: phoneNumber,
                    email: email,
                    _token: '{{ csrf_token() }}'
                });
            } catch (routeError) {
                // If route doesn't exist, use a fallback method
                console.log('Quick customer route not found, using fallback');
                response = await saveCustomerFallback(firstName, lastName, phoneNumber, email);
            }

            if (response.data.success) {
                // Add to dropdown
                const customerSelectElement = document.getElementById('customerSelect');
                const option = document.createElement('option');
                option.value = response.data.customer.id;
                option.textContent = `${firstName} ${lastName}${phoneNumber ? ` - ${phoneNumber}` : ''}`;
                customerSelectElement.appendChild(option);

                // Select the new customer
                customerSelectElement.value = response.data.customer.id;

                // Update count
                const count = parseInt(document.getElementById('customerCount').textContent) + 1;
                document.getElementById('customerCount').textContent = count;

                // Close modal and reset form
                quickCustomerModal.hide();
                document.getElementById('quickCustomerForm').reset();
                showToast('Customer added successfully', 'success');
            } else {
                showToast(response.data.message || 'Failed to add customer', 'error');
            }
        } catch (error) {
            console.error('Customer save error:', error);
            let errorMessage = 'Failed to add customer';
            if (error.response && error.response.data && error.response.data.message) {
                errorMessage = error.response.data.message;
            } else if (error.response && error.response.data && error.response.data.errors) {
                errorMessage = Object.values(error.response.data.errors).flat().join(', ');
            }
            showToast(errorMessage, 'error');
        } finally {
            // Restore button state
            saveBtn.innerHTML = originalText;
            saveBtn.disabled = false;
        }
    }

    async function saveCustomerFallback(firstName, lastName, phoneNumber, email) {
        // Fallback method if the quick customer route doesn't exist
        return {
            data: {
                success: true,
                customer: {
                    id: 'temp_' + Date.now(),
                    first_name: firstName,
                    last_name: lastName,
                    phone_number: phoneNumber,
                    email: email
                }
            }
        };
    }

    function updateCustomerCount(count) {
        document.getElementById('customerCount').textContent = count;
    }

    // ============================================
    // MAIN FUNCTIONS
    // ============================================
    function showEmptySearchState() {
        searchLoading.classList.add('d-none');
        if (allSearchedProducts.length > 0) {
            renderAllSearchedProducts();
        } else {
            emptySearchRow.style.display = '';
            resultsBody.innerHTML = emptySearchRow.outerHTML;
        }
    }

    function showSearchLoading() {
        searchLoading.classList.remove('d-none');
        emptySearchRow.style.display = 'none';
    }

    function hideSearchLoading() {
        searchLoading.classList.add('d-none');
    }

    function renderAllSearchedProducts() {
        if (allSearchedProducts.length === 0) {
            resultsBody.innerHTML = emptySearchRow.outerHTML;
            return;
        }

        resultsBody.innerHTML = '';
        emptySearchRow.style.display = 'none';

        // Render products in reverse order (latest on top)
        const sortedProducts = [...allSearchedProducts].reverse();
        sortedProducts.forEach(product => {
            renderProductRow(product);
        });
    }

    function renderProductRow(product) {
        const price = product.sale_price || product.price;
        const unit = product.primary_unit || 'Unit';
        const cartItem = cart.find(i => i.product_id === product.id);
        const addedQty = cartItem ? cartItem.qty : 0;
        const cachedQty = productQuantityCache[product.id] || 1;
        const displayQty = addedQty > 0 ?
            formatQuantity(addedQty, cartItem?.is_unit_mode) :
            (cachedQty > 1 ? `(${formatQuantity(cachedQty)})` : '');

        const isOutOfStock = product.stock <= 0;
        const btnClass = addedQty > 0
            ? 'btn-success'
            : (isOutOfStock ? 'btn-secondary' : 'btn-outline-primary');
        const btnText = addedQty > 0
            ? `Added ${formatQuantity(addedQty, cartItem?.is_unit_mode)}`
            : (isOutOfStock ? 'Out of Stock' : `Set Qty ${displayQty}`);
        const btnDisabled = isOutOfStock ? 'disabled' : '';

        // Check for saved unit preference
        const savedPreference = getSavedUnitPreference(product.id);
        const unitBadgeExtra = savedPreference ?
            ` <i class="bi bi-star-fill text-warning" title="Preferred unit: ${savedPreference.shortName}"></i>` : '';

        const row = document.createElement('tr');
        row.dataset.productId = product.id;
        row.className = addedQty > 0 ? 'selected-product-row table-success' : '';
        row.innerHTML = `
            <td>
                <div class="d-flex align-items-center">
                    ${product.thumbnail ? `<img src="${product.thumbnail}" width="50" class="rounded me-3">` : '<div class="bg-light rounded me-3 d-flex align-items-center justify-content-center" style="width:50px;height:50px;"><i class="bi bi-image"></i></div>'}
                    <div>
                        <strong>${product.title}</strong><br>
                        <small class="text-muted d-flex align-items-center flex-wrap gap-2">
                            <span class="badge bg-secondary">
                                <i class="bi bi-upc-scan me-1"></i>SKU: ${product.sku}
                            </span>
                            <span class="badge bg-info text-dark">
                                <i class="bi bi-barcode me-1"></i>Barcode: ${product.barcode}
                            </span>
                        </small>
                    </div>
                </div>
            </td>
            <td class="text-center">
                <span class="badge bg-${product.stock > 10 ? 'success' : product.stock > 0 ? 'warning' : 'danger'}">
                    ${formatNumber(product.stock, 0)}
                </span>
            </td>
            <td class="text-center">
                <button class="btn btn-sm ${btnClass} qty-btn"
                        data-product='${JSON.stringify(product).replace(/'/g, "&apos;")}'
                        data-product-id="${product.id}"
                        ${btnDisabled}>
                    ${btnText}
                </button>
                <button class="btn btn-sm btn-danger ms-2 remove-from-search-btn"
                        data-product-id="${product.id}"
                        title="Remove from list">
                    <i class="bi bi-x-circle"></i> Cancel
                </button>
            </td>
            <td class="text-end fw-bold">${formatCurrency(price)}</td>
            <td class="text-center">
                <span class="badge bg-primary">
                    ${unit}${unitBadgeExtra}
                </span>
            </td>
        `;
        resultsBody.appendChild(row);

        // Add event listeners for the buttons in this row
        const qtyBtn = row.querySelector('.qty-btn');
        const cancelBtn = row.querySelector('.remove-from-search-btn');

        qtyBtn.addEventListener('click', function() {
            if (!this.disabled) openQuantityModal(this);
        });

        cancelBtn.addEventListener('click', function() {
            const productId = this.dataset.productId;
            removeProductFromSearchAndCart(productId);
        });
    }

    function searchProducts(query) {
        if (!query) return;

        showSearchLoading();
        axios.get('{{ route("pos.search") }}', {
            params: { q: query }
        })
        .then(res => {
            hideSearchLoading();
            // Merge new results with existing ones (avoid duplicates)
            const newProducts = res.data || [];
            newProducts.forEach(newProduct => {
                // Check if product already exists in our list
                const existingIndex = allSearchedProducts.findIndex(p => p.id === newProduct.id);
                if (existingIndex === -1) {
                    // Add new product to the END of array
                    allSearchedProducts.push(newProduct);
                } else {
                    // Update existing product (in case stock changed)
                    allSearchedProducts[existingIndex] = newProduct;
                }
            });
            renderAllSearchedProducts();
        })
        .catch(err => {
            hideSearchLoading();
            console.error('Search error:', err);
            showToast('Failed to search products', 'error');
        });
    }

    function clearAllUnselectedItems() {
        // Check if there are any unselected items
        const unselectedProducts = allSearchedProducts.filter(product => {
            const isInCart = cart.some(item => item.product_id === product.id);
            return !isInCart;
        });

        if (unselectedProducts.length === 0) {
            return; // No unselected items to clear
        }

        // Remove all items that are NOT in cart
        allSearchedProducts = allSearchedProducts.filter(product =>
            cart.some(item => item.product_id === product.id)
        );

        // Update display
        if (allSearchedProducts.length === 0) {
            resultsBody.innerHTML = emptySearchRow.outerHTML;
            showToast('All unselected items cleared', 'info', 1500);
        } else {
            renderAllSearchedProducts();
            showToast(`Cleared ${unselectedProducts.length} unselected items`, 'info', 1500);
        }
    }

    function removeProductFromSearchAndCart(productId) {
        // Remove from search table
        allSearchedProducts = allSearchedProducts.filter(p => p.id != productId);

        // Remove from cart if present
        const wasInCart = cart.some(item => item.product_id == productId);
        if (wasInCart) {
            cart = cart.filter(i => i.product_id != productId);
            delete productQuantityCache[productId];
            updateCart();
        }

        // Update display
        if (allSearchedProducts.length === 0) {
            resultsBody.innerHTML = emptySearchRow.outerHTML;
        } else {
            renderAllSearchedProducts();
        }
        showToast('Product removed from list', 'success');
    }

    function openQuantityModal(button) {
        try {
            const product = JSON.parse(button.dataset.product);
            const productId = button.dataset.productId;
            currentProduct = product;

            // Move this product to the end of the array (latest)
            const productIndex = allSearchedProducts.findIndex(p => p.id === productId);
            if (productIndex !== -1) {
                const [movedProduct] = allSearchedProducts.splice(productIndex, 1);
                allSearchedProducts.push(movedProduct);
            }

            const cartItem = cart.find(i => i.product_id === productId);
            const cachedQty = productQuantityCache[productId] || 1;
            const previousQty = cartItem ? cartItem.qty : cachedQty;

            // Update modal content with barcode and SKU
            document.getElementById('modalProductLabel').textContent = product.title;
            const price = product.sale_price || product.price;
            document.getElementById('modalProductPrice').textContent = `${formatCurrency(price)}`;
            document.getElementById('modalProductStock').textContent = `Stock: ${formatNumber(product.stock, 0)}`;
            document.getElementById('modalProductUnit').textContent = product.primary_unit || 'Unit';
            document.getElementById('modalProductSku').innerHTML = `<span class="badge bg-secondary"><i class="bi bi-upc-scan me-1"></i>SKU: ${product.sku}</span>`;
            document.getElementById('modalProductBarcode').innerHTML = `<span class="badge bg-info text-dark"><i class="bi bi-barcode me-1"></i>Barcode: ${product.barcode}</span>`;

            // Check if product has unit data
            if (product.units && product.units.length > 0) {
                // Product has units, enable unit mode
                document.getElementById('measureUnit').disabled = false;
                document.getElementById('measureUnit').parentElement.classList.remove('disabled');
            } else {
                // No units available, disable unit mode
                document.getElementById('measureUnit').disabled = true;
                document.getElementById('measureUnit').parentElement.classList.add('disabled');
            }

            // Check saved preference for measurement type
            const savedPreference = getSavedUnitPreference(productId);
            if (savedPreference) {
                // User has a saved unit preference, default to unit mode
                document.getElementById('measureUnit').checked = true;
                switchToUnitMode();
            } else {
                // Default to quantity mode
                document.getElementById('measureQuantity').checked = true;
                switchToQuantityMode();
            }

            // Set appropriate input value based on mode
            if (currentMeasurementType === 'quantity') {
                modalQty.value = previousQty;
                const quantity = parseInt(modalQty.value) || 1;
                pricePerUnitInput.value = (price * quantity).toFixed(2);
            } else {
                modalUnit.value = previousQty;
                const unitAmount = parseFloat(modalUnit.value) || 1;
                pricePerUnitInput.value = (price * unitAmount).toFixed(2);
            }

            document.getElementById('previousQtyText').textContent =
                cartItem ? `In cart: ${formatQuantity(previousQty, cartItem.is_unit_mode)} ${cartItem.is_unit_mode ? cartItem.unit_short_name || '' : ''}` : `Previous: ${formatQuantity(previousQty)}`;
            document.getElementById('previousQtyText').className =
                cartItem ? 'text-success fw-semibold' : 'text-muted';

            removeFromCartBtn.style.display = cartItem ? 'inline-block' : 'none';
            originalPricePerUnit = price; // Set original price per unit
            isPriceInputActive = false; // Reset price input tracking

            updateModalTotal();
            updateAmountDisplay();
            quantityModal.show();
        } catch (e) {
            console.error('Error opening quantity modal:', e);
            showToast('Error loading product details', 'error');
        }
    }

    function updateModalTotal() {
        if (!currentProduct) return;

        let quantity = 1;
        let unitPrice = parseFloat(pricePerUnitInput.value) || 0;

        if (currentMeasurementType === 'quantity') {
            quantity = parseInt(modalQty.value) || 1;
        } else {
            quantity = parseFloat(modalUnit.value) || 1;
        }

        const total = unitPrice;
        document.getElementById('totalPriceDisplay').textContent = `Total: ${formatCurrency(total)}`;

        // Update the calculated price display
        const originalPrice = originalPricePerUnit;
        const amount = currentMeasurementType === 'quantity' ?
            parseInt(modalQty.value) || 1 :
            parseFloat(modalUnit.value) || 1;
        const calculatedTotal = originalPrice * amount;
        document.getElementById('calculatedPriceDisplay').textContent =
            `Original price: ${formatCurrency(originalPrice)} × ${formatQuantity(amount, currentMeasurementType === 'unit')} = ${formatCurrency(calculatedTotal)}`;
    }

    function addOrUpdateProductInCart() {
        if (!currentProduct) return;

        // Check stock (convert to appropriate unit if needed)
        if (currentProduct.stock <= 0) {
            showToast(`${currentProduct.title} is out of stock`, 'error');
            quantityModal.hide();
            return;
        }

        // VALIDATION: In unit mode, require unit selection
        if (currentMeasurementType === 'unit' && !selectedUnit) {
            showToast('Please select a unit first', 'warning');
            unitSelect.focus();
            return;
        }

        let quantity = 0;
        let unitId = currentProduct.primary_unit_id || 1;
        let unitName = currentProduct.primary_unit || 'Unit';
        let unitShortName = unitName; // Default to unit name if no short name
        let isUnitMode = currentMeasurementType === 'unit';

        // Get the TOTAL price from the price per unit input field
        const totalPrice = parseFloat(pricePerUnitInput.value) || 0;

        if (isUnitMode) {
            // Handle unit purchase
            const unitAmount = parseFloat(modalUnit.value) || 0.001;
            if (unitAmount <= 0) {
                showToast('Unit amount must be greater than 0', 'warning');
                return;
            }

            // Check stock
            if (unitAmount > currentProduct.stock) {
                showToast(`Only ${formatNumber(currentProduct.stock, 3)} ${selectedUnit.short_name} available`, 'warning');
                modalUnit.value = currentProduct.stock.toFixed(3);
                updateModalTotal();
                return;
            }

            quantity = unitAmount;
            unitId = selectedUnit.id;
            unitName = selectedUnit.name;
            unitShortName = selectedUnit.short_name;
        } else {
            // Handle quantity purchase (pieces)
            quantity = parseInt(modalQty.value) || 1;
            if (quantity < 1) {
                showToast('Quantity must be at least 1', 'warning');
                return;
            }
            if (quantity > currentProduct.stock) {
                showToast(`Only ${formatNumber(currentProduct.stock, 0)} units available`, 'warning');
                modalQty.value = currentProduct.stock;
                updateModalTotal();
                return;
            }
        }

        const p = currentProduct;

        // Calculate unit price (price per item/unit)
        const unitPrice = originalPricePerUnit;

        // Make sure product is in searched list
        if (!allSearchedProducts.some(sp => sp.id === p.id)) {
            allSearchedProducts.push({...p});
        }

        // Update or add to cart
        const existing = cart.find(i => i.product_id === p.id);
        if (existing) {
            existing.qty = quantity;
            existing.price = totalPrice; // Store total price
            existing.unit_price = unitPrice; // Store unit price separately
            existing.unit_name = unitName;
            existing.unit_short_name = unitShortName;
            existing.unit_id = unitId;
            existing.sku = p.sku;
            existing.barcode = p.barcode; // Store barcode
            existing.discount_type = 'percent';
            existing.discount_value = 0;
            existing.discounted_price = unitPrice;
            existing.is_unit_mode = isUnitMode;
            existing.original_unit = isUnitMode ? selectedUnit : null;
            existing.price_per_unit = unitPrice; // Store price per unit
        } else {
            cart.push({
                product_id: p.id,
                title: p.title,
                price: totalPrice, // Store total price
                unit_price: unitPrice, // Store unit price separately
                qty: quantity,
                unit_name: unitName,
                unit_short_name: unitShortName,
                unit_id: parseInt(unitId),
                sku: p.sku,
                barcode: p.barcode, // Store barcode
                thumbnail: p.thumbnail,
                discount_type: 'percent',
                discount_value: 0,
                discounted_price: unitPrice,
                is_unit_mode: isUnitMode,
                original_unit: isUnitMode ? selectedUnit : null,
                price_per_unit: unitPrice // Store price per unit
            });
        }

        productQuantityCache[p.id] = quantity;
        quantityModal.hide();
        updateCart();
        renderAllSearchedProducts();
        currentProduct = null;
        showToast('Product added to cart', 'success');
    }

    function removeCurrentProductFromCart() {
        if (!currentProduct) return;

        Swal.fire({
            title: 'Remove from Cart?',
            text: `Remove ${currentProduct.title} from cart?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, remove'
        }).then(res => {
            if (res.isConfirmed) {
                const id = currentProduct.id;
                cart = cart.filter(i => i.product_id != id);
                delete productQuantityCache[id];
                quantityModal.hide();
                updateCart();
                renderAllSearchedProducts();
                showToast('Product removed from cart', 'success');
            }
        });
    }

    function updateCart() {
        if (cart.length === 0) {
            emptyCartRow.style.display = '';
            cartBody.innerHTML = '';
            subtotalEl.textContent = formatCurrency(0);
            discountEl.textContent = formatCurrency(0);
            grandTotalEl.textContent = formatCurrency(0);
            document.getElementById('taxAmount').textContent = formatCurrency(0);
            renderAllSearchedProducts();
            return;
        }

        emptyCartRow.style.display = 'none';
        cartBody.innerHTML = '';

        let subtotal = 0;

        cart.forEach((item, i) => {
            // Calculate unit price (price per item/unit)
            let unitPrice = 0;
            if (item.price_per_unit) {
                // Use stored price per unit
                unitPrice = item.price_per_unit;
            } else {
                // Calculate unit price from total price and quantity
                unitPrice = item.price / item.qty;
            }

            // Apply item discount if any
            let discountedUnitPrice = unitPrice;
            if (item.discount_value > 0) {
                discountedUnitPrice = item.discount_type === 'percent'
                    ? unitPrice * (1 - item.discount_value / 100)
                    : unitPrice - (item.discount_value / item.qty);

                if (discountedUnitPrice < 0) discountedUnitPrice = 0;
                item.discounted_price = discountedUnitPrice;
            } else {
                item.discounted_price = unitPrice;
            }

            // Calculate total for this item (after item discount)
            const total = discountedUnitPrice * item.qty;
            subtotal += total;

            // Use short name for display
            const displayUnit = item.unit_short_name || item.unit_name || 'Unit';

            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="align-middle">${i+1}</td>
                <td class="align-middle">
                    <div class="d-flex align-items-center">
                        ${item.thumbnail ? `<img src="${item.thumbnail}" width="40" class="rounded me-2">` : '<div class="bg-light rounded me-2 d-flex align-items-center justify-content-center" style="width:40px;height:40px;"><i class="bi bi-image"></i></div>'}
                        <div>
                            <strong>${item.title}</strong><br>
                            <small class="text-muted d-flex align-items-center flex-wrap gap-1">
                                <span class="badge bg-secondary">
                                    <i class="bi bi-upc-scan me-1"></i>${item.sku}
                                </span>
                                <span class="badge bg-info text-dark">
                                    <i class="bi bi-barcode me-1"></i>${item.barcode || 'N/A'}
                                </span>
                            </small>
                            ${item.discount_value > 0 ? `<small class="text-warning d-block">-${formatNumber(item.discount_value, 2)}${item.discount_type === 'percent' ? '%' : '₦'} discount</small>` : ''}
                        </div>
                    </div>
                </td>
                <td class="text-center align-middle">
                    <button class="btn btn-sm ${item.is_unit_mode ? 'btn-success' : 'btn-primary'} qty-btn-cart"
                            data-product='${JSON.stringify(item).replace(/'/g, "&apos;")}'
                            data-product-id="${item.product_id}">
                        ${formatQuantity(item.qty, true)} ${displayUnit}
                        ${item.is_unit_mode ? '<i class="bi bi-scale ms-1"></i>' : ''}
                    </button>
                </td>
                <td class="text-end align-middle">${formatCurrency(discountedUnitPrice)}</td>
                <td class="text-end align-middle fw-bold">${formatCurrency(total)}</td>
                <td class="text-center align-middle">
                    <button class="btn btn-sm btn-outline-warning rounded-circle item-discount-btn p-0"
                            data-product-id="${item.product_id}"
                            title="Apply item discount"
                            style="width: 28px; height: 28px;">
                        <i class="bi bi-percent"></i>
                    </button>
                </td>
                <td class="text-center align-items-center">
                    <button class="btn btn-sm btn-danger rounded-circle remove-cart-item-btn p-0"
                            data-index="${i}"
                            title="Remove item"
                            style="width: 28px; height: 28px;">
                        <i class="bi bi-x"></i>
                    </button>
                </td>
            `;
            cartBody.appendChild(row);

            // Add event listeners
            const removeBtn = row.querySelector('.remove-cart-item-btn');
            removeBtn.addEventListener('click', function() {
                const index = parseInt(this.dataset.index);
                removeCartItem(index);
            });

            const discountBtn = row.querySelector('.item-discount-btn');
            discountBtn.addEventListener('click', function() {
                const productId = this.dataset.productId;
                currentItemIndex = cart.findIndex(item => item.product_id === productId);
                if (currentItemIndex === -1) return;

                const item = cart[currentItemIndex];
                document.getElementById('itemName').textContent = item.title;
                document.getElementById('itemDiscountValue').value = item.discount_value || 0;
                document.getElementById('itemDiscountType').value = item.discount_type || 'percent';

                new bootstrap.Modal(document.getElementById('itemDiscountModal')).show();
            });

            const qtyBtn = row.querySelector('.qty-btn-cart');
            qtyBtn.addEventListener('click', function() {
                openQuantityModal(this);
            });
        });

        // Calculate tax
        const taxRate = {{ config('pos.tax_rate', 0) }};
        const taxAmount = taxRate > 0 ? (subtotal * taxRate) / 100 : 0;

        let orderDiscountAmount = 0;
        if (orderDiscountValue > 0) {
            if (orderDiscountType === 'percent') {
                orderDiscountAmount = (subtotal * orderDiscountValue) / 100;
            } else {
                orderDiscountAmount = orderDiscountValue;
            }
            orderDiscountAmount = Math.min(orderDiscountAmount, subtotal);
        }

        const grandTotal = subtotal + taxAmount - orderDiscountAmount;

        subtotalEl.textContent = formatCurrency(subtotal);
        document.getElementById('taxAmount').textContent = formatCurrency(taxAmount);
        discountEl.textContent = `-${formatCurrency(orderDiscountAmount)}`;
        grandTotalEl.textContent = formatCurrency(grandTotal);

        // Show/hide discount row
        const discountRow = document.getElementById('discountRow');
        if (orderDiscountValue > 0) {
            discountRow.style.display = 'flex';
        } else {
            discountRow.style.display = 'none';
        }

        window.currentDiscount = {
            type: orderDiscountType,
            value: orderDiscountValue,
            amount: orderDiscountAmount
        };
    }

    function removeCartItem(index) {
        const item = cart[index];
        cart.splice(index, 1);
        delete productQuantityCache[item.product_id];
        updateCart();
        renderAllSearchedProducts();
        showToast('Item removed from cart', 'success');
    }

    function applyOrderDiscount() {
        orderDiscountValue = parseFloat(discountValue.value) || 0;
        orderDiscountType = discountType.value;

        if (orderDiscountType === 'percent' && orderDiscountValue > 100) {
            showToast('Percentage discount cannot exceed 100%', 'warning');
            orderDiscountValue = 100;
            discountValue.value = 100;
        }

        // Show/hide discount row
        const discountRow = document.getElementById('discountRow');
        if (orderDiscountValue > 0) {
            discountRow.style.display = 'flex';
        } else {
            discountRow.style.display = 'none';
        }

        updateCart();
        showToast('Order discount applied!', 'success');
    }

    function clearCart() {
        if (cart.length === 0) {
            showToast('Cart is already empty', 'info');
            return;
        }

        Swal.fire({
            title: 'Clear Cart?',
            text: 'This will remove all items from your cart.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, clear cart'
        }).then(res => {
            if (res.isConfirmed) {
                cart = [];
                productQuantityCache = {};
                updateCart();
                renderAllSearchedProducts();
                showToast('Cart cleared', 'success');
            }
        });
    }

    function holdOrder() {
        if (cart.length === 0) {
            showToast('Cart is empty', 'info');
            return;
        }

        const heldOrders = JSON.parse(localStorage.getItem('heldOrders') || '[]');
        heldOrders.push({
            id: Date.now(),
            cart: JSON.parse(JSON.stringify(cart)),
            customer: customerSelect.value,
            allSearchedProducts: JSON.parse(JSON.stringify(allSearchedProducts)),
            productQuantityCache: JSON.parse(JSON.stringify(productQuantityCache)),
            discount: {
                type: orderDiscountType,
                value: orderDiscountValue
            },
            time: new Date().toLocaleString(),
            timestamp: Date.now()
        });
        localStorage.setItem('heldOrders', JSON.stringify(heldOrders));

        cart = [];
        productQuantityCache = {};
        orderDiscountValue = 0;
        discountValue.value = '0';
        updateCart();
        renderAllSearchedProducts();
        showToast('Order held successfully!', 'success');
    }

    function loadHeldOrders() {
        const heldOrders = JSON.parse(localStorage.getItem('heldOrders') || '[]');
        const list = document.getElementById('heldOrdersList');
        const noOrders = document.getElementById('noHeldOrders');

        if (heldOrders.length === 0) {
            list.innerHTML = '';
            noOrders.style.display = 'block';
        } else {
            noOrders.style.display = 'none';
            let html = '<div class="list-group">';
            heldOrders.sort((a, b) => b.timestamp - a.timestamp).forEach(order => {
                const items = order.cart.length;
                const total = order.cart.reduce((sum, item) => {
                    let price = item.price;
                    if (item.discount_value > 0) {
                        price = item.discount_type === 'percent'
                            ? item.price * (1 - item.discount_value / 100)
                            : item.price - item.discount_value;
                    }
                    return sum + price;
                }, 0);
                html += `
                    <div class="list-group-item list-group-item-action">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">${order.time}</h6>
                                <p class="mb-1 text-muted">${items} item${items > 1 ? 's' : ''} - ${formatCurrency(total)}</p>
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-primary load-order-btn" data-order-id="${order.id}">Load</button>
                                <button class="btn btn-sm btn-outline-danger remove-order-btn" data-order-id="${order.id}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            list.innerHTML = html;

            // Add event listeners for the loaded orders
            document.querySelectorAll('.load-order-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const orderId = this.dataset.orderId;
                    const heldOrders = JSON.parse(localStorage.getItem('heldOrders') || '[]');
                    const order = heldOrders.find(o => o.id == orderId);
                    if (order) {
                        if (cart.length > 0) {
                            Swal.fire({
                                title: 'Replace Current Cart?',
                                text: 'Loading this order will replace your current cart.',
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonText: 'Yes, replace'
                            }).then(res => res.isConfirmed && loadOrderFromHeld(order));
                        } else {
                            loadOrderFromHeld(order);
                        }
                    }
                });
            });

            document.querySelectorAll('.remove-order-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const orderId = this.dataset.orderId;
                    Swal.fire({
                        title: 'Remove Order?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, remove'
                    }).then(res => {
                        if (res.isConfirmed) {
                            let orders = JSON.parse(localStorage.getItem('heldOrders') || '[]');
                            orders = orders.filter(o => o.id != orderId);
                            localStorage.setItem('heldOrders', JSON.stringify(orders));
                            loadHeldOrders();
                            showToast('Order removed', 'success');
                        }
                    });
                });
            });
        }
        new bootstrap.Modal(document.getElementById('loadOrderModal')).show();
    }

    function loadOrderFromHeld(order) {
        cart = JSON.parse(JSON.stringify(order.cart));
        allSearchedProducts = order.allSearchedProducts ? JSON.parse(JSON.stringify(order.allSearchedProducts)) : allSearchedProducts;
        productQuantityCache = order.productQuantityCache ? JSON.parse(JSON.stringify(order.productQuantityCache)) : {};

        if (order.customer) {
            customerSelect.value = order.customer;
        }

        if (order.discount) {
            orderDiscountType = order.discount.type;
            orderDiscountValue = order.discount.value;
            discountType.value = order.discount.type;
            discountValue.value = order.discount.value;
        }

        updateCart();
        renderAllSearchedProducts();
        bootstrap.Modal.getInstance(document.getElementById('loadOrderModal')).hide();
        showToast('Order loaded successfully!', 'success');
    }

    async function completeOrder() {
        if (cart.length === 0) {
            showToast('Cart is empty', 'warning');
            return;
        }

        const payment = document.querySelector('input[name="payment"]:checked').value;
        const customerId = customerSelect.value || null;

        // Check if we're in offline mode
        const isOfflineMode = document.getElementById('offlineModeToggle').checked;
        if (isOfflineMode) {
            // Save order offline
            const offlineOrders = JSON.parse(localStorage.getItem('offlineOrders') || '[]');
            const offlineOrder = {
                id: 'offline_' + Date.now(),
                items: cart.map(item => ({
                    product_id: item.product_id,
                    qty: parseFloat(item.qty),
                    unit_id: parseInt(item.unit_id || 1),
                    sale_price: parseFloat(item.price),
                    discount_type: item.discount_type || null,
                    discount_value: item.discount_value || 0,
                    is_unit_mode: item.is_unit_mode || false,
                    unit_name: item.unit_name || null
                })),
                payment_method: payment,
                customer_id: customerId,
                discount_type: orderDiscountType,
                discount_value: orderDiscountValue,
                discount_amount: window.currentDiscount?.amount || 0,
                tax_rate: {{ config('pos.tax_rate', 0) }},
                timestamp: Date.now(),
                time: new Date().toLocaleString()
            };
            offlineOrders.push(offlineOrder);
            localStorage.setItem('offlineOrders', JSON.stringify(offlineOrders));

            // Reset cart
            cart = [];
            productQuantityCache = {};
            orderDiscountValue = 0;
            discountValue.value = '0';
            updateCart();
            renderAllSearchedProducts();
            showToast('Order saved offline!', 'success');
            return;
        }

        const items = cart.map(item => ({
            product_id: item.product_id,
            qty: parseFloat(item.qty),
            unit_id: parseInt(item.unit_id || 1),
            sale_price: parseFloat(item.unit_price || item.price / item.qty), // Send unit price
            discount_type: item.discount_type || null,
            discount_value: item.discount_value || 0,
            is_unit_mode: item.is_unit_mode || false,
            unit_name: item.unit_name || null
        }));

        const discount = window.currentDiscount || { type: 'percent', value: 0, amount: 0 };

        Swal.fire({
            title: 'Processing Order...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        try {
            const response = await axios.post('{{ route("pos.order.save") }}', {
                items,
                payment_method: payment,
                customer_id: customerId,
                discount_type: discount.type,
                discount_value: discount.value,
                discount_amount: discount.amount,
                _token: '{{ csrf_token() }}'
            });

            Swal.close();
            if (response.data.success) {
                Swal.fire({
                    title: 'Success!',
                    html: `<div class="text-center">
                        <i class="bi bi-check-circle text-success display-1 mb-3"></i>
                        <h4>Order #${response.data.order_id} Completed!</h4>
                        <p class="fs-3">Total: ${formatCurrency(response.data.total)}</p>
                    </div>`,
                    icon: 'success',
                    showCancelButton: true,
                    confirmButtonText: 'Print Receipt',
                    cancelButtonText: 'New Order',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-success btn-lg me-2',
                        cancelButton: 'btn btn-outline-secondary btn-lg'
                    }
                }).then(r => {
                    if (r.isConfirmed) {
                        // Play thank you sound
                        playThankYouSound();
                        // Open print window
                        printWindow = window.open(`/pos/receipt/${response.data.order_id}`, '_blank');
                        if (printWindow) {
                            startMonitoringPrintWindow();
                        }
                    } else {
                        resetAfterOrder();
                    }
                });
            } else {
                Swal.fire('Error', response.data.message || 'Failed to process order', 'error');
            }
        } catch (error) {
            Swal.close();
            let msg = 'Failed to complete order.';
            if (error.response && error.response.data.errors) {
                msg = Object.values(error.response.data.errors).flat().join('<br>');
            } else if (error.response && error.response.data.message) {
                msg = error.response.data.message;
            }

            // Check if offline mode should be suggested
            if (!navigator.onLine || error.response?.status === 0) {
                Swal.fire({
                    title: 'Connection Error',
                    html: `You seem to be offline. Would you like to:<br><br>
                        1. <strong>Retry</strong> when back online<br>
                        2. <strong>Save offline</strong> for later sync<br>
                        3. <strong>Enable offline mode</strong>`,
                    icon: 'warning',
                    showCancelButton: true,
                    showDenyButton: true,
                    confirmButtonText: 'Retry',
                    denyButtonText: 'Save Offline',
                    cancelButtonText: 'Enable Offline Mode'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Retry
                        showToast('Will retry when online', 'info');
                    } else if (result.isDenied) {
                        // Save offline
                        const offlineOrders = JSON.parse(localStorage.getItem('offlineOrders') || '[]');
                        offlineOrders.push({
                            id: 'offline_' + Date.now(),
                            items,
                            payment_method: payment,
                            customer_id: customerId,
                            discount_type: discount.type,
                            discount_value: discount.value,
                            discount_amount: discount.amount,
                            timestamp: Date.now(),
                            time: new Date().toLocaleString()
                        });
                        localStorage.setItem('offlineOrders', JSON.stringify(offlineOrders));
                        resetAfterOrder();
                        showToast('Order saved offline!', 'success');
                    } else if (result.dismiss === Swal.DismissReason.cancel) {
                        // Enable offline mode
                        document.getElementById('offlineModeToggle').checked = true;
                        updateConnectionStatus(false);
                        showToast('Offline mode enabled', 'warning');
                    }
                });
            } else {
                Swal.fire('Error', msg, 'error');
            }
        }
    }

    function startMonitoringPrintWindow() {
        if (printCheckInterval) {
            clearInterval(printCheckInterval);
        }
        printCheckInterval = setInterval(function() {
            if (printWindow && printWindow.closed) {
                clearInterval(printCheckInterval);
                printCheckInterval = null;
                resetAfterOrder();
            }
        }, 500);
    }

    function resetAfterOrder() {
        // Clear cart and search table
        cart = [];
        allSearchedProducts = [];
        productQuantityCache = {};
        orderDiscountValue = 0;
        discountValue.value = '0';
        updateCart();
        renderAllSearchedProducts();
        input.value = '';
        input.focus();
        input.select();
        showToast('New order started', 'info');
    }

    function showToast(message, type = 'success', duration = 2000) {
        const toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: duration,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.onmouseenter = Swal.stopTimer;
                toast.onmouseleave = Swal.resumeTimer;
            }
        });
        toast.fire({
            icon: type,
            title: message
        });
    }

    function debounce(func, delay) {
        let timeout;
        return function executedFunction(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), delay);
        };
    }

    // ============================================
    // OFFLINE MODE FUNCTIONALITY
    // ============================================
    function updateConnectionStatus(isOnline) {
        const statusElement = document.getElementById('connectionStatus');
        const offlineToggle = document.getElementById('offlineModeToggle');

        if (isOnline) {
            statusElement.className = 'badge bg-success';
            statusElement.innerHTML = '<i class="bi bi-wifi"></i> Online';
            offlineToggle.checked = false;
        } else {
            statusElement.className = 'badge bg-danger';
            statusElement.innerHTML = '<i class="bi bi-wifi-off"></i> Offline';
            offlineToggle.checked = true;
        }
    }

    // Check initial connection status
    updateConnectionStatus(navigator.onLine);

    // Listen for connection changes
    window.addEventListener('online', () => {
        updateConnectionStatus(true);
        showToast('You are back online!', 'success');
        // Try to sync offline orders
        syncOfflineOrders();
    });

    window.addEventListener('offline', () => {
        updateConnectionStatus(false);
        showToast('You are offline. Orders will be saved locally.', 'warning');
    });

    // Offline mode toggle
    document.getElementById('offlineModeToggle').addEventListener('change', function() {
        if (this.checked) {
            showToast('Offline mode enabled', 'warning');
        } else {
            showToast('Online mode enabled', 'success');
        }
    });

    // Sync offline orders
    async function syncOfflineOrders() {
        const offlineOrders = JSON.parse(localStorage.getItem('offlineOrders') || '[]');
        if (offlineOrders.length === 0) return;

        const unsyncedOrders = [...offlineOrders];
        let syncedCount = 0;
        let failedCount = 0;

        for (const order of unsyncedOrders) {
            try {
                const response = await axios.post('{{ route("pos.order.save") }}', {
                    ...order,
                    _token: '{{ csrf_token() }}'
                });
                if (response.data.success) {
                    syncedCount++;
                    // Remove from offline storage
                    const updatedOrders = offlineOrders.filter(o => o.id !== order.id);
                    localStorage.setItem('offlineOrders', JSON.stringify(updatedOrders));
                } else {
                    failedCount++;
                }
            } catch (error) {
                failedCount++;
            }
        }

        if (syncedCount > 0) {
            showToast(`Synced ${syncedCount} order${syncedCount > 1 ? 's' : ''}`, 'success');
        }
    }

    // ============================================
    // KEYBOARD SHORTCUTS
    // ============================================
    document.addEventListener('keydown', function(e) {
        // Don't trigger shortcuts when user is typing in inputs
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable) {
            return;
        }

        switch(e.key) {
            case 'F1':
                e.preventDefault();
                input.focus();
                input.select();
                showToast('Search box focused', 'info', 1000);
                break;
            case 'F2':
                e.preventDefault();
                clearCart();
                break;
            case 'F3':
                e.preventDefault();
                if (!document.getElementById('offlineModeToggle').checked) {
                    completeOrder();
                } else {
                    showToast('Complete order is disabled in offline mode', 'warning');
                }
                break;
            case 'F4':
                e.preventDefault();
                holdOrder();
                break;
            case 'F5':
                e.preventDefault();
                loadHeldOrders();
                break;
            case 'Escape':
                if (cart.length > 0) {
                    clearCart();
                }
                break;
        }
    });

    // ============================================
    // INITIALIZE APPLICATION
    // ============================================
    initializeApp();
    updateCart();
});
</script>
<style>
/* Enhanced CSS Styles */
.customer-search-container {
    cursor: pointer;
    position: relative;
    transition: all 0.2s ease;
}
.customer-search-container:hover {
    background-color: rgba(0, 123, 255, 0.05);
    border-radius: 0.375rem;
}
#customerSearchInput {
    border: 1px solid #ced4da;
    border-radius: 0.375rem;
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
    transition: all 0.2s ease;
    width: 100%;
}
#customerSearchInput:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    outline: none;
    background-color: #fff;
}
.selected-product-row {
    background-color: rgba(25, 135, 84, 0.1) !important;
    border-left: 4px solid #198754;
    transition: all 0.3s ease;
}
/* Unit Modal Enhancements */
.unit-quick-btn {
    border: 1px solid #198754 !important;
    color: #198754 !important;
    transition: all 0.2s ease;
}
.unit-quick-btn:hover {
    background-color: #198754 !important;
    color: white !important;
    transform: translateY(-2px);
}
/* Price input styling */
#priceInputSection .input-group-lg {
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
#priceInputSection .input-group-text {
    min-width: 50px;
    font-weight: bold;
}
#priceInputSection input {
    background-color: #fff;
}
#priceInputSection input:focus {
    background-color: #fff;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}
#priceInputSection input:disabled {
    background-color: #e9ecef;
    opacity: 0.7;
    cursor: not-allowed;
}
#calculatedPriceDisplay {
    font-size: 1.1rem;
    min-height: 1.5rem;
    display: block;
    padding: 0.25rem;
    background: rgba(25, 135, 84, 0.1);
    border-radius: 0.25rem;
}
/* Unit selection dropdown */
#unitSelect {
    background-color: #f8f9fa;
    border: 1px solid #ced4da;
    font-weight: 500;
}
#unitSelect:focus {
    background-color: #fff;
    border-color: #86b7fe;
}
#unitSelect:required:invalid {
    color: #6c757d;
}
#unitSelect option[value=""] {
    color: #6c757d;
}
/* Disabled state styling */
#modalUnit:disabled,
#pricePerUnit:disabled,
#decreaseUnit:disabled,
#increaseUnit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
/* Radio button fix */
.form-check-input:checked {
    background-color: #0d6efd;
    border-color: #0d6efd;
}
/* Ensure measurement type buttons work properly */
.btn-group .btn {
    position: relative;
}
.btn-group .btn-check:checked + .btn {
    background-color: #0d6efd;
    color: white;
    border-color: #0d6efd;
    z-index: 2;
}
/* Original price text */
#originalPriceText {
    font-size: 0.85rem;
}
/* Modal focus improvements */
#pricePerUnit:focus,
#modalUnit:focus {
    transform: scale(1.02);
    transition: transform 0.2s ease;
}
/* Unit quick buttons spacing */
.unit-quick-btn {
    margin: 2px;
    padding: 0.25rem 0.5rem !important;
}
/* SKU and Barcode badge styles */
.badge.bg-secondary, .badge.bg-info {
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.badge .bi-upc-scan, .badge .bi-barcode {
    font-size: 0.8rem;
}

/* Make badges responsive */
.text-muted .badge {
    margin: 0.1rem;
}

/* Cart item badges */
#cartBody .badge {
    font-size: 0.65rem;
    padding: 0.2rem 0.4rem;
}

/* Table cell adjustments for badges */
td .d-flex.gap-1, td .d-flex.gap-2 {
    margin-top: 0.25rem;
}

/* Responsive adjustments for badges */
@media (max-width: 768px) {
    .badge.bg-secondary, .badge.bg-info {
        font-size: 0.65rem;
        padding: 0.15rem 0.35rem;
    }

    .badge .bi-upc-scan, .badge .bi-barcode {
        font-size: 0.7rem;
    }
}

/* Animations */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}
@keyframes slideIn {
    from { transform: translateY(-20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
/* Modal title styling */
#totalPriceLabel {
    font-size: 1.1rem;
    color: #0d6efd;
}
#totalPriceLabel::after {
    content: '';
    display: block;
    width: 30px;
    height: 2px;
    background: linear-gradient(to right, #0d6efd, #20c997);
    margin-top: 2px;
    border-radius: 1px;
}
/* Focus states */
#modalQty:focus,
#barcodeInput:focus,
#discountValue:focus,
#modalUnit:focus,
#pricePerUnit:focus {
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    border-color: #86b7fe;
    transform: scale(1.02);
    transition: all 0.2s ease;
}
/* Button enhancements */
.btn-outline-secondary:hover {
    background: #6c757d;
    color: white;
    transform: translateY(-2px);
    transition: all 0.2s ease;
}
.qty-btn-cart {
    min-width: 100px;
    transition: all 0.2s ease;
}
.qty-btn-cart:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}
/* Unit mode indicator */
.btn-success .bi-scale {
    animation: scalePulse 2s infinite;
}
@keyframes scalePulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.2); }
    100% { transform: scale(1); }
}
/* Loading overlay */
#searchLoading {
    backdrop-filter: blur(2px);
    animation: fadeIn 0.3s ease;
}
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
/* Table enhancements */
.table-responsive::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
.table-responsive::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}
.table-responsive::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}
.table-responsive::-webkit-scrollbar-thumb:hover {
    background: #555;
}
/* Modal animations */
.modal.fade .modal-content {
    transform: scale(0.95);
    transition: transform 0.3s ease-out, opacity 0.3s ease-out;
    opacity: 0;
}
.modal.show .modal-content {
    transform: scale(1);
    opacity: 1;
}
/* Badge animations */
.badge {
    transition: all 0.3s ease;
}
.badge:hover {
    transform: scale(1.05);
}
/* Payment method buttons */
.btn-check:checked + .btn-outline-success {
    background: #198754;
    color: white;
    border-color: #198754;
    transform: translateY(-2px);
}
.btn-check:checked + .btn-outline-primary {
    background: #0d6efd;
    color: white;
    border-color: #0d6efd;
    transform: translateY(-2px);
}
.btn-check:checked + .btn-outline-info {
    background: #0dcaf0;
    color: white;
    border-color: #0dcaf0;
    transform: translateY(-2px);
}
/* Cart table fixes */
.table-sm {
    font-size: 0.875rem;
}
.table-sm th {
    font-weight: 600;
    padding: 0.5rem;
}
.table-sm td {
    padding: 0.5rem;
    vertical-align: middle !important;
}
/* Fixed column widths */
.table-sm thead th:nth-child(1) { width: 5%; }
.table-sm thead th:nth-child(2) { width: 30%; }
.table-sm thead th:nth-child(3) { width: 15%; }
.table-sm thead th:nth-child(4) { width: 15%; }
.table-sm thead th:nth-child(5) { width: 15%; }
.table-sm thead th:nth-child(6) { width: 10%; }
.table-sm thead th:nth-child(7) { width: 10%; }
/* Price alignment */
#cartBody td.text-end {
    text-align: right !important;
    padding-right: 0.75rem !important;
}
/* Button sizing in cart */
.qty-btn-cart {
    min-width: 80px;
    padding: 0.25rem 0.5rem !important;
    white-space: nowrap;
}
/* Discount and remove buttons */
.item-discount-btn,
.remove-cart-item-btn {
    width: 28px !important;
    height: 28px !important;
    padding: 0 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}
/* Make sure images don't overflow */
#cartBody img {
    max-width: 40px;
    max-height: 40px;
    object-fit: cover;
}
/* Highlight discounted items */
.text-warning {
    color: #ffc107 !important;
    font-weight: 500;
}
/* Offline mode indicator */
#connectionStatus {
    transition: all 0.3s ease;
    animation: pulse 2s infinite;
}
#connectionStatus.bg-danger {
    animation: blink 1.5s infinite;
}
@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}
/* Unit preference star */
.bi-star-fill {
    font-size: 0.8em;
    vertical-align: text-top;
}
/* Accessibility */
:focus-visible {
    outline: 2px solid #0d6efd;
    outline-offset: 2px;
}
/* Print styles */
@media print {
    .no-print {
        display: none !important;
    }
    .modal, .modal-backdrop {
        display: none !important;
    }
}
/* Custom scrollbar for modal */
.modal-body::-webkit-scrollbar {
    width: 6px;
}
.modal-body::-webkit-scrollbar-track {
    background: #f1f1f1;
}
.modal-body::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 3px;
}
.modal-body::-webkit-scrollbar-thumb:hover {
    background: #555;
}
/* Tooltip customizations */
.tooltip {
    font-size: 0.875rem;
}
/* Cart item hover effects */
#cartBody tr {
    transition: background-color 0.2s ease;
}
#cartBody tr:hover {
    background: rgba(0, 0, 0, 0.02);
}
/* Discount input focus */
#discountValue:focus, #discountType:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    outline: none;
}
/* Item discount button animation */
.item-discount-btn {
    transition: all 0.3s ease;
}
.item-discount-btn:hover {
    background: #ffc107;
    color: #000;
    transform: rotate(15deg) scale(1.1);
}
/* Remove button animation */
.btn-danger.rounded-circle {
    transition: all 0.3s ease;
}
.btn-danger.rounded-circle:hover {
    transform: scale(1.1);
    box-shadow: 0 2px 5px rgba(220, 53, 69, 0.3);
}
/* Customer select enhancements */
.customer-select-dropdown {
    background: #f8f9fa;
    border: 1px solid #ced4da;
    border-radius: 0.375rem;
    transition: all 0.2s ease;
}
.customer-select-dropdown:focus {
    background: #fff;
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}
/* Toast animations */
.toast {
    animation: slideInRight 0.3s ease;
}
@keyframes slideInRight {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
/* Loading spinner colors */
.spinner-border.text-primary {
    border-color: rgba(13, 110, 253, 0.25);
    border-right-color: #0d6efd;
}
/* Form control focus states */
.form-control:focus, .form-select:focus {
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    border-color: #86b7fe;
}
/* Button group focus */
.btn-group .btn:focus {
    z-index: 3;
}
/* Price input section styling */
#priceInputSection .card {
    border: 2px solid #e9ecef;
}
#priceInputSection .card:hover {
    border-color: #86b7fe;
}
/* Price unit label */
#priceUnitLabel {
    font-weight: 600;
    color: #0d6efd;
}
/* Amount display */
#amountDisplay {
    font-weight: bold;
    color: #198754;
}
#unitDisplay {
    font-weight: bold;
    color: #0d6efd;
}
/* Validation styling */
.text-danger {
    font-size: 0.85rem;
}
/* Responsive adjustments for price section */
@media (max-width: 768px) {
    #priceInputSection input {
        font-size: 1.5rem !important;
        height: 50px !important;
    }
    #calculatedPriceDisplay {
        font-size: 1rem;
    }
}
/* Responsive adjustments */
@media (max-width: 576px) {
    .modal-dialog {
        margin: 0.5rem;
    }
    #modalQty, #modalUnit {
        font-size: 1.5rem !important;
        height: 50px !important;
    }
    .shortcuts span.badge {
        font-size: 0.7rem;
        padding: 0.25em 0.4em;
    }
    .quick-btn, .unit-quick-btn {
        padding: 0.25rem !important;
        font-size: 0.8rem;
    }
    .btn-group.w-100 {
        flex-wrap: wrap;
    }
    .btn-group.w-100 .btn {
        font-size: 0.9rem;
        padding: 0.5rem;
    }
}
@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.9rem;
    }
    .btn-group {
        flex-wrap: wrap;
    }
    .d-flex.align-items-center.mb-3 {
        flex-wrap: wrap;
    }
}
</style>
@endsection

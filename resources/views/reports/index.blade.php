@extends('layouts.master')

@section('title', 'Products Management')

@section('content')
<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">
            <!-- PAGE TITLE -->
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                        <h4 class="mb-sm-0">{{ $pagetitle ?? 'Product Management' }}</h4>
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="javascript:void(0)">Ecommerce</a></li>
                                <li class="breadcrumb-item active">Products</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ANALYTICS DASHBOARD -->
            <div class="row">
                <div class="col-xl-3 col-md-6">
                    <div class="card card-animate bg-primary-subtle border-0">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <p class="text-uppercase fw-medium text-primary mb-0">Total Products</p>
                                    <h4 class="fs-22 fw-semibold mb-0">{{ number_format($analytics['total_products'] ?? 0) }}</h4>
                                </div>
                                <div class="avatar-sm flex-shrink-0">
                                    <span class="avatar-title bg-primary rounded-circle fs-3">
                                        <i class="bi bi-box-seam"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card card-animate bg-success-subtle border-0">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <p class="text-uppercase fw-medium text-success mb-0">Total Revenue</p>
                                    <h4 class="fs-22 fw-semibold mb-0">${{ number_format($analytics['total_revenue'] ?? 0, 2) }}</h4>
                                </div>
                                <div class="avatar-sm flex-shrink-0">
                                    <span class="avatar-title bg-success rounded-circle fs-3">
                                        <i class="bi bi-currency-dollar"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card card-animate bg-warning-subtle border-0">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <p class="text-uppercase fw-medium text-warning mb-0">Featured Products</p>
                                    <h4 class="fs-22 fw-semibold mb-0">{{ $analytics['featured_count'] ?? 0 }}</h4>
                                </div>
                                <div class="avatar-sm flex-shrink-0">
                                    <span class="avatar-title bg-warning rounded-circle fs-3">
                                        <i class="bi bi-star-fill text-white"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card card-animate {{ ($analytics['low_stock_count'] ?? 0) > 0 ? 'bg-danger-subtle' : 'bg-info-subtle' }} border-0">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <p class="text-uppercase fw-medium {{ ($analytics['low_stock_count'] ?? 0) > 0 ? 'text-danger' : 'text-info' }} mb-0">Low Stock Alert</p>
                                    <h4 class="fs-22 fw-semibold mb-0 {{ ($analytics['low_stock_count'] ?? 0) > 0 ? 'text-danger' : 'text-info' }}">{{ $analytics['low_stock_count'] ?? 0 }}</h4>
                                </div>
                                <div class="avatar-sm flex-shrink-0">
                                    <span class="avatar-title {{ ($analytics['low_stock_count'] ?? 0) > 0 ? 'bg-danger' : 'bg-info' }} rounded-circle fs-3">
                                        <i class="bi bi-exclamation-triangle-fill text-white"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CHARTS & TOP PRODUCTS -->
            <div class="row mt-4">
                <div class="col-xl-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Sales Overview (Last 30 Days)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="salesChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Top Selling Products</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-borderless align-middle mb-0">
                                    <tbody>
                                        @forelse($analytics['top_products'] ?? [] as $item)
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-xs me-3">
                                                            <img src="{{ $item->thumbnail ? asset('storage/'.$item->thumbnail) : asset('img/no-image.png') }}" class="rounded-circle avatar-xs">
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-0">{{ Str::limit($item->title, 30) }}</h6>
                                                            <small class="text-muted">{{ $item->sold_quantity }} sold</small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-end">
                                                    <span class="badge bg-success-subtle text-success">${{ number_format($item->total_sales ?? 0, 2) }}</span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="2" class="text-center text-muted py-4">No sales yet</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PRODUCT LIST -->
            <div id="productList" class="mt-4">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h5 class="card-title mb-0">
                                        Products <span class="badge bg-dark-subtle text-dark ms-1" id="totalProducts">{{ $products->total() }}</span>
                                    </h5>
                                </div>
                                <div class="flex-shrink-0">
                                    <div class="d-flex flex-wrap align-items-start gap-2">
                                        @can('Create product')
                                            <button type="button" class="btn btn-primary add-btn" data-bs-toggle="modal" data-bs-target="#showModal">Add Product</button>
                                        @endcan
                                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importModal">Import CSV</button>
                                        <a href="{{ route('products.export') }}" class="btn btn-info">Export CSV</a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-centered align-middle table-nowrap mb-0">
                                        <thead class="table-active">
                                            <tr>
                                                <th>Product</th>
                                                <th>Category</th>
                                                <th>Stock</th>
                                                <th>Price</th>
                                                <th>Sold</th>
                                                <th>Featured</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($products as $product)
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-sm bg-light rounded p-1 me-3">
                                                                @if($product->thumbnail)
                                                                    <img src="{{ asset('storage/' . $product->thumbnail) }}" alt="" class="img-fluid rounded" style="max-height:40px;">
                                                                @else
                                                                    <div class="bg-secondary-subtle rounded d-flex align-items-center justify-content-center" style="width:40px;height:40px;"><i class="bi bi-image text-muted fs-5"></i></div>
                                                                @endif
                                                            </div>
                                                            <div>
                                                                <h6 class="mb-1"><a href="{{ route('products.show', $product->id) }}" class="text-reset">{{ Str::limit($product->title, 50) }}</a></h6>
                                                                <p class="mb-0 text-muted small">SKU: {{ $product->sku }}</p>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>{{ $product->category?->name ?? 'Uncategorized' }}</td>
                                                    <td id="stock-{{ $product->id }}" data-current-stock="{{ $product->stock }}">
                                                        @php
                                                            $primaryUnit = $product->units->first();
                                                            $primaryShort = $primaryUnit ? $primaryUnit->short_name : '';
                                                        @endphp
                                                        @if($product->stock > 10)
                                                            <span class="badge bg-success-subtle text-success">{{ $product->stock }} {{ $primaryShort }} in stock</span>
                                                        @elseif($product->stock > 0)
                                                            <span class="badge bg-warning-subtle text-warning">{{ $product->stock }} {{ $primaryShort }} low stock</span>
                                                        @else
                                                            <span class="badge bg-danger-subtle text-danger">Out of stock</span>
                                                        @endif
                                                        @if($product->units->count() > 1)
                                                            <small class="text-muted d-block mt-1">
                                                                @foreach($product->units->skip(1) as $unit)
                                                                    {{ floor($product->stock / $unit->pivot->quantity_per_unit) }} {{ $unit->short_name }}<br>
                                                                @endforeach
                                                            </small>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($product->sale_price && $product->sale_price < $product->price)
                                                            @php $discount = round((($product->price - $product->sale_price) / $product->price) * 100); @endphp
                                                            <del class="text-muted small">${{ number_format($product->price, 2) }}</del><br>
                                                            <span class="text-danger fw-bold">${{ number_format($product->sale_price, 2) }}</span>
                                                            <span class="badge bg-danger position-relative" style="top:-8px;right:-32px;font-size:0.65rem;">-{{ $discount }}%</span>
                                                        @else
                                                            <span class="fw-bold">${{ number_format($product->price, 2) }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-center"><span class="fw-semibold">{{ $product->sold_quantity ?? 0 }}</span></td>
                                                    <td>
                                                        @if($product->is_featured)
                                                            <span class="badge bg-primary-subtle text-primary"><i class="bi bi-star-fill text-warning me-1"></i> Featured</span>
                                                        @else
                                                            <span class="badge bg-secondary-subtle text-secondary">Regular</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <div class="dropdown">
                                                            <button class="btn btn-subtle-secondary btn-sm btn-icon" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                                            <ul class="dropdown-menu dropdown-menu-end">
                                                                <li><a class="dropdown-item" href="{{ route('products.show', $product->id) }}">View</a></li>
                                                                @can('Update product')
                                                                    <li>
                                                                        <a class="dropdown-item edit-item-btn"
                                                                           href="javascript:void(0);"
                                                                           data-id="{{ $product->id }}"
                                                                           data-bs-toggle="modal"
                                                                           data-bs-target="#showModal">
                                                                            Edit
                                                                        </a>
                                                                    </li>
                                                                @endcan
                                                                @can('Delete product')
                                                                    <li>
                                                                        <a class="dropdown-item remove-item-btn text-danger"
                                                                           href="javascript:void(0);"
                                                                           data-id="{{ $product->id }}">
                                                                            Delete
                                                                        </a>
                                                                    </li>
                                                                @endcan
                                                            </ul>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="7" class="text-center py-5 text-muted">No products found</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="row mt-3 align-items-center">
                                    <div class="col-sm">
                                        <div class="text-muted text-center text-sm-start">
                                            Showing {{ $products->firstItem() }} to {{ $products->lastItem() }} of {{ $products->total() }} Results
                                        </div>
                                    </div>
                                    <div class="col-sm-auto mt-3 mt-sm-0">
                                        {!! $products->appends(request()->query())->links('pagination::bootstrap-5') !!}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ADD/EDIT MODAL -->
            <div class="modal fade" id="showModal" tabindex="-1" data-bs-backdrop="static">
                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <form id="productForm" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="id" id="product_id">
                            <div class="modal-header">
                                <h5 class="modal-title" id="modalTitle">Add Product</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                                <div class="row g-4">
                                    <div class="col-lg-8">
                                        <div class="card">
                                            <div class="card-body">
                                                <h6 class="card-title mb-3">Basic Information</h6>
                                                <div class="row g-3">
                                                    <div class="col-md-6"><label class="form-label">Title *</label><input type="text" name="title" id="title" class="form-control" required></div>
                                                    <div class="col-md-6"><label class="form-label">SKU *</label><input type="text" name="sku" id="sku" class="form-control" required></div>
                                                    <div class="col-md-4"><label class="form-label">Price *</label><div class="input-group"><span class="input-group-text">$</span><input type="number" step="0.01" name="price" id="price" class="form-control" required></div></div>
                                                    <div class="col-md-4"><label class="form-label">Discount %</label><input type="number" min="0" max="100" id="discount_percent" class="form-control"></div>
                                                    <div class="col-md-4"><label class="form-label">Sale Price</label><div class="input-group"><span class="input-group-text">$</span><input type="number" step="0.01" name="sale_price" id="sale_price" class="form-control"></div></div>
                                                    <div class="col-md-4"><label class="form-label">Stock *</label><input type="number" name="stock" id="stock" class="form-control" required min="0"></div>
                                                    <div class="col-md-6"><label class="form-label">Product Type *</label><select name="product_type" id="product_type" class="form-control" required>
                                                        <option value="simple">Simple Product</option>
                                                        <option value="variable">Variable Product</option>
                                                    </select></div>
                                                    <div class="col-12"><label class="form-label">Description</label><textarea name="description" id="description" rows="4" class="form-control"></textarea></div>
                                                </div>
                                            </div>
                                        </div>

                                        <div id="variationsSection" style="display:none;">
                                            <div class="card mt-4">
                                                <div class="card-header d-flex justify-content-between">
                                                    <h6 class="mb-0">Product Variations</h6>
                                                    <div>
                                                        <input type="number" min="0" max="100" id="bulk_discount" class="form-control form-control-sm d-inline w-auto me-2" placeholder="Bulk %">
                                                        <button type="button" class="btn btn-success btn-sm" id="applyBulkDiscount">Apply</button>
                                                    </div>
                                                </div>
                                                <div class="card-body">
                                                    <div id="attributesContainer" class="mb-4">
                                                        <div class="row g-3 align-items-end attribute-row">
                                                            <div class="col-md-5"><input type="text" class="form-control" placeholder="e.g. Color" name="attributes[0][name]"></div>
                                                            <div class="col-md-6"><input type="text" class="form-control" placeholder="Red, Blue, Green" name="attributes[0][values]"></div>
                                                            <div class="col-md-1"><button type="button" class="btn btn-danger btn-sm remove-attribute">Remove</button></div>
                                                        </div>
                                                    </div>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm mb-3" id="addAttribute">+ Add Attribute</button>
                                                    <button type="button" class="btn btn-primary btn-sm mb-3" id="generateVariations">Generate Variations</button>
                                                    <hr>
                                                    <div id="variationsTable"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-4">
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <label class="form-label">Thumbnail</label>
                                                <input type="file" name="thumbnail" id="thumbnail_input" class="form-control mb-2" accept="image/*">
                                                <div class="text-center">
                                                    <img id="thumbnail_preview" src="" class="img-fluid rounded" style="max-height:200px;display:none;">
                                                    <div id="thumbnail_placeholder" class="text-muted"><i class="bi bi-image display-4"></i><p>No image</p></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <label class="form-label">Gallery</label>
                                                <input type="file" name="images[]" id="gallery_input" multiple class="form-control mb-3" accept="image/*">
                                                <div id="imageGallery" class="row g-2"></div>
                                            </div>
                                        </div>
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Brand</label>
                                                    <select name="brand_id" id="brand_id" class="form-control">
                                                        <option value="">No Brand</option>
                                                        @foreach($brands as $brand)
                                                            <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Category</label>
                                                    <select name="category_id" id="category_id" class="form-control">
                                                        <option value="">Select Category</option>
                                                        @foreach($categories as $cat)
                                                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Primary Unit *</label>
                                                    <select name="primary_unit_id" id="primary_unit_id" class="form-control" required>
                                                        <option value="">Select Unit</option>
                                                        @foreach($units as $unit)
                                                            <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->short_name }})</option>
                                                        @endforeach
                                                    </select>
                                                    <small class="text-muted">Default unit for selling this product</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <h6 class="mb-3">Additional Units</h6>
                                                <div id="unitsContainer">
                                                    <!-- Dynamic unit rows will be added here -->
                                                </div>
                                                <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="addUnit">+ Add Unit</button>
                                            </div>
                                        </div>
                                        <div class="card">
                                            <div class="card-body">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="is_featured" id="is_featured">
                                                    <label class="form-check-label" for="is_featured">Featured Product</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <span class="spinner-border spinner-border-sm d-none me-1" id="submitSpinner"></span>
                                    Save Product
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (csrfToken) {
        axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken.getAttribute('content');
    }

    // Sales Chart
    const salesChartCtx = document.getElementById('salesChart');
    if (salesChartCtx) {
        new Chart(salesChartCtx, {
            type: 'line',
            data: {
                labels: @json($analytics['sales_chart']['labels'] ?? []),
                datasets: [{
                    label: 'Daily Sales ($)',
                    data: @json($analytics['sales_chart']['data'] ?? []),
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13,110,253,0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: { x: { grid: { display: false } }, y: { beginAtZero: true } }
            }
        });
    }

    // Toggle Variations
    const productTypeSelect = document.getElementById('product_type');
    const variationsSection = document.getElementById('variationsSection');
    function toggleVariationsSection() {
        if (productTypeSelect && variationsSection) {
            variationsSection.style.display = productTypeSelect.value === 'variable' ? 'block' : 'none';
        }
    }
    productTypeSelect?.addEventListener('change', toggleVariationsSection);
    toggleVariationsSection();

    // Price calculation
    const priceInput = document.getElementById('price');
    const discountInput = document.getElementById('discount_percent');
    const salePriceInput = document.getElementById('sale_price');
    function calculateSalePrice() {
        const price = parseFloat(priceInput?.value) || 0;
        const discount = parseFloat(discountInput?.value) || 0;
        if (price > 0 && discount > 0 && discount <= 100) {
            salePriceInput.value = (price * (1 - discount / 100)).toFixed(2);
        }
    }
    priceInput?.addEventListener('input', calculateSalePrice);
    discountInput?.addEventListener('input', calculateSalePrice);

    // Bulk discount
    document.getElementById('applyBulkDiscount')?.addEventListener('click', function () {
        const bulk = parseFloat(document.getElementById('bulk_discount').value) || 0;
        if (bulk < 0 || bulk > 100) return Swal.fire('Invalid', 'Discount 0-100', 'warning');
        document.querySelectorAll('#variationsTable tbody tr').forEach(row => {
            const priceIn = row.querySelector('input[name*="price"]');
            const saleIn = row.querySelector('input[name*="sale_price"]');
            const price = parseFloat(priceIn?.value) || 0;
            if (price > 0) saleIn.value = (price * (1 - bulk / 100)).toFixed(2);
        });
        Swal.fire('Applied!', `${bulk}% discount applied`, 'success');
    });

    // Add/Remove Attributes
    let attrIndex = 1;
    document.getElementById('addAttribute')?.addEventListener('click', () => {
        const html = `<div class="row g-3 align-items-end attribute-row mt-3">
            <div class="col-md-5"><input type="text" class="form-control" placeholder="e.g. Size" name="attributes[${attrIndex}][name]"></div>
            <div class="col-md-6"><input type="text" class="form-control" placeholder="S, M, L" name="attributes[${attrIndex}][values]"></div>
            <div class="col-md-1"><button type="button" class="btn btn-danger btn-sm remove-attribute">Remove</button></div>
        </div>`;
        document.getElementById('attributesContainer').insertAdjacentHTML('beforeend', html);
        attrIndex++;
    });

    // Add/Remove Units
    let unitIndex = 0;
    document.getElementById('addUnit')?.addEventListener('click', () => {
        const html = `
            <div class="row g-3 align-items-end unit-row mt-3">
                <div class="col-md-5">
                    <select name="units[${unitIndex}][unit_id]" class="form-control">
                        <option value="">Select Unit</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->short_name }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <input type="number" step="0.01" name="units[${unitIndex}][quantity_per_unit]" class="form-control" placeholder="Qty per primary unit">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-danger btn-sm remove-unit">Remove</button>
                </div>
            </div>`;
        document.getElementById('unitsContainer').insertAdjacentHTML('beforeend', html);
        unitIndex++;
    });

    // Remove events
    document.addEventListener('click', e => {
        if (e.target.classList.contains('remove-attribute')) e.target.closest('.attribute-row').remove();
        if (e.target.classList.contains('remove-unit')) e.target.closest('.unit-row').remove();
        if (e.target.classList.contains('remove-variation')) e.target.closest('tr').remove();
    });

    document.getElementById('generateVariations')?.addEventListener('click', () => {
        const attrs = [];
        document.querySelectorAll('.attribute-row').forEach(row => {
            const name = row.querySelector('input[name$="[name]"]')?.value.trim();
            const values = row.querySelector('input[name$="[values]"]')?.value.split(',').map(v => v.trim()).filter(v => v);
            if (name && values.length) attrs.push({ name, values });
        });

        if (attrs.length === 0) {
            document.getElementById('variationsTable').innerHTML = '<p class="text-muted">Add at least one attribute</p>';
            return;
        }

        const combinations = attrs.reduce((acc, attr) =>
            acc.flatMap(obj => attr.values.map(val => ({ ...obj, [attr.name]: val })))
        , [{}]);

        let tableHTML = `<table class="table table-bordered"><thead><tr>
            <th>Variant</th><th>SKU</th><th>Price</th><th>Sale Price</th><th>Stock</th><th>Image</th><th>Action</th>
        </tr></thead><tbody>`;

        combinations.forEach((combo, i) => {
            const variantName = Object.entries(combo).map(([k, v]) => `${k}: ${v}`).join(' | ');
            tableHTML += `<tr>
                <td><small>${variantName}</small>${Object.entries(combo).map(([k, v]) =>
                    `<input type="hidden" name="variations[${i}][attributes][${k}]" value="${v}">`).join('')}
                </td>
                <td><input type="text" name="variations[${i}][sku]" class="form-control form-control-sm" required></td>
                <td><input type="number" step="0.01" name="variations[${i}][price]" class="form-control form-control-sm" required></td>
                <td><input type="number" step="0.01" name="variations[${i}][sale_price]" class="form-control form-control-sm"></td>
                <td><input type="number" name="variations[${i}][stock]" class="form-control form-control-sm" required min="0"></td>
                <td>
                    <input type="file" name="variations[${i}][image]" class="form-control form-control-sm variation-image" accept="image/*">
                    <img class="variation-preview mt-2 img-fluid rounded" style="max-height:80px;display:none;">
                </td>
                <td><button type="button" class="btn btn-danger btn-sm remove-variation">Remove</button></td>
            </tr>`;
        });

        tableHTML += `</tbody></table>`;
        document.getElementById('variationsTable').innerHTML = tableHTML;
    });

    // Image previews (thumbnail, gallery, variation)
    document.getElementById('thumbnail_input')?.addEventListener('change', function(e) {
        if (e.target.files[0]) {
            const reader = new FileReader();
            reader.onload = ev => {
                const preview = document.getElementById('thumbnail_preview');
                const placeholder = document.getElementById('thumbnail_placeholder');
                preview.src = ev.target.result;
                preview.style.display = 'block';
                placeholder.style.display = 'none';
            };
            reader.readAsDataURL(e.target.files[0]);
        }
    });

    document.getElementById('gallery_input')?.addEventListener('change', function(e) {
        const container = document.getElementById('imageGallery');
        container.innerHTML = '';
        Array.from(e.target.files).forEach(file => {
            const reader = new FileReader();
            reader.onload = ev => {
                container.innerHTML += `
                    <div class="col-4 position-relative">
                        <img src="${ev.target.result}" class="img-fluid rounded" style="height:100px;object-fit:cover;">
                        <button type="button" class="btn-close position-absolute top-0 end-0 bg-white" onclick="this.parentElement.remove()"></button>
                    </div>`;
            };
            reader.readAsDataURL(file);
        });
    });

    document.addEventListener('change', e => {
        if (e.target.classList.contains('variation-image') && e.target.files[0]) {
            const reader = new FileReader();
            reader.onload = ev => {
                const preview = e.target.closest('td')?.querySelector('.variation-preview');
                if (preview) {
                    preview.src = ev.target.result;
                    preview.style.display = 'block';
                }
            };
            reader.readAsDataURL(e.target.files[0]);
        }
    });

    // Reset form
    window.resetForm = function () {
        document.getElementById('productForm').reset();
        document.getElementById('product_id').value = '';
        document.getElementById('modalTitle').textContent = 'Add Product';
        document.getElementById('submitBtn').innerHTML = '<span class="spinner-border spinner-border-sm d-none me-1" id="submitSpinner"></span> Save Product';
        toggleVariationsSection();
        document.getElementById('attributesContainer').innerHTML = `
            <div class="row g-3 align-items-end attribute-row">
                <div class="col-md-5"><input type="text" class="form-control" placeholder="e.g. Color" name="attributes[0][name]"></div>
                <div class="col-md-6"><input type="text" class="form-control" placeholder="Red, Blue, Green" name="attributes[0][values]"></div>
                <div class="col-md-1"><button type="button" class="btn btn-danger btn-sm remove-attribute">Remove</button></div>
            </div>`;
        document.getElementById('variationsTable').innerHTML = '';
        document.getElementById('imageGallery').innerHTML = '';
        document.getElementById('thumbnail_preview').style.display = 'none';
        document.getElementById('thumbnail_placeholder').style.display = 'block';
        document.querySelectorAll('.variation-preview').forEach(img => img.style.display = 'none');
        document.getElementById('unitsContainer').innerHTML = '';
        attrIndex = 1;
        unitIndex = 0;
    };

    // EDIT PRODUCT - Load data
    document.addEventListener('click', function(e) {
        if (e.target.matches('.edit-item-btn') || e.target.closest('.edit-item-btn')) {
            const btn = e.target.matches('.edit-item-btn') ? e.target : e.target.closest('.edit-item-btn');
            const id = btn.dataset.id || localStorage.getItem('editProductId');
            if (!id) return;
            localStorage.removeItem('editProductId');

            Swal.fire({ title: 'Loading...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            axios.get(`/products/${id}/edit`)
                .then(response => {
                    const p = response.data;

                    document.getElementById('product_id').value = p.id;
                    document.getElementById('title').value = p.title || '';
                    document.getElementById('sku').value = p.sku || '';
                    document.getElementById('price').value = p.price || '';
                    document.getElementById('sale_price').value = p.sale_price || '';
                    document.getElementById('stock').value = p.stock || 0;
                    document.getElementById('description').value = p.description || '';
                    document.getElementById('product_type').value = p.product_type || 'simple';
                    document.getElementById('brand_id').value = p.brand_id || '';
                    document.getElementById('category_id').value = p.category_id || '';
                    document.getElementById('primary_unit_id').value = p.primary_unit_id || '';
                    document.getElementById('is_featured').checked = !!p.is_featured;

                    // Thumbnail
                    const thumbPrev = document.getElementById('thumbnail_preview');
                    const thumbPlace = document.getElementById('thumbnail_placeholder');
                    if (p.thumbnail) {
                        thumbPrev.src = p.thumbnail;
                        thumbPrev.style.display = 'block';
                        thumbPlace.style.display = 'none';
                    } else {
                        thumbPrev.style.display = 'none';
                        thumbPlace.style.display = 'block';
                    }

                    // Gallery
                    const gallery = document.getElementById('imageGallery');
                    gallery.innerHTML = '';
                    if (p.gallery?.length) {
                        p.gallery.forEach(img => {
                            gallery.innerHTML += `
                                <div class="col-4 position-relative">
                                    <img src="${img.url}" class="img-fluid rounded" style="height:100px;object-fit:cover;">
                                    <button type="button" class="btn-close position-absolute top-0 end-0 bg-white" onclick="this.parentElement.remove()"></button>
                                </div>`;
                        });
                    }

                    // Additional Units
                    if (p.additional_units?.length) {
                        p.additional_units.forEach(u => {
                            document.getElementById('addUnit').click();
                            const rows = document.querySelectorAll('.unit-row');
                            const row = rows[rows.length - 1];
                            row.querySelector('select[name*="unit_id"]').value = u.unit_id;
                            row.querySelector('input[name*="quantity_per_unit"]').value = u.quantity_per_unit;
                        });
                    }

                    // Variations
                    toggleVariationsSection();
                    if (p.product_type === 'variable' && p.attributes?.length && p.variations?.length) {
                        document.getElementById('attributesContainer').innerHTML = `
                            <div class="row g-3 align-items-end attribute-row">
                                <div class="col-md-5"><input type="text" class="form-control" placeholder="e.g. Color" name="attributes[0][name]"></div>
                                <div class="col-md-6"><input type="text" class="form-control" placeholder="Red, Blue, Green" name="attributes[0][values]"></div>
                                <div class="col-md-1"><button type="button" class="btn btn-danger btn-sm remove-attribute">Remove</button></div>
                            </div>`;

                        attrIndex = 0;
                        p.attributes.forEach(attr => {
                            if (attrIndex > 0) document.getElementById('addAttribute').click();
                            const row = document.querySelectorAll('.attribute-row')[attrIndex];
                            row.querySelector('input[name$="[name]"]').value = attr.name;
                            row.querySelector('input[name$="[values]"]').value = attr.values.join(', ');
                            attrIndex++;
                        });

                        setTimeout(() => {
                            document.getElementById('generateVariations').click();
                            setTimeout(() => {
                                p.variations.forEach((v, i) => {
                                    const row = document.querySelectorAll('#variationsTable tbody tr')[i];
                                    if (row) {
                                        row.querySelector('input[name*="sku"]').value = v.sku || '';
                                        row.querySelector('input[name*="price"]').value = v.price || '';
                                        row.querySelector('input[name*="sale_price"]').value = v.sale_price || '';
                                        row.querySelector('input[name*="stock"]').value = v.stock || 0;

                                        if (v.image) {
                                            const preview = row.querySelector('.variation-preview');
                                            preview.src = v.image;
                                            preview.style.display = 'block';
                                        }

                                        if (v.attributes) {
                                            Object.entries(v.attributes).forEach(([key, val]) => {
                                                const hidden = row.querySelector(`input[name="variations[${i}][attributes][${key}]"]`);
                                                if (hidden) hidden.value = val;
                                            });
                                        }
                                    }
                                });
                            }, 100);
                        }, 100);
                    }

                    document.getElementById('modalTitle').textContent = 'Edit Product';
                    const modal = new bootstrap.Modal(document.getElementById('showModal'));
                    modal.show();
                    Swal.close();
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire('Error', 'Failed to load product', 'error');
                });
        }
    });

    // DELETE PRODUCT
    document.addEventListener('click', function(e) {
        if (e.target.matches('.remove-item-btn') || e.target.closest('.remove-item-btn')) {
            const btn = e.target.matches('.remove-item-btn') ? e.target : e.target.closest('.remove-item-btn');
            const id = btn.dataset.id;

            Swal.fire({
                title: 'Delete Product?',
                text: "This action cannot be undone!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Yes, delete!'
            }).then(result => {
                if (result.isConfirmed) {
                    axios.delete(`/products/${id}`)
                        .then(res => {
                            Swal.fire('Deleted!', res.data.message || 'Product deleted', 'success')
                                .then(() => location.reload());
                        })
                        .catch(() => Swal.fire('Error!', 'Failed to delete', 'error'));
                }
            });
        }
    });

    // FORM SUBMIT
    document.getElementById('productForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const id = document.getElementById('product_id').value;
        if (id) formData.append('_method', 'PUT');

        const url = id ? `/products/${id}` : '/products';
        const btn = document.getElementById('submitBtn');
        const spinner = document.getElementById('submitSpinner');
        btn.disabled = true;
        spinner.classList.remove('d-none');

        axios.post(url, formData, { headers: { 'Content-Type': 'multipart/form-data' } })
            .then(res => {
                Swal.fire('Success!', res.data.message || 'Saved!', 'success')
                    .then(() => location.reload());
            })
            .catch(err => {
                let msg = 'Error occurred';
                if (err.response?.data?.errors) {
                    msg = Object.values(err.response.data.errors).flat().join('<br>');
                } else if (err.response?.data?.message) {
                    msg = err.response.data.message;
                }
                Swal.fire('Error', msg, 'error');
            })
            .finally(() => {
                btn.disabled = false;
                spinner.classList.add('d-none');
            });
    });
});
</script>
@endsection

<!doctype html>
<html lang="en" data-layout="vertical" data-sidebar="dark" data-sidebar-size="lg" data-preloader="disable" data-theme="default" data-topbar="light" data-bs-theme="light">

<head>

    <meta charset="utf-8">
   <!-- Dynamic Title: Page Title | Supermarket Name -->
    @php
        $store = \App\Models\StoreSetting::getSettings();
        $pageTitle = $pagetitle ?? 'Dashboard';
        $storeName = $store?->store_name ?? 'Supermarket';
    @endphp
    <title>{{ $pageTitle }} | {{ $storeName }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="school management software" name="description">
    <meta content="" name="author">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- App favicon -->
   <head>
    <!-- Other meta tags, title, css, etc. -->

    <!-- Dynamic Favicon -->
    @if($store?->logo)
        <link rel="shortcut icon" href="{{ $store->getLogoUrlAttribute() }}" type="image/png">
        <link rel="icon" href="{{ $store->getLogoUrlAttribute() }}" type="image/png">
        <link rel="apple-touch-icon" href="{{ $store->getLogoUrlAttribute() }}">
    @else
        <link rel="shortcut icon" href="{{ asset('theme/layouts/assets/images/favicon.ico') }}">
        <link rel="icon" href="{{ asset('theme/layouts/assets/images/favicon.ico') }}">
        <link rel="apple-touch-icon" href="{{ asset('theme/layouts/assets/images/favicon.ico') }}">
    @endif

    <!-- Rest of head -->
</head>

    <!-- Fonts css load -->
    <link id="fontsLink" href="{{ asset('vendor/fonts/fonts.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/choices/choices.min.css') }}" rel="stylesheet">
    <link href="{{ asset('theme/layouts/assets/fonts/materialdesignicons-webfont.woff2') }}?v=6.5.95" rel="stylesheet" type="font/woff2">
    <style>
        .pagination-wrap .page-item { margin: 0 5px; }
        .pagination-wrap .page-link { padding: 5px 10px; }
        .pagination-wrap .active .page-link { background-color: #007bff; color: white; }
        .pagination-wrap .disabled .page-link { pointer-events: none; opacity: 0.5; }
    </style>
    <link rel="stylesheet" href="{{ asset('vendor/flatpickr/flatpickr.min.css') }}">

    <!-- CSS includes for different routes -->
    @if (Route::is('dashboard'))
        @include('layouts.pages-assets.css.users-list-css')
    @endif

    @if (Route::is('users.*'))
        @include('layouts.pages-assets.css.users-list-css')
    @endif

     @if (Route::is('user.*'))
        @include('layouts.pages-assets.css.users-list-css')
    @endif

    @if (Route::is('roles.*'))
        @include('layouts.pages-assets.css.roles-list-css')
    @endif

    @if (Route::is('permissions.*'))
        @include('layouts.pages-assets.css.permission-list-css')
    @endif

    @if (Route::is('brands.*'))
        @include('layouts.pages-assets.css.users-list-css')
    @endif

    @if (Route::is('categories.*'))
        @include('layouts.pages-assets.css.users-list-css')
    @endif

    @if (Route::is('banners.*'))
        @include('layouts.pages-assets.css.users-list-css')
    @endif

    @if (Route::is('products.*'))
        @include('layouts.pages-assets.css.users-list-css')
    @endif

    @if (Route::is('pos.*') || Route::is('pricing.*') || Route::is('adjustments.*') || Route::is('security.*') || Route::is('system-config.*') || Route::is('report-emails.*') || Route::is('audit.*') || Route::is('pos.transactions*') || Route::is('pos.shifts.*') || Route::is('mb.*') || Route::is('catalogue.*') || Route::is('pos.payments.*') || Route::is('pos.registers.*') || Route::is('transfers.*') || Route::is('counts.*') || Route::is('suppliers.*') || Route::is('purchase-orders.*') || Route::is('goods-receipts.*') || Route::is('supplier-returns.*'))
        @include('layouts.pages-assets.css.users-list-css')
    @endif


    @if (Route::is('inventory.*') || Route::is('stock-locations.*'))
         @include('layouts.pages-assets.css.users-list-css')
    @endif

     @if (Route::is('orders.*'))
         @include('layouts.pages-assets.css.users-list-css')
    @endif

    @if (Route::is('customers.*'))
         @include('layouts.pages-assets.css.users-list-css')
    @endif

   @if (Route::is('settings.store.*'))
         @include('layouts.pages-assets.css.users-list-css')
    @endif

     @if (Route::is('sales.*'))
         @include('layouts.pages-assets.css.users-list-css')
    @endif

    @if (Route::is('salesperson.*'))
         @include('layouts.pages-assets.css.users-list-css')
    @endif
    <style>
        /* Upgraded modules */
        .min-w-0 { min-width: 0; }
        .vm-stat .vm-stat-value { font-size: 1.35rem; font-weight: 600; line-height: 1.2; }
        .vm-stat .vm-stat-label { font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: var(--tb-secondary-color, #6c757d); }
        .vm-stat .vm-stat-sub { font-size: .8rem; }
        .vm-table td, .vm-table th { vertical-align: middle; }
        .vm-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .85em; }
        .vm-sticky-actions { position: sticky; bottom: 0; z-index: 5; background: var(--tb-secondary-bg, #fff); border-top: 1px solid var(--tb-border-color, #e9ebec); }
        .vm-diff-old { background: rgba(240, 101, 72, .12); text-decoration: line-through; }
        .vm-diff-new { background: rgba(10, 179, 156, .14); }
        .vm-chip { display: inline-flex; align-items: center; gap: .25rem; padding: .15rem .55rem; border-radius: 1rem; background: var(--tb-light, #f3f6f9); font-size: .8rem; }
        .vm-bulkbar { display: none; }
        .vm-bulkbar.show { display: flex; }
        .table-hover tbody tr.vm-selected { background: rgba(64, 81, 137, .06); }
    </style>

    @include('layouts.partials.cb-shell-head')
</head>

<body class="cb-ui">

    <!-- Begin page -->
    <div id="layout-wrapper">

        <!-- ========== App Menu ========== -->
        <div class="app-menu navbar-menu">
            <!-- LOGO -->
            <div class="navbar-brand-box">
               <!-- Replace the old static logo with this dynamic one -->
<a href="{{ route('dashboard') }}" class="logo logo-dark">
    <span class="logo-sm">
        @if($store?->logo)
            <img src="{{ $store->getLogoUrlAttribute() }}" alt="{{ $storeName }} Logo" height="22">
        @else
            <img src="{{ asset('theme/layouts/assets/images/logo-sm.png') }}" alt="Default Logo" height="22">
        @endif
    </span>
    <span class="logo-lg">
        @if($store?->logo)
            <img src="{{ $store->getLogoUrlAttribute() }}" alt="{{ $storeName }} Logo" height="60">
        @else
            <img src="{{ asset('theme/layouts/assets/images/logo-dark.png') }}" alt="Default Logo" height="60">
        @endif
    </span>
</a>

<a href="{{ route('dashboard') }}" class="logo logo-light">
    <span class="logo-sm">
        @if($store?->logo)
            <img src="{{ $store->getLogoUrlAttribute() }}" alt="{{ $storeName }} Logo" height="22">
        @else
            <img src="{{ asset('theme/layouts/assets/images/logo-sm.png') }}" alt="Default Logo" height="60">
        @endif
    </span>
    <span class="logo-lg">
        @if($store?->logo)
            <img src="{{ $store->getLogoUrlAttribute() }}" alt="{{ $storeName }} Logo" height="60">
        @else
            <img src="{{ asset('theme/layouts/assets/images/logo-light.png') }}" alt="Default Logo" height="60">
        @endif
    </span>
</a>
                <button type="button" class="btn btn-sm p-0 fs-3xl header-item float-end btn-vertical-sm-hover" id="vertical-hover">
                    <i class="ri-record-circle-line"></i>
                </button>
            </div>

            <div id="scrollbar">
                <div class="container-fluid">

                    <div id="two-column-menu">
                    </div>
                    <ul class="navbar-nav" id="navbar-nav">

                        <li class="menu-title"><span data-key="t-menu">Menu</span></li>
                        <li class="nav-item">
                            <a class="nav-link menu-link collapsed " href="#sidebarDashboards" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarDashboards">
                                <i class="ph-gauge"></i> <span data-key="t-dashboards">Dashboards</span>
                            </a>
                            <div class="collapse menu-dropdown" id="sidebarDashboards">
                                <ul class="nav nav-sm flex-column">
                                    @can('dashboard')
                                        <li class="nav-item">
                                            <a href="{{ route('dashboard') }}" class="nav-link" data-key="t-analytics"> Administration Analytics </a>
                                        </li>
                                    @endcan
                                    @can('finance dashboard')
                                        <li class="nav-item">
                                            <a href="dashboard-crm.html" class="nav-link" data-key="t-crm"> Finance Analytics</a>
                                        </li>
                                    @endcan
                                    @can('academics dashboard')
                                        <li class="nav-item">
                                            <a href="index.html" class="nav-link" data-key="t-ecommerce"> Academics Analytics </a>
                                        </li>
                                    @endcan

                                    <!-- Inventory Dashboard -->
                                    @can('View inventory dashboard|View inventory')
                                        <li class="nav-item">
                                            <a href="{{ route('inventory.dashboard') }}" class="nav-link" data-key="t-inventory"> Inventory Dashboard </a>
                                        </li>
                                    @endcan
                                </ul>
                            </div>
                        </li>


                        <li class="menu-title"><i class="ri-more-fill"></i> <span data-key="t-pages">USERS & PRIVILEDGES</span></li>

                        @can('View user')
                            <li class="nav-item">
                                <a class="nav-link menu-link collapsed" href="#sidebarusers" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarAuth">
                                    <i class="ph-user-circle"></i> <span data-key="t-authentication">User Managements</span>
                                </a>
                                <div class="collapse menu-dropdown" id="sidebarusers">
                                    <ul class="nav nav-sm flex-column">
                                        <li class="nav-item">
                                            <a href="{{ route('users.index') }}" class="nav-link" role="button" data-key="t-signin"> Users </a>
                                        </li>
                                    </ul>
                                </div>
                            </li>
                        @endcan

                        @can('View role')
                            <li class="nav-item">
                                <a class="nav-link menu-link collapsed" href="#sidebarroles" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarPages">
                                    <i class="ph-address-book"></i> <span data-key="t-pages">Roles And Permissions</span>
                                </a>
                                <div class="collapse menu-dropdown" id="sidebarroles">
                                    <ul class="nav nav-sm flex-column">
                                        @can('View role')
                                            <li class="nav-item">
                                                <a href="{{ route('roles.index') }}" class="nav-link" data-key="t-starter"> Roles </a>
                                            </li>
                                        @endcan
                                        @can('View permission')
                                            <li class="nav-item">
                                                <a href="{{ route('permissions.index') }}" class="nav-link" data-key="t-profile"> Permissions </a>
                                            </li>
                                        @endcan
                                    </ul>
                                </div>
                            </li>
                        @endcan


                        <li class="nav-item">
                            <a class="nav-link menu-link collapsed" href="#sidebaraccount" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebaraccoun">
                                <i class="ph-address-book"></i> <span data-key="t-pages">User Account</span>
                            </a>
                            <div class="collapse menu-dropdown" id="sidebaraccount">
                                <ul class="nav nav-sm flex-column">
                                    <li class="nav-item">
                                        <a href="{{ auth()->user() ? route('user.overview', auth()->id()) : '#' }}" class="nav-link" data-key="t-starter"> My Account </a>
                                    </li>
                                </ul>
                            </div>
                        </li>

                        <li class="menu-title"><i class="ri-more-fill"></i> <span data-key="t-pages">INVENTORY MANAGEMENT</span></li>

                        @can('View banner')
                            <li class="nav-item">
                                <a class="nav-link menu-link collapsed" href="#sidebarbanner" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarbanner">
                                    <i class="ph-user-circle"></i> <span data-key="t-authentication">Banner Managements</span>
                                </a>
                                <div class="collapse menu-dropdown" id="sidebarbanner">
                                    <ul class="nav nav-sm flex-column">
                                        <li class="nav-item">
                                            <a href="{{ route('banners.index') }}" class="nav-link" role="button" data-key="t-signin"> Banner </a>
                                        </li>
                                    </ul>
                                </div>
                            </li>
                        @endcan

                        @can('View category')
                            <li class="nav-item">
                                <a class="nav-link menu-link collapsed" href="#sidebarcategories" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarcategories">
                                    <i class="ph-user-circle"></i> <span data-key="t-authentication">Category Managements</span>
                                </a>
                                <div class="collapse menu-dropdown" id="sidebarcategories">
                                    <ul class="nav nav-sm flex-column">
                                        <li class="nav-item">
                                            <a href="{{ route('categories.index') }}" class="nav-link" role="button" data-key="t-signin"> Category </a>
                                        </li>
                                    </ul>
                                </div>
                            </li>
                        @endcan

                        @can('View brand')
                            <li class="nav-item">
                                <a class="nav-link menu-link collapsed" href="#sidebarbrand" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarbrand">
                                    <i class="ph-user-circle"></i> <span data-key="t-authentication">Brand Managements</span>
                                </a>
                                <div class="collapse menu-dropdown" id="sidebarbrand">
                                    <ul class="nav nav-sm flex-column">
                                        <li class="nav-item">
                                            <a href="{{ route('brands.index') }}" class="nav-link" role="button" data-key="t-signin"> Brand </a>
                                        </li>
                                    </ul>
                                </div>
                            </li>
                        @endcan

                        @can('View product')
                            <li class="nav-item">
                                <a class="nav-link menu-link collapsed" href="#sidebarproduct" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarproduct">
                                    <i class="ph-package"></i> <span data-key="t-authentication">Products & Pricing</span>
                                </a>
                                <div class="collapse menu-dropdown" id="sidebarproduct">
                                    <ul class="nav nav-sm flex-column">
                                        <li class="nav-item"><a href="{{ route('products.index') }}" class="nav-link"> All Products </a></li>
                                        @can('Create product')
                                            <li class="nav-item"><a href="{{ route('products.create') }}" class="nav-link"> Add Product </a></li>
                                        @endcan
                                        @canany(['Manage price control', 'View price history'])
                                            <li class="nav-item"><a href="{{ route('pricing.index') }}" class="nav-link"> Price Control </a></li>
                                            <li class="nav-item"><a href="{{ route('pricing.history') }}" class="nav-link"> Price History </a></li>
                                        @endcanany
                                    </ul>
                                </div>
                            </li>
                        @endcan
                        @can('View pos')
                            <li class="nav-item">
                                <a class="nav-link menu-link collapsed" href="#sidebarpos" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarpos">
                                    <i class="ph-shopping-cart"></i> <span data-key="t-authentication">Point of Sale</span>
                                </a>
                                <div class="collapse menu-dropdown" id="sidebarpos">
                                    <ul class="nav nav-sm flex-column">
                                        <li class="nav-item"><a href="{{ route('pos.index') }}" class="nav-link"> POS Terminal </a></li>
                                        <li class="nav-item"><a href="{{ route('pos.transactions') }}" class="nav-link"> Sales Transactions </a></li>
                                        @canany(['View all pos shifts', 'Manage pos shifts'])
                                            <li class="nav-item"><a href="{{ route('pos.shifts.index') }}" class="nav-link"> Cash Drawer Shifts </a></li>
                                        @endcanany
                                        @can('Confirm payments')
                                            @php $__pendPay = \Illuminate\Support\Facades\Schema::hasColumn('order_payments', 'status') ? \App\Models\OrderPayment::where('status', 'pending')->count() : 0; @endphp
                                            <li class="nav-item"><a href="{{ route('pos.payments.index') }}" class="nav-link"> Payments to confirm @if ($__pendPay)<span class="badge bg-warning ms-1">{{ $__pendPay }}</span>@endif</a></li>
                                        @endcan
                                        @can('Manage cash registers')
                                            <li class="nav-item"><a href="{{ route('pos.registers.index') }}" class="nav-link"> Tills </a></li>
                                        @endcan
                                    </ul>
                                </div>
                            </li>
                        @endcan
                        <!-- =========================================== -->
                        <!-- INVENTORY MANAGEMENT MENU -->
                        <!-- =========================================== -->

                        @can('View inventory')
                            <li class="nav-item">
                                <a class="nav-link menu-link collapsed" href="#sidebarmanageinventory" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarmanageinventory">
                                    <i class="ph-user-circle"></i> <span data-key="t-authentication">Inventory Management</span>
                                </a>
                                <div class="collapse menu-dropdown" id="sidebarmanageinventory">
                                    <ul class="nav nav-sm flex-column">
                                        @can('View inventory')
                                            <li class="nav-item">
                                                <a href="{{ route('inventory.index') }}" class="nav-link" role="button" data-key="t-signin"> Stock Movements </a>
                                            </li>
                                        @endcan
                                        @canany(['View stock adjustment', 'Create stock adjustment', 'Approve stock adjustment', 'Adjust stock'])
                                            @php $__pendingAdj = \Illuminate\Support\Facades\Schema::hasTable('stock_adjustments') ? \App\Models\StockAdjustment::where('status', 'pending')->count() : 0; @endphp
                                            <li class="nav-item">
                                                <a href="{{ route('adjustments.index') }}" class="nav-link"> Stock Adjustments
                                                    @if($__pendingAdj)<span class="badge bg-warning ms-1">{{ $__pendingAdj }}</span>@endif
                                                </a>
                                            </li>
                                            <li class="nav-item"><a href="{{ route('adjustments.create', ['type' => 'receive']) }}" class="nav-link"> Receive Goods </a></li>
                                            <li class="nav-item"><a href="{{ route('adjustments.create', ['type' => 'count']) }}" class="nav-link"> Stock Count </a></li>
                                        @endcanany

                                        @can('View stock levels')
                                            <li class="nav-item">
                                                <a href="{{ route('inventory.stock-levels') }}" class="nav-link" role="button" data-key="t-signin"> Stock Levels </a>
                                            </li>
                                        @endcan

                                        @can('Manage stock locations')
                                            <li class="nav-item">
                                                <a href="{{ route('stock-locations.index') }}" class="nav-link" role="button" data-key="t-signin"> Stock Locations </a>
                                            </li>
                                        @endcan

                                        @can('View inventory reports')
                                            <li class="nav-item">
                                                <a href="{{ route('inventory.stock-value-report') }}" class="nav-link" role="button" data-key="t-signin"> Stock Value Report </a>
                                            </li>
                                        @endcan

                                        @can('View low stock alerts')
                                            <li class="nav-item">
                                                <a href="{{ route('inventory.low-stock-alerts') }}" class="nav-link" role="button" data-key="t-signin"> Low Stock Alerts </a>
                                            </li>
                                        @endcan

                                         @can('View sale')
                                            <li class="nav-item">
                                                <a href="{{ route('sales.index') }}" class="nav-link" role="button" data-key="t-signin"> Sales Management </a>
                                            </li>
                                        @endcan

                                        @can('View personal sales dashboard')
                                            <li class="nav-item">
                                                <a href="{{ route('salesperson.dashboard') }}" class="nav-link" role="button" data-key="t-signin"> Sales Person Management </a>
                                            </li>
                                        @endcan
                                    </ul>
                                </div>
                            </li>
                        @endcan

                        <!-- =========================================== -->
                        <!-- PRODUCT REVIEWS MENU -->
                        <!-- =========================================== -->

                        @can('View review')
                            {{-- <li class="nav-item">
                                <a class="nav-link menu-link collapsed" href="#sidebarreviews" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarreviews">
                                    <i class="ph-star"></i> <span data-key="t-authentication">Reviews Management</span>
                                </a>
                                <div class="collapse menu-dropdown" id="sidebarreviews">
                                    <ul class="nav nav-sm flex-column">
                                        <li class="nav-item">
                                            <a href="{{ route('reviews.index') }}" class="nav-link" role="button" data-key="t-signin"> Product Reviews </a>
                                        </li>
                                    </ul>
                                </div>
                            </li> --}}
                        @endcan

                         @can('View order')
                            <li class="nav-item">
                                <a class="nav-link menu-link collapsed" href="#sidebarorders" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarorders">
                                    <i class="ph-star"></i> <span data-key="t-authentication">Orders Management</span>
                                </a>
                                <div class="collapse menu-dropdown" id="sidebarorders">
                                    <ul class="nav nav-sm flex-column">
                                        <li class="nav-item">
                                            <a href="{{ route('orders.index') }}" class="nav-link" role="button" data-key="t-signin"> Orders </a>
                                        </li>
                                    </ul>
                                </div>
                            </li>
                        @endcan

                        @can('View customer')
                            <li class="nav-item">
                                <a class="nav-link menu-link collapsed" href="#sidebarcustomer" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarcustomer">
                                    <i class="ph-star"></i> <span data-key="t-authentication">Customers Management</span>
                                </a>
                                <div class="collapse menu-dropdown" id="sidebarcustomer">
                                    <ul class="nav nav-sm flex-column">
                                        <li class="nav-item">
                                            <a href="{{ route('customers.index') }}" class="nav-link" role="button" data-key="t-signin"> Customers </a>
                                        </li>
                                    </ul>
                                </div>
                            </li>
                        @endcan

                        @canany(['Request stock transfer', 'Approve stock transfer', 'Dispatch stock transfer', 'Receive stock transfer', 'Allocate stock', 'Count stock', 'Create stock adjustment', 'View purchasing', 'Create purchase order', 'Post goods receipt', 'Manage suppliers'])
                            <li class="menu-title"><span data-key="t-menu">Stock &amp; buying</span></li>
                            @canany(['Request stock transfer', 'Approve stock transfer', 'Dispatch stock transfer', 'Receive stock transfer', 'Allocate stock'])
                                @php
                                    $__trf = \Illuminate\Support\Facades\Schema::hasTable('stock_transfers') && \App\Services\MultiBranch\Install::branchId()
                                        ? \App\Models\StockTransfer::where(fn ($q) => $q->where(fn ($w) => $w->where('to_branch_id', \App\Services\MultiBranch\Install::branchId())->where('status', 'dispatched'))
                                            ->orWhere(fn ($w) => $w->where('from_branch_id', \App\Services\MultiBranch\Install::branchId())->whereIn('status', ['requested', 'approved'])))->count() : 0;
                                @endphp
                                <li class="nav-item"><a class="nav-link menu-link" href="{{ route('transfers.index') }}"><i class="ph-truck"></i> <span>Transfers</span>@if ($__trf)<span class="badge bg-warning ms-auto">{{ $__trf }}</span>@endif</a></li>
                            @endcanany
                            @canany(['Count stock', 'Create stock adjustment', 'Approve stock adjustment'])
                                <li class="nav-item"><a class="nav-link menu-link" href="{{ route('counts.index') }}"><i class="ph-list-checks"></i> <span>Stock counts</span></a></li>
                            @endcanany
                            @canany(['View purchasing', 'Create purchase order', 'Approve purchase order', 'Post goods receipt', 'Manage suppliers', 'Return to supplier'])
                                <li class="nav-item">
                                    <a class="nav-link menu-link" href="#sidebarBuying" data-bs-toggle="collapse" role="button" aria-expanded="{{ request()->routeIs('purchase-orders.*', 'goods-receipts.*', 'supplier-returns.*', 'suppliers.*') ? 'true' : 'false' }}">
                                        <i class="ph-shopping-bag"></i> <span>Purchasing</span>
                                    </a>
                                    <div class="collapse menu-dropdown {{ request()->routeIs('purchase-orders.*', 'goods-receipts.*', 'supplier-returns.*', 'suppliers.*') ? 'show' : '' }}" id="sidebarBuying">
                                        <ul class="nav nav-sm flex-column">
                                            <li class="nav-item"><a href="{{ route('purchase-orders.index') }}" class="nav-link">Purchase orders</a></li>
                                            <li class="nav-item"><a href="{{ route('goods-receipts.index') }}" class="nav-link">Goods received</a></li>
                                            <li class="nav-item"><a href="{{ route('supplier-returns.index') }}" class="nav-link">Returns to suppliers</a></li>
                                            <li class="nav-item"><a href="{{ route('suppliers.index') }}" class="nav-link">Suppliers</a></li>
                                        </ul>
                                    </div>
                                </li>
                            @endcanany
                        @endcanany

                        @canany(['View branches', 'Manage branches', 'View sync status', 'Manage sync'])
                            <li class="menu-title"><span data-key="t-menu">Branches</span></li>
                            @if (\App\Services\MultiBranch\Install::isCentral())
                                <li class="nav-item">
                                    <a class="nav-link menu-link {{ request()->routeIs('mb.hq-dashboard*') ? 'active' : '' }}" href="{{ route('mb.hq-dashboard') }}">
                                        <i class="ph-chart-bar"></i> <span>All branches</span>
                                    </a>
                                </li>
                            @endif
                            <li class="nav-item">
                                <a class="nav-link menu-link" href="{{ route('mb.branches.index') }}">
                                    <i class="ph-storefront"></i> <span>Branches</span>
                                    @php $__mode = \App\Services\MultiBranch\Install::mode(); @endphp
                                    <span class="badge bg-{{ $__mode === 'central' ? 'primary' : ($__mode === 'branch' ? 'info' : 'secondary') }} ms-auto">{{ $__mode === 'central' ? 'HQ' : ($__mode === 'branch' ? \App\Services\MultiBranch\Install::branchCode() : 'single') }}</span>
                                </a>
                            </li>
                            @if ($__mode !== 'standalone')
                                @canany(['View sync status', 'Manage sync'])
                                    @php
                                        $__syncWarn = $__mode === 'branch' && \Illuminate\Support\Facades\Schema::hasTable('sync_outbox')
                                            ? \Illuminate\Support\Facades\DB::table('sync_outbox')->where(fn ($q) => $q->where('status', 'dead')->orWhere('attempts', '>', 0))->where('status', '!=', 'sent')->count() : 0;
                                    @endphp
                                    <li class="nav-item">
                                        <a class="nav-link menu-link" href="{{ route('mb.sync.index') }}">
                                            <i class="ph-arrows-clockwise"></i> <span>Sync</span>
                                            @if ($__syncWarn)<span class="badge bg-danger ms-auto">{{ $__syncWarn }}</span>@endif
                                        </a>
                                    </li>
                                @endcanany
                            @endif
                        @endcanany
                        @if (!\App\Services\MultiBranch\Install::isStandalone())
                            @canany(['Approve price change', 'Approve product submission', 'Request price change', 'Manage price lists', 'Manage price control', 'View price history', 'Manage field policies'])
                                @php
                                    $__reqCount = \Illuminate\Support\Facades\Schema::hasTable('price_change_requests')
                                        ? \App\Models\PriceChangeRequest::where('status', 'pending')->count() + \App\Models\ProductSubmission::where('status', 'pending')->count() : 0;
                                @endphp
                                <li class="nav-item">
                                    <a class="nav-link menu-link" href="#sidebarCatalogueMb" data-bs-toggle="collapse" role="button" aria-expanded="{{ request()->routeIs('catalogue.*') ? 'true' : 'false' }}">
                                        <i class="ph-books"></i> <span>Catalogue</span>
                                        @if ($__reqCount)<span class="badge bg-warning ms-auto">{{ $__reqCount }}</span>@endif
                                    </a>
                                    <div class="collapse menu-dropdown {{ request()->routeIs('catalogue.*') ? 'show' : '' }}" id="sidebarCatalogueMb">
                                        <ul class="nav nav-sm flex-column">
                                            <li class="nav-item"><a href="{{ route('catalogue.requests') }}" class="nav-link">{{ \App\Services\MultiBranch\Install::isCentral() ? 'Requests from branches' : 'Requests to head office' }}</a></li>
                                            @canany(['Manage price lists', 'Manage price control', 'View price history', 'Manage field policies'])
                                                <li class="nav-item"><a href="{{ route('catalogue.price-lists') }}" class="nav-link">Branch prices</a></li>
                                                <li class="nav-item"><a href="{{ route('catalogue.policies') }}" class="nav-link">What branches may change</a></li>
                                            @endcanany
                                        </ul>
                                    </div>
                                </li>
                            @endcanany
                        @endif
                        @canany(['Manage login control', 'View active sessions', 'Force logout users', 'View audit trail'])
                            <li class="menu-title"><span data-key="t-menu">Security</span></li>
                            @canany(['Manage login control', 'View active sessions', 'Force logout users'])
                                <li class="nav-item">
                                    <a class="nav-link menu-link" href="{{ route('security.index') }}">
                                        <i class="ph-lock-key"></i> <span>Login Control</span>
                                    </a>
                                </li>
                            @endcanany
                            @can('View audit trail')
                                <li class="nav-item">
                                    <a class="nav-link menu-link" href="{{ route('audit.index') }}">
                                        <i class="ph-clipboard-text"></i> <span>Audit Trail</span>
                                    </a>
                                </li>
                            @endcan
                        @endcanany
                        <li class="menu-title"><span data-key="t-menu">Settings</span></li>
                        @can('View store setting')
                            <li class="nav-item">
                                <a class="nav-link menu-link collapsed" href="#sidebarsetting" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarsetting">
                                    <i class="ph-star"></i> <span data-key="t-authentication">Store Settings Management</span>
                                </a>
                                <div class="collapse menu-dropdown" id="sidebarsetting">
                                    <ul class="nav nav-sm flex-column">
                                        <li class="nav-item">
                                            <a href="{{ route('settings.store.index') }}" class="nav-link" role="button" data-key="t-signin"> Store Settings </a>
                                        </li>
                                    </ul>
                                </div>
                            </li>
                        @endcan
                        @can('Manage report emails')
                            <li class="nav-item">
                                <a class="nav-link menu-link {{ request()->routeIs('report-emails.*') ? 'active' : '' }}" href="{{ route('report-emails.index') }}">
                                    <i class="ph-envelope-simple"></i> <span>Sales Report Emails</span>
                                </a>
                            </li>
                        @endcan
                        @can('Manage system configuration')
                            <li class="nav-item">
                                <a class="nav-link menu-link {{ request()->routeIs('system-config.*') ? 'active' : '' }}" href="{{ route('system-config.index') }}">
                                    <i class="ph-sliders-horizontal"></i> <span>System Configuration</span>
                                </a>
                            </li>
                        @endcan
                    </ul>
                </div>
                <!-- Sidebar -->
            </div>

            @include('layouts.partials.cb-sidebar-footer')

            <div class="sidebar-background"></div>
        </div>
<!-- Left Sidebar End -->
<!-- Vertical Overlay-->
<div class="vertical-overlay" id="vertical-overlay"></div>

        <header id="page-topbar">
            <div class="layout-width">
                <div class="navbar-header">
                    <div class="d-flex">
                        <!-- LOGO -->
                        <div class="navbar-brand-box horizontal-logo">
                            <a href="index.html" class="logo logo-dark">
                                <span class="logo-sm">
                                    <img src="{{ asset('theme/layouts/assets/images/logo-sm.png')}}" alt="" height="22">
                                </span>
                                <span class="logo-lg">
                                    <img src="{{ asset('theme/layouts/assets/images/logo-dark.png')}}" alt="" height="22">
                                </span>
                            </a>

                            <a href="index.html" class="logo logo-light">
                                <span class="logo-sm">
                                    <img src="{{ asset('theme/layouts/assets/images/logo-sm.png')}}" alt="" height="22">
                                </span>
                                <span class="logo-lg">
                                    <img src="{{ asset('theme/layouts/assets/images/logo-light.png')}}" alt="" height="22">
                                </span>
                            </a>
                        </div>

                        <button type="button" class="btn btn-sm px-3 fs-16 header-item vertical-menu-btn topnav-hamburger shadow-none" id="topnav-hamburger-icon">
                            <span class="hamburger-icon">
                                <span></span>
                                <span></span>
                                <span></span>
                            </span>
                        </button>

                        @include('layouts.partials.cb-topbar', ['part' => 'left'])
                    </div>

                    <div class="d-flex align-items-center gap-1">
                        @include('layouts.partials.cb-topbar', ['part' => 'right'])
                    </div>
                </div>
            </div>
        </header>

          @yield('content')

       <footer class="footer">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6">
                        <script>document.write(new Date().getFullYear())</script> ©
                        @php
                            $storeSettings = \App\Models\StoreSetting::first();
                        @endphp
                        {{ $storeSettings->store_name ?? 'Store Name' }}
                    </div>
                    <div class="col-sm-6">
                        <div class="text-sm-end d-none d-sm-block">
                            Powered by Qudroid Systems
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    </div>
    <!-- end main content-->

    </div>
    <!-- END layout-wrapper -->


    <!--start back-to-top-->
    <button class="btn btn-dark btn-icon" id="back-to-top">
        <i class="bi bi-caret-up fs-3xl"></i>
    </button>
    <!--end back-to-top-->

    <!--preloader-->
    <div id="preloader">
        <div id="status">
            <div class="spinner-border text-primary avatar-sm" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    </div>

    <div class="customizer-setting d-none d-md-block">
        <div class="btn btn-info p-2 text-uppercase rounded-end-0 shadow-lg" data-bs-toggle="offcanvas" data-bs-target="#theme-settings-offcanvas" aria-controls="theme-settings-offcanvas">
            <i class="bi bi-gear mb-1"></i> Customizer
        </div>
    </div>

    <!-- Theme Settings -->
    <div class="offcanvas offcanvas-end border-0" tabindex="-1" id="theme-settings-offcanvas">
        <div class="d-flex align-items-center bg-primary bg-gradient p-3 offcanvas-header">
            <div class="me-2">
                <h5 class="mb-1 text-white">Steex Builder</h5>
                <p class="text-white text-opacity-75 mb-0">Choose your themes & layouts etc.</p>
            </div>

            <button type="button" class="btn-close btn-close-white ms-auto" id="customizerclose-btn" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body p-0">
            <div data-simplebar class="h-100">
                <div class="p-4">
                    <h6 class="fs-md mb-1">Layout</h6>
                    <p class="text-muted fs-sm">Choose your layout</p>

                    <div class="row">
                        <div class="col-4">
                            <div class="form-check card-radio">
                                <input id="customizer-layout01" name="data-layout" type="radio" value="vertical" class="form-check-input">
                                <label class="form-check-label p-0 avatar-md w-100" for="customizer-layout01">
                                    <span class="d-flex gap-1 h-100">
                                        <span class="flex-shrink-0">
                                            <span class="bg-light d-flex h-100 flex-column gap-1 p-1">
                                                <span class="d-block p-1 px-2 bg-primary-subtle rounded mb-2"></span>
                                                <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                            </span>
                                        </span>
                                        <span class="flex-grow-1">
                                            <span class="d-flex h-100 flex-column">
                                                <span class="bg-light d-block p-1"></span>
                                                <span class="bg-light d-block p-1 mt-auto"></span>
                                            </span>
                                        </span>
                                    </span>
                                </label>
                            </div>
                            <h5 class="fs-sm text-center fw-medium mt-2">Vertical</h5>
                        </div>
                        <div class="col-4">
                            <div class="form-check card-radio">
                                <input id="customizer-layout02" name="data-layout" type="radio" value="horizontal" class="form-check-input">
                                <label class="form-check-label p-0 avatar-md w-100" for="customizer-layout02">
                                    <span class="d-flex h-100 flex-column gap-1">
                                        <span class="bg-light d-flex p-1 gap-1 align-items-center">
                                            <span class="d-block p-1 bg-primary-subtle rounded me-1"></span>
                                            <span class="d-block p-1 pb-0 px-2 bg-primary-subtle ms-auto"></span>
                                            <span class="d-block p-1 pb-0 px-2 bg-primary-subtle"></span>
                                        </span>
                                        <span class="bg-light d-block p-1"></span>
                                        <span class="bg-light d-block p-1 mt-auto"></span>
                                    </span>
                                </label>
                            </div>
                            <h5 class="fs-sm text-center fw-medium mt-2">Horizontal</h5>
                        </div>
                        <div class="col-4">
                            <div class="form-check card-radio">
                                <input id="customizer-layout03" name="data-layout" type="radio" value="twocolumn" class="form-check-input">
                                <label class="form-check-label p-0 avatar-md w-100" for="customizer-layout03">
                                    <span class="d-flex gap-1 h-100">
                                        <span class="flex-shrink-0">
                                            <span class="bg-light d-flex h-100 flex-column gap-1">
                                                <span class="d-block p-1 bg-primary-subtle mb-2"></span>
                                                <span class="d-block p-1 pb-0 bg-primary-subtle"></span>
                                                <span class="d-block p-1 pb-0 bg-primary-subtle"></span>
                                                <span class="d-block p-1 pb-0 bg-primary-subtle"></span>
                                            </span>
                                        </span>
                                        <span class="flex-shrink-0">
                                            <span class="bg-light d-flex h-100 flex-column gap-1 p-1">
                                                <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                            </span>
                                        </span>
                                        <span class="flex-grow-1">
                                            <span class="d-flex h-100 flex-column">
                                                <span class="bg-light d-block p-1"></span>
                                                <span class="bg-light d-block p-1 mt-auto"></span>
                                            </span>
                                        </span>
                                    </span>
                                </label>
                            </div>
                            <h5 class="fs-sm text-center fw-medium mt-2">Two Column</h5>
                        </div>
                        <!-- end col -->
                    </div>

                    <h6 class="mt-4 fs-md mb-1">Theme</h6>
                    <p class="text-muted fs-sm">Choose your suitable Theme.</p>

                    <div class="row">
                        <div class="col-6">
                            <div class="form-check card-radio">
                                <input id="customizer-theme01" name="data-theme" type="radio" value="default" class="form-check-input">
                                <label class="form-check-label p-0" for="customizer-theme01">
                                    <img src="{{ asset('theme/layouts/assets/images/custom-theme/light-mode.png')}}" alt="" class="img-fluid">
                                </label>
                            </div>
                            <h5 class="fs-sm text-center fw-medium mt-2">Default</h5>
                        </div>
                        <div class="col-6">
                            <div class="form-check card-radio">
                                <input id="customizer-theme02" name="data-theme" type="radio" value="material" class="form-check-input">
                                <label class="form-check-label p-0" for="customizer-theme02">
                                    <img src="{{ asset('theme/layouts/assets/images/custom-theme/material.png')}}" alt="" class="img-fluid">
                                </label>
                            </div>
                            <h5 class="fs-sm text-center fw-medium mt-2">Material</h5>
                        </div>
                        <div class="col-6">
                            <div class="form-check card-radio">
                                <input id="customizer-theme03" name="data-theme" type="radio" value="creative" class="form-check-input">
                                <label class="form-check-label p-0" for="customizer-theme03">
                                    <img src="{{ asset('theme/layouts/assets/images/custom-theme/creative.png')}}" alt="" class="img-fluid">
                                </label>
                            </div>
                            <h5 class="fs-sm text-center fw-medium mt-2">Creative</h5>
                        </div>
                        <div class="col-6">
                            <div class="form-check card-radio">
                                <input id="customizer-theme04" name="data-theme" type="radio" value="minimal" class="form-check-input">
                                <label class="form-check-label p-0" for="customizer-theme04">
                                    <img src="{{ asset('theme/layouts/assets/images/custom-theme/minimal.png')}}" alt="" class="img-fluid">
                                </label>
                            </div>
                            <h5 class="fs-sm text-center fw-medium mt-2">Minimal</h5>
                        </div>
                        <div class="col-6">
                            <div class="form-check card-radio">
                                <input id="customizer-theme05" name="data-theme" type="radio" value="modern" class="form-check-input">
                                <label class="form-check-label p-0" for="customizer-theme05">
                                    <img src="{{ asset('theme/layouts/assets/images/custom-theme/modern.png')}}" alt="" class="img-fluid">
                                </label>
                            </div>
                            <h5 class="fs-sm text-center fw-medium mt-2">Modern</h5>
                        </div>
                        <!-- end col -->
                        <div class="col-6">
                            <div class="form-check card-radio">
                                <input id="customizer-theme06" name="data-theme" type="radio" value="interaction" class="form-check-input">
                                <label class="form-check-label p-0" for="customizer-theme06">
                                    <img src="{{ asset('theme/layouts/assets/images/custom-theme/interaction.png')}}" alt="" class="img-fluid">
                                </label>
                            </div>
                            <h5 class="fs-sm text-center fw-medium mt-2">Interaction</h5>
                        </div><!-- end col -->
                    </div>

                    <h6 class="mt-4 fs-md mb-1">Color Scheme</h6>
                    <p class="text-muted fs-sm">Choose Light or Dark Scheme.</p>

                    <div class="colorscheme-cardradio">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="form-check card-radio">
                                    <input class="form-check-input" type="radio" name="data-bs-theme" id="layout-mode-light" value="light">
                                    <label class="form-check-label p-0 bg-transparent" for="layout-mode-light">
                                        <img src="{{ asset('theme/layouts/assets/images/custom-theme/light-mode.png')}}" alt="" class="img-fluid">
                                    </label>
                                </div>
                                <h5 class="fs-sm text-center fw-medium mt-2">Light</h5>
                            </div>

                            <div class="col-6">
                                <div class="form-check card-radio dark">
                                    <input class="form-check-input" type="radio" name="data-bs-theme" id="layout-mode-dark" value="dark">
                                    <label class="form-check-label p-0 bg-transparent" for="layout-mode-dark">
                                        <img src="{{ asset('theme/layouts/assets/images/custom-theme/dark-mode.png')}}" alt="" class="img-fluid">
                                    </label>
                                </div>
                                <h5 class="fs-sm text-center fw-medium mt-2">Dark</h5>
                            </div>
                        </div>
                    </div>

                    <div id="layout-width">
                        <h6 class="mt-4 fs-md mb-1">Layout Width</h6>
                        <p class="text-muted fs-sm">Choose Fluid or Boxed layout.</p>

                        <div class="row">
                            <div class="col-4">
                                <div class="form-check card-radio">
                                    <input class="form-check-input" type="radio" name="data-layout-width" id="layout-width-fluid" value="fluid">
                                    <label class="form-check-label p-0 avatar-md w-100" for="layout-width-fluid">
                                        <span class="d-flex gap-1 h-100">
                                            <span class="flex-shrink-0">
                                                <span class="bg-light d-flex h-100 flex-column gap-1 p-1">
                                                    <span class="d-block p-1 px-2 bg-primary-subtle rounded mb-2"></span>
                                                    <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                    <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                    <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                </span>
                                            </span>
                                            <span class="flex-grow-1">
                                                <span class="d-flex h-100 flex-column">
                                                    <span class="bg-light d-block p-1"></span>
                                                    <span class="bg-light d-block p-1 mt-auto"></span>
                                                </span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                <h5 class="fs-sm text-center fw-medium mt-2">Fluid</h5>
                            </div>
                            <div class="col-4">
                                <div class="form-check card-radio">
                                    <input class="form-check-input" type="radio" name="data-layout-width" id="layout-width-boxed" value="boxed">
                                    <label class="form-check-label p-0 avatar-md w-100 px-2" for="layout-width-boxed">
                                        <span class="d-flex gap-1 h-100 border-start border-end">
                                            <span class="flex-shrink-0">
                                                <span class="bg-light d-flex h-100 flex-column gap-1 p-1">
                                                    <span class="d-block p-1 px-2 bg-primary-subtle rounded mb-2"></span>
                                                    <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                    <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                    <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                </span>
                                            </span>
                                            <span class="flex-grow-1">
                                                <span class="d-flex h-100 flex-column">
                                                    <span class="bg-light d-block p-1"></span>
                                                    <span class="bg-light d-block p-1 mt-auto"></span>
                                                </span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                <h5 class="fs-sm text-center fw-medium mt-2">Boxed</h5>
                            </div>
                        </div>
                    </div>

                    <div id="layout-position">
                        <h6 class="mt-4 fs-md mb-1">Layout Position</h6>
                        <p class="text-muted fs-sm">Choose Fixed or Scrollable Layout Position.</p>

                        <div class="btn-group radio" role="group">
                            <input type="radio" class="btn-check" name="data-layout-position" id="layout-position-fixed" value="fixed">
                            <label class="btn btn-light w-sm" for="layout-position-fixed">Fixed</label>

                            <input type="radio" class="btn-check" name="data-layout-position" id="layout-position-scrollable" value="scrollable">
                            <label class="btn btn-light w-sm ms-0" for="layout-position-scrollable">Scrollable</label>
                        </div>
                    </div>

                    <h6 class="mt-4 fs-md mb-1">Topbar Color</h6>
                    <p class="text-muted fs-sm">Choose Light or Dark Topbar Color.</p>

                    <div class="row">
                        <div class="col-4">
                            <div class="form-check card-radio">
                                <input class="form-check-input" type="radio" name="data-topbar" id="topbar-color-light" value="light">
                                <label class="form-check-label p-0 avatar-md w-100" for="topbar-color-light">
                                    <span class="d-flex gap-1 h-100">
                                        <span class="flex-shrink-0">
                                            <span class="bg-light d-flex h-100 flex-column gap-1 p-1">
                                                <span class="d-block p-1 px-2 bg-primary-subtle rounded mb-2"></span>
                                                <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                            </span>
                                        </span>
                                        <span class="flex-grow-1">
                                            <span class="d-flex h-100 flex-column">
                                                <span class="bg-light d-block p-1"></span>
                                                <span class="bg-light d-block p-1 mt-auto"></span>
                                            </span>
                                        </span>
                                    </span>
                                </label>
                            </div>
                            <h5 class="fs-sm text-center fw-medium mt-2">Light</h5>
                        </div>
                        <div class="col-4">
                            <div class="form-check card-radio">
                                <input class="form-check-input" type="radio" name="data-topbar" id="topbar-color-dark" value="dark">
                                <label class="form-check-label p-0 avatar-md w-100" for="topbar-color-dark">
                                    <span class="d-flex gap-1 h-100">
                                        <span class="flex-shrink-0">
                                            <span class="bg-light d-flex h-100 flex-column gap-1 p-1">
                                                <span class="d-block p-1 px-2 bg-primary-subtle rounded mb-2"></span>
                                                <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                            </span>
                                        </span>
                                        <span class="flex-grow-1">
                                            <span class="d-flex h-100 flex-column">
                                                <span class="bg-primary d-block p-1"></span>
                                                <span class="bg-light d-block p-1 mt-auto"></span>
                                            </span>
                                        </span>
                                    </span>
                                </label>
                            </div>
                            <h5 class="fs-sm text-center fw-medium mt-2">Dark</h5>
                        </div>
                    </div>

                    <div id="sidebar-size">
                        <h6 class="mt-4 fs-md mb-1">Sidebar Size</h6>
                        <p class="text-muted fs-sm">Choose a size of Sidebar.</p>

                        <div class="row">
                            <div class="col-4">
                                <div class="form-check sidebar-setting card-radio">
                                    <input class="form-check-input" type="radio" name="data-sidebar-size" id="sidebar-size-default" value="lg">
                                    <label class="form-check-label p-0 avatar-md w-100" for="sidebar-size-default">
                                        <span class="d-flex gap-1 h-100">
                                            <span class="flex-shrink-0">
                                                <span class="bg-light d-flex h-100 flex-column gap-1 p-1">
                                                    <span class="d-block p-1 px-2 bg-primary-subtle rounded mb-2"></span>
                                                    <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                    <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                    <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                </span>
                                            </span>
                                            <span class="flex-grow-1">
                                                <span class="d-flex h-100 flex-column">
                                                    <span class="bg-light d-block p-1"></span>
                                                    <span class="bg-light d-block p-1 mt-auto"></span>
                                                </span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                <h5 class="fs-sm text-center fw-medium mt-2">Default</h5>
                            </div>

                            <div class="col-4">
                                <div class="form-check sidebar-setting card-radio">
                                    <input class="form-check-input" type="radio" name="data-sidebar-size" id="sidebar-size-compact" value="md">
                                    <label class="form-check-label p-0 avatar-md w-100" for="sidebar-size-compact">
                                        <span class="d-flex gap-1 h-100">
                                            <span class="flex-shrink-0">
                                                <span class="bg-light d-flex h-100 flex-column gap-1 p-1">
                                                    <span class="d-block p-1 bg-primary-subtle rounded mb-2"></span>
                                                    <span class="d-block p-1 pb-0 bg-primary-subtle"></span>
                                                    <span class="d-block p-1 pb-0 bg-primary-subtle"></span>
                                                    <span class="d-block p-1 pb-0 bg-primary-subtle"></span>
                                                </span>
                                            </span>
                                            <span class="flex-grow-1">
                                                <span class="d-flex h-100 flex-column">
                                                    <span class="bg-light d-block p-1"></span>
                                                    <span class="bg-light d-block p-1 mt-auto"></span>
                                                </span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                <h5 class="fs-sm text-center fw-medium mt-2">Compact</h5>
                            </div>

                            <div class="col-4">
                                <div class="form-check sidebar-setting card-radio">
                                    <input class="form-check-input" type="radio" name="data-sidebar-size" id="sidebar-size-small" value="sm">
                                    <label class="form-check-label p-0 avatar-md w-100" for="sidebar-size-small">
                                        <span class="d-flex gap-1 h-100">
                                            <span class="flex-shrink-0">
                                                <span class="bg-light d-flex h-100 flex-column gap-1">
                                                    <span class="d-block p-1 bg-primary-subtle mb-2"></span>
                                                    <span class="d-block p-1 pb-0 bg-primary-subtle"></span>
                                                    <span class="d-block p-1 pb-0 bg-primary-subtle"></span>
                                                    <span class="d-block p-1 pb-0 bg-primary-subtle"></span>
                                                </span>
                                            </span>
                                            <span class="flex-grow-1">
                                                <span class="d-flex h-100 flex-column">
                                                    <span class="bg-light d-block p-1"></span>
                                                    <span class="bg-light d-block p-1 mt-auto"></span>
                                                </span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                <h5 class="fs-sm text-center fw-medium mt-2">Small (Icon View)</h5>
                            </div>

                            <div class="col-4">
                                <div class="form-check sidebar-setting card-radio">
                                    <input class="form-check-input" type="radio" name="data-sidebar-size" id="sidebar-size-small-hover" value="sm-hover">
                                    <label class="form-check-label p-0 avatar-md w-100" for="sidebar-size-small-hover">
                                        <span class="d-flex gap-1 h-100">
                                            <span class="flex-shrink-0">
                                                <span class="bg-light d-flex h-100 flex-column gap-1">
                                                    <span class="d-block p-1 bg-primary-subtle mb-2"></span>
                                                    <span class="d-block p-1 pb-0 bg-primary-subtle"></span>
                                                    <span class="d-block p-1 pb-0 bg-primary-subtle"></span>
                                                    <span class="d-block p-1 pb-0 bg-primary-subtle"></span>
                                                </span>
                                            </span>
                                            <span class="flex-grow-1">
                                                <span class="d-flex h-100 flex-column">
                                                    <span class="bg-light d-block p-1"></span>
                                                    <span class="bg-light d-block p-1 mt-auto"></span>
                                                </span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                <h5 class="fs-sm text-center fw-medium mt-2">Small Hover View</h5>
                            </div>
                        </div>
                    </div>

                    <div id="sidebar-view">
                        <h6 class="mt-4 fs-md mb-1">Sidebar View</h6>
                        <p class="text-muted fs-sm">Choose Default or Detached Sidebar view.</p>

                        <div class="row">
                            <div class="col-4">
                                <div class="form-check sidebar-setting card-radio">
                                    <input class="form-check-input" type="radio" name="data-layout-style" id="sidebar-view-default" value="default">
                                    <label class="form-check-label p-0 avatar-md w-100" for="sidebar-view-default">
                                        <span class="d-flex gap-1 h-100">
                                            <span class="flex-shrink-0">
                                                <span class="bg-light d-flex h-100 flex-column gap-1 p-1">
                                                    <span class="d-block p-1 px-2 bg-primary-subtle rounded mb-2"></span>
                                                    <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                    <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                    <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                </span>
                                            </span>
                                            <span class="flex-grow-1">
                                                <span class="d-flex h-100 flex-column">
                                                    <span class="bg-light d-block p-1"></span>
                                                    <span class="bg-light d-block p-1 mt-auto"></span>
                                                </span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                <h5 class="fs-sm text-center fw-medium mt-2">Default</h5>
                            </div>
                            <div class="col-4">
                                <div class="form-check sidebar-setting card-radio">
                                    <input class="form-check-input" type="radio" name="data-layout-style" id="sidebar-view-detached" value="detached">
                                    <label class="form-check-label p-0 avatar-md w-100" for="sidebar-view-detached">
                                        <span class="d-flex h-100 flex-column">
                                            <span class="bg-light d-flex p-1 gap-1 align-items-center px-2">
                                                <span class="d-block p-1 bg-primary-subtle rounded me-1"></span>
                                                <span class="d-block p-1 pb-0 px-2 bg-primary-subtle ms-auto"></span>
                                                <span class="d-block p-1 pb-0 px-2 bg-primary-subtle"></span>
                                            </span>
                                            <span class="d-flex gap-1 h-100 p-1 px-2">
                                                <span class="flex-shrink-0">
                                                    <span class="bg-light d-flex h-100 flex-column gap-1 p-1">
                                                        <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                        <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                        <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                    </span>
                                                </span>
                                            </span>
                                            <span class="bg-light d-block p-1 mt-auto px-2"></span>
                                        </span>
                                    </label>
                                </div>
                                <h5 class="fs-sm text-center fw-medium mt-2">Detached</h5>
                            </div>
                        </div>
                    </div>
                    <div id="sidebar-color">
                        <h6 class="mt-4 fs-md mb-1">Sidebar Color</h6>
                        <p class="text-muted fs-sm">Choose a color of Sidebar.</p>

                        <div class="row">
                            <div class="col-4">
                                <div class="form-check sidebar-setting card-radio" data-bs-toggle="collapse" data-bs-target="#collapseBgGradient.show">
                                    <input class="form-check-input" type="radio" name="data-sidebar" id="sidebar-color-light" value="light">
                                    <label class="form-check-label p-0 avatar-md w-100" for="sidebar-color-light">
                                        <span class="d-flex gap-1 h-100">
                                            <span class="flex-shrink-0">
                                                <span class="bg-white border-end d-flex h-100 flex-column gap-1 p-1">
                                                    <span class="d-block p-1 px-2 bg-primary-subtle rounded mb-2"></span>
                                                    <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                    <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                    <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                </span>
                                            </span>
                                            <span class="flex-grow-1">
                                                <span class="d-flex h-100 flex-column">
                                                    <span class="bg-light d-block p-1"></span>
                                                    <span class="bg-light d-block p-1 mt-auto"></span>
                                                </span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                <h5 class="fs-sm text-center fw-medium mt-2">Light</h5>
                            </div>
                            <div class="col-4">
                                <div class="form-check sidebar-setting card-radio" data-bs-toggle="collapse" data-bs-target="#collapseBgGradient.show">
                                    <input class="form-check-input" type="radio" name="data-sidebar" id="sidebar-color-dark" value="dark">
                                    <label class="form-check-label p-0 avatar-md w-100" for="sidebar-color-dark">
                                        <span class="d-flex gap-1 h-100">
                                            <span class="flex-shrink-0">
                                                <span class="bg-primary d-flex h-100 flex-column gap-1 p-1">
                                                    <span class="d-block p-1 px-2 bg-soft-light rounded mb-2"></span>
                                                    <span class="d-block p-1 px-2 pb-0 bg-soft-light"></span>
                                                    <span class="d-block p-1 px-2 pb-0 bg-soft-light"></span>
                                                    <span class="d-block p-1 px-2 pb-0 bg-soft-light"></span>
                                                </span>
                                            </span>
                                            <span class="flex-grow-1">
                                                <span class="d-flex h-100 flex-column">
                                                    <span class="bg-light d-block p-1"></span>
                                                    <span class="bg-light d-block p-1 mt-auto"></span>
                                                </span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                <h5 class="fs-sm text-center fw-medium mt-2">Dark</h5>
                            </div>
                            <div class="col-4">
                                <button class="btn btn-link avatar-md w-100 p-0 overflow-hidden border collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseBgGradient" aria-expanded="false" aria-controls="collapseBgGradient">
                                    <span class="d-flex gap-1 h-100">
                                        <span class="flex-shrink-0">
                                            <span class="bg-vertical-gradient d-flex h-100 flex-column gap-1 p-1">
                                                <span class="d-block p-1 px-2 bg-soft-light rounded mb-2"></span>
                                                <span class="d-block p-1 px-2 pb-0 bg-soft-light"></span>
                                                <span class="d-block p-1 px-2 pb-0 bg-soft-light"></span>
                                                <span class="d-block p-1 px-2 pb-0 bg-soft-light"></span>
                                            </span>
                                        </span>
                                        <span class="flex-grow-1">
                                            <span class="d-flex h-100 flex-column">
                                                <span class="bg-light d-block p-1"></span>
                                                <span class="bg-light d-block p-1 mt-auto"></span>
                                            </span>
                                        </span>
                                    </span>
                                </button>
                                <h5 class="fs-sm text-center fw-medium mt-2">Gradient</h5>
                            </div>
                        </div>
                        <!-- end row -->

                        <div class="collapse" id="collapseBgGradient">
                            <div class="d-flex gap-2 flex-wrap img-switch p-2 px-3 bg-light rounded">

                                <div class="form-check sidebar-setting card-radio">
                                    <input class="form-check-input" type="radio" name="data-sidebar" id="sidebar-color-gradient" value="gradient">
                                    <label class="form-check-label p-0 avatar-xs rounded-circle" for="sidebar-color-gradient">
                                        <span class="avatar-title rounded-circle bg-vertical-gradient"></span>
                                    </label>
                                </div>
                                <div class="form-check sidebar-setting card-radio">
                                    <input class="form-check-input" type="radio" name="data-sidebar" id="sidebar-color-gradient-2" value="gradient-2">
                                    <label class="form-check-label p-0 avatar-xs rounded-circle" for="sidebar-color-gradient-2">
                                        <span class="avatar-title rounded-circle bg-vertical-gradient-2"></span>
                                    </label>
                                </div>
                                <div class="form-check sidebar-setting card-radio">
                                    <input class="form-check-input" type="radio" name="data-sidebar" id="sidebar-color-gradient-3" value="gradient-3">
                                    <label class="form-check-label p-0 avatar-xs rounded-circle" for="sidebar-color-gradient-3">
                                        <span class="avatar-title rounded-circle bg-vertical-gradient-3"></span>
                                    </label>
                                </div>
                                <div class="form-check sidebar-setting card-radio">
                                    <input class="form-check-input" type="radio" name="data-sidebar" id="sidebar-color-gradient-4" value="gradient-4">
                                    <label class="form-check-label p-0 avatar-xs rounded-circle" for="sidebar-color-gradient-4">
                                        <span class="avatar-title rounded-circle bg-vertical-gradient-4"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="sidebar-img">
                        <h6 class="mt-4 fw-semibold fs-base">Sidebar Images</h6>
                        <p class="text-muted fs-sm">Choose a image of Sidebar.</p>

                        <div class="d-flex gap-2 flex-wrap img-switch">
                            <div class="form-check sidebar-setting card-radio">
                                <input class="form-check-input" type="radio" name="data-sidebar-image" id="sidebarimg-none" value="none">
                                <label class="form-check-label p-0 avatar-sm h-auto" for="sidebarimg-none">
                                    <span class="avatar-md w-auto bg-light d-flex align-items-center justify-content-center">
                                        <i class="ri-close-fill fs-3xl"></i>
                                    </span>
                                </label>
                            </div>

                            <div class="form-check sidebar-setting card-radio">
                                <input class="form-check-input" type="radio" name="data-sidebar-image" id="sidebarimg-01" value="img-1">
                                <label class="form-check-label p-0 avatar-sm h-auto" for="sidebarimg-01">
                                    <img src="{{ asset('theme/layouts/assets/images/sidebar/img-sm-1.jpg')}}" alt="" class="avatar-md w-auto object-cover">
                                </label>
                            </div>

                            <div class="form-check sidebar-setting card-radio">
                                <input class="form-check-input" type="radio" name="data-sidebar-image" id="sidebarimg-02" value="img-2">
                                <label class="form-check-label p-0 avatar-sm h-auto" for="sidebarimg-02">
                                    <img src="{{ asset('theme/layouts/assets/images/sidebar/img-sm-2.jpg')}}" alt="" class="avatar-md w-auto object-cover">
                                </label>
                            </div>
                            <div class="form-check sidebar-setting card-radio">
                                <input class="form-check-input" type="radio" name="data-sidebar-image" id="sidebarimg-03" value="img-3">
                                <label class="form-check-label p-0 avatar-sm h-auto" for="sidebarimg-03">
                                    <img src="{{ asset('theme/layouts/assets/images/sidebar/img-sm-3.jpg')}}" alt="" class="avatar-md w-auto object-cover">
                                </label>
                            </div>
                            <div class="form-check sidebar-setting card-radio">
                                <input class="form-check-input" type="radio" name="data-sidebar-image" id="sidebarimg-04" value="img-4">
                                <label class="form-check-label p-0 avatar-sm h-auto" for="sidebarimg-04">
                                    <img src="{{ asset('theme/layouts/assets/images/sidebar/img-sm-4.jpg')}}" alt="" class="avatar-md w-auto object-cover">
                                </label>
                            </div>
                        </div>
                    </div>

                    <div id="preloader-menu">
                        <h6 class="mt-4 fw-semibold fs-base">Preloader</h6>
                        <p class="text-muted fs-sm">Choose a preloader.</p>

                        <div class="row">
                            <div class="col-4">
                                <div class="form-check sidebar-setting card-radio">
                                    <input class="form-check-input" type="radio" name="data-preloader" id="preloader-view-custom" value="enable">
                                    <label class="form-check-label p-0 avatar-md w-100" for="preloader-view-custom">
                                        <span class="d-flex gap-1 h-100">
                                            <span class="flex-shrink-0">
                                                <span class="bg-light d-flex h-100 flex-column gap-1 p-1">
                                                    <span class="d-block p-1 px-2 bg-primary-subtle rounded mb-2"></span>
                                                    <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                    <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                    <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                </span>
                                            </span>
                                            <span class="flex-grow-1">
                                                <span class="d-flex h-100 flex-column">
                                                    <span class="bg-light d-block p-1"></span>
                                                    <span class="bg-light d-block p-1 mt-auto"></span>
                                                </span>
                                            </span>
                                        </span>
                                        <!-- <div id="preloader"> -->
                                        <span class="d-flex align-items-center justify-content-center">
                                            <span class="spinner-border text-primary avatar-xxs m-auto" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </span>
                                        </span>
                                        <!-- </div> -->
                                    </label>
                                </div>
                                <h5 class="fs-sm text-center fw-medium mt-2">Enable</h5>
                            </div>
                            <div class="col-4">
                                <div class="form-check sidebar-setting card-radio">
                                    <input class="form-check-input" type="radio" name="data-preloader" id="preloader-view-none" value="disable">
                                    <label class="form-check-label p-0 avatar-md w-100" for="preloader-view-none">
                                        <span class="d-flex gap-1 h-100">
                                            <span class="flex-shrink-0">
                                                <span class="bg-light d-flex h-100 flex-column gap-1 p-1">
                                                    <span class="d-block p-1 px-2 bg-primary-subtle rounded mb-2"></span>
                                                    <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                    <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                    <span class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                </span>
                                            </span>
                                            <span class="flex-grow-1">
                                                <span class="d-flex h-100 flex-column">
                                                    <span class="bg-light d-block p-1"></span>
                                                    <span class="bg-light d-block p-1 mt-auto"></span>
                                                </span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                <h5 class="fs-sm text-center fw-medium mt-2">Disable</h5>
                            </div>
                        </div>

                    </div><!-- end preloader-menu -->
                </div>
            </div>

        </div>
        <div class="offcanvas-footer border-top p-3 text-center">
            <div class="row">
                <div class="col-6">
                    <button type="button" class="btn btn-light w-100" id="reset-layout">Reset</button>
                </div>
            </div>
        </div>
    </div>


      @if (Route::is('dashboard') || Route::is('products.*') || Route::is('pricing.*') || Route::is('adjustments.*') || Route::is('security.*') || Route::is('system-config.*') || Route::is('report-emails.*') || Route::is('audit.*') || Route::is('pos.transactions*') || Route::is('pos.shifts.*') || Route::is('mb.*') || Route::is('catalogue.*') || Route::is('pos.payments.*') || Route::is('pos.registers.*') || Route::is('transfers.*') || Route::is('counts.*') || Route::is('suppliers.*') || Route::is('purchase-orders.*') || Route::is('goods-receipts.*') || Route::is('supplier-returns.*'))
            @include('layouts.pages-assets.js.common-js')
      @endif

      @if (Route::is('users.*'))
            @include('layouts.pages-assets.js.users-list-js')
      @endif

      @if (Route::is('user.*'))
            @include('layouts.pages-assets.js.users-list-js')
      @endif

      @if (Route::is('roles.*'))
             @include('layouts.pages-assets.js.role-list-js')
      @endif

      @if (Route::is('permissions.*'))
            @include('layouts.pages-assets.js.permissions-list-js')
      @endif

      @if (Route::is('brands.*'))
            @include('layouts.pages-assets.js.brand-list-js')
      @endif

      @if (Route::is('categories.*'))
            @include('layouts.pages-assets.js.category-list-js')
      @endif

       @if (Route::is('banners.*'))
            @include('layouts.pages-assets.js.banner-list-js')
      @endif



      @if (Route::is('pos.*') && !Route::is('pos.transactions*') && !Route::is('pos.shifts.*') && !Route::is('pos.payments.*') && !Route::is('pos.registers.*'))
            @include('layouts.pages-assets.js.pos-list-js')
      @endif

     @if (Route::is('inventory.*') || Route::is('stock-locations.*'))
            @include('layouts.pages-assets.js.inventory-list-js')
     @endif

     @if (Route::is('orders.*'))
         @include('layouts.pages-assets.js.order-list-js')
     @endif

     @if (Route::is('customers.*'))
            @include('layouts.pages-assets.js.customer-list-js')
     @endif

    @if (Route::is('settings.store.*'))
            @include('layouts.pages-assets.js.storesetting-list-js')
    @endif
    @if (Route::is('sales.*'))
            @include('layouts.pages-assets.js.sale-list-js')
    @endif
     @if (Route::is('salesperson.*'))
            @include('layouts.pages-assets.js.sale-list-js')
    @endif
    @include('layouts.partials.cb-spotlight')
    @include('layouts.partials.cb-shell-js')
      </body>

      </html>

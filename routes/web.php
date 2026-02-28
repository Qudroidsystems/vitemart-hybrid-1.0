<?php

use App\Http\Controllers\APIAuthController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\BiodataController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OverviewController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductReviewController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\SalesPersonController;
use App\Http\Controllers\StockLocationController;
use App\Http\Controllers\StoreSettingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;



// Public Routes
Route::get('/', function () {
    return view('welcome');
});

Route::get('/email/verify/{id}/{hash}', [APIAuthController::class, 'verifyEmail'])
    ->middleware('signed')
    ->name('verification.verify');

Auth::routes();

Route::get('/home', [HomeController::class, 'index'])->name('home');

Route::get('/payment-callback', function () {
    return view('payment-callback');
})->name('payment.callback');

// ===================================================================
// AUTHENTICATED ROUTES (Admin Panel)
// ===================================================================
Route::middleware(['auth'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/export', [DashboardController::class, 'export'])->name('dashboard.export');

    // Roles & Permissions
    Route::resource('roles', RoleController::class);
    Route::resource('permissions', PermissionController::class);

    Route::get('/adduser/{id}', [RoleController::class, 'adduser'])->name('roles.adduser');
    Route::post('/updateuserrole', [RoleController::class, 'updateuserrole'])->name('roles.updateuserrole');
    Route::delete('roles/removeuserrole/{userid}/{roleid}', [RoleController::class, 'removeuserrole'])
        ->name('roles.removeuserrole');

    // Users Management
    Route::resource('users', UserController::class);
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::get('/users/{user}/overview', [UserController::class, 'overview'])->name('user.overview');
    Route::get('/users/all', [UserController::class, 'allUsers'])->name('users.all');
    Route::get('/users/paginate', [UserController::class, 'paginate'])->name('users.paginate');
    Route::get('/users/roles', [UserController::class, 'roles'])->name('users.roles');

    // Biodata / Profile
    Route::resource('biodata', BiodataController::class);

    Route::get('/users/{user}/settings', [BiodataController::class, 'show'])->name('user.settings');

    // Banner Management
    Route::resource('banners', BannerController::class)->except(['show']);
    Route::get('banners/{banner}/edit', [BannerController::class, 'edit'])->name('banners.edit');

    // Brand Management
    Route::resource('brands', BrandController::class)->except(['show']);
    Route::get('brands/{brand}/edit', [BrandController::class, 'edit'])->name('brands.edit');

    // Category Management
    Route::resource('categories', CategoryController::class)->except(['show']);
    Route::get('categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');

    // Product Management
    Route::resource('products', ProductController::class);
    Route::delete('/products/{id}/images/{imageId}', [ProductController::class, 'deleteImage'])->name('products.images.destroy');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::get('/products/{product}/inventory', [ProductController::class, 'inventoryLog'])->name('products.inventory');
    Route::get('/products/template', [ProductController::class, 'template'])->name('products.template');
    Route::get('/products/search', [ProductController::class, 'search'])->name('products.search');
    Route::get('/products/realtime-stock', [ProductController::class, 'realtimeStock'])->name('products.realtime-stock');
    Route::post('/products/import', [ProductController::class, 'import'])->name('products.import');
    Route::get('/products/export', [ProductController::class, 'export'])->name('products.export');
    Route::post('/products/bulk-update', [ProductController::class, 'bulkUpdate'])->name('products.bulkUpdate');


    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::get('/pos/search', [PosController::class, 'search'])->name('pos.search');
    Route::post('/pos/save-order', [PosController::class, 'savePosOrder'])->name('pos.order.save');
    Route::get('/pos/receipt/{orderId}', [PosController::class, 'receipt'])->name('pos.receipt');
    // Add this route for product units
    Route::get('/api/products/{product}/units', [PosController::class, 'getProductUnits'])
    ->name('api.product.units')
    ->middleware('auth');





            // Customer Routes
    Route::resource('customers', CustomerController::class);
    Route::get('/customers/export', [CustomerController::class, 'export'])->name('customers.export');
    // Add this route (preferably in your web.php routes)
    Route::post('/customers/quick', [CustomerController::class, 'quickStore'])->name('customers.quick');


    // POS Routes
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::get('/pos/search', [PosController::class, 'search'])->name('pos.search');
    Route::post('/pos/save-order', [PosController::class, 'savePosOrder'])->name('pos.order.save');
    Route::get('/pos/receipt/{orderId}', [PosController::class, 'generateReceipt'])->name('pos.receipt');
    Route::get('/pos/print/{orderId}', [PosController::class, 'printReceipt'])->name('pos.print');
    Route::get('/pos/barcode/{barcode}', [PosController::class, 'getProductByBarcode'])->name('pos.barcode');
    Route::get('/pos/today-sales', [PosController::class, 'getTodaySales'])->name('pos.today-sales');
    Route::post('/pos/void-order/{orderId}', [PosController::class, 'voidOrder'])->name('pos.void');
    Route::get('/pos/product-stock/{productId}', [PosController::class, 'getProductStock'])->name('pos.product-stock');
    Route::post('/pos/apply-discount', [PosController::class, 'applyDiscount'])->name('pos.apply-discount');

    Route::get('/reports/loyalty-points', [LoyaltyReportController::class, 'index'])->name('reports.loyalty-points');
    Route::get('/reports/loyalty-points/{customer}', [LoyaltyReportController::class, 'customerHistory'])->name('reports.loyalty-points.history');
    Route::get('/reports/loyalty-points/export', [LoyaltyReportController::class, 'export'])->name('reports.loyalty-points.export');






    // Product Reviews
    Route::group(['prefix' => 'products/{product}/reviews'], function() {
        Route::post('/', [ProductReviewController::class, 'store'])->name('products.reviews.store');
    });

    // Admin Reviews Management
    Route::resource('reviews', ProductReviewController::class)->except(['show']);
    Route::get('/reviews/{id}/edit', [ProductReviewController::class, 'edit'])->name('reviews.edit');
    Route::post('/reviews/{id}/company-comment', [ProductReviewController::class, 'addCompanyComment'])->name('reviews.company-comment');

    // ===================================================================
    // INVENTORY MANAGEMENT ROUTES
    // ===================================================================


    // Stock Locations Management - SIMPLIFIED VERSION
    // Remove ALL duplicate routes and use this clean version:

    // Custom routes that need to come BEFORE resource
    Route::get('stock-locations/{id}/stock', [StockLocationController::class, 'getLocationStock'])->name('stock-locations.stock');
    Route::get('stock-locations/{id}/view', [StockLocationController::class, 'show'])->name('stock-locations.view'); // Alternative show route
    Route::post('stock-locations/{id}/set-default', [StockLocationController::class, 'setAsDefault'])->name('stock-locations.set-default');
    Route::post('stock-locations/{id}/toggle-status', [StockLocationController::class, 'toggleStatus'])->name('stock-locations.toggle-status');

    // Main resource route - this creates ALL standard routes (index, create, store, show, edit, update, destroy)
    Route::resource('stock-locations', StockLocationController::class);

    // Other non-conflicting routes
    Route::post('stock-locations/update-sort', [StockLocationController::class, 'updateSortOrder'])->name('stock-locations.update-sort');
    Route::get('stock-locations/export', [StockLocationController::class, 'exportLocations'])->name('stock-locations.export');

    Route::get('/settings/store', [StoreSettingController::class, 'index'])->name('settings.store.index');
    Route::put('/settings/store', [StoreSettingController::class, 'update'])->name('settings.store.update');

    // Inventory Management Routes
    Route::prefix('inventory')->group(function () {
        // Main inventory routes
        Route::get('/', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('/dashboard', [InventoryController::class, 'dashboard'])->name('inventory.dashboard');
        Route::get('/stock-levels', [InventoryController::class, 'stockLevels'])->name('inventory.stock-levels');
        Route::get('/history/{id}', [InventoryController::class, 'stockHistory'])->name('inventory.history');
        // In your routes file (web.php)
        // ADD THIS LINE - API endpoint for stock history
        Route::get('/history/{id}', [InventoryController::class, 'getStockHistory'])->name('inventory.history.api');


        // Report pages
        Route::get('/low-stock-alerts', [InventoryController::class, 'lowStockAlerts'])->name('inventory.low-stock-alerts');
        Route::get('/stock-value-report', [InventoryController::class, 'stockValueReport'])->name('inventory.stock-value-report');



        // Stock operations (AJAX)
        Route::post('/adjust', [InventoryController::class, 'adjustStock'])->name('inventory.adjust');
        Route::post('/transfer', [InventoryController::class, 'transferStock'])->name('inventory.transfer');
        Route::post('/bulk-adjust', [InventoryController::class, 'bulkAdjust'])->name('inventory.bulk-adjust');
        Route::post('/import', [InventoryController::class, 'import'])->name('inventory.import');

        // Export routes
        Route::get('/export/transactions', [InventoryController::class, 'exportTransactions'])->name('inventory.export.transactions');
        Route::get('/export/stock-levels', [InventoryController::class, 'exportStockLevels'])->name('inventory.export.stock-levels');

        // API endpoints for AJAX requests
        Route::get('/{id}', [InventoryController::class, 'show'])->name('inventory.show');
        Route::delete('/{id}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
        Route::get('/stock-level/{productId}/{locationId}', [InventoryController::class, 'getProductStock'])->name('inventory.get-product-stock');

        // API endpoints for data
        Route::get('/api/low-stock-alerts', [InventoryController::class, 'getLowStockAlerts'])->name('inventory.api.low-stock-alerts');
        Route::get('/api/stock-value-report', [InventoryController::class, 'getStockValueReport'])->name('inventory.api.stock-value-report');
    });

    Route::get('/inventory/export/stock-levels/pdf', [InventoryController::class, 'exportStockLevelsPDF'])
    ->name('inventory.export.stock-levels.pdf');
    // Real-time stock updates
    Route::get('/inventory/realtime-product-stock', [InventoryController::class, 'realtimeProductStock']);
    Route::get('/inventory/product/{productId}/location/{locationId}/stock', [InventoryController::class, 'getLocationStock']);
    // Sync product stock (admin only)
    Route::post('/inventory/sync-stocks', [InventoryController::class, 'syncAllProductStocks']) ->name('inventory.sync-stocks');
    Route::get('/inventory/export/stock-levels/pdf', [InventoryController::class, 'exportStockLevelsPDF'])->name('inventory.export.stock-levels.pdf');
    // Add this route
    Route::get('/inventory/recalculate-stock', [InventoryController::class, 'recalculateStock']) ->name('inventory.recalculate-stock');



    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->name('index');
        Route::get('{order}', [OrderController::class, 'show'])->name('show');
        Route::post('{order}/status', [OrderController::class, 'updateStatus'])->name('status');
        Route::get('{order}/invoice', [OrderController::class, 'invoice'])->name('invoice');
        Route::post('{order}/email-invoice', [OrderController::class, 'emailInvoice'])->name('emailInvoice');
        Route::post('{order}/note', [OrderController::class, 'addNote'])->name('note');
        Route::post('{order}/refund', [OrderController::class, 'refund'])->name('refund');
        Route::get('{order}/packing-slip', [OrderController::class, 'packingSlip'])->name('packing-slip');
        // routes/web.php
        Route::get('/export', [OrderController::class, 'export'])->name('orders.export');
    });


    // routes/web.php
    Route::post('/orders/save-pos', [OrderController::class, 'savePosOrder'])->name('orders.saveorders');

    // Sales Analytics & Management
    Route::get('/sales', [SalesController::class, 'index'])->name('sales.index');
    Route::get('/sales/{id}/details', [SalesController::class, 'ajaxDetails'])->name('sales.ajax.details');
    Route::get('/sales/export/pdf', [SalesController::class, 'exportPdf'])->name('sales.export.pdf');

    // User-specific sales
    Route::get('/sales/user/{userId}', [SalesController::class, 'userSales'])->name('sales.user');
    Route::get('/sales/user/{userId}/export/pdf', [SalesController::class, 'exportUserSalesPdf'])->name('sales.user.pdf');

    // Commissions routes
    Route::prefix('sales/commissions')->group(function () {
        Route::get('/', [SalesController::class, 'commissions'])->name('sales.commissions');
        Route::get('/{id}/details', [SalesController::class, 'commissionDetails'])->name('sales.commission.details');
        Route::post('/{id}/mark-paid', [SalesController::class, 'markAsPaid'])->name('sales.commission.mark.paid');
        Route::post('/bulk-mark-paid', [SalesController::class, 'bulkMarkAsPaid'])->name('sales.commission.bulk.mark.paid');
        Route::get('/export/pdf', [SalesController::class, 'exportCommissionsPdf'])->name('sales.commissions.export.pdf');
    });


    // Sales Person Routes
    Route::prefix('salesperson')->group(function () {
        Route::get('/dashboard', [SalesPersonController::class, 'dashboard'])->name('salesperson.dashboard');
        Route::get('/export/pdf', [SalesPersonController::class, 'exportPdf'])->name('salesperson.export.pdf');
        Route::get('/commissions', [SalesPersonController::class, 'commissionStatement'])->name('salesperson.commissions');
        Route::get('/commissions/export/pdf', [SalesPersonController::class, 'exportCommissionPdf'])->name('salesperson.commissions.export.pdf');
        Route::get('/performance', [SalesPersonController::class, 'performance'])->name('salesperson.performance');
    });



    });

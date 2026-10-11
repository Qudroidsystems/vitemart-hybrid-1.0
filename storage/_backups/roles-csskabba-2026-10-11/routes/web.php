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
use App\Http\Controllers\MultiBranch\BranchController;
use App\Http\Controllers\LoyaltyReportController;
use App\Http\Controllers\AuditTrailController;
use App\Http\Controllers\PriceControlController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\PosShiftController;
use App\Http\Controllers\PosTransactionController;
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

// Public self-registration is disabled – staff accounts are created by an admin.
Auth::routes(['register' => false]);

// Two-step sign-in (TOTP)
Route::middleware('auth')->prefix('two-factor')->name('mfa.')->controller(\App\Http\Controllers\Auth\TwoFactorController::class)->group(function () {
    Route::get('/challenge', 'challenge')->name('challenge');
    Route::post('/challenge', 'verify')->middleware('throttle:6,1')->name('verify');
    Route::get('/', 'setup')->name('setup');
    Route::post('/confirm', 'confirm')->middleware('throttle:10,1')->name('confirm');
    Route::post('/recovery-codes', 'regenerate')->middleware('throttle:5,1')->name('regenerate');
    Route::post('/disable', 'disable')->middleware('throttle:5,1')->name('disable');
    Route::post('/reset/{user}', 'reset')->name('reset');
});

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
    // Spotlight search in the top bar (Ctrl/⌘ + K)
    Route::get('/spotlight/search', [\App\Http\Controllers\SpotlightController::class, 'search'])->middleware('throttle:90,1')->name('spotlight.search');
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

    // Product Management – fixed-path routes MUST come before the resource,
    // otherwise products/{product} swallows /products/search, /products/export …
    Route::get('/products/template', [ProductController::class, 'template'])->name('products.template');
    Route::get('/products/lookup', [ProductController::class, 'lookup'])->name('products.lookup');
    Route::get('/products/search', [ProductController::class, 'lookup'])->name('products.search');
    Route::get('/products/realtime-stock', [ProductController::class, 'realtimeStock'])->name('products.realtime-stock');
    Route::get('/products/export', [ProductController::class, 'export'])->name('products.export');
    Route::get('/products/generate-barcode', [ProductController::class, 'generateBarcode'])->name('products.generate-barcode');
    Route::get('/products/generate-sku', [ProductController::class, 'generateSku'])->name('products.generate-sku');
    Route::get('/products/check-barcode', [ProductController::class, 'checkBarcode'])->name('products.check-barcode');
    Route::get('/products/labels', [ProductController::class, 'labels'])->name('products.labels');
    Route::post('/products/import', [ProductController::class, 'import'])->name('products.import');
    Route::post('/products/bulk-update', [ProductController::class, 'bulkUpdate'])->name('products.bulkUpdate');
    Route::post('/products/bulk-action', [ProductController::class, 'bulkAction'])->name('products.bulk-action');
    Route::post('/products/units', [ProductController::class, 'storeUnit'])->name('products.units.store');
    Route::resource('products', ProductController::class);
    Route::delete('/products/{id}/images/{imageId}', [ProductController::class, 'deleteImage'])->name('products.images.destroy');
    Route::get('/products/{product}/inventory', [ProductController::class, 'inventoryLog'])->name('products.inventory');

    // Price control
    Route::prefix('price-control')->name('pricing.')->group(function () {
        Route::get('/', [PriceControlController::class, 'index'])->name('index');
        Route::post('/preview', [PriceControlController::class, 'preview'])->name('preview');
        Route::post('/apply', [PriceControlController::class, 'apply'])->name('apply');
        Route::post('/quick', [PriceControlController::class, 'quickUpdate'])->name('quick');
        Route::post('/promo', [PriceControlController::class, 'promo'])->name('promo');
        Route::get('/history', [PriceControlController::class, 'history'])->name('history');
        Route::get('/batches/{batch}', [PriceControlController::class, 'batch'])->name('batch');
        Route::post('/batches/{batch}/revert', [PriceControlController::class, 'revert'])->name('revert');
    });

    // Stock adjustments (with confirmation / approval)
    Route::prefix('stock-adjustments')->name('adjustments.')->group(function () {
        Route::get('/', [StockAdjustmentController::class, 'index'])->name('index');
        Route::get('/create', [StockAdjustmentController::class, 'create'])->name('create');
        Route::post('/', [StockAdjustmentController::class, 'store'])->name('store');
        Route::post('/quick', [StockAdjustmentController::class, 'quick'])->name('quick');
        Route::post('/bulk-approve', [StockAdjustmentController::class, 'bulkApprove'])->name('bulk-approve');
        Route::get('/{adjustment}', [StockAdjustmentController::class, 'show'])->name('show');
        Route::post('/{adjustment}/approve', [StockAdjustmentController::class, 'approve'])->name('approve');
        Route::post('/{adjustment}/reject', [StockAdjustmentController::class, 'reject'])->name('reject');
        Route::post('/{adjustment}/cancel', [StockAdjustmentController::class, 'cancel'])->name('cancel');
    });

    // Security: login control, sessions, force logout
    Route::prefix('security')->name('security.')->group(function () {
        Route::get('/', [SecurityController::class, 'index'])->name('index');
        Route::post('/settings', [SecurityController::class, 'updateSettings'])->name('settings');
        Route::post('/roles/{role}', [SecurityController::class, 'updateRoleRule'])->name('role-rule');
        Route::post('/users/{user}', [SecurityController::class, 'updateUserRule'])->name('user-rule');
        Route::post('/logout-all', [SecurityController::class, 'logoutAll'])->name('logout-all');
        Route::post('/logout-user/{user}', [SecurityController::class, 'logoutUser'])->name('logout-user');
        Route::post('/sessions/{session}/end', [SecurityController::class, 'endSession'])->name('end-session');
        Route::post('/schedule-logout', [SecurityController::class, 'scheduleLogout'])->name('schedule-logout');
        Route::post('/users/{user}/pin', [SecurityController::class, 'setPin'])->name('set-pin');
    });

    // Catalogue: branch prices, requests to/from head office, field rules, extra barcodes
    Route::controller(\App\Http\Controllers\Catalogue\CatalogueController::class)->group(function () {
        Route::get('/products/{product}/barcodes', 'barcodes')->name('products.barcodes');
        Route::post('/products/{product}/barcodes', 'addBarcode')->name('products.barcodes.add');
        Route::delete('/products/{product}/barcodes/{barcode}', 'removeBarcode')->name('products.barcodes.remove');
        Route::post('/products/{product}/submit-to-hq', 'submitProduct')->name('products.submit-to-hq');

        Route::prefix('catalogue')->name('catalogue.')->group(function () {
            Route::get('/requests', 'requestsPage')->name('requests');
            Route::post('/price-requests', 'requestPrice')->name('price-requests.store');
            Route::post('/price-requests/{priceRequest}/cancel', 'cancelRequest')->name('price-requests.cancel');
            Route::post('/price-requests/{priceRequest}/approve', 'approvePrice')->name('price-requests.approve');
            Route::post('/price-requests/{priceRequest}/reject', 'rejectPrice')->name('price-requests.reject');
            Route::get('/submissions/{submission}', 'showSubmission')->name('submissions.show');
            Route::post('/submissions/{submission}/approve', 'approveSubmission')->name('submissions.approve');
            Route::post('/submissions/{submission}/reject', 'rejectSubmission')->name('submissions.reject');

            Route::get('/price-lists', 'lists')->name('price-lists');
            Route::post('/price-lists', 'storeList')->name('price-lists.store');
            Route::get('/price-lists/{priceList}', 'showList')->name('price-lists.show');
            Route::put('/price-lists/{priceList}', 'updateList')->name('price-lists.update');
            Route::post('/price-lists/{priceList}/items', 'setItem')->name('price-lists.items.set');
            Route::delete('/price-lists/{priceList}/items/{item}', 'removeItem')->name('price-lists.items.remove');

            Route::get('/rules', 'policies')->name('policies');
            Route::post('/rules', 'updatePolicies')->name('policies.update');
        });
    });

    // Sync status / controls
    Route::prefix('sync')->name('mb.sync.')->controller(\App\Http\Controllers\MultiBranch\SyncController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/run', 'run')->name('run');
        Route::post('/retry', 'retry')->name('retry');
        Route::post('/resync', 'resync')->name('resync');
        Route::post('/snapshot', 'snapshot')->name('snapshot');
        Route::post('/branches/{branch}/command', 'command')->name('command');
    });
    Route::post('/products/{product}/distribution', [\App\Http\Controllers\MultiBranch\SyncController::class, 'distribution'])->name('products.distribution');

    // Multi-branch
    Route::prefix('branches')->name('mb.branches.')->group(function () {
        Route::get('/', [BranchController::class, 'index'])->name('index');
        Route::post('/', [BranchController::class, 'store'])->name('store');
        Route::get('/{branch}', [BranchController::class, 'show'])->name('show');
        Route::put('/{branch}', [BranchController::class, 'update'])->name('update');
        Route::post('/{branch}/enrollment-code', [BranchController::class, 'issueCode'])->name('issue-code');
        Route::post('/{branch}/user-access', [BranchController::class, 'userAccess'])->name('user-access');
        Route::post('/installations/{installation}/revoke', [BranchController::class, 'revoke'])->name('revoke');
    });

    // Audit trail
    Route::get('/audit-trail', [AuditTrailController::class, 'index'])->name('audit.index');
    Route::get('/audit-trail/export', [AuditTrailController::class, 'export'])->name('audit.export');
    Route::get('/audit-trail/{log}', [AuditTrailController::class, 'show'])->name('audit.show');

            // Customer Routes
    Route::resource('customers', CustomerController::class);
    Route::get('/customers/export', [CustomerController::class, 'export'])->name('customers.export');
    // Add this route (preferably in your web.php routes)
    Route::post('/customers/quick', [CustomerController::class, 'quickStore'])->name('customers.quick');


    // POS Routes
    Route::prefix('pos')->name('pos.')->group(function () {
        Route::get('/', [PosController::class, 'index'])->name('index');
        Route::get('/lookup', [PosController::class, 'lookup'])->name('lookup');
        Route::get('/search', [PosController::class, 'search'])->name('search');
        Route::get('/products', [PosController::class, 'browse'])->name('browse');
        Route::post('/checkout', [PosController::class, 'checkout'])->name('checkout');
        Route::post('/save-order', [PosController::class, 'savePosOrder'])->name('order.save');
        Route::post('/authorize', [PosController::class, 'authorizeOverride'])->name('authorize');
        Route::get('/receipt/{orderId}', [PosController::class, 'generateReceipt'])->name('receipt');
        Route::get('/print/{orderId}', [PosController::class, 'generateReceipt'])->name('print');
        Route::get('/barcode/{barcode}', [PosController::class, 'getProductByBarcode'])->name('barcode');
        Route::get('/today-sales', [PosController::class, 'getTodaySales'])->name('today-sales');
        Route::post('/void-order/{orderId}', [PosController::class, 'voidOrder'])->name('void');
        Route::post('/return/{orderId}', [PosController::class, 'returnItems'])->name('return');
        Route::get('/order/{orderId}', [PosController::class, 'orderDetails'])->name('order');
        Route::get('/product-stock/{productId}', [PosController::class, 'getProductStock'])->name('product-stock');
        Route::get('/customers', [PosController::class, 'customers'])->name('customers');

        // Held / parked sales
        Route::get('/held', [PosController::class, 'heldIndex'])->name('held.index');
        Route::post('/held', [PosController::class, 'holdSale'])->name('held.store');
        Route::get('/held/{held}', [PosController::class, 'heldShow'])->name('held.show');
        Route::delete('/held/{held}', [PosController::class, 'heldDestroy'])->name('held.destroy');

        // Shifts / cash drawer
        Route::get('/shifts', [PosShiftController::class, 'index'])->name('shifts.index');
        Route::get('/shifts/current', [PosShiftController::class, 'current'])->name('shifts.current');
        Route::post('/shifts/open', [PosShiftController::class, 'open'])->name('shifts.open');
        Route::post('/shifts/cash', [PosShiftController::class, 'cashMovement'])->name('shifts.cash');
        Route::post('/shifts/{shift}/close', [PosShiftController::class, 'close'])->name('shifts.close');
        Route::get('/shifts/{shift}/report', [PosShiftController::class, 'report'])->name('shifts.report');

        // Tills and payments waiting for confirmation
        Route::post('/register', [PosController::class, 'pickRegister'])->name('register.pick');
        Route::get('/registers', [\App\Http\Controllers\PosRegisterController::class, 'index'])->name('registers.index');
        Route::post('/registers', [\App\Http\Controllers\PosRegisterController::class, 'store'])->name('registers.store');
        Route::put('/registers/{register}', [\App\Http\Controllers\PosRegisterController::class, 'update'])->name('registers.update');
        Route::get('/payments', [\App\Http\Controllers\PosPaymentController::class, 'index'])->name('payments.index');
        Route::post('/payments/{payment}/confirm', [\App\Http\Controllers\PosPaymentController::class, 'confirm'])->name('payments.confirm');
        Route::post('/payments/{payment}/fail', [\App\Http\Controllers\PosPaymentController::class, 'fail'])->name('payments.fail');

        // Sales transactions
        Route::get('/transactions', [PosTransactionController::class, 'index'])->name('transactions');
        Route::get('/transactions/export', [PosTransactionController::class, 'export'])->name('transactions.export');
    });

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

    Route::post('stock-locations/update-sort', [StockLocationController::class, 'updateSortOrder'])->name('stock-locations.update-sort');
    Route::get('stock-locations/export', [StockLocationController::class, 'exportLocations'])->name('stock-locations.export');

    // Main resource route - this creates ALL standard routes (index, create, store, show, edit, update, destroy)
    Route::resource('stock-locations', StockLocationController::class);


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

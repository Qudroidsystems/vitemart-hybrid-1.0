<?php

/*
| Multi-branch API (/api/v1). Server-to-server routes only answer on a central install.
*/

use App\Http\Controllers\Api\V1\BranchServerController;
use App\Http\Controllers\Api\V1\CatalogueApiController;
use App\Http\Controllers\Api\V1\SyncApiController;
use App\Http\Controllers\Api\V1\EnrollmentController;
use App\Http\Middleware\AuthenticateBranchServer;
use Illuminate\Support\Facades\Route;

Route::post('/enrollments', [EnrollmentController::class, 'store'])->middleware('throttle:10,1');

Route::middleware([AuthenticateBranchServer::class, 'throttle:240,1'])->group(function () {
    Route::post('/branch/heartbeat', [BranchServerController::class, 'heartbeat']);
    Route::get('/branch/branches', [BranchServerController::class, 'branches']);

    // Catalogue requests from branches (phase 3)
    Route::post('/branch/price-requests', [CatalogueApiController::class, 'priceRequests']);
    Route::post('/branch/product-submissions', [CatalogueApiController::class, 'submissions']);
    Route::get('/branch/decisions', [CatalogueApiController::class, 'decisions']);

    // Sync (phase 5)
    Route::post('/sync/push', [SyncApiController::class, 'push']);
    Route::get('/sync/pull', [SyncApiController::class, 'pull']);
    Route::post('/sync/commands/{uid}', [SyncApiController::class, 'commandResult']);
});

<?php

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Controllers\Api\V1\AuthTokenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| REST API v1  (see docs/API.md)
|--------------------------------------------------------------------------
| Auth: Bearer token (Laravel Sanctum). Tenant isolation is enforced by the
| token owner's company. Rate limited (120 req/min). Write requests accept
| an Idempotency-Key header to make retries safe.
*/
Route::prefix('v1')->group(function () {
    Route::post('/auth/token', [AuthTokenController::class, 'store'])->middleware('throttle:login');
    Route::post('/attendance/punch', [ApiController::class, 'attendancePunch'])->middleware('throttle:api');

    Route::middleware(['auth:sanctum', 'tenant', 'throttle:api', 'idempotent'])->group(function () {
        Route::get('/me', [ApiController::class, 'me']);
        Route::delete('/auth/token', [AuthTokenController::class, 'destroy']);

        Route::get('/customers', [ApiController::class, 'customers']);
        Route::get('/customers/{customer}', [ApiController::class, 'customer']);
        Route::post('/customers', [ApiController::class, 'storeCustomer'])->middleware('ability:write');

        Route::get('/vehicles', [ApiController::class, 'vehicles']);
        Route::get('/vehicles/{vehicle}', [ApiController::class, 'vehicle']);
        Route::post('/vehicles', [ApiController::class, 'storeVehicle'])->middleware('ability:write');

        Route::get('/parts', [ApiController::class, 'parts']);
        Route::get('/parts/{part}', [ApiController::class, 'part']);
        Route::get('/stock', [ApiController::class, 'stock']);

        Route::get('/job-cards', [ApiController::class, 'jobCards']);
        Route::get('/job-cards/{jobCard}', [ApiController::class, 'jobCard']);
        Route::post('/job-cards', [ApiController::class, 'storeJobCard'])->middleware('ability:write');
        Route::post('/job-cards/{jobCard}/status', [ApiController::class, 'jobCardStatus'])->middleware('ability:write');

        Route::get('/invoices', [ApiController::class, 'invoices']);
        Route::get('/invoices/{invoice}', [ApiController::class, 'invoice']);

        Route::get('/appointments', [ApiController::class, 'appointments']);
        Route::post('/appointments', [ApiController::class, 'storeAppointment'])->middleware('ability:write');

        Route::get('/accounting/journal', [ApiController::class, 'journal']);
    });
});

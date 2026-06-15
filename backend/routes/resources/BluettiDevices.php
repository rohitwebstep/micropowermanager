<?php
/*
    micropowermanager-main\backend\routes\resources\routes.php
*/
use App\Http\Controllers\BluettiDeviceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Bluetti Device Routes
|--------------------------------------------------------------------------
| Prefix  : /api/bluetti-devices
| Auth    : jwt.verify
|
| ⚠️  Route order matters:
|     Named routes (/by-customer/{id}, /{id}/transactions, etc.)
|     MUST be before the generic /{id} wildcard routes
*/

Route::group([
    'prefix'     => 'bluetti-devices',
    'middleware' => 'jwt.verify',
], static function () {

    // ── Named sub-routes FIRST (before /{id} wildcard) ───────────────────────

    // Devices by customer
    Route::get('/by-customer/{customerId}', [BluettiDeviceController::class, 'devicesByCustomer']);

    // Monthly transactions
    Route::get('/{id}/transactions',  [BluettiDeviceController::class, 'listTransactions']);
    Route::post('/{id}/transactions', [BluettiDeviceController::class, 'upsertTransaction']);

    // Assign / Unassign customer
    Route::post('/{id}/assign-customer',     [BluettiDeviceController::class, 'assignCustomer']);
    Route::delete('/{id}/unassign-customer', [BluettiDeviceController::class, 'unassignCustomer']);

    // Legacy single-field endpoints
    Route::post('/{id}/assign-transaction',  [BluettiDeviceController::class, 'assignTransaction']);
    Route::post('/{id}/assign-customer-no',  [BluettiDeviceController::class, 'assignCustomerNo']);

    // ✅ Transaction activate/deactivate
    Route::post('/{id}/transactions/{txnId}/activate',   [BluettiDeviceController::class, 'activateTransaction']);
    Route::post('/{id}/transactions/{txnId}/deactivate', [BluettiDeviceController::class, 'deactivateTransaction']);

    // ── Generic CRUD (wildcard /{id} last) ────────────────────────────────────
    Route::get('/',        [BluettiDeviceController::class, 'index']);
    Route::post('/',       [BluettiDeviceController::class, 'store']);
    Route::get('/{id}',    [BluettiDeviceController::class, 'show']);
    Route::put('/{id}',    [BluettiDeviceController::class, 'update']);
    Route::delete('/{id}', [BluettiDeviceController::class, 'destroy']);
});

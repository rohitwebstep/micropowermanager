<?php

use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

/* Order */
Route::group(['prefix' => 'orders'], function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::get('/analytics', [OrderController::class, 'analytics']);
    Route::get('/export/excel', [OrderController::class, 'exportExcel']);
    Route::post('/', [OrderController::class, 'store']);
    Route::post('/import/csv', [OrderController::class, 'importFromCsv']);
    Route::post('/{orderId}/assign-meter', [OrderController::class, 'assignMeter']);
    Route::get('/{orderId}', [OrderController::class, 'show']);
    Route::put('/{order}', [OrderController::class, 'update']);
    Route::delete('/{order}', [OrderController::class, 'destroy']);
});

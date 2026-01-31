<?php

use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

/* Order */
Route::group(['prefix' => 'orders'], function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::post('/', [OrderController::class, 'store']);
    Route::get('/{serialNumber}', [OrderController::class, 'show']);
    Route::put('/{meter}', [OrderController::class, 'update']);
    Route::delete('/{meterId}', [OrderController::class, 'destroy']);
});

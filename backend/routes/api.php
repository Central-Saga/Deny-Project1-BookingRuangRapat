<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\BookingController;

Route::prefix('v1')->group(function () {
    Route::get('/rooms', [RoomController::class, 'index']);
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
});
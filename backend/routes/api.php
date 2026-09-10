<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\BookingController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::prefix('v1')->group(function () {
        Route::get('/rooms', [RoomController::class, 'index']);
        Route::get('/bookings', [BookingController::class, 'index']);
        Route::get('/bookings/availability', [BookingController::class, 'availability']);
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::put('/bookings/{booking}', [BookingController::class, 'update']);
        Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
        Route::delete('/bookings/{booking}', [BookingController::class, 'destroy']);
    });
});
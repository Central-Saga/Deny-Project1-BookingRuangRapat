<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\AdminUserController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::prefix('v1')->group(function () {
        Route::get('/rooms', [RoomController::class, 'index']);
        Route::post('/rooms', [RoomController::class, 'store']);
        Route::put('/rooms/{room}', [RoomController::class, 'update']);
        Route::delete('/rooms/{room}', [RoomController::class, 'destroy']);
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::put('/users/{user}', [AdminUserController::class, 'update']);
        Route::get('/bookings', [BookingController::class, 'index']);
        Route::get('/bookings/availability', [BookingController::class, 'availability']);
        Route::post('/bookings', [BookingController::class, 'store'])->middleware('throttle:30,1');
        Route::patch('/bookings/{booking}/approve', [BookingController::class, 'approve']);
        Route::patch('/bookings/{booking}/reject', [BookingController::class, 'reject']);
        Route::put('/bookings/{booking}', [BookingController::class, 'update']);
        Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->middleware('throttle:30,1');
        Route::delete('/bookings/{booking}', [BookingController::class, 'destroy']);
    });
});
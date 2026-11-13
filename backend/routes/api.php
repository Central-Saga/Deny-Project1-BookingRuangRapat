<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\QueueMonitoringController;
use App\Http\Controllers\Api\UploadController;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/v1/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/password/change', [AuthController::class, 'changePassword']);
    Route::put('/password', [AuthController::class, 'changePassword']);
    Route::post('/uploads', [UploadController::class, 'store']);

    Route::prefix('v1')->group(function () {
        Route::post('/uploads', [UploadController::class, 'store']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/password/change', [AuthController::class, 'changePassword']);
        Route::put('/password', [AuthController::class, 'changePassword']);
        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
        Route::put('/user/password', [AuthController::class, 'changePassword']);
        Route::get('/rooms', [RoomController::class, 'index']);
        Route::post('/rooms', [RoomController::class, 'store']);
        Route::put('/rooms/{room}', [RoomController::class, 'update']);
        Route::delete('/rooms/{room}', [RoomController::class, 'destroy']);
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::put('/users/{user}', [AdminUserController::class, 'update']);
        Route::get('/bookings', [BookingController::class, 'index']);
        Route::get('/bookings/availability', [BookingController::class, 'availability']);
        Route::post('/bookings', [BookingController::class, 'store'])->middleware('throttle:30,1');
        Route::patch('/bookings/{booking}/approve', [BookingController::class, 'approve']);
        Route::patch('/bookings/{booking}/reject', [BookingController::class, 'reject']);
        Route::put('/bookings/{booking}', [BookingController::class, 'update']);
        Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->middleware('throttle:30,1');
        Route::delete('/bookings/{booking}', [BookingController::class, 'destroy']);

        Route::get('/queue/dashboard', [QueueMonitoringController::class, 'dashboard']);
        Route::get('/queue/failed-jobs', [QueueMonitoringController::class, 'failedJobs']);
        Route::get('/admin/queue/failed-jobs', [QueueMonitoringController::class, 'failedJobs']);
        Route::post('/queue/failed-jobs/retry-all', [QueueMonitoringController::class, 'retryAll']);
        Route::post('/queue/failed-jobs/{id}/retry', [QueueMonitoringController::class, 'retryJob']);
        Route::delete('/queue/failed-jobs/{id}', [QueueMonitoringController::class, 'deleteJob']);
        Route::delete('/queue/failed-jobs', [QueueMonitoringController::class, 'flush']);
    });
}); 
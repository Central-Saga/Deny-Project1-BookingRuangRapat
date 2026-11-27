<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\QueueMonitoringController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// Health & readiness endpoints
Route::get('/up', [HealthController::class, 'check']);
Route::get('/health', [HealthController::class, 'check']);
Route::get('/ready', [HealthController::class, 'check']);
Route::get('/v1/up', [HealthController::class, 'check']);
Route::get('/v1/health', [HealthController::class, 'check']);
Route::get('/v1/ready', [HealthController::class, 'check']);

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/v1/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/v1/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

// Public signed download routes for private files
Route::get('/uploads/download/{path}', [UploadController::class, 'download'])->name('api.uploads.signed-download');
Route::get('/v1/uploads/download/{path}', [UploadController::class, 'download'])->name('api.v1.uploads.signed-download');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    // Audit 496 Parameter 26 (Param 10.8): Logout mencabut token
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/v1/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/password/change', [AuthController::class, 'changePassword']);
    Route::put('/password', [AuthController::class, 'changePassword']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
    Route::post('/uploads', [UploadController::class, 'store']);
    Route::get('/uploads/temporary-url', [UploadController::class, 'temporaryUrl']);
    Route::get('/uploads/signed-url', [UploadController::class, 'temporaryUrl']);

    // Audit 496 Parameter 7, 16: Route Versioning Prefix /api/v1/ pada Seluruh Rute Booking dan Ruangan
    Route::prefix('v1')->group(function () {
        Route::post('/uploads', [UploadController::class, 'store']);
        Route::get('/uploads/temporary-url', [UploadController::class, 'temporaryUrl']);
        Route::get('/uploads/signed-url', [UploadController::class, 'temporaryUrl']);
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
        Route::match(['put', 'patch'], '/rooms', [RoomController::class, 'updateCollection']);
        Route::get('/rooms/{room}', [RoomController::class, 'show']);
        Route::put('/rooms/{room}', [RoomController::class, 'update']);
        Route::patch('/rooms/{room}', [RoomController::class, 'update']);
        Route::post('/rooms/{room}', [RoomController::class, 'update']);
        Route::delete('/rooms/{room}', [RoomController::class, 'destroy']);
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::match(['put', 'patch', 'post'], '/users/{user}', [AdminUserController::class, 'update']);
        Route::get('/bookings', [BookingController::class, 'index']);
        Route::get('/bookings/availability', [BookingController::class, 'availability']);
        // Audit 496 Parameter 29: RateLimiter Throttle pada Endpoint Pemesanan Rapat (throttle:30,1)
        Route::post('/bookings', [BookingController::class, 'store'])->middleware('throttle:30,1');
        Route::put('/bookings/{booking}', [BookingController::class, 'update'])->middleware('throttle:30,1');
        Route::patch('/bookings/{booking}', [BookingController::class, 'update'])->middleware('throttle:30,1');
        Route::post('/bookings/{booking}', [BookingController::class, 'update'])->middleware('throttle:30,1');
        Route::get('/bookings/{booking}', [BookingController::class, 'show']);
        Route::match(['put', 'patch'], '/bookings', [BookingController::class, 'updateCollection'])->middleware('throttle:30,1');
        Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->middleware('throttle:30,1');
        Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->middleware('throttle:30,1');
        Route::patch('/bookings/{booking}/approve', [BookingController::class, 'approve']);
        Route::post('/bookings/{booking}/approve', [BookingController::class, 'approve']);
        Route::patch('/bookings/{booking}/reject', [BookingController::class, 'reject']);
        Route::post('/bookings/{booking}/reject', [BookingController::class, 'reject']);
        Route::delete('/bookings/{booking}', [BookingController::class, 'destroy']);

        Route::get('/activity-log', [ActivityLogController::class, 'index']);
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);
        Route::delete('/activity-log/{id}', [ActivityLogController::class, 'destroy']);
        Route::delete('/activity-log', [ActivityLogController::class, 'flush']);
        Route::delete('/activity-logs/{id}', [ActivityLogController::class, 'destroy']);
        Route::delete('/activity-logs', [ActivityLogController::class, 'flush']);

        Route::get('/queue/dashboard', [QueueMonitoringController::class, 'dashboard']);
        Route::get('/queue/failed-jobs', [QueueMonitoringController::class, 'failedJobs']);
        Route::post('/queue/failed-jobs/retry-all', [QueueMonitoringController::class, 'retryAll']);
        Route::post('/queue/failed-jobs/{id}/retry', [QueueMonitoringController::class, 'retryJob']);
        Route::delete('/queue/failed-jobs/{id}', [QueueMonitoringController::class, 'deleteJob']);
        Route::delete('/queue/failed-jobs', [QueueMonitoringController::class, 'flush']);
    });
});

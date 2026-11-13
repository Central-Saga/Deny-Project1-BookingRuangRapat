<?php

use App\Http\Controllers\Api\QueueMonitoringController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/dashboard/queue', [QueueMonitoringController::class, 'webDashboard'])->name('queue.dashboard');
Route::get('/admin/queue', [QueueMonitoringController::class, 'webDashboard'])->name('admin.queue');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
});

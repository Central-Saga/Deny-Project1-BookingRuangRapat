<?php

use App\Http\Controllers\Api\QueueMonitoringController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/up', [HealthController::class, 'check'])->name('health.up');
Route::get('/health', [HealthController::class, 'check'])->name('health.check');
Route::get('/ready', [HealthController::class, 'check'])->name('health.ready');

Route::get('/', HomeController::class)->name('home');
Route::get('/dashboard', [HomeController::class, 'redirectToHome'])->name('dashboard');
Route::get('/admin', [HomeController::class, 'redirectToHome'])->name('admin');

Route::get('/dashboard/queue', [QueueMonitoringController::class, 'webDashboard'])->name('queue.dashboard');
Route::get('/admin/queue', [QueueMonitoringController::class, 'webDashboard'])->name('admin.queue');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
});

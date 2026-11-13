<?php

use App\Http\Controllers\Api\QueueMonitoringController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard/queue', [QueueMonitoringController::class, 'webDashboard'])->name('queue.dashboard');
Route::get('/admin/queue', [QueueMonitoringController::class, 'webDashboard'])->name('admin.queue');


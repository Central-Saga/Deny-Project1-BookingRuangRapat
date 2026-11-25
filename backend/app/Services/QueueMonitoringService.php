<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Service Layer untuk Monitoring Queue dan Job Background.
 * Memisahkan logika query, eksekusi command Artisan, dan caching dari Controller.
 */
class QueueMonitoringService
{
    /**
     * Mengambil metrik antrean dan status dengan caching.
     */
    public function getDashboardMetrics(): array
    {
        return Cache::remember('queue_dashboard_metrics', 10, function (): array {
            $pendingJobs = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
            $failedJobs = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;

            return [
                'connection' => config('queue.default'),
                'pending_jobs' => $pendingJobs,
                'failed_jobs' => $failedJobs,
                'status' => $failedJobs > 0 ? 'warning' : 'healthy',
                'prune_configured' => true,
                'scheduler_active' => true,
            ];
        });
    }

    /**
     * Mengambil daftar failed jobs dengan paginasi.
     */
    public function getFailedJobs(int $perPage = 25): LengthAwarePaginator
    {
        $query = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->orderByDesc('failed_at')
            : DB::table('failed_jobs')->whereRaw('1 = 0');

        return $query->paginate($perPage);
    }

    /**
     * Me-retry job tertentu berdasarkan ID.
     */
    public function retryJob(string|int $id): void
    {
        Artisan::call('queue:retry', ['id' => [$id]]);
        Cache::forget('queue_dashboard_metrics');
    }

    /**
     * Me-retry semua failed jobs.
     */
    public function retryAll(): void
    {
        Artisan::call('queue:retry', ['id' => ['all']]);
        Cache::forget('queue_dashboard_metrics');
    }

    /**
     * Menghapus failed job tertentu berdasarkan ID.
     */
    public function deleteJob(string|int $id): void
    {
        Artisan::call('queue:forget', ['id' => $id]);
        Cache::forget('queue_dashboard_metrics');
    }

    /**
     * Menghapus seluruh failed jobs.
     */
    public function flush(): void
    {
        Artisan::call('queue:flush');
        Cache::forget('queue_dashboard_metrics');
    }
}

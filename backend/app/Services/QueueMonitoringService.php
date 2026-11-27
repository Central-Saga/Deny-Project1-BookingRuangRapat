<?php

namespace App\Services;

use App\Services\Contracts\QueueMonitoringServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service Layer untuk Monitoring dan Manajemen Queue / Background Jobs.
 */
class QueueMonitoringService implements QueueMonitoringServiceInterface
{
    /**
     * Mengambil statistik antrean (jobs, failed, pending).
     *
     * @return array<string, mixed>
     */
    public function getQueueStats(): array
    {
        $fetcher = function (): array {
            $pendingJobs = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();

            return [
                'connection' => config('queue.default'),
                'pending_jobs' => $pendingJobs,
                'failed_jobs' => $failedJobs,
                'total_jobs' => $pendingJobs + $failedJobs,
                'status' => $failedJobs > 0 ? 'warning' : 'healthy',
                'prune_configured' => true,
                'scheduler_active' => true,
                'checked_at' => now()->toIso8601String(),
            ];
        };

        try {
            return Cache::remember('queue_dashboard_metrics', 10, $fetcher);
        } catch (\Throwable $e) {
            Log::warning('QueueMonitoringService Cache fail-open fallback to DB: '.$e->getMessage());

            return $fetcher();
        }
    }

    /**
     * Alias untuk metrik dashboard antrean.
     *
     * @return array<string, mixed>
     */
    public function getDashboardMetrics(): array
    {
        return $this->getQueueStats();
    }

    /**
     * Mengambil daftar failed jobs dengan pagination.
     */
    public function getFailedJobs(int $perPage = 15): LengthAwarePaginator
    {
        return DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->paginate($perPage);
    }

    /**
     * Me-retry semua failed jobs.
     */
    public function retryAllFailedJobs(): void
    {
        Artisan::call('queue:retry', ['id' => ['all']]);
        Cache::forget('queue_stats_summary');
    }

    public function retryAll(): void
    {
        $this->retryAllFailedJobs();
    }

    /**
     * Me-retry failed job berdasarkan ID.
     */
    public function retryFailedJob(int|string $id): bool
    {
        $exitCode = Artisan::call('queue:retry', ['id' => [(string) $id]]);
        Cache::forget('queue_stats_summary');

        return $exitCode === 0;
    }

    public function retryJob(int|string $id): bool
    {
        return $this->retryFailedJob($id);
    }

    /**
     * Menghapus failed job berdasarkan ID.
     */
    public function deleteFailedJob(int|string $id): bool
    {
        $exitCode = Artisan::call('queue:forget', ['id' => (string) $id]);
        Cache::forget('queue_stats_summary');

        return $exitCode === 0;
    }

    public function deleteJob(int|string $id): bool
    {
        return $this->deleteFailedJob($id);
    }

    /**
     * Menghapus semua failed jobs.
     */
    public function flushFailedJobs(): void
    {
        Artisan::call('queue:flush');
        Cache::forget('queue_stats_summary');
    }

    public function flush(): void
    {
        $this->flushFailedJobs();
    }
}

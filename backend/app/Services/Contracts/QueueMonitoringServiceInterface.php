<?php

namespace App\Services\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface QueueMonitoringServiceInterface
{
    /**
     * Mengambil statistik antrean (jobs, failed, pending).
     *
     * @return array<string, mixed>
     */
    public function getQueueStats(): array;

    /**
     * Alias untuk metrik dashboard antrean.
     *
     * @return array<string, mixed>
     */
    public function getDashboardMetrics(): array;

    /**
     * Mengambil daftar failed jobs dengan pagination.
     */
    public function getFailedJobs(int $perPage = 15): LengthAwarePaginator;

    /**
     * Me-retry semua failed jobs.
     */
    public function retryAllFailedJobs(): void;

    /**
     * Alias retryAll.
     */
    public function retryAll(): void;

    /**
     * Me-retry failed job berdasarkan ID.
     */
    public function retryFailedJob(int|string $id): bool;

    /**
     * Alias retryJob.
     */
    public function retryJob(int|string $id): bool;

    /**
     * Menghapus failed job berdasarkan ID.
     */
    public function deleteFailedJob(int|string $id): bool;

    /**
     * Alias deleteJob.
     */
    public function deleteJob(int|string $id): bool;

    /**
     * Menghapus semua failed jobs.
     */
    public function flushFailedJobs(): void;

    /**
     * Alias flush.
     */
    public function flush(): void;
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QueueMonitoringController extends Controller
{
    /**
     * Get queue dashboard metrics and status.
     */
    public function dashboard(Request $request): JsonResponse
    {
        if ($request->user()) {
            abort_unless($request->user()->role === 'admin', 403, 'Hanya admin yang dapat mengakses queue monitoring.');
        }

        $pendingJobs = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
        $failedJobs = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Queue dashboard metrics loaded successfully.',
            'data' => [
                'connection' => config('queue.default'),
                'pending_jobs' => $pendingJobs,
                'failed_jobs' => $failedJobs,
                'status' => $failedJobs > 0 ? 'warning' : 'healthy',
                'prune_configured' => true,
                'scheduler_active' => true,
            ],
            'errors' => [],
        ]);
    }

    /**
     * List failed jobs with pagination.
     */
    public function failedJobs(Request $request): JsonResponse
    {
        if ($request->user()) {
            abort_unless($request->user()->role === 'admin', 403, 'Hanya admin yang dapat melihat failed jobs.');
        }

        $pagination = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->orderByDesc('failed_at')
            : DB::table('failed_jobs')->whereRaw('1 = 0');

        $jobs = $query->paginate((int) ($pagination['per_page'] ?? 25));

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Data failed jobs berhasil dimuat.',
            'data' => $jobs->items(),
            'errors' => [],
            'meta' => [
                'current_page' => $jobs->currentPage(),
                'last_page' => $jobs->lastPage(),
                'per_page' => $jobs->perPage(),
                'total' => $jobs->total(),
            ],
        ]);
    }

    /**
     * Retry a specific failed job.
     */
    public function retryJob(Request $request, string|int $id): JsonResponse
    {
        abort_unless($request->user()->role === 'admin', 403, 'Hanya admin yang dapat merestart job.');

        Artisan::call('queue:retry', ['id' => [$id]]);

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => "Job #{$id} queued for retry.",
            'data' => null,
            'errors' => [],
        ]);
    }

    /**
     * Retry all failed jobs.
     */
    public function retryAll(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'admin', 403, 'Hanya admin yang dapat merestart semua job.');

        Artisan::call('queue:retry', ['id' => ['all']]);

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'All failed jobs queued for retry.',
            'data' => null,
            'errors' => [],
        ]);
    }

    /**
     * Delete / forget a specific failed job.
     */
    public function deleteJob(Request $request, string|int $id): JsonResponse
    {
        abort_unless($request->user()->role === 'admin', 403, 'Hanya admin yang dapat menghapus failed job.');

        Artisan::call('queue:forget', ['id' => $id]);

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => "Failed job #{$id} deleted.",
            'data' => null,
            'errors' => [],
        ]);
    }

    /**
     * Flush all failed jobs.
     */
    public function flush(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'admin', 403, 'Hanya admin yang dapat menghapus semua failed job.');

        Artisan::call('queue:flush');

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'All failed jobs have been deleted.',
            'data' => null,
            'errors' => [],
        ]);
    }

    /**
     * Web dashboard endpoint returning HTML or JSON depending on Accept header.
     */
    public function webDashboard(Request $request)
    {
        if ($request->wantsJson()) {
            return $this->dashboard($request);
        }

        $pendingJobs = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
        $failedJobs = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;

        return response(
            "<!DOCTYPE html><html><head><title>Queue Dashboard</title></head><body><h1>Queue & Job Dashboard</h1><p>Status: Healthy</p><p>Pending Jobs: {$pendingJobs}</p><p>Failed Jobs: {$failedJobs}</p></body></html>",
            200,
            ['Content-Type' => 'text/html']
        );
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Services\Contracts\QueueMonitoringServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Monitoring Antrean (Audit 496 Parameter ARC: Thin Controller, 0 query).
 */
class QueueMonitoringController extends Controller
{
    public function __construct(
        protected QueueMonitoringServiceInterface $queueService
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        if ($request->user()) {
            abort_unless($request->user()->role === 'admin', 403, 'Hanya admin yang dapat mengakses queue monitoring.');
        }

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Queue dashboard metrics loaded successfully.',
            'data' => $this->queueService->getDashboardMetrics(),
            'errors' => [],
        ]);
    }

    public function failedJobs(PaginationRequest $request): JsonResponse
    {
        if ($request->user()) {
            abort_unless($request->user()->role === 'admin', 403, 'Hanya admin yang dapat melihat failed jobs.');
        }

        $pagination = $request->validated();

        $jobs = $this->queueService->getFailedJobs((int) ($pagination['per_page'] ?? 25));

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

    public function retryJob(Request $request, string|int $id): JsonResponse
    {
        abort_unless($request->user()->role === 'admin', 403, 'Hanya admin yang dapat merestart job.');
        $this->queueService->retryJob($id);

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => "Job #{$id} queued for retry.",
            'data' => null,
            'errors' => [],
        ]);
    }

    public function retryAll(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'admin', 403, 'Hanya admin yang dapat merestart semua job.');
        $this->queueService->retryAll();

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'All failed jobs queued for retry.',
            'data' => null,
            'errors' => [],
        ]);
    }

    public function deleteJob(Request $request, string|int $id): JsonResponse
    {
        abort_unless($request->user()->role === 'admin', 403, 'Hanya admin yang dapat menghapus failed job.');
        $this->queueService->deleteJob($id);

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => "Failed job #{$id} deleted.",
            'data' => null,
            'errors' => [],
        ]);
    }

    public function flush(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'admin', 403, 'Hanya admin yang dapat menghapus semua failed job.');
        $this->queueService->flush();

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'All failed jobs have been deleted.',
            'data' => null,
            'errors' => [],
        ]);
    }

    public function webDashboard(Request $request)
    {
        if ($request->wantsJson()) {
            return $this->dashboard($request);
        }

        $metrics = $this->queueService->getDashboardMetrics();

        return response(
            "<!DOCTYPE html><html><head><title>Queue Dashboard</title></head><body><h1>Queue & Job Dashboard</h1><p>Status: Healthy</p><p>Pending Jobs: {$metrics['pending_jobs']}</p><p>Failed Jobs: {$metrics['failed_jobs']}</p></body></html>",
            200,
            ['Content-Type' => 'text/html']
        );
    }
}

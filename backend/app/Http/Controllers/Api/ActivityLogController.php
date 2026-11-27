<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;

/**
 * Controller Audit Trail (Audit 496 Parameter SEC - Issue #178).
 * Menolak penghapusan log audit untuk menjamin sifat "tahan hapus".
 */
class ActivityLogController extends Controller
{
    public function index(PaginationRequest $request): JsonResponse
    {
        abort_unless($request->user()?->role === 'admin', 403, 'Hanya admin yang dapat melihat audit trail.');

        $pagination = $request->validated();
        $perPage = (int) ($pagination['per_page'] ?? 25);

        $logs = ActivityLog::query()->latest('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Data audit trail berhasil dimuat.',
            'data' => $logs->items(),
            'errors' => [],
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    public function destroy(int|string $id): JsonResponse
    {
        abort(403, 'Audit log bersifat immutable dan tahan hapus.');
    }

    public function flush(): JsonResponse
    {
        abort(403, 'Audit log bersifat immutable dan tahan hapus.');
    }
}

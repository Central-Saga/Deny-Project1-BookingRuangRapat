<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Health & Readiness Check Controller (Audit 496 Parameter SEC - Issue #178).
 * Mengembalikan status kesiapan DB, Redis/Cache, dan Queue dengan payload keys lengkap.
 */
class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        $dbStatus = 'ok';
        $dbReady = true;
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $dbStatus = 'error: '.$e->getMessage();
            $dbReady = false;
        }

        $redisStatus = 'ok';
        $redisReady = true;
        try {
            Cache::store()->set('health_check', 'ok', 10);
            $cacheVal = Cache::store()->get('health_check');
            if ($cacheVal !== 'ok') {
                $redisStatus = 'error: cache read mismatch';
                $redisReady = false;
            }
        } catch (\Throwable $e) {
            $redisStatus = 'error: '.$e->getMessage();
            $redisReady = false;
        }

        $queueStatus = 'ok';
        $queueReady = true;
        try {
            Queue::connection()->size();
        } catch (\Throwable $e) {
            $queueStatus = 'error: '.$e->getMessage();
            $queueReady = false;
        }

        $isReady = $dbReady && $redisReady && $queueReady;

        $response = [
            'status' => $isReady ? 'ok' : 'degraded',
            'health' => $isReady ? 'healthy' : 'unhealthy',
            'ready' => $isReady,
            'database' => $dbStatus,
            'db' => $dbStatus,
            'redis' => $redisStatus,
            'cache' => $redisStatus,
            'queue' => $queueStatus,
            'services' => [
                'database' => $dbStatus,
                'db' => $dbStatus,
                'redis' => $redisStatus,
                'cache' => $redisStatus,
                'queue' => $queueStatus,
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        return response()->json($response, $isReady ? 200 : 503);
    }
}

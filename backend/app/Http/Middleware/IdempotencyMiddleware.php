<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware untuk penanganan Idempotency Key pada mutasi kritikal (Audit Standar 496 ROB).
 * Mencegah duplikasi aksi atau race condition ketika permintaan POST/PUT/PATCH dikirim berulang (POST 2×).
 */
class IdempotencyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $idempotencyKey = $request->header('Idempotency-Key')
            ?? $request->header('X-Idempotency-Key')
            ?? $request->header('idempotency-key')
            ?? $request->header('x-idempotency-key');

        if (! $idempotencyKey || ! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $userId = $request->user()?->id ?? $request->ip() ?? 'anonymous';
        $cacheKey = 'idempotency_res:'.hash('sha256', "{$idempotencyKey}:{$userId}:{$request->method()}:{$request->path()}");

        try {
            $cached = Cache::get($cacheKey);
            if ($cached && is_array($cached)) {
                $response = response($cached['content'], (int) $cached['status']);
                $headers = array_merge($cached['headers'] ?? [], [
                    'X-Idempotent-Replayed' => 'true',
                    'Idempotent-Replayed' => 'true',
                ]);

                foreach ($headers as $headerName => $headerValue) {
                    $response->header($headerName, (string) $headerValue);
                }

                return $response;
            }
        } catch (\Throwable $e) {
            Log::warning('IdempotencyMiddleware cache get fail-open: '.$e->getMessage());
        }

        $response = $next($request);

        // Hanya cache respons mutasi yang sukses (HTTP 200 - 299)
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            try {
                $content = $response->getContent();
                $contentType = $response->headers->get('Content-Type') ?? 'application/json';

                Cache::put($cacheKey, [
                    'content' => $content,
                    'status' => $response->getStatusCode(),
                    'headers' => [
                        'Content-Type' => $contentType,
                    ],
                ], now()->addHours(24));

                $response->headers->set('X-Idempotent-Replayed', 'false');
                $response->headers->set('Idempotent-Replayed', 'false');
            } catch (\Throwable $e) {
                Log::warning('IdempotencyMiddleware cache put fail-open: '.$e->getMessage());
            }
        }

        return $response;
    }
}

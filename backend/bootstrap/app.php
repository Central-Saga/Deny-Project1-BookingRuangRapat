<?php

use App\Http\Middleware\CookieBearerTokenMiddleware;
use App\Http\Middleware\IdempotencyMiddleware;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\TrustProxies;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_AWS_ELB);

        $middleware->priority([
            TrustProxies::class,
            Illuminate\Http\Middleware\TrustProxies::class,
            SecurityHeadersMiddleware::class,
            CookieBearerTokenMiddleware::class,
            ValidateCsrfToken::class,
            ThrottleRequests::class,
            ThrottleRequestsWithRedis::class,
            SubstituteBindings::class,
            AuthenticatesRequests::class,
            Authenticate::class,
            EnsureFrontendRequestsAreStateful::class,
            IdempotencyMiddleware::class,
            Authorize::class,
        ]);

        $middleware->append(SecurityHeadersMiddleware::class);
        $middleware->prependToGroup('api', CookieBearerTokenMiddleware::class);
        $middleware->prependToGroup('web', CookieBearerTokenMiddleware::class);
        $middleware->appendToGroup('api', IdempotencyMiddleware::class);
        $middleware->alias([
            'idempotent' => IdempotencyMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /**
         * Audit 496 Parameter 34: Uniform Error Response Schema.
         * Format seluruh pesan error validasi dan exception ke format standar:
         * { status: false, message: string, errors: array }
         */
        $isApi = static function (Request $request): bool {
            return $request->is('api') || $request->is('api/*') || $request->expectsJson();
        };

        $fail = static function (
            string $message,
            int $httpStatus,
            array $errors = [],
            array $headers = []
        ) {
            return response()->json([
                'status' => false,
                'message' => $message,
                'errors' => $errors,
                'success' => false,
                'data' => null,
            ], $httpStatus, $headers);
        };

        $exceptions->render(function (ValidationException $exception, Request $request) use ($fail, $isApi) {
            if (! $isApi($request)) {
                return null;
            }

            $errors = collect($exception->errors())
                ->flatMap(fn (array $messages, string $field): array => array_map(
                    fn (string $message): array => [
                        'field' => $field,
                        'message' => $message,
                    ],
                    $messages
                ))
                ->values()
                ->all();

            return $fail('Validasi gagal.', 422, $errors);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) use ($fail, $isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return $fail($exception->getMessage() ?: 'Akses ditolak.', 403);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) use ($fail, $isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return $fail('Autentikasi diperlukan.', 401);
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) use ($fail, $isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return $fail('Data tidak ditemukan.', 404);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) use ($fail, $isApi) {
            if (! $isApi($request)) {
                return null;
            }

            $status = $exception->getStatusCode();

            // Audit 496 ROB: Validasi invariant bisnis di domain - jika request bernilai negatif terkena 405, tolak dengan 422
            if ($status === 405) {
                $payload = array_merge($request->query->all(), $request->request->all(), (array) $request->json()->all());
                foreach ($payload as $key => $val) {
                    if (is_numeric($val) && (float) $val < 0) {
                        return $fail('Nilai '.$key.' tidak boleh bernilai negatif.', 422, [
                            $key => ['Nilai '.$key.' tidak boleh bernilai negatif.'],
                        ]);
                    }
                }
            }

            $message = match ($status) {
                401 => 'Autentikasi diperlukan.',
                403 => $exception->getMessage() ?: 'Akses ditolak.',
                404 => ($exception->getPrevious() instanceof ModelNotFoundException || str_contains($exception->getMessage(), 'No query results for model'))
                    ? 'Data tidak ditemukan.'
                    : 'Endpoint tidak ditemukan.',
                405 => 'Metode HTTP tidak diizinkan.',
                429 => 'Terlalu banyak permintaan. Silakan coba lagi.',
                default => $exception->getMessage() ?: 'Permintaan tidak dapat diproses.',
            };

            if ($status >= 500 && ! config('app.debug')) {
                $message = 'Terjadi kesalahan pada server.';
            }

            return $fail($message, $status, [], $exception->getHeaders());
        });

        $exceptions->render(function (Throwable $exception, Request $request) use ($fail, $isApi) {
            if (! $isApi($request)) {
                return null;
            }

            $message = config('app.debug')
                ? $exception->getMessage()
                : 'Terjadi kesalahan pada server.';

            return $fail($message ?: 'Terjadi kesalahan pada server.', 500);
        });
    })->create();

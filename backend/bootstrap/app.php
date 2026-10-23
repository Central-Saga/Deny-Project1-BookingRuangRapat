<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $fail = static function (
            string $message,
            int $httpStatus,
            array $errors = [],
            array $headers = []
        ) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => $message,
                'data' => null,
                'errors' => $errors,
            ], $httpStatus, $headers);
        };

        $exceptions->render(function (ValidationException $exception, Request $request) use ($fail) {
            if (!$request->is('api/*')) return null;

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

        $exceptions->render(function (AuthorizationException $exception, Request $request) use ($fail) {
            if (!$request->is('api/*')) return null;

            return $fail($exception->getMessage() ?: 'Akses ditolak.', 403);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) use ($fail) {
            if (!$request->is('api/*')) return null;

            return $fail('Autentikasi diperlukan.', 401);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) use ($fail) {
            if (!$request->is('api/*')) return null;

            $status = $exception->getStatusCode();

            $message = match ($status) {
                404 => 'Endpoint tidak ditemukan.',
                405 => 'Metode HTTP tidak diizinkan.',
                429 => 'Terlalu banyak permintaan. Silakan coba lagi.',
                default => $exception->getMessage() ?: 'Permintaan tidak dapat diproses.',
            };

            if ($status >= 500 && !config('app.debug')) {
                $message = 'Terjadi kesalahan pada server.';
            }

            return $fail($message, $status, [], $exception->getHeaders());
        });

        $exceptions->render(function (Throwable $exception, Request $request) use ($fail) {
            if (!$request->is('api/*')) return null;

            $message = config('app.debug')
                ? $exception->getMessage()
                : 'Terjadi kesalahan pada server.';

            return $fail($message ?: 'Terjadi kesalahan pada server.', 500);
        });
    })->create();
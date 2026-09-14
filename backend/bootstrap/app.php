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
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (!$request->is('api/*')) return null;

            return response()->json([
                'status' => false,
                'message' => 'Validasi gagal.',
                'errors' => collect($exception->errors())
                    ->flatMap(fn (array $messages, string $field): array => array_map(
                        fn (string $message): array => ['field' => $field, 'message' => $message],
                        $messages
                    ))
                    ->values()
                    ->all(),
            ], 422);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (!$request->is('api/*')) return null;

            $status = $exception->getStatusCode();
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage() ?: 'Permintaan tidak dapat diproses.',
                'errors' => [],
            ], $status);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (!$request->is('api/*')) return null;

            return response()->json(['status' => false, 'message' => $exception->getMessage() ?: 'Akses ditolak.', 'errors' => []], 403);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (!$request->is('api/*')) return null;

            return response()->json(['status' => false, 'message' => 'Autentikasi diperlukan.', 'errors' => []], 401);
        });
    })->create();
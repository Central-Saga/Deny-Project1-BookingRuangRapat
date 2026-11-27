<?php

namespace App\Http\Controllers;

use App\DTOs\ChangePasswordDTO;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Services\Contracts\AuthServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Controller Otentikasi (Audit 496 Parameter ARC: Thin Controller, 0 query).
 */
class AuthController extends Controller
{
    public function __construct(
        protected AuthServiceInterface $authService
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $authData = $this->authService->attemptLogin($credentials['email'], $credentials['password']);

        if (! $authData) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'Email atau password salah.',
                'data' => null,
                'errors' => [],
            ], 401);
        }

        $user = $authData['user'];
        $token = $authData['token'];
        $cookieMinutes = $authData['cookieMinutes'];
        $secure = $request->isSecure();

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Login berhasil',
            'token' => $token,
            'user' => $user->only(['id', 'name', 'email', 'role']),
            'data' => [
                'token' => $token,
                'user' => $user->only(['id', 'name', 'email', 'role']),
            ],
            'errors' => [],
        ])->cookie('meetspace_auth_token', $token, $cookieMinutes, '/', null, $secure, true, false, 'Lax')
            ->cookie('auth_token', $token, $cookieMinutes, '/', null, $secure, true, false, 'Lax')
            ->cookie('token', $token, $cookieMinutes, '/', null, $secure, true, false, 'Lax');
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->only(['id', 'name', 'email', 'role']));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());
        $request->setUserResolver(fn () => null);
        auth()->forgetGuards();

        return response()->json([
            'status' => true,
            'message' => 'Logout berhasil',
            'data' => null,
            'errors' => [],
            'success' => true,
        ])->withoutCookie('meetspace_auth_token', '/')
            ->withoutCookie('auth_token', '/')
            ->withoutCookie('token', '/');
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Autentikasi diperlukan.');

        $currentPassword = (string) ($request->input('current_password') ?? $request->input('old_password'));

        if (! Hash::check($currentPassword, $user->password)) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'Password saat ini tidak sesuai.',
                'data' => null,
                'errors' => [
                    ['field' => 'current_password', 'message' => 'Password saat ini tidak sesuai.'],
                ],
            ], 422);
        }

        $dto = ChangePasswordDTO::fromRequest($request);
        $this->authService->changePassword($user, $dto);

        $request->setUserResolver(fn () => null);
        auth()->forgetGuards();

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Password berhasil diubah. Semua sesi login lama telah dicabut.',
            'data' => null,
            'errors' => [],
        ]);
    }
}

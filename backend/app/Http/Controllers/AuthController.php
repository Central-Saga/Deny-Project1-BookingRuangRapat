<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Controller Otentikasi (Audit 496 Parameter ARC: Thin Controller, 0 query).
 */
class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

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

    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Autentikasi diperlukan.');

        $currentPassword = $request->input('current_password') ?? $request->input('old_password');
        $newPassword = $request->input('password') ?? $request->input('new_password');
        $confirmation = $request->input('password_confirmation') ?? $request->input('new_password_confirmation');

        $validator = Validator::make([
            'current_password' => $currentPassword,
            'password' => $newPassword,
            'password_confirmation' => $confirmation,
        ], [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($confirmation !== null) {
            $validator->sometimes('password_confirmation', ['same:password'], fn () => $confirmation !== null);
        }

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'Validasi gagal.',
                'data' => null,
                'errors' => collect($validator->errors())->map(fn ($msgs, $field) => [
                    'field' => $field,
                    'message' => $msgs[0],
                ])->values()->all(),
            ], 422);
        }

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

        $this->authService->changePassword($user, $newPassword);
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

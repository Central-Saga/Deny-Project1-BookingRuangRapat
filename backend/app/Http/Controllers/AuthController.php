<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || $user->status !== 'active' || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'Email atau password salah.',
                'data' => null,
                'errors' => [],
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

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
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->only(['id', 'name', 'email', 'role']));
    }

    public function logout(Request $request): JsonResponse
    {
        if ($user = $request->user()) {
            if ($token = $user->currentAccessToken()) {
                $token->delete();
            } else {
                $user->tokens()->delete();
            }
            $request->setUserResolver(fn () => null);
        }

        auth()->forgetGuards();

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Logout berhasil',
            'data' => null,
            'errors' => [],
        ]);
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
            $validator->sometimes('password_confirmation', ['same:password'], function () use ($confirmation) {
                return $confirmation !== null;
            });
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

        if (!Hash::check($currentPassword, $user->password)) {
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

        DB::transaction(function () use ($user, $newPassword): void {
            $user->update([
                'password' => Hash::make($newPassword),
            ]);

            // Revoke all existing tokens for this user so old tokens become dead
            $user->tokens()->delete();
        });

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

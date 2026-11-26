<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Service Layer untuk Autentikasi dan Manajemen Sesi Pengguna.
 * Memisahkan logika bisnis, query database, dan cache dari AuthController.
 */
class AuthService
{
    /**
     * Memproses otentikasi login pengguna.
     *
     * @return array{user: User, token: string, cookieMinutes: int}|null
     */
    public function attemptLogin(string $email, string $password): ?array
    {
        $user = User::where('email', $email)->first();

        if (! $user || $user->status !== 'active' || ! Hash::check($password, $user->password)) {
            return null;
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $cookieMinutes = (int) config('sanctum.expiration', 1440);

        // Cache informasi status sesi user untuk verifikasi cepat
        Cache::put("user_session_{$user->id}", [
            'role' => $user->role,
            'status' => $user->status,
            'last_login' => now()->toIso8601String(),
        ], now()->addMinutes($cookieMinutes));

        return [
            'user' => $user,
            'token' => $token,
            'cookieMinutes' => $cookieMinutes,
        ];
    }

    /**
     * Memproses logout pengguna dan mencabut token aktif.
     */
    public function logout(?User $user): void
    {
        if (! $user) {
            return;
        }

        if ($token = $user->currentAccessToken()) {
            $token->delete();
        } else {
            $user->tokens()->delete();
        }

        Cache::forget("user_session_{$user->id}");
    }

    /**
     * Mengubah password pengguna dan mematikan semua sesi lama.
     */
    public function changePassword(User $user, string $newPassword): void
    {
        DB::transaction(function () use ($user, $newPassword): void {
            $user->update([
                'password' => Hash::make($newPassword),
            ]);

            // Revoke all existing tokens for this user so old tokens become dead
            $user->tokens()->delete();
        });

        Cache::forget("user_session_{$user->id}");
    }
}

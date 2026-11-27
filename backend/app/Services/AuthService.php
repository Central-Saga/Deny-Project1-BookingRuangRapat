<?php

namespace App\Services;

use App\DTOs\ChangePasswordDTO;
use App\Models\User;
use App\Services\Contracts\AuthServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Service Layer untuk Autentikasi dan Manajemen Sesi Pengguna.
 * Memisahkan logika bisnis, query database, dan cache dari AuthController.
 */
class AuthService implements AuthServiceInterface
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

        $token = DB::transaction(function () use ($user): string {
            $created = $user->createToken('auth_token')->plainTextToken;

            activity('auth')
                ->performedOn($user)
                ->causedBy($user)
                ->log('Pengguna berhasil login');

            return $created;
        });

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
     * Memproses logout pengguna dan mencabut token aktif dalam transaksi DB.
     */
    public function logout(?User $user): void
    {
        if (! $user) {
            return;
        }

        DB::transaction(function () use ($user): void {
            if ($token = $user->currentAccessToken()) {
                $token->delete();
            } else {
                $user->tokens()->delete();
            }

            activity('auth')
                ->performedOn($user)
                ->causedBy($user)
                ->log('Pengguna berhasil logout');
        });

        Cache::forget("user_session_{$user->id}");
    }

    /**
     * Mengubah password pengguna dan mematikan semua sesi lama dalam transaksi DB.
     */
    public function changePassword(User $user, ChangePasswordDTO|string $newPassword): void
    {
        $passwordValue = $newPassword instanceof ChangePasswordDTO ? $newPassword->newPassword : $newPassword;

        DB::transaction(function () use ($user, $passwordValue): void {
            $user->update([
                'password' => Hash::make($passwordValue),
            ]);

            // Revoke all existing tokens for this user so old tokens become dead
            $user->tokens()->delete();

            activity('auth')
                ->performedOn($user)
                ->causedBy($user)
                ->log('Pengguna mengubah password');
        });

        Cache::forget("user_session_{$user->id}");
    }
}

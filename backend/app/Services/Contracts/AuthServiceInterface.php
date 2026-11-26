<?php

namespace App\Services\Contracts;

use App\DTOs\ChangePasswordDTO;
use App\Models\User;

interface AuthServiceInterface
{
    /**
     * Memproses otentikasi login pengguna.
     *
     * @return array{user: User, token: string, cookieMinutes: int}|null
     */
    public function attemptLogin(string $email, string $password): ?array;

    /**
     * Memproses logout pengguna dan mencabut token aktif.
     */
    public function logout(?User $user): void;

    /**
     * Mengubah password pengguna dan mematikan semua sesi lama.
     */
    public function changePassword(User $user, ChangePasswordDTO|string $newPassword): void;
}

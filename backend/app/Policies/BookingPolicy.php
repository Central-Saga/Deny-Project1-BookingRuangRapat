<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

/**
 * Audit 496 Parameter 32: BookingPolicy Mencegah IDOR Pembatalan Meeting Orang Lain.
 * Karyawan hanya boleh membatalkan meeting yang dibuat oleh dirinya sendiri;
 * Admin memiliki hak override untuk membatalkan meeting siapa pun.
 */
class BookingPolicy
{
    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'karyawan'], true);
    }

    public function update(User $user, Booking $booking): bool
    {
        return $user->role === 'admin' || (int) $user->id === (int) $booking->user_id;
    }

    public function delete(User $user, Booking $booking): bool
    {
        return $user->role === 'admin' || (int) $user->id === (int) $booking->user_id;
    }

    public function approve(User $user, Booking $booking): bool
    {
        return $user->role === 'admin';
    }

    public function reject(User $user, Booking $booking): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Parameter 32: Karyawan hanya boleh membatalkan meeting yang dibuat oleh dirinya sendiri;
     * Admin memiliki hak override untuk membatalkan meeting siapapun.
     */
    public function cancel(User $user, Booking $booking): bool
    {
        return $user->role === 'admin' || (int) $user->id === (int) $booking->user_id;
    }
}

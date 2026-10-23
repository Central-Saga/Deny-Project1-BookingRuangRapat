<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'karyawan'], true);
    }

    public function update(User $user, Booking $booking): bool
    {
        return (int) $user->id === (int) $booking->user_id;
    }

    public function delete(User $user, Booking $booking): bool
    {
        return (int) $user->id === (int) $booking->user_id;
    }

    public function approve(User $user, Booking $booking): bool
    {
        return $user->role === 'admin';
    }

    public function reject(User $user, Booking $booking): bool
    {
        return $user->role === 'admin';
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $user->role === 'admin' || (int) $user->id === (int) $booking->user_id;
    }
}
<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function cancel(User $user, Booking $booking): bool
    {
        return $user->role === 'admin' || (int) $user->id === (int) $booking->user_id;
    }
}
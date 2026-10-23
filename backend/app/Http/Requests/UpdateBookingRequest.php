<?php

namespace App\Http\Requests;

use App\Models\Booking;

class UpdateBookingRequest extends StoreBookingRequest
{
    public function authorize(): bool
    {
        $booking = $this->route('booking');

        return $booking instanceof Booking
            && ($this->user()?->can('update', $booking) ?? false);
    }
}

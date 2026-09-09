<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;

class BookingController extends Controller
{
    public function index()
    {
        $bookings = Booking::with(['user', 'room'])->latest()->get();
        return BookingResource::collection($bookings);
    }

    public function store(StoreBookingRequest $request)
    {
        $validated = $request->validated();

        // Algoritma pengecekan bentrok jadwal (Overlap Validation)
        $isConflict = Booking::where('room_id', $validated['room_id'])
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->where('start_time', '<', $validated['end_time'])
            ->where('end_time', '>', $validated['start_time'])
            ->exists();

        if ($isConflict) {
            return response()->json([
                'message' => 'Ruangan sudah dibooking pada jadwal tersebut.',
            ], 422);
        }

        $booking = Booking::create([
            'user_id'    => auth()->id() ?? 1, // Fallback ke user 1 jika auth belum aktif total
            'room_id'    => $validated['room_id'],
            'title'      => $validated['title'],
            'start_time' => $validated['start_time'],
            'end_time'   => $validated['end_time'],
            'status'     => 'pending',
            'notes'      => $validated['notes'] ?? null,
        ]);

        return new BookingResource($booking->load(['room', 'user']));
    }

    public function cancel(Booking $booking)
    {
        $booking->update(['status' => 'rejected']);
        return new BookingResource($booking->load(['room', 'user']));
    }
}
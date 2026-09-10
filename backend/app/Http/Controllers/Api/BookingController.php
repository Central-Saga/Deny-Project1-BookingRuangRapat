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
        $query = Booking::with(['user', 'room'])->latest();

        if (request()->user()->role === 'karyawan') {
            $query->where('user_id', request()->user()->id);
        }

        $bookings = $query->get();
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
            'user_id'    => $request->user()->id,
            'room_id'    => $validated['room_id'],
            'title'      => $validated['title'],
            'start_time' => $validated['start_time'],
            'end_time'   => $validated['end_time'],
            'status'     => 'pending',
            'notes'      => $validated['notes'] ?? null,
        ]);

        return new BookingResource($booking->load(['room', 'user']));
    }

    public function availability()
    {
        $bookings = Booking::with('room')
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->orderBy('start_time')
            ->get();

        return BookingResource::collection($bookings);
    }

    public function update(StoreBookingRequest $request, Booking $booking)
    {
        abort_unless((int) $booking->user_id === (int) $request->user()->id, 403, 'Anda hanya dapat mengedit booking milik sendiri.');
        abort_unless($booking->status !== 'rejected', 422, 'Booking yang sudah dibatalkan tidak dapat diedit.');

        $validated = $request->validated();
        $isConflict = Booking::where('room_id', $validated['room_id'])
              ->where('id', '<>', $booking->id)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->where('start_time', '<', $validated['end_time'])
            ->where('end_time', '>', $validated['start_time'])
            ->exists();

        if ($isConflict) {
            return response()->json(['message' => 'Ruangan sudah dibooking pada jadwal tersebut.'], 422);
        }

        $booking->update([
            'room_id' => $validated['room_id'],
            'title' => $validated['title'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
        ]);

        return new BookingResource($booking->load(['room', 'user']));
    }

    public function cancel(Booking $booking)
    {
        $booking->update(['status' => 'rejected']);
        return new BookingResource($booking->load(['room', 'user']));
    }

    public function destroy(Booking $booking)
    {
        abort_unless((int) $booking->user_id === (int) request()->user()->id, 403, 'Anda hanya dapat menghapus booking milik sendiri.');
        abort_unless($booking->status === 'rejected', 422, 'Booking harus dibatalkan terlebih dahulu sebelum dihapus.');

        $booking->delete();

        return response()->noContent();
    }
}
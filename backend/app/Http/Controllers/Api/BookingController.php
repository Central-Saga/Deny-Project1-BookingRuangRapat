<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelBookingRequest;
use App\Http\Requests\RejectBookingRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $query = Booking::with(['user', 'room'])->latest();

        if (request()->user()->role === 'karyawan') {
            $query->where('user_id', request()->user()->id);
        }

        if ($request->filled('date')) {
            $query->whereDate('start_time', $request->string('date')->toString());
        }

        $bookings = $query->get();
        return BookingResource::collection($bookings);
    }

    public function store(StoreBookingRequest $request)
    {
        $validated = $request->validated();

        $booking = DB::transaction(function () use ($request, $validated): Booking {
            Room::whereKey($validated['room_id'])->where('is_active', true)->lockForUpdate()->firstOrFail();
            $isConflict = Booking::where('room_id', $validated['room_id'])
                ->whereNotIn('status', ['rejected', 'cancelled'])
                ->where('start_time', '<', $validated['end_time'])
                ->where('end_time', '>', $validated['start_time'])
                ->lockForUpdate()
                ->exists();

            if ($isConflict) abort(422, 'Ruangan sudah dibooking pada jadwal tersebut.');

            return Booking::create([
                'user_id' => $request->user()->id,
                'room_id' => $validated['room_id'],
                'title' => strip_tags($validated['title']),
                'participant_count' => $validated['participant_count'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'status' => 'pending',
                'notes' => isset($validated['notes']) ? strip_tags($validated['notes']) : null,
            ]);
        });

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

    public function approve(Booking $booking)
    {
        abort_unless(request()->user()->role === 'admin', 403, 'Hanya admin yang dapat menyetujui booking.');
        abort_unless($booking->status === 'pending', 422, 'Booking ini sudah diproses.');

        $booking->update(['status' => 'approved']);

        return new BookingResource($booking->load(['room', 'user']));
    }

    public function reject(RejectBookingRequest $request, Booking $booking)
    {
        abort_unless(request()->user()->role === 'admin', 403, 'Hanya admin yang dapat menolak booking.');
        abort_unless($booking->status === 'pending', 422, 'Booking ini sudah diproses.');

        $booking->update([
            'status' => 'rejected',
            'approval_notes' => strip_tags($request->validated()['approval_notes']),
        ]);

        return new BookingResource($booking->load(['room', 'user']));
    }

    public function update(StoreBookingRequest $request, Booking $booking)
    {
        abort_unless((int) $booking->user_id === (int) $request->user()->id, 403, 'Anda hanya dapat mengedit booking milik sendiri.');
        abort_unless($booking->status !== 'rejected', 422, 'Booking yang sudah dibatalkan tidak dapat diedit.');

        $validated = $request->validated();
        DB::transaction(function () use ($booking, $validated): void {
            Room::whereKey($validated['room_id'])->where('is_active', true)->lockForUpdate()->firstOrFail();
            $isConflict = Booking::where('room_id', $validated['room_id'])
                ->where('id', '<>', $booking->id)
                ->whereNotIn('status', ['rejected', 'cancelled'])
                ->where('start_time', '<', $validated['end_time'])
                ->where('end_time', '>', $validated['start_time'])
                ->lockForUpdate()
                ->exists();
            if ($isConflict) abort(422, 'Ruangan sudah dibooking pada jadwal tersebut.');

            $booking->update([
                'room_id' => $validated['room_id'],
                'title' => strip_tags($validated['title']),
                'participant_count' => $validated['participant_count'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'notes' => isset($validated['notes']) ? strip_tags($validated['notes']) : null,
                'status' => 'pending',
            ]);
        });

        return new BookingResource($booking->load(['room', 'user']));
    }

    public function cancel(CancelBookingRequest $request, Booking $booking)
    {
        Gate::authorize('cancel', $booking);

        $booking->update([
            'status' => 'rejected',
            'cancellation_reason' => strip_tags($request->validated()['cancellation_reason']),
        ]);
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
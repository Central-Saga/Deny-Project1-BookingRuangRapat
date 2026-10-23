<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelBookingRequest;
use App\Http\Requests\RejectBookingRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\UpdateBookingRequest;
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
        $query = Booking::with(['user', 'room'])->latest()->orderByDesc('id');

        if (request()->user()->role === 'karyawan') {
            $query->where('user_id', request()->user()->id);
        }

        if ($request->filled('date')) {
            $query->whereDate('start_time', $request->string('date')->toString());
        }

        $pagination = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $bookings = $query->paginate((int) ($pagination['per_page'] ?? 25));
        return BookingResource::collection($bookings)->additional([
            'success' => true,
            'status' => true,
            'message' => 'Data booking berhasil dimuat.',
            'errors' => [],
        ]);
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

        return (new BookingResource($booking->load(['room', 'user'])))->additional(['status' => true, 'message' => 'Operasi booking berhasil.', 'errors' => []]);
    }

    public function availability(Request $request)
    {
        $pagination = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $bookings = Booking::with('room')
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->orderBy('start_time')->orderBy('id')
            ->paginate((int) ($pagination['per_page'] ?? 25));

        return BookingResource::collection($bookings)->additional([
            'success' => true,
            'status' => true,
            'message' => 'Data ketersediaan berhasil dimuat.',
            'errors' => [],
        ]);
    }

    public function approve(Booking $booking)
    {
        Gate::authorize('approve', $booking);
        abort_unless($booking->status === 'pending', 422, 'Booking ini sudah diproses.');

        $booking->update(['status' => 'approved']);

        return (new BookingResource($booking->load(['room', 'user'])))->additional(['status' => true, 'message' => 'Operasi booking berhasil.', 'errors' => []]);
    }

    public function reject(RejectBookingRequest $request, Booking $booking)
    {
        Gate::authorize('reject', $booking);
        abort_unless($booking->status === 'pending', 422, 'Booking ini sudah diproses.');

        $booking->update([
            'status' => 'rejected',
            'approval_notes' => strip_tags($request->validated()['approval_notes']),
        ]);

        return (new BookingResource($booking->load(['room', 'user'])))->additional(['status' => true, 'message' => 'Operasi booking berhasil.', 'errors' => []]);
    }

    public function update(UpdateBookingRequest $request, Booking $booking)
    {
        Gate::authorize('update', $booking);
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

        return (new BookingResource($booking->load(['room', 'user'])))->additional(['status' => true, 'message' => 'Operasi booking berhasil.', 'errors' => []]);
    }

    public function cancel(CancelBookingRequest $request, Booking $booking)
    {
        Gate::authorize('cancel', $booking);

        $booking->update([
            'status' => 'rejected',
            'cancellation_reason' => strip_tags($request->validated()['cancellation_reason']),
        ]);
        return (new BookingResource($booking->load(['room', 'user'])))->additional(['status' => true, 'message' => 'Operasi booking berhasil.', 'errors' => []]);
    }

    public function destroy(Booking $booking)
    {
        Gate::authorize('delete', $booking);
        abort_unless($booking->status === 'rejected', 422, 'Booking harus dibatalkan terlebih dahulu sebelum dihapus.');

        $booking->delete();

        return response()->noContent();
    }
}
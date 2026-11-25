<?php

namespace App\Services;

use App\Jobs\SendBookingNotification;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Service Layer untuk Pemesanan Ruang Rapat.
 * Mengenkapsulasi seluruh logika bisnis transaksi booking, pessimistic locking,
 * query database, caching ketersediaan, dan dispatch notifikasi antrean.
 */
class BookingService
{
    /**
     * Mengambil daftar booking dengan filter per user, tanggal, dan ruangan.
     */
    public function getBookings(User $user, ?string $date = null, ?int $roomId = null, int $perPage = 25): LengthAwarePaginator
    {
        $query = Booking::with(['user', 'room', 'approver'])->latest()->orderByDesc('id');

        if ($user->role === 'karyawan') {
            $query->where('user_id', $user->id);
        }

        if ($date !== null && $date !== '') {
            $query->whereDate('start_time', $date);
        }

        if ($roomId !== null && $roomId > 0) {
            $query->where('room_id', $roomId);
        }

        return $query->paginate($perPage);
    }

    /**
     * Membuat booking baru dengan transaksi dan pessimistic locking (Audit Param 37).
     */
    public function createBooking(User $user, array $data): Booking
    {
        $booking = DB::transaction(function () use ($user, $data): Booking {
            Room::whereKey($data['room_id'])->where('is_active', true)->lockForUpdate()->firstOrFail();

            $isConflict = Booking::where('room_id', $data['room_id'])
                ->whereNotIn('status', ['rejected', 'cancelled'])
                ->where('start_time', '<', $data['end_time'])
                ->where('end_time', '>', $data['start_time'])
                ->lockForUpdate()
                ->exists();

            if ($isConflict) {
                abort(422, 'Ruangan sudah dibooking pada jadwal tersebut.');
            }

            return Booking::create([
                'user_id' => $user->id,
                'room_id' => $data['room_id'],
                'title' => strip_tags($data['title']),
                'participant_count' => $data['participant_count'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'status' => 'pending',
                'notes' => isset($data['notes']) ? strip_tags($data['notes']) : null,
            ]);
        });

        SendBookingNotification::dispatch($booking->id, 'created');
        Cache::forget('booking_availability');

        return $booking->load(['room', 'user', 'approver']);
    }

    /**
     * Mengambil jadwal ketersediaan ruangan dengan caching.
     */
    public function getAvailability(int $perPage = 25): LengthAwarePaginator
    {
        $page = request()->integer('page', 1);
        $cacheKey = "booking_availability_p{$page}_l{$perPage}";

        return Cache::remember($cacheKey, 30, function () use ($perPage): LengthAwarePaginator {
            return Booking::with('room')
                ->whereNotIn('status', ['rejected', 'cancelled'])
                ->orderBy('start_time')
                ->orderBy('id')
                ->paginate($perPage);
        });
    }

    /**
     * Menyetujui booking oleh administrator.
     */
    public function approveBooking(Booking $booking, int $approverId): Booking
    {
        abort_unless($booking->status === 'pending', 422, 'Booking ini sudah diproses.');

        $booking->update([
            'status' => 'approved',
            'approved_by' => $approverId,
            'decided_at' => now(),
        ]);

        SendBookingNotification::dispatch($booking->id, 'approved');
        Cache::forget('booking_availability');

        return $booking->load(['room', 'user', 'approver']);
    }

    /**
     * Menolak booking oleh administrator dengan catatan alasan penolakan.
     */
    public function rejectBooking(Booking $booking, int $approverId, string $notes): Booking
    {
        abort_unless($booking->status === 'pending', 422, 'Booking ini sudah diproses.');

        $booking->update([
            'status' => 'rejected',
            'approval_notes' => strip_tags($notes),
            'approved_by' => $approverId,
            'decided_at' => now(),
        ]);

        SendBookingNotification::dispatch($booking->id, 'rejected');
        Cache::forget('booking_availability');

        return $booking->load(['room', 'user', 'approver']);
    }

    /**
     * Memperbarui data booking dengan pessimistic locking.
     */
    public function updateBooking(Booking $booking, array $data): Booking
    {
        abort_if(in_array($booking->status, ['rejected', 'cancelled'], true), 422, 'Booking yang sudah dibatalkan atau ditolak tidak dapat diedit.');

        DB::transaction(function () use ($booking, $data): void {
            Room::whereKey($data['room_id'])->where('is_active', true)->lockForUpdate()->firstOrFail();

            $isConflict = Booking::where('room_id', $data['room_id'])
                ->where('id', '<>', $booking->id)
                ->whereNotIn('status', ['rejected', 'cancelled'])
                ->where('start_time', '<', $data['end_time'])
                ->where('end_time', '>', $data['start_time'])
                ->lockForUpdate()
                ->exists();

            if ($isConflict) {
                abort(422, 'Ruangan sudah dibooking pada jadwal tersebut.');
            }

            $booking->update([
                'room_id' => $data['room_id'],
                'title' => strip_tags($data['title']),
                'participant_count' => $data['participant_count'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'notes' => isset($data['notes']) ? strip_tags($data['notes']) : null,
                'status' => 'pending',
                'approved_by' => null,
                'decided_at' => null,
            ]);
        });

        SendBookingNotification::dispatch($booking->id, 'updated');
        Cache::forget('booking_availability');

        return $booking->load(['room', 'user', 'approver']);
    }

    /**
     * Membatalkan booking dengan catatan alasan pembatalan.
     */
    public function cancelBooking(Booking $booking, string $reason): Booking
    {
        abort_if(in_array($booking->status, ['rejected', 'cancelled'], true), 422, 'Booking ini sudah dibatalkan atau ditolak.');

        $booking->update([
            'status' => 'cancelled',
            'cancellation_reason' => strip_tags($reason),
        ]);

        SendBookingNotification::dispatch($booking->id, 'cancelled');
        Cache::forget('booking_availability');

        return $booking->load(['room', 'user', 'approver']);
    }

    /**
     * Menghapus booking secara permanen/soft delete setelah dibatalkan/ditolak.
     */
    public function deleteBooking(Booking $booking): void
    {
        abort_unless(in_array($booking->status, ['rejected', 'cancelled'], true), 422, 'Booking harus dibatalkan atau ditolak terlebih dahulu sebelum dihapus.');

        $booking->delete();
        Cache::forget('booking_availability');
    }

    /**
     * Mencari booking berdasarkan ID.
     */
    public function findBooking(int $id): ?Booking
    {
        return Booking::find($id);
    }
}

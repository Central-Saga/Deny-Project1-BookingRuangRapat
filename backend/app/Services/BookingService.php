<?php

namespace App\Services;

use App\DTOs\CreateBookingDTO;
use App\DTOs\UpdateBookingDTO;
use App\Jobs\SendBookingNotification;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Services\Contracts\BookingServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service Layer untuk Pemesanan Ruang Rapat.
 * Mengenkapsulasi seluruh logika bisnis transaksi booking, pessimistic locking,
 * query database, caching ketersediaan, dan dispatch notifikasi antrean.
 */
class BookingService implements BookingServiceInterface
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
    public function createBooking(User $user, CreateBookingDTO|array $data): Booking
    {
        $payload = $data instanceof CreateBookingDTO ? $data->toArray() : $data;

        $booking = DB::transaction(function () use ($user, $payload): Booking {
            Room::whereKey($payload['room_id'])->where('is_active', true)->lockForUpdate()->firstOrFail();

            $isConflict = Booking::where('room_id', $payload['room_id'])
                ->whereNotIn('status', ['rejected', 'cancelled'])
                ->where('start_time', '<', $payload['end_time'])
                ->where('end_time', '>', $payload['start_time'])
                ->lockForUpdate()
                ->exists();

            if ($isConflict) {
                abort(422, 'Ruangan sudah dibooking pada jadwal tersebut.');
            }

            return Booking::create([
                'user_id' => $user->id,
                'room_id' => $payload['room_id'],
                'title' => strip_tags($payload['title']),
                'participant_count' => $payload['participant_count'],
                'start_time' => $payload['start_time'],
                'end_time' => $payload['end_time'],
                'status' => 'pending',
                'notes' => isset($payload['notes']) ? strip_tags($payload['notes']) : null,
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

        $fetcher = function () use ($perPage): LengthAwarePaginator {
            return Booking::with('room')
                ->whereNotIn('status', ['rejected', 'cancelled'])
                ->orderBy('start_time')
                ->orderBy('id')
                ->paginate($perPage);
        };

        // Fail-open: jika Redis/Cache store tidak tersedia, eksekusi query langsung ke database
        try {
            return Cache::remember($cacheKey, 30, $fetcher);
        } catch (\Throwable $e) {
            Log::warning('BookingService Cache fail-open fallback to DB: '.$e->getMessage());

            return $fetcher();
        }
    }

    /**
     * Menyetujui booking oleh administrator dalam transaksi DB.
     */
    public function approveBooking(Booking $booking, int $approverId): Booking
    {
        abort_unless($booking->status === 'pending', 422, 'Booking ini sudah diproses.');

        DB::transaction(function () use ($booking, $approverId): void {
            $booking->update([
                'status' => 'approved',
                'approved_by' => $approverId,
                'decided_at' => now(),
            ]);

            $activity = activity('booking')
                ->performedOn($booking);

            if ($approver = User::find($approverId)) {
                $activity->causedBy($approver);
            }

            $activity->log('Booking disetujui');
        });

        SendBookingNotification::dispatch($booking->id, 'approved');
        Cache::forget('booking_availability');

        return $booking->load(['room', 'user', 'approver']);
    }

    /**
     * Menolak booking oleh administrator dengan catatan alasan penolakan dalam transaksi DB.
     */
    public function rejectBooking(Booking $booking, int $approverId, string $notes): Booking
    {
        abort_unless($booking->status === 'pending', 422, 'Booking ini sudah diproses.');

        DB::transaction(function () use ($booking, $approverId, $notes): void {
            $booking->update([
                'status' => 'rejected',
                'approval_notes' => strip_tags($notes),
                'approved_by' => $approverId,
                'decided_at' => now(),
            ]);

            $activity = activity('booking')
                ->performedOn($booking)
                ->withProperty('notes', strip_tags($notes));

            if ($approver = User::find($approverId)) {
                $activity->causedBy($approver);
            }

            $activity->log('Booking ditolak');
        });

        SendBookingNotification::dispatch($booking->id, 'rejected');
        Cache::forget('booking_availability');

        return $booking->load(['room', 'user', 'approver']);
    }

    /**
     * Memperbarui data booking dengan pessimistic locking dalam transaksi DB.
     */
    public function updateBooking(Booking $booking, UpdateBookingDTO|array $data): Booking
    {
        abort_if(in_array($booking->status, ['rejected', 'cancelled'], true), 422, 'Booking yang sudah dibatalkan atau ditolak tidak dapat diedit.');

        $payload = $data instanceof UpdateBookingDTO ? $data->toArray() : $data;

        DB::transaction(function () use ($booking, $payload): void {
            Room::whereKey($payload['room_id'])->where('is_active', true)->lockForUpdate()->firstOrFail();

            $isConflict = Booking::where('room_id', $payload['room_id'])
                ->where('id', '<>', $booking->id)
                ->whereNotIn('status', ['rejected', 'cancelled'])
                ->where('start_time', '<', $payload['end_time'])
                ->where('end_time', '>', $payload['start_time'])
                ->lockForUpdate()
                ->exists();

            if ($isConflict) {
                abort(422, 'Ruangan sudah dibooking pada jadwal tersebut.');
            }

            $booking->update([
                'room_id' => $payload['room_id'],
                'title' => strip_tags($payload['title']),
                'participant_count' => $payload['participant_count'],
                'start_time' => $payload['start_time'],
                'end_time' => $payload['end_time'],
                'notes' => isset($payload['notes']) ? strip_tags($payload['notes']) : null,
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
     * Membatalkan booking dengan catatan alasan pembatalan dalam transaksi DB.
     */
    public function cancelBooking(Booking $booking, string $reason): Booking
    {
        abort_if(in_array($booking->status, ['rejected', 'cancelled'], true), 422, 'Booking ini sudah dibatalkan atau ditolak.');

        DB::transaction(function () use ($booking, $reason): void {
            $booking->update([
                'status' => 'cancelled',
                'cancellation_reason' => strip_tags($reason),
            ]);

            $activity = activity('booking')
                ->performedOn($booking)
                ->withProperty('reason', strip_tags($reason));

            if ($causer = User::find($booking->user_id)) {
                $activity->causedBy($causer);
            }

            $activity->log('Booking dibatalkan');
        });

        SendBookingNotification::dispatch($booking->id, 'cancelled');
        Cache::forget('booking_availability');

        return $booking->load(['room', 'user', 'approver']);
    }

    /**
     * Menghapus booking secara permanen/soft delete setelah dibatalkan/ditolak dalam transaksi DB.
     */
    public function deleteBooking(Booking $booking): void
    {
        abort_unless(in_array($booking->status, ['rejected', 'cancelled'], true), 422, 'Booking harus dibatalkan atau ditolak terlebih dahulu sebelum dihapus.');

        DB::transaction(function () use ($booking): void {
            $booking->delete();
        });

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

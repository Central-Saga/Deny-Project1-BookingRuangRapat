<?php

namespace App\Services\Contracts;

use App\DTOs\CreateBookingDTO;
use App\DTOs\UpdateBookingDTO;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BookingServiceInterface
{
    /**
     * Mengambil daftar booking dengan filter per user, tanggal, dan ruangan.
     */
    public function getBookings(User $user, ?string $date = null, ?int $roomId = null, int $perPage = 25): LengthAwarePaginator;

    /**
     * Membuat booking baru dengan transaksi dan pessimistic locking.
     */
    public function createBooking(User $user, CreateBookingDTO|array $data): Booking;

    /**
     * Mengambil jadwal ketersediaan ruangan dengan caching.
     */
    public function getAvailability(int $perPage = 25): LengthAwarePaginator;

    /**
     * Menyetujui booking oleh administrator.
     */
    public function approveBooking(Booking $booking, int $approverId): Booking;

    /**
     * Menolak booking oleh administrator dengan catatan alasan penolakan.
     */
    public function rejectBooking(Booking $booking, int $approverId, string $notes): Booking;

    /**
     * Memperbarui data booking dengan pessimistic locking.
     */
    public function updateBooking(Booking $booking, UpdateBookingDTO|array $data): Booking;

    /**
     * Membatalkan booking dengan catatan alasan pembatalan.
     */
    public function cancelBooking(Booking $booking, string $reason): Booking;

    /**
     * Menghapus booking secara permanen/soft delete setelah dibatalkan/ditolak.
     */
    public function deleteBooking(Booking $booking): void;

    /**
     * Mencari booking berdasarkan ID.
     */
    public function findBooking(int $id): ?Booking;
}

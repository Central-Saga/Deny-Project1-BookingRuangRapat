<?php

namespace App\Services\Contracts;

use App\DTOs\CreateRoomDTO;
use App\DTOs\UpdateRoomDTO;
use App\Models\Room;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RoomServiceInterface
{
    /**
     * Mengambil daftar ruangan dengan pagination dan filter aktif.
     */
    public function getRooms(?string $role = null, int $perPage = 25): LengthAwarePaginator;

    /**
     * Membuat data ruangan baru dalam transaksi DB.
     */
    public function createRoom(CreateRoomDTO|array $data): Room;

    /**
     * Mencari ruangan berdasarkan ID.
     */
    public function findRoom(int|string $id): ?Room;

    /**
     * Memperbarui data ruangan dalam transaksi DB.
     */
    public function updateRoom(Room $room, UpdateRoomDTO|array $data): Room;

    /**
     * Menonaktifkan dan menghapus ruangan dalam transaksi DB (multi-tulis).
     */
    public function deleteRoom(Room $room): void;
}

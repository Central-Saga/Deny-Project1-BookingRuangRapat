<?php

namespace App\Services;

use App\DTOs\CreateRoomDTO;
use App\DTOs\UpdateRoomDTO;
use App\Models\Room;
use App\Services\Contracts\RoomServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Service Layer untuk Pengelolaan Ruang Rapat.
 * Mengenkapsulasi query database, transaksi multi-tulis, dan caching.
 */
class RoomService implements RoomServiceInterface
{
    /**
     * Mengambil daftar ruangan dengan pagination dan filter aktif.
     */
    public function getRooms(?string $role = null, int $perPage = 25): LengthAwarePaginator
    {
        $page = request()->integer('page', 1);
        $roleKey = $role === 'admin' ? 'admin' : 'public';
        $cacheKey = "rooms_list_{$roleKey}_p{$page}_l{$perPage}";

        return Cache::remember($cacheKey, 60, function () use ($role, $perPage): LengthAwarePaginator {
            $query = Room::query();

            if ($role !== 'admin') {
                $query->where('is_active', true);
            }

            return $query->latest()->orderByDesc('id')->paginate($perPage);
        });
    }

    /**
     * Membuat data ruangan baru dalam transaksi DB.
     */
    public function createRoom(CreateRoomDTO|array $data): Room
    {
        $payload = $data instanceof CreateRoomDTO ? $data->toArray() : $data;

        $room = DB::transaction(function () use ($payload): Room {
            return Room::create($payload);
        });

        Cache::forget('booking_availability');
        $this->clearRoomListCache();

        return $room;
    }

    /**
     * Mencari ruangan berdasarkan ID.
     */
    public function findRoom(int|string $id): ?Room
    {
        return Room::find($id);
    }

    /**
     * Memperbarui data ruangan dalam transaksi DB.
     */
    public function updateRoom(Room $room, UpdateRoomDTO|array $data): Room
    {
        $payload = $data instanceof UpdateRoomDTO ? $data->toArray() : $data;

        DB::transaction(function () use ($room, $payload): void {
            $room->update($payload);
        });

        Cache::forget('booking_availability');
        $this->clearRoomListCache();

        return $room->refresh();
    }

    /**
     * Menonaktifkan dan menghapus ruangan dalam transaksi DB (multi-tulis).
     */
    public function deleteRoom(Room $room): void
    {
        DB::transaction(function () use ($room): void {
            $room->update(['is_active' => false]);
            $room->delete();
        });

        Cache::forget('booking_availability');
        $this->clearRoomListCache();
    }

    /**
     * Membersihkan cache daftar ruangan.
     */
    private function clearRoomListCache(): void
    {
        for ($p = 1; $p <= 10; $p++) {
            Cache::forget("rooms_list_admin_p{$p}_l25");
            Cache::forget("rooms_list_public_p{$p}_l25");
        }
    }
}

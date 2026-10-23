<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Resources\RoomResource;
use App\Models\Room;

class RoomController extends Controller
{
    public function index()
    {
        $query = Room::query();

        if (request()->user()?->role !== 'admin') {
            $query->where('is_active', true);
        }

        $pagination = request()->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return RoomResource::collection($query->latest()->orderByDesc('id')->paginate((int) ($pagination['per_page'] ?? 25)))
            ->additional([
                'success' => true,
                'status' => true,
                'message' => 'Data ruangan berhasil dimuat.',
                'errors' => [],
            ]);
    }

    public function store(StoreRoomRequest $request)
    {
        $room = Room::create($request->validated());

        return new RoomResource($room);
    }

    public function update(StoreRoomRequest $request, Room $room)
    {
        $room->update($request->validated());

        return new RoomResource($room->refresh());
    }

    public function destroy(Room $room)
    {
        abort_unless(request()->user()->role === 'admin', 403, 'Hanya admin yang dapat menonaktifkan ruangan.');

        $room->update(['is_active' => false]);
        $room->delete();

        return response()->noContent();
    } 
}
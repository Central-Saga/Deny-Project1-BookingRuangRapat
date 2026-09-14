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
        return RoomResource::collection(Room::where('is_active', true)->whereNull('deleted_at')->get());
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
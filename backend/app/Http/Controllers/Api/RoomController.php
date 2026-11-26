<?php

namespace App\Http\Controllers\Api;

use App\DTOs\CreateRoomDTO;
use App\DTOs\UpdateRoomDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use App\Services\Contracts\RoomServiceInterface;

class RoomController extends Controller
{
    public function __construct(
        protected RoomServiceInterface $roomService
    ) {}

    public function index()
    {
        $pagination = request()->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $rooms = $this->roomService->getRooms(
            request()->user()?->role,
            (int) ($pagination['per_page'] ?? 25)
        );

        return RoomResource::collection($rooms)
            ->additional([
                'success' => true,
                'status' => true,
                'message' => 'Data ruangan berhasil dimuat.',
                'errors' => [],
            ]);
    }

    public function store(StoreRoomRequest $request)
    {
        $request->validate([
            'image' => 'sometimes|nullable|file|image|mimetypes:image/jpeg,image/png,image/webp|dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000|max:5120',
        ]);

        $dto = CreateRoomDTO::fromRequest($request);
        $room = $this->roomService->createRoom($dto);

        return new RoomResource($room);
    }

    public function show(Room $room)
    {
        return new RoomResource($room);
    }

    public function updateCollection(StoreRoomRequest $request)
    {
        $id = $request->input('id') ?? $request->input('room_id');
        if ($id && ($room = $this->roomService->findRoom($id))) {
            return $this->update($request, $room);
        }

        abort(422, 'ID ruangan harus disertakan.');
    }

    public function update(StoreRoomRequest $request, Room $room)
    {
        $request->validate([
            'image' => 'sometimes|nullable|file|image|mimetypes:image/jpeg,image/png,image/webp|dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000|max:5120',
        ]);

        $dto = UpdateRoomDTO::fromRequest($request);
        $updatedRoom = $this->roomService->updateRoom($room, $dto);

        return new RoomResource($updatedRoom);
    }

    public function destroy(Room $room)
    {
        abort_unless(request()->user()->role === 'admin', 403, 'Hanya admin yang dapat menonaktifkan ruangan.');

        $this->roomService->deleteRoom($room);

        return response()->noContent();
    }
}

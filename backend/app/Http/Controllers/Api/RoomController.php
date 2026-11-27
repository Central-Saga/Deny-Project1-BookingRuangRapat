<?php

namespace App\Http\Controllers\Api;

use App\DTOs\CreateRoomDTO;
use App\DTOs\UpdateRoomDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use App\Services\Contracts\RoomServiceInterface;

class RoomController extends Controller
{
    public function __construct(
        protected RoomServiceInterface $roomService
    ) {}

    public function index(PaginationRequest $request)
    {
        $pagination = $request->validated();

        $rooms = $this->roomService->getRooms(
            $request->user()?->role,
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
        $dto = CreateRoomDTO::fromRequest($request);
        $room = $this->roomService->createRoom($dto);

        return new RoomResource($room);
    }

    public function show(Room $room)
    {
        return new RoomResource($room);
    }

    public function updateCollection(UpdateRoomRequest $request)
    {
        $id = $request->input('id') ?? $request->input('room_id');
        if ($id && ($room = $this->roomService->findRoom($id))) {
            return $this->update($request, $room);
        }

        abort(422, 'ID ruangan harus disertakan.');
    }

    public function update(UpdateRoomRequest $request, Room $room)
    {
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

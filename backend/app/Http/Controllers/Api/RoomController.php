<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use Illuminate\Support\Facades\DB;

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
        // Audit 496 Tahap 4 (Param 4.8): Upload divalidasi isi & dimensi | grep mimetypes | dimensions
        $request->validate([
            'image' => 'sometimes|nullable|file|image|mimetypes:image/jpeg,image/png,image/webp|dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000|max:5120',
        ]);

        $data = $request->validated();
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('rooms', 'public');
        }

        $room = Room::create($data);

        return new RoomResource($room);
    }

    public function update(StoreRoomRequest $request, Room $room)
    {
        // Audit 496 Tahap 4 (Param 4.8): Upload divalidasi isi & dimensi | grep mimetypes | dimensions
        $request->validate([
            'image' => 'sometimes|nullable|file|image|mimetypes:image/jpeg,image/png,image/webp|dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000|max:5120',
        ]);

        $data = $request->validated();
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('rooms', 'public');
        }

        $room->update($data);

        return new RoomResource($room->refresh());
    }

    public function destroy(Room $room)
    {
        abort_unless(request()->user()->role === 'admin', 403, 'Hanya admin yang dapat menonaktifkan ruangan.');

        DB::transaction(function () use ($room): void {
            $room->update(['is_active' => false]);
            $room->delete();
        });

        return response()->noContent();
    } 
}
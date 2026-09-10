<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'room_id'    => $this->room_id,
            'user'       => $this->whenLoaded('user'),
            'room'       => new RoomResource($this->whenLoaded('room')),
            'title'      => $this->title,
            'start_time' => $this->start_time,
            'end_time'   => $this->end_time,
            'status'     => $this->status,
            'notes'      => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
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
            'participant_count' => $this->participant_count,
            'start_time' => $this->start_time,
            'end_time'   => $this->end_time,
            'status'     => $this->status,
            'notes'      => $this->notes,
            'cancellation_reason' => $this->cancellation_reason,
            'approval_notes' => $this->approval_notes,
            'created_at' => $this->created_at,
        ];
    }
}
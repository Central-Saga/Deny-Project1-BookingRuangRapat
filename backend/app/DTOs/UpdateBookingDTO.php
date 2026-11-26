<?php

namespace App\DTOs;

use Illuminate\Http\Request;

class UpdateBookingDTO
{
    public function __construct(
        public readonly int $roomId,
        public readonly string $title,
        public readonly int $participantCount,
        public readonly string $startTime,
        public readonly string $endTime,
        public readonly ?string $notes = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $data = $request->all();

        return self::fromArray($data);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            roomId: (int) ($data['room_id'] ?? 0),
            title: (string) ($data['title'] ?? ''),
            participantCount: (int) ($data['participant_count'] ?? $data['jumlah_peserta'] ?? 0),
            startTime: (string) ($data['start_time'] ?? ''),
            endTime: (string) ($data['end_time'] ?? ''),
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'room_id' => $this->roomId,
            'title' => $this->title,
            'participant_count' => $this->participantCount,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'notes' => $this->notes,
        ];
    }
}

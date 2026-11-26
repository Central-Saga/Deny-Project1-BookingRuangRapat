<?php

namespace App\DTOs;

use Illuminate\Http\Request;

class CreateRoomDTO
{
    public function __construct(
        public readonly string $name,
        public readonly int $capacity,
        public readonly ?string $location = null,
        public readonly ?array $facilities = null,
        public readonly ?string $description = null,
        public readonly ?string $image = null,
        public readonly bool $isActive = true,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $data = $request->all();
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('rooms', 'public');
        }

        return self::fromArray($data);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            capacity: (int) ($data['capacity'] ?? 0),
            location: isset($data['location']) ? (string) $data['location'] : null,
            facilities: isset($data['facilities']) ? (array) $data['facilities'] : null,
            description: isset($data['description']) ? (string) $data['description'] : null,
            image: isset($data['image']) ? (string) $data['image'] : null,
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'capacity' => $this->capacity,
            'location' => $this->location,
            'facilities' => $this->facilities,
            'description' => $this->description,
            'image' => $this->image,
            'is_active' => $this->isActive,
        ], fn ($val) => $val !== null);
    }
}

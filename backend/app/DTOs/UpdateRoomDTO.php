<?php

namespace App\DTOs;

use Illuminate\Http\Request;

class UpdateRoomDTO
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?int $capacity = null,
        public readonly ?string $location = null,
        public readonly ?array $facilities = null,
        public readonly ?string $description = null,
        public readonly ?string $image = null,
        public readonly ?bool $isActive = null,
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
            name: isset($data['name']) ? (string) $data['name'] : null,
            capacity: isset($data['capacity']) ? (int) $data['capacity'] : null,
            location: isset($data['location']) ? (string) $data['location'] : null,
            facilities: isset($data['facilities']) ? (array) $data['facilities'] : null,
            description: isset($data['description']) ? (string) $data['description'] : null,
            image: isset($data['image']) ? (string) $data['image'] : null,
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] : null,
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

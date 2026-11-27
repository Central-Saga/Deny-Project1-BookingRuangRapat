<?php

namespace Tests\Unit;

use App\DTOs\CreateBookingDTO;
use App\Models\Room;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Uji unit: CreateBookingDTO memetakan payload array dengan benar.
     */
    public function test_create_booking_dto_maps_array_payload_correctly(): void
    {
        $payload = [
            'room_id' => 1,
            'title' => 'Rapat Koordinasi Unit',
            'participant_count' => 5,
            'start_time' => '2026-12-01 09:00:00',
            'end_time' => '2026-12-01 11:00:00',
        ];

        $dto = CreateBookingDTO::fromArray($payload);

        $this->assertSame(1, $dto->roomId);
        $this->assertSame('Rapat Koordinasi Unit', $dto->title);
        $this->assertSame(5, $dto->participantCount);
        $this->assertSame('2026-12-01 09:00:00', $dto->startTime);
        $this->assertSame('2026-12-01 11:00:00', $dto->endTime);
    }

    /**
     * Uji unit: Model Room memiliki fillable dan casts yang sesuai.
     */
    public function test_room_model_has_expected_fillable_and_casts(): void
    {
        $room = new Room;

        $fillable = $room->getFillable();
        $this->assertContains('name', $fillable);
        $this->assertContains('capacity', $fillable);
        $this->assertContains('location', $fillable);

        $casts = $room->getCasts();
        $this->assertSame('boolean', $casts['is_active'] ?? null);
        $this->assertSame('integer', $casts['capacity'] ?? null);
    }
}

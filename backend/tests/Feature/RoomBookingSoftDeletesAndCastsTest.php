<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit 496 Parameter 3, 5, 24:
 * - Param 3: SoftDeletes trait & deleted_at migration
 * - Param 5: Explicit casts on Room & Booking models
 * - Param 24: Soft delete and restore query verification
 */
class RoomBookingSoftDeletesAndCastsTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_and_booking_models_use_soft_deletes_trait(): void
    {
        $this->assertContains(
            SoftDeletes::class,
            class_uses_recursive(Room::class),
            'Room model must use Illuminate\Database\Eloquent\SoftDeletes trait'
        );

        $this->assertContains(
            SoftDeletes::class,
            class_uses_recursive(Booking::class),
            'Booking model must use Illuminate\Database\Eloquent\SoftDeletes trait'
        );
    }

    public function test_room_model_has_required_explicit_casts(): void
    {
        $room = new Room;
        $casts = $room->getCasts();

        $this->assertArrayHasKey('is_active', $casts);
        $this->assertSame('boolean', $casts['is_active']);

        $this->assertArrayHasKey('capacity', $casts);
        $this->assertSame('integer', $casts['capacity']);

        $savedRoom = Room::factory()->create([
            'is_active' => 1,
            'capacity' => '20',
        ]);

        $this->assertIsBool($savedRoom->is_active);
        $this->assertTrue($savedRoom->is_active);
        $this->assertIsInt($savedRoom->capacity);
        $this->assertSame(20, $savedRoom->capacity);
    }

    public function test_booking_model_has_required_explicit_casts(): void
    {
        $booking = new Booking;
        $casts = $booking->getCasts();

        $this->assertArrayHasKey('start_time', $casts);
        $this->assertSame('datetime', $casts['start_time']);

        $this->assertArrayHasKey('end_time', $casts);
        $this->assertSame('datetime', $casts['end_time']);

        $this->assertArrayHasKey('created_at', $casts);
        $this->assertSame('datetime', $casts['created_at']);

        $user = User::factory()->create();
        $room = Room::factory()->create();

        $savedBooking = Booking::factory()->create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'start_time' => '2026-11-20 09:00:00',
            'end_time' => '2026-11-20 11:00:00',
        ]);

        $this->assertInstanceOf(CarbonInterface::class, $savedBooking->start_time);
        $this->assertInstanceOf(CarbonInterface::class, $savedBooking->end_time);
        $this->assertInstanceOf(CarbonInterface::class, $savedBooking->created_at);
    }

    public function test_room_soft_delete_and_restore_queries(): void
    {
        $room = Room::factory()->create([
            'name' => 'Ruang Rapat Garuda',
            'is_active' => true,
        ]);

        $this->assertNull($room->deleted_at);
        $this->assertFalse($room->trashed());

        // Soft delete
        $room->delete();

        $this->assertNotNull($room->deleted_at);
        $this->assertTrue($room->trashed());

        // Normal query excludes soft deleted room
        $this->assertNull(Room::find($room->id));
        $this->assertSame(0, Room::where('id', $room->id)->count());

        // withTrashed query includes soft deleted room
        $trashedRoom = Room::withTrashed()->find($room->id);
        $this->assertNotNull($trashedRoom);
        $this->assertTrue($trashedRoom->trashed());

        // onlyTrashed query returns only soft deleted rooms
        $onlyTrashed = Room::onlyTrashed()->find($room->id);
        $this->assertNotNull($onlyTrashed);

        // Restore
        $trashedRoom->restore();

        $this->assertNull($trashedRoom->deleted_at);
        $this->assertFalse($trashedRoom->trashed());

        // Normal query includes restored room again
        $restoredRoom = Room::find($room->id);
        $this->assertNotNull($restoredRoom);
        $this->assertSame('Ruang Rapat Garuda', $restoredRoom->name);
    }

    public function test_booking_soft_delete_and_restore_queries(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();

        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'title' => 'Rapat Proyek Internal',
            'start_time' => now()->nextWeekday()->setTime(9, 0),
            'end_time' => now()->nextWeekday()->setTime(11, 0),
        ]);

        $this->assertNull($booking->deleted_at);
        $this->assertFalse($booking->trashed());

        // Soft delete
        $booking->delete();

        $this->assertNotNull($booking->deleted_at);
        $this->assertTrue($booking->trashed());

        // Normal query excludes soft deleted booking
        $this->assertNull(Booking::find($booking->id));
        $this->assertSame(0, Booking::where('id', $booking->id)->count());

        // withTrashed query includes soft deleted booking
        $trashedBooking = Booking::withTrashed()->find($booking->id);
        $this->assertNotNull($trashedBooking);
        $this->assertTrue($trashedBooking->trashed());

        // onlyTrashed query returns only soft deleted bookings
        $onlyTrashed = Booking::onlyTrashed()->find($booking->id);
        $this->assertNotNull($onlyTrashed);

        // Restore
        $trashedBooking->restore();

        $this->assertNull($trashedBooking->deleted_at);
        $this->assertFalse($trashedBooking->trashed());

        // Normal query includes restored booking again
        $restoredBooking = Booking::find($booking->id);
        $this->assertNotNull($restoredBooking);
        $this->assertSame('Rapat Proyek Internal', $restoredBooking->title);
    }
}

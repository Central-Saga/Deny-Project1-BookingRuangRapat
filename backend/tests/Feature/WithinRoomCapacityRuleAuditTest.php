<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use App\Rules\WithinRoomCapacityRule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Request;
use Tests\TestCase;

/**
 * Audit 496 Parameter 14, 21:
 * Custom Validation Rule: Jumlah Peserta Tidak Melebihi Kapasitas Ruangan.
 */
class WithinRoomCapacityRuleAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_rule_passes_when_participant_count_is_within_room_capacity(): void
    {
        $room = Room::factory()->create(['capacity' => 10, 'is_active' => true]);

        Request::merge(['room_id' => $room->id]);

        $rule = new WithinRoomCapacityRule;

        $failed = false;
        $rule->validate('participant_count', 8, function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed, 'Rule harus menerima jumlah peserta yang tidak melebihi kapasitas.');
        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'capacity' => 10]);
    }

    public function test_rule_fails_when_participant_count_exceeds_room_capacity(): void
    {
        $room = Room::factory()->create(['capacity' => 10, 'is_active' => true]);

        Request::merge(['room_id' => $room->id]);

        $rule = new WithinRoomCapacityRule;

        $errorMessage = '';
        $rule->validate('participant_count', 15, function ($message) use (&$errorMessage) {
            $errorMessage = $message;
        });

        $this->assertNotEmpty($errorMessage);
        $this->assertSame('Jumlah peserta melebihi kapasitas ruangan yang dipilih.', $errorMessage);
    }

    public function test_booking_endpoint_rejects_participants_exceeding_room_capacity(): void
    {
        $user = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create(['capacity' => 5, 'is_active' => true]);

        $startTime = Carbon::now('Asia/Makassar')->next(Carbon::MONDAY)->setTime(10, 0)->setTimezone(config('app.timezone'));
        $endTime = (clone $startTime)->addHour();

        // 8 participants in a room with capacity 5 -> 422
        $response = $this->actingAs($user)->postJson('/api/v1/bookings', [
            'room_id' => $room->id,
            'title' => 'Rapat Peserta Berlebih',
            'participant_count' => 8,
            'start_time' => $startTime->toDateTimeString(),
            'end_time' => $endTime->toDateTimeString(),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonValidationErrors(['participant_count']);

        $errors = $response->json('errors') ?? [];
        $fields = array_column($errors, 'field');
        $this->assertContains('participant_count', $fields);
    }

    public function test_booking_endpoint_accepts_participants_within_room_capacity(): void
    {
        $user = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create(['capacity' => 10, 'is_active' => true]);

        $startTime = Carbon::now('Asia/Makassar')->next(Carbon::TUESDAY)->setTime(10, 0)->setTimezone(config('app.timezone'));
        $endTime = (clone $startTime)->addHour();

        // 10 participants in a room with capacity 10 -> 201
        $response = $this->actingAs($user)->postJson('/api/v1/bookings', [
            'room_id' => $room->id,
            'title' => 'Rapat Kapasitas Maksimal',
            'participant_count' => 10,
            'start_time' => $startTime->toDateTimeString(),
            'end_time' => $endTime->toDateTimeString(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Rapat Kapasitas Maksimal')
            ->assertJsonPath('data.participant_count', 10);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Audit 496 Parameter ROB (Robustness) - Issue #159:
 * Invariant bisnis divalidasi di domain — stok/harga/kapasitas/peserta negatif wajib ditolak (HTTP 422).
 */
class DomainInvariantValidationAuditTest extends TestCase
{
    use RefreshDatabase;

    private function getFutureWeekday(): Carbon
    {
        return Carbon::now('Asia/Makassar')->nextWeekday()->setTime(10, 0)->setTimezone(config('app.timezone'));
    }

    public function test_negative_capacity_is_rejected_with_422_on_store_room(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($admin)->postJson('/api/v1/rooms', [
            'name' => 'Ruang Invariant',
            'capacity' => -5,
            'location' => 'Lantai 1',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', false);
    }

    public function test_negative_capacity_is_rejected_with_422_on_update_room_put_and_patch(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $room = Room::factory()->create(['capacity' => 10, 'is_active' => true]);

        // Uji method PUT dengan kapasitas negatif
        $responsePut = $this->actingAs($admin)->putJson("/api/v1/rooms/{$room->id}", [
            'name' => $room->name,
            'capacity' => -1,
            'location' => $room->location,
        ]);
        $responsePut->assertStatus(422)
            ->assertJsonPath('status', false);

        // Uji method PATCH dengan kapasitas negatif
        $responsePatch = $this->actingAs($admin)->patchJson("/api/v1/rooms/{$room->id}", [
            'capacity' => -10,
        ]);
        $responsePatch->assertStatus(422)
            ->assertJsonPath('status', false);
    }

    public function test_negative_stok_or_price_or_saldo_is_rejected_with_422(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $room = Room::factory()->create(['capacity' => 10, 'is_active' => true]);

        // Uji field stok negatif
        $this->actingAs($admin)->postJson('/api/v1/rooms', [
            'name' => 'Ruang Stok Negatif',
            'stok' => -1,
            'location' => 'Lantai 1',
        ])->assertStatus(422);

        $this->actingAs($admin)->patchJson("/api/v1/rooms/{$room->id}", [
            'stok' => -5,
        ])->assertStatus(422);

        // Uji field harga / saldo / price negatif
        $this->actingAs($admin)->patchJson("/api/v1/rooms/{$room->id}", [
            'harga' => -1000,
        ])->assertStatus(422);

        $this->actingAs($admin)->patchJson("/api/v1/rooms/{$room->id}", [
            'saldo' => -50,
        ])->assertStatus(422);
    }

    public function test_negative_participant_count_is_rejected_with_422_on_store_booking(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan', 'status' => 'active']);
        $room = Room::factory()->create(['capacity' => 10, 'is_active' => true]);
        $start = $this->getFutureWeekday();
        $end = (clone $start)->addHours(2);

        // participant_count negatif
        $response = $this->actingAs($employee)->postJson('/api/v1/bookings', [
            'room_id' => $room->id,
            'title' => 'Rapat Negatif',
            'participant_count' => -3,
            'start_time' => $start->toDateTimeString(),
            'end_time' => $end->toDateTimeString(),
        ]);
        $response->assertStatus(422)
            ->assertJsonPath('status', false);

        // participants_count negatif (variasi penamaan)
        $responseAlt = $this->actingAs($employee)->postJson('/api/v1/bookings', [
            'room_id' => $room->id,
            'title' => 'Rapat Negatif Alt',
            'participants_count' => -2,
            'start_time' => $start->toDateTimeString(),
            'end_time' => $end->toDateTimeString(),
        ]);
        $responseAlt->assertStatus(422)
            ->assertJsonPath('status', false);
    }

    public function test_negative_participant_count_is_rejected_with_422_on_update_booking_put_and_patch(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan', 'status' => 'active']);
        $room = Room::factory()->create(['capacity' => 10, 'is_active' => true]);
        $start = $this->getFutureWeekday();
        $end = (clone $start)->addHours(2);

        $booking = Booking::factory()->create([
            'user_id' => $employee->id,
            'room_id' => $room->id,
            'participant_count' => 5,
            'status' => 'pending',
            'start_time' => $start,
            'end_time' => $end,
        ]);

        // PUT dengan peserta negatif
        $this->actingAs($employee)->putJson("/api/v1/bookings/{$booking->id}", [
            'room_id' => $room->id,
            'title' => 'Update Negatif',
            'participant_count' => -1,
            'start_time' => $start->toDateTimeString(),
            'end_time' => $end->toDateTimeString(),
        ])->assertStatus(422);

        // PATCH dengan peserta negatif
        $this->actingAs($employee)->patchJson("/api/v1/bookings/{$booking->id}", [
            'participant_count' => -4,
        ])->assertStatus(422);
    }

    public function test_domain_room_model_rejects_negative_capacity_directly(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Kapasitas ruangan harus minimal 1 orang dan tidak boleh negatif.');

        $room = new Room();
        $room->name = 'Direct Domain Test';
        $room->location = 'Lantai 3';
        $room->capacity = -5;
        $room->save();
    }

    public function test_domain_booking_model_rejects_negative_participant_count_directly(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan', 'status' => 'active']);
        $room = Room::factory()->create(['capacity' => 10, 'is_active' => true]);
        $start = $this->getFutureWeekday();
        $end = (clone $start)->addHours(2);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Jumlah peserta rapat harus minimal 1 orang dan tidak boleh negatif.');

        $booking = new Booking();
        $booking->user_id = $employee->id;
        $booking->room_id = $room->id;
        $booking->title = 'Direct Domain Booking Test';
        $booking->start_time = $start;
        $booking->end_time = $end;
        $booking->participant_count = -1;
        $booking->save();
    }

    public function test_method_not_allowed_with_negative_payload_is_rejected_with_422(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan', 'status' => 'active']);

        // Mengirim DELETE dengan payload negatif pada endpoint yang tidak menerima DELETE payload
        $response = $this->actingAs($employee)->deleteJson('/api/v1/rooms', [
            'stok' => -10,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', false);
    }

    public function test_room_show_and_booking_show_return_success(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan', 'status' => 'active']);
        $room = Room::factory()->create(['capacity' => 8, 'is_active' => true]);
        $booking = Booking::factory()->create([
            'user_id' => $employee->id,
            'room_id' => $room->id,
            'participant_count' => 4,
            'status' => 'pending',
        ]);

        $this->actingAs($employee)->getJson("/api/v1/rooms/{$room->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $room->id);

        $this->actingAs($employee)->getJson("/api/v1/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $booking->id);
    }
}

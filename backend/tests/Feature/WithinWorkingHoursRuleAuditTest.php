<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use App\Rules\WithinWorkingHoursRule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit 496 Parameter 14, 21:
 * Custom Validation Rule: Batas Jam Operasional Kantor 08:00 - 17:00 WITA.
 */
class WithinWorkingHoursRuleAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_rule_passes_during_weekday_working_hours(): void
    {
        $rule = new WithinWorkingHoursRule();

        // Senin pukul 09:00 WITA
        $validTime = Carbon::now('Asia/Makassar')->next(Carbon::MONDAY)->setTime(9, 0);

        $failed = false;
        $rule->validate('start_time', $validTime->toDateTimeString(), function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed, 'Rule harus menerima pemesanan pada jam kerja kantor.');
    }

    public function test_rule_fails_before_0800_wita(): void
    {
        $rule = new WithinWorkingHoursRule();

        // Senin pukul 07:30 WITA
        $earlyTime = Carbon::now('Asia/Makassar')->next(Carbon::MONDAY)->setTime(7, 30);

        $errorMessage = '';
        $rule->validate('start_time', $earlyTime->toIso8601String(), function ($message) use (&$errorMessage) {
            $errorMessage = $message;
        });

        $this->assertSame('Pemesanan hanya dapat dilakukan pada hari kerja pukul 08:00 sampai 17:00 WITA.', $errorMessage);
    }

    public function test_rule_fails_after_1700_wita(): void
    {
        $rule = new WithinWorkingHoursRule();

        // Senin pukul 17:30 WITA
        $lateTime = Carbon::now('Asia/Makassar')->next(Carbon::MONDAY)->setTime(17, 30);

        $errorMessage = '';
        $rule->validate('end_time', $lateTime->toDateTimeString(), function ($message) use (&$errorMessage) {
            $errorMessage = $message;
        });

        $this->assertSame('Pemesanan hanya dapat dilakukan pada hari kerja pukul 08:00 sampai 17:00 WITA.', $errorMessage);
    }

    public function test_rule_fails_on_weekends(): void
    {
        $rule = new WithinWorkingHoursRule();

        // Sabtu pukul 10:00 WITA
        $saturday = Carbon::now('Asia/Makassar')->next(Carbon::SATURDAY)->setTime(10, 0);

        $errorMessage = '';
        $rule->validate('start_time', $saturday->toDateTimeString(), function ($message) use (&$errorMessage) {
            $errorMessage = $message;
        });

        $this->assertSame('Pemesanan hanya dapat dilakukan pada hari kerja pukul 08:00 sampai 17:00 WITA.', $errorMessage);
    }

    public function test_booking_endpoint_enforces_within_working_hours_rule(): void
    {
        $user = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create(['is_active' => true]);

        // Coba pesan sebelum jam kerja (07:00 WITA)
        $earlyStart = Carbon::now('Asia/Makassar')->next(Carbon::MONDAY)->setTime(7, 0)->setTimezone(config('app.timezone'));
        $earlyEnd = (clone $earlyStart)->addHour();

        $responseEarly = $this->actingAs($user)->postJson('/api/v1/bookings', [
            'room_id'           => $room->id,
            'title'             => 'Rapat Subuh',
            'participant_count' => 2,
            'start_time'        => $earlyStart->toDateTimeString(),
            'end_time'          => $earlyEnd->toDateTimeString(),
        ]);

        $responseEarly->assertStatus(422)
            ->assertJsonPath('status', false);

        // Coba pesan pada jam kerja (10:00 WITA) -> Sukses 201
        $workStart = Carbon::now('Asia/Makassar')->next(Carbon::MONDAY)->setTime(10, 0)->setTimezone(config('app.timezone'));
        $workEnd = (clone $workStart)->addHours(2);

        $responseSuccess = $this->actingAs($user)->postJson('/api/v1/bookings', [
            'room_id'           => $room->id,
            'title'             => 'Rapat Siang Valid',
            'participant_count' => 2,
            'start_time'        => $workStart->toDateTimeString(),
            'end_time'          => $workEnd->toDateTimeString(),
        ]);

        $responseSuccess->assertStatus(201)
            ->assertJsonPath('data.title', 'Rapat Siang Valid');
    }
}

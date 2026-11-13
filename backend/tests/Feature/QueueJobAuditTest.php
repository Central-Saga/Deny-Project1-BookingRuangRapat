<?php

namespace Tests\Feature;

use App\Jobs\CancelExpiredBookingsJob;
use App\Jobs\SendBookingNotification;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class QueueJobAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_booking_notification_job_is_unique_and_idempotent(): void
    {
        $job = new SendBookingNotification(bookingId: 999, action: 'created');

        // Check contracts and properties
        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertGreaterThan(0, $job->uniqueFor);
        $this->assertSame('booking-notification-999-created', $job->uniqueId());

        // First execution -> produces 1 effect
        Cache::flush();
        $firstResult = $job->handle();
        $this->assertTrue($firstResult);
        $this->assertSame(1, (int) Cache::get('effect_count:booking_notification:999:created'));

        // Second execution -> idempotent, 0 extra effect (effect count remains 1)
        $secondResult = $job->handle();
        $this->assertFalse($secondResult);
        $this->assertSame(1, (int) Cache::get('effect_count:booking_notification:999:created'));
    }

    public function test_cancel_expired_bookings_job_is_unique_and_idempotent(): void
    {
        $job = new CancelExpiredBookingsJob();

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertGreaterThan(0, $job->uniqueFor);
        $this->assertNotEmpty($job->uniqueId());

        $user = User::factory()->create();
        $room = Room::factory()->create();

        // Create an expired pending booking
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'status' => 'pending',
            'start_time' => now()->subHours(2),
            'end_time' => now()->subHour(),
        ]);

        // First run -> cancels 1 booking
        $cancelledFirst = $job->handle();
        $this->assertSame(1, $cancelledFirst);
        $this->assertSame('cancelled', $booking->fresh()->status);

        // Second run -> 0 bookings cancelled (idempotent 1 effect)
        $cancelledSecond = $job->handle();
        $this->assertSame(0, $cancelledSecond);
    }

    public function test_admin_can_view_queue_dashboard_and_failed_jobs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'karyawan']);

        // Employee is forbidden
        $this->actingAs($employee)
            ->getJson('/api/v1/queue/dashboard')
            ->assertForbidden();

        $this->actingAs($employee)
            ->getJson('/api/v1/queue/failed-jobs')
            ->assertForbidden();

        // Admin can access dashboard
        $this->actingAs($admin)
            ->getJson('/api/v1/queue/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'healthy')
            ->assertJsonPath('data.prune_configured', true);

        // Admin can access failed-jobs endpoint with pagination
        $this->actingAs($admin)
            ->getJson('/api/v1/queue/failed-jobs')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_docker_compose_and_ci_workflow_contain_required_queue_configurations(): void
    {
        $composePath = base_path('../docker-compose.yml');
        $this->assertFileExists($composePath);
        $composeContent = file_get_contents($composePath);
        $this->assertStringContainsString('worker:', $composeContent);
        $this->assertStringContainsString('queue:work', $composeContent);

        $ciPath = base_path('../.github/workflows/ci.yml');
        $this->assertFileExists($ciPath);
        $ciContent = file_get_contents($ciPath);
        $this->assertStringContainsString('queue:restart', $ciContent);

        $consolePath = base_path('routes/console.php');
        $this->assertFileExists($consolePath);
        $consoleContent = file_get_contents($consolePath);
        $this->assertStringContainsString('Schedule::', $consoleContent);
        $this->assertStringContainsString('queue:prune-failed', $consoleContent);
        $this->assertStringContainsString('everyMinute', $consoleContent);
    }
}

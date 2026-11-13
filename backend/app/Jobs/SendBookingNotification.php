<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendBookingNotification implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 3600;

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return "booking-notification-{$this->bookingId}-{$this->action}";
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $bookingId,
        public string $action = 'created'
    ) {}

    /**
     * Execute the job idempotently.
     */
    public function handle(): bool
    {
        $idempotencyKey = "job_executed:booking_notification:{$this->bookingId}:{$this->action}";
        $effectKey = "effect_count:booking_notification:{$this->bookingId}:{$this->action}";

        // If job already executed, preserve idempotency (1 effect only)
        if (Cache::has($idempotencyKey)) {
            Log::info("Idempotent skip: SendBookingNotification for booking #{$this->bookingId} [{$this->action}] already executed.");
            return false;
        }

        // Mark as executed with 24h retention
        Cache::put($idempotencyKey, true, 86400);

        // Record effect
        Cache::increment($effectKey);

        Log::info("SendBookingNotification executed for booking #{$this->bookingId} [{$this->action}].");

        return true;
    }
}

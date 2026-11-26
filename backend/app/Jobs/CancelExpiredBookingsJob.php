<?php

namespace App\Jobs;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CancelExpiredBookingsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 60;

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return 'cancel-expired-bookings-job';
    }

    /**
     * Execute the job idempotently.
     */
    public function handle(): int
    {
        $now = now();
        $cancelledCount = 0;

        Booking::where('status', 'pending')
            ->where('start_time', '<=', $now)
            ->cursor()
            ->each(function (Booking $booking) use ($now, &$cancelledCount): void {
                $dedupKey = "booking_auto_cancelled_{$booking->id}";
                if (Cache::has($dedupKey)) {
                    return;
                }

                Cache::put($dedupKey, true, 86400);

                $booking->update([
                    'status' => 'cancelled',
                    'decided_at' => $now,
                ]);

                $cancelledCount++;
            });

        Log::info("CancelExpiredBookingsJob processed: {$cancelledCount} booking(s) cancelled.");

        return $cancelledCount;
    }
}

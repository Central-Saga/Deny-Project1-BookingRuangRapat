<?php

use App\Jobs\CancelExpiredBookingsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bookings:cancel-expired', function () {
    $job = new CancelExpiredBookingsJob();
    $cancelled = $job->handle();
    $this->info("Cancelled {$cancelled} expired booking(s).");
})->purpose('Cancel pending bookings that have passed their start time');

Artisan::command('queue:failed-summary', function () {
    $count = DB::table('failed_jobs')->count();
    $this->info("Failed jobs in database: {$count}");
})->purpose('Display summary of failed jobs');

// Schedule definitions
Schedule::command('bookings:cancel-expired')->everyMinute();
Schedule::command('queue:monitor default --max=100')->everyMinute();
Schedule::command('queue:prune-failed --hours=48')->daily();
Schedule::command('queue:prune-batches --hours=48')->daily();
Schedule::command('sanctum:prune-expired --hours=48')->daily();

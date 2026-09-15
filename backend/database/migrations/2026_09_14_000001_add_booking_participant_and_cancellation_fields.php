<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (!Schema::hasColumn('bookings', 'participant_count')) {
                $table->unsignedInteger('participant_count')->default(1)->after('title');
            }

            if (!Schema::hasColumn('bookings', 'cancellation_reason')) {
                $table->string('cancellation_reason')->nullable()->after('notes');
            }

            $table->index(['room_id', 'start_time', 'end_time', 'status'], 'bookings_room_schedule_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex('bookings_room_schedule_status_index');
            $table->dropColumn(['participant_count', 'cancellation_reason']);
        });
    }
}; 
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->index(['room_id', 'start_time', 'end_time'], 'bookings_room_time_index');
            $table->index('status', 'bookings_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex('bookings_room_time_index');
            $table->dropIndex('bookings_status_index');
        });
    }
};

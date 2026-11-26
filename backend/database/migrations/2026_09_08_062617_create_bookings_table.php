<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index()->constrained()->onDelete('cascade');
            $table->foreignId('room_id')->index()->constrained()->onDelete('cascade');
            $table->string('title');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['room_id', 'start_time', 'end_time', 'deleted_at'], 'bookings_room_time_deleted_at_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};

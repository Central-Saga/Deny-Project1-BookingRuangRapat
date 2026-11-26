<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        static $sequence = 0;
        $sequence++;
        $startTime = now()->addDays(1)->startOfDay()->addHours(8 + ($sequence % 8));
        $endTime = (clone $startTime)->addHour();

        return [
            'user_id' => User::factory(),
            'room_id' => Room::factory(),
            'title' => $this->faker->sentence(3),
            'participant_count' => 2,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => 'pending',
            'notes' => $this->faker->optional()->sentence(),
            'cancellation_reason' => null,
        ];
    }
}

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
        return [
            'user_id'    => User::factory(),
            'room_id'    => Room::factory(),
            'title'      => $this->faker->sentence(3),
            'start_time' => now()->addDays(1),
            'end_time'   => now()->addDays(1)->addHours(2),
            'status'     => 'pending',
            'notes'      => $this->faker->optional()->sentence(),
        ];
    }
}
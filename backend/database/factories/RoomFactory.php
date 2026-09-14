<?php

namespace Database\Factories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        return [
            'name'        => $this->faker->unique()->word . ' Meeting Room',
            'capacity'    => $this->faker->numberBetween(5, 50),
            'location'    => 'Lantai ' . $this->faker->randomDigitNotNull,
            'facilities'  => ['Proyektor', 'Whiteboard'],
            'description' => $this->faker->sentence(),
            'is_active'   => true,
        ];
    }
}
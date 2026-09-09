<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        Room::create([
            'name' => 'Ruang Innovation',
            'capacity' => 10,
            'location' => 'Lantai 2',
            'description' => 'Dilengkapi proyektor dan whiteboard.',
            'is_active' => true,
        ]);

        Room::create([
            'name' => 'Ruang Executive',
            'capacity' => 20,
            'location' => 'Lantai 3',
            'description' => 'Fasilitas Smart TV dan Video Conference.',
            'is_active' => true,
        ]);
    }
}
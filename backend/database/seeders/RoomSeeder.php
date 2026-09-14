<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        Room::updateOrCreate(
            ['name' => 'Ruang Rapat Utama'],
            [
                'capacity' => 20,
                'location' => 'Lantai 1',
                'facilities' => ['Proyektor', 'Whiteboard', 'Video Conference'],
                'description' => 'Ruang utama untuk rapat lintas divisi.',
                'is_active' => true,
            ]
        );

        Room::updateOrCreate(
            ['name' => 'Ruang Diskusi'],
            [
                'capacity' => 8,
                'location' => 'Lantai 2',
                'facilities' => ['Whiteboard', 'TV Display'],
                'description' => 'Ruang kecil untuk diskusi tim.',
                'is_active' => true,
            ]
        );

        Room::updateOrCreate(
            ['name' => 'Jineng Meet'],
            [
                'capacity' => 12,
                'location' => 'Lantai 3',
                'facilities' => ['Proyektor', 'Papan Tulis', 'Kamera Meeting'],
                'description' => 'Ruang rapat fleksibel untuk kolaborasi.',
                'is_active' => true,
            ]
        );
    }
}
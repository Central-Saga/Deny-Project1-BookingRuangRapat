<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Membuat akun user default untuk login/pengujian
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin MeetSpace',
                'password' => 'password',
                'role' => 'admin',
                'status' => 'active',
            ]
        );

        User::updateOrCreate(
            ['email' => 'karyawan@example.com'],
            [
                'name' => 'Karyawan MeetSpace',
                'password' => 'password',
                'role' => 'karyawan',
                'status' => 'active',
            ]
        );

        // Memanggil RoomSeeder untuk mengisi data ruangan
        $this->call([
            RoomSeeder::class,
        ]);
    }
} 
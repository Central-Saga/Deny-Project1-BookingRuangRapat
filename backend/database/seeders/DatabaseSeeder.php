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
        $admin = User::firstOrNew(['email' => 'admin@example.com']);
        $admin->forceFill([
            'name' => 'Admin MeetSpace',
            'password' => 'password',
            'role' => 'admin',
            'status' => 'active',
        ])->save();

        $karyawan = User::firstOrNew(['email' => 'karyawan@example.com']);
        $karyawan->forceFill([
            'name' => 'Karyawan MeetSpace',
            'password' => 'password',
            'role' => 'karyawan',
            'status' => 'active',
        ])->save();

        // Memanggil RoomSeeder untuk mengisi data ruangan
        $this->call([
            RoomSeeder::class,
        ]);
    }
} 
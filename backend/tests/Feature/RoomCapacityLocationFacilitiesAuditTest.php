<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Audit 496 Parameter 4:
 * Tambahkan Kolom capacity, location, dan facilities pada Tabel rooms
 */
class RoomCapacityLocationFacilitiesAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_rooms_table_has_required_audit_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumn('rooms', 'capacity'),
            'Tabel rooms harus memiliki kolom capacity'
        );

        $this->assertTrue(
            Schema::hasColumn('rooms', 'location'),
            'Tabel rooms harus memiliki kolom location'
        );

        $this->assertTrue(
            Schema::hasColumn('rooms', 'facilities'),
            'Tabel rooms harus memiliki kolom facilities'
        );
    }

    public function test_room_model_persists_and_casts_capacity_location_and_facilities(): void
    {
        $facilities = ['Proyektor 4K', 'Whiteboard', 'Sound System', 'Video Conference'];

        $room = Room::create([
            'name'        => 'Ruang Eksklusif Batur',
            'capacity'    => 20,
            'location'    => 'Gedung Rektorat Lt. 2',
            'facilities'  => $facilities,
            'description' => 'Ruang rapat berstandar eksekutif.',
            'is_active'   => true,
        ]);

        $this->assertDatabaseHas('rooms', [
            'id'       => $room->id,
            'name'     => 'Ruang Eksklusif Batur',
            'capacity' => 20,
            'location' => 'Gedung Rektorat Lt. 2',
        ]);

        $savedRoom = Room::findOrFail($room->id);
        $this->assertIsInt($savedRoom->capacity);
        $this->assertSame(20, $savedRoom->capacity);
        $this->assertSame('Gedung Rektorat Lt. 2', $savedRoom->location);
        $this->assertIsArray($savedRoom->facilities);
        $this->assertSame($facilities, $savedRoom->facilities);
    }

    public function test_rooms_api_index_and_create_endpoints_return_new_columns(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $payload = [
            'name'        => 'Ruang Rapat Agung',
            'capacity'    => 15,
            'location'    => 'Sayap Barat Lt. 1',
            'facilities'  => ['Smart TV', 'AC', 'WiFi 6'],
            'description' => 'Ruang serbaguna tim pengembang.',
            'is_active'   => true,
        ];

        // Create room via API
        $createResponse = $this->actingAs($admin)->postJson('/api/v1/rooms', $payload);
        $createResponse->assertStatus(201)
            ->assertJsonPath('data.name', 'Ruang Rapat Agung')
            ->assertJsonPath('data.capacity', 15)
            ->assertJsonPath('data.location', 'Sayap Barat Lt. 1')
            ->assertJsonPath('data.facilities', ['Smart TV', 'AC', 'WiFi 6']);

        // Get room list via API
        $indexResponse = $this->actingAs($admin)->getJson('/api/v1/rooms');
        $indexResponse->assertOk()
            ->assertJsonFragment([
                'name'     => 'Ruang Rapat Agung',
                'capacity' => 15,
                'location' => 'Sayap Barat Lt. 1',
            ]);
    }
}

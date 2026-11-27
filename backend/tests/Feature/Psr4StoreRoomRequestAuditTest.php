<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\RoomController;
use App\Http\Requests\StoreRoomRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Audit 496 Parameter 14:
 * Rename StoreRoomgRequest.php ke StoreRoomRequest.php untuk Kepatuhan PSR-4 Autoloading Linux
 */
class Psr4StoreRoomRequestAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_typo_file_storeroomgrequest_does_not_exist(): void
    {
        $backendPath = base_path('app/Http/Requests/StoreRoomgRequest.php');
        $this->assertFileDoesNotExist($backendPath, 'File StoreRoomgRequest.php with typo must not exist.');
        $this->assertFalse(file_exists($backendPath));
    }

    public function test_storeroomrequest_file_exists_and_conforms_to_psr4(): void
    {
        $backendPath = base_path('app/Http/Requests/StoreRoomRequest.php');
        $this->assertFileExists($backendPath, 'File StoreRoomRequest.php must exist.');

        $this->assertTrue(
            class_exists(StoreRoomRequest::class),
            'Class App\Http\Requests\StoreRoomRequest must be autoloadable according to PSR-4.'
        );
    }

    public function test_room_controller_store_method_typehints_storeroomrequest(): void
    {
        $reflection = new ReflectionMethod(RoomController::class, 'store');
        $parameters = $reflection->getParameters();

        $this->assertNotEmpty($parameters, 'RoomController::store must have parameters.');
        $firstParam = $parameters[0];

        $type = $firstParam->getType();
        $this->assertNotNull($type, 'First parameter must have a typehint.');
        $this->assertSame(
            StoreRoomRequest::class,
            $type->getName(),
            'RoomController::store must typehint StoreRoomRequest.'
        );
    }

    public function test_room_creation_endpoint_uses_storeroomrequest_validation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Missing required fields
        $response = $this->actingAs($admin)->postJson('/api/v1/rooms', []);
        $response->assertStatus(422);

        // Valid payload
        $validPayload = [
            'name' => 'Ruang Sirius',
            'capacity' => 12,
            'location' => 'Lantai 3 Gedung A',
            'description' => 'Ruang rapat berkapasitas sedang.',
            'is_active' => true,
        ];

        $createResponse = $this->actingAs($admin)->postJson('/api/v1/rooms', $validPayload);
        $createResponse->assertStatus(201)
            ->assertJsonPath('data.name', 'Ruang Sirius')
            ->assertJsonPath('data.capacity', 12);

        $this->assertDatabaseHas('rooms', [
            'name' => 'Ruang Sirius',
            'capacity' => 12,
        ]);
    }
}

<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Audit 496 - Parameter MNT (Issue #168):
 * Tipe dan skema data frontend belum sinkron dengan API.
 * Memastikan kontrak OpenAPI 3.1 ada dan sinkron dengan tipe skema frontend tanpa duplikasi tipe backend.
 */
class OpenApiContractAndFrontendTypesSyncAuditTest extends TestCase
{
    public function test_openapi_specification_contract_file_exists_and_defines_core_schemas(): void
    {
        $openapiPath = base_path('../docs/openapi.yaml');
        $this->assertFileExists($openapiPath, 'Spesifikasi OpenAPI wajib ada pada docs/openapi.yaml.');

        $content = file_get_contents($openapiPath);
        $this->assertStringContainsString('openapi: 3.1.0', $content);
        $this->assertStringContainsString('/v1/rooms', $content);
        $this->assertStringContainsString('/v1/bookings', $content);
        $this->assertStringContainsString('/v1/users', $content);
        $this->assertStringContainsString('Room:', $content);
        $this->assertStringContainsString('Booking:', $content);
        $this->assertStringContainsString('User:', $content);
    }

    public function test_frontend_types_are_synchronized_from_openapi_contract(): void
    {
        $frontendOpenApiPath = base_path('../frontend/src/types/openapi.ts');
        $frontendIndexPath = base_path('../frontend/src/types/index.ts');

        $this->assertFileExists($frontendOpenApiPath, 'Kontrak TypeScript OpenAPI wajib tersedia pada frontend/src/types/openapi.ts.');
        $this->assertFileExists($frontendIndexPath, 'Tipe frontend index.ts wajib ada.');

        $content = file_get_contents($frontendIndexPath);
        $this->assertStringContainsString("from './openapi'", $content);
        $this->assertStringContainsString("components['schemas']['User']", $content);
        $this->assertStringContainsString("components['schemas']['Room']", $content);
        $this->assertStringContainsString("components['schemas']['Booking']", $content);
    }
}

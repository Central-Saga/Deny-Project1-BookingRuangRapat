<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Environment Configuration Audit Test (Audit 496 Parameter SUS - Issue #180).
 *
 * Menguji:
 * 1. .env.example lengkap: 100% key dari config/*.php tercakup dalam .env.example.
 * 2. Env terdokumentasi: seluruh variabel process.env (CI, NEXT_PUBLIC_API_URL) terdokumentasi di frontend .env.example.
 * 3. Env per environment: konfigurasi dan template env tersedia untuk local, testing, staging, dan production.
 */
class EnvironmentConfigurationAuditTest extends TestCase
{
    /**
     * Uji 1: 100% key yang dipanggil via env() di config/*.php harus tercakup dalam .env.example.
     */
    public function test_backend_env_example_covers_one_hundred_percent_of_config_keys(): void
    {
        $configPath = base_path('config');
        $configFiles = glob($configPath.'/*.php');
        $this->assertNotEmpty($configFiles, 'File config harus ada.');

        $configEnvKeys = [];
        foreach ($configFiles as $file) {
            $content = file_get_contents($file);
            if (preg_match_all("/\benv\s*\(\s*['\"]([^'\"]+)['\"]/", $content, $matches)) {
                foreach ($matches[1] as $k) {
                    $configEnvKeys[$k][] = basename($file);
                }
            }
        }

        $this->assertGreaterThan(50, count($configEnvKeys), 'Harus ada env keys yang dideteksi di direktori config.');

        $examplePath = base_path('.env.example');
        $this->assertFileExists($examplePath, 'File backend/.env.example harus ada.');

        $exampleContent = file_get_contents($examplePath);
        $exampleKeys = [];
        foreach (explode("\n", $exampleContent) as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            if (str_starts_with($line, '#')) {
                $line = trim(ltrim($line, '#'));
            }
            if (preg_match('/^([A-Z0-9_]+)=/', $line, $m)) {
                $exampleKeys[$m[1]] = true;
            }
        }

        $missingKeys = [];
        foreach (array_keys($configEnvKeys) as $k) {
            if (! isset($exampleKeys[$k])) {
                $missingKeys[$k] = $configEnvKeys[$k];
            }
        }

        $this->assertEmpty(
            $missingKeys,
            'Semua key env() di config/ harus tercakup 100% di .env.example. Key hilang: '.json_encode(array_keys($missingKeys))
        );
    }

    /**
     * Uji 2: Seluruh variabel process.env pada frontend terdokumentasi di frontend/.env.example.
     */
    public function test_frontend_env_example_documents_all_process_env_variables(): void
    {
        $frontendExamplePath = base_path('../frontend/.env.example');
        $this->assertFileExists($frontendExamplePath, 'File frontend/.env.example harus ada.');

        $content = file_get_contents($frontendExamplePath);

        $requiredVars = ['CI', 'NEXT_PUBLIC_API_URL', 'NODE_ENV'];
        foreach ($requiredVars as $var) {
            $this->assertMatchesRegularExpression(
                "/^#?\\s*{$var}=/m",
                $content,
                "frontend/.env.example harus mendokumentasikan variabel {$var}."
            );
        }
    }

    /**
     * Uji 3: Template konfigurasi per environment (local, testing, staging, production) lengkap.
     */
    public function test_per_environment_configurations_exist_for_all_environments(): void
    {
        $requiredBackendEnvFiles = [
            'backend/.env.example' => base_path('.env.example'),
            'backend/.env.local.example' => base_path('.env.local.example'),
            'backend/.env.testing' => base_path('.env.testing'),
            'backend/.env.testing.example' => base_path('.env.testing.example'),
            'backend/.env.staging.example' => base_path('.env.staging.example'),
            'backend/.env.production.example' => base_path('.env.production.example'),
        ];

        foreach ($requiredBackendEnvFiles as $name => $path) {
            $this->assertFileExists($path, "Berkas {$name} harus ada di repositori.");
        }

        $requiredFrontendEnvFiles = [
            'frontend/.env.example' => base_path('../frontend/.env.example'),
            'frontend/.env.development.example' => base_path('../frontend/.env.development.example'),
            'frontend/.env.production.example' => base_path('../frontend/.env.production.example'),
            'frontend/.env.test.example' => base_path('../frontend/.env.test.example'),
            'frontend/.env.local.example' => base_path('../frontend/.env.local.example'),
        ];

        foreach ($requiredFrontendEnvFiles as $name => $path) {
            $this->assertFileExists($path, "Berkas {$name} harus ada di repositori.");
        }

        $requiredRootEnvFiles = [
            'root .env.example' => base_path('../.env.example'),
            'root .env.local.example' => base_path('../.env.local.example'),
            'root .env.production.example' => base_path('../.env.production.example'),
            'root .env.testing.example' => base_path('../.env.testing.example'),
        ];

        foreach ($requiredRootEnvFiles as $name => $path) {
            $this->assertFileExists($path, "Berkas {$name} harus ada di repositori.");
        }
    }

    /**
     * Uji 4: Config per environment tersedia di backend/config/environment.php.
     */
    public function test_config_environment_returns_environment_matrix(): void
    {
        $envConfig = config('environment');
        $this->assertIsArray($envConfig, 'Config environment harus terdaftar.');
        $this->assertArrayHasKey('supported', $envConfig);
        $this->assertArrayHasKey('environments', $envConfig);

        $environments = $envConfig['environments'];
        $this->assertArrayHasKey('local', $environments);
        $this->assertArrayHasKey('testing', $environments);
        $this->assertArrayHasKey('staging', $environments);
        $this->assertArrayHasKey('production', $environments);

        $this->assertTrue($environments['testing']['debug']);
        $this->assertFalse($environments['production']['debug']);
    }

    /**
     * Uji 5: Template produksi menonaktifkan debug dan mengatur session secure.
     */
    public function test_production_env_defaults_have_debug_disabled_and_secure_flags(): void
    {
        $prodEnvPath = base_path('.env.production.example');
        $content = file_get_contents($prodEnvPath);

        $this->assertMatchesRegularExpression('/^APP_DEBUG=false/m', $content, 'Production env template harus memiliki APP_DEBUG=false.');
        $this->assertMatchesRegularExpression('/^APP_ENV=production/m', $content, 'Production env template harus memiliki APP_ENV=production.');
        $this->assertMatchesRegularExpression('/^SESSION_SECURE_COOKIE=true/m', $content, 'Production env template harus mengaktifkan SESSION_SECURE_COOKIE=true.');
    }
}

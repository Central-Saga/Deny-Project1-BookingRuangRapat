<?php

namespace Tests\Feature;

use Closure;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteClosureAuditTest extends TestCase
{
    public function test_no_routes_use_closures_as_handlers(): void
    {
        $closureRoutes = [];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getAction();

            // Ignore framework internal routes registered by Laravel core in vendor/
            if (isset($action['uses']) && $action['uses'] instanceof Closure) {
                $reflector = new \ReflectionFunction($action['uses']);
                $fileName = str_replace('\\', '/', $reflector->getFileName());
                if (str_contains($fileName, '/vendor/')) {
                    continue;
                }

                $closureRoutes[] = [
                    'uri' => $route->uri(),
                    'methods' => $route->methods(),
                    'file' => $fileName,
                ];
            }
        }

        // Standard backend 3.1: harus 0 route yang menggunakan closure handler
        $this->assertCount(
            0,
            $closureRoutes,
            'Semua route harus menggunakan Controller action agar dapat dicache (route:cache).'
        );
    }

    public function test_route_files_have_no_closure_endpoints(): void
    {
        $routeFiles = [
            base_path('routes/web.php'),
            base_path('routes/api.php'),
        ];

        $closurePattern = '/Route::(get|post|put|patch|delete|any|options)\s*\([^,]+,\s*(function|fn)\s*\(/i';
        $foundMatches = [];

        foreach ($routeFiles as $filePath) {
            if (!file_exists($filePath)) {
                continue;
            }

            $content = file_get_contents($filePath);
            if (preg_match_all($closurePattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $foundMatches[] = [
                        'file' => basename($filePath),
                        'snippet' => $match[0],
                    ];
                }
            }
        }

        $this->assertCount(
            0,
            $foundMatches,
            'Ditemukan closure pada definisi route: ' . json_encode($foundMatches)
        );
    }

    public function test_home_route_renders_welcome_view(): void
    {
        $response = $this->get('/');
        $response->assertOk();
    }

    public function test_route_cache_command_executes_successfully(): void
    {
        $exitCode = Artisan::call('route:cache');
        $this->assertSame(0, $exitCode, 'Artisan route:cache gagal dieksekusi.');

        // Clean up cache after test
        Artisan::call('route:clear');
    }
}

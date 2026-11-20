<?php

/**
 * Audit 496 Parameter 28: Whitelist CORS Origin pada config/cors.php.
 * Pastikan config/cors.php hanya mengizinkan origin frontend yang sah dan tidak menggunakan wildcard *.
 */

$defaultOrigins = [
    'http://localhost:3000',
    'http://127.0.0.1:3000',
];

$envOrigins = array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL', '')))));
$allowedOrigins = array_values(array_unique(array_filter(array_merge($defaultOrigins, $envOrigins))));

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Parameter 28: Strict whitelist origin frontend tanpa wildcard '*'
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];

<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Environment Configuration (Audit 496 - Issue #180)
    |--------------------------------------------------------------------------
    |
    | Menyediakan konfigurasi terpusat berbasis environment (local, testing, staging, production)
    | untuk mempermudah pemeliharaan, CI/CD, dan audit sustainability.
    |
    */

    'current' => env('APP_ENV', 'production'),

    'supported' => [
        'local',
        'testing',
        'staging',
        'production',
    ],

    'environments' => [
        'local' => [
            'debug' => true,
            'url' => env('APP_URL', 'http://localhost:8000'),
            'db_connection' => env('DB_CONNECTION', 'pgsql'),
            'cache_store' => env('CACHE_STORE', 'database'),
            'queue_connection' => env('QUEUE_CONNECTION', 'database'),
            'session_driver' => env('SESSION_DRIVER', 'database'),
            'mail_mailer' => env('MAIL_MAILER', 'log'),
        ],
        'testing' => [
            'debug' => true,
            'url' => env('APP_URL', 'http://localhost:8000'),
            'db_connection' => 'sqlite',
            'cache_store' => 'array',
            'queue_connection' => 'sync',
            'session_driver' => 'array',
            'mail_mailer' => 'array',
        ],
        'staging' => [
            'debug' => false,
            'url' => env('APP_URL', 'https://staging.meetspace.example.com'),
            'db_connection' => env('DB_CONNECTION', 'pgsql'),
            'cache_store' => env('CACHE_STORE', 'redis'),
            'queue_connection' => env('QUEUE_CONNECTION', 'redis'),
            'session_driver' => env('SESSION_DRIVER', 'redis'),
            'mail_mailer' => env('MAIL_MAILER', 'log'),
        ],
        'production' => [
            'debug' => false,
            'url' => env('APP_URL', 'https://meetspace.example.com'),
            'db_connection' => env('DB_CONNECTION', 'pgsql'),
            'cache_store' => env('CACHE_STORE', 'redis'),
            'queue_connection' => env('QUEUE_CONNECTION', 'redis'),
            'session_driver' => env('SESSION_DRIVER', 'redis'),
            'mail_mailer' => env('MAIL_MAILER', 'log'),
        ],
    ],
];

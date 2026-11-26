<?php

use App\Providers\AppServiceProvider;
use NunoMaduro\Collision\Adapters\Laravel\CollisionServiceProvider;

$providers = [
    AppServiceProvider::class,
];

if (class_exists(CollisionServiceProvider::class)) {
    $providers[] = CollisionServiceProvider::class;
}

return $providers;

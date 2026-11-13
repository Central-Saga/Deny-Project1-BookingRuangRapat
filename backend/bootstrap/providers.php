<?php

$providers = [
    App\Providers\AppServiceProvider::class,
];

if (class_exists(\NunoMaduro\Collision\Adapters\Laravel\CollisionServiceProvider::class)) {
    $providers[] = \NunoMaduro\Collision\Adapters\Laravel\CollisionServiceProvider::class;
}

return $providers;

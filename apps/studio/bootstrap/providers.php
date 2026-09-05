<?php

use App\Providers\AppServiceProvider;
use App\Providers\ObservabilityServiceProvider;
use App\Providers\TypeScriptTransformerServiceProvider;
use App\Support\Surface;
use Laravel\Horizon\HorizonServiceProvider;
use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider as BaseTypeScriptTransformerServiceProvider;

// Horizon's own package provider (Laravel\Horizon\HorizonServiceProvider)
// registers its dashboard routes unconditionally, so composer.json disables
// its auto-discovery and both Horizon providers are added here by hand,
// admin surface only. Left to auto-discovery, the dashboard would exist on
// the portal and api surfaces too — reachable, if denied by the gate, rather
// than absent the way config/surface.php's "network-restricted, enforced by
// absence" rule expects for everything else on those surfaces.
//
// env() rather than config('surface.name'): this file runs before
// configuration is loaded.
return [
    AppServiceProvider::class,
    ObservabilityServiceProvider::class,
    ...(env('APP_SURFACE', 'admin') === Surface::ADMIN ? [
        HorizonServiceProvider::class,
        App\Providers\HorizonServiceProvider::class,
    ] : []),
    // spatie/laravel-typescript-transformer is a require-dev package: it
    // regenerates resources/js/types from PHP attributes and has no job in a
    // deployed container. Registered unconditionally, the provider's parent
    // class is simply missing after `composer install --no-dev` and the
    // application cannot boot at all — so its presence is what decides.
    ...(class_exists(BaseTypeScriptTransformerServiceProvider::class)
        ? [TypeScriptTransformerServiceProvider::class]
        : []),
];

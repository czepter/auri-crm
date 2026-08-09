<?php

use App\Providers\AppServiceProvider;
use App\Providers\TypeScriptTransformerServiceProvider;
use App\Support\Surface;
use Laravel\Horizon\HorizonServiceProvider;

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
    ...(env('APP_SURFACE', 'admin') === Surface::ADMIN ? [
        HorizonServiceProvider::class,
        App\Providers\HorizonServiceProvider::class,
    ] : []),
    TypeScriptTransformerServiceProvider::class,
];

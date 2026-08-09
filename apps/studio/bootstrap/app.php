<?php

use App\Http\Middleware\EnsureAudience;
use App\Http\Middleware\EnsurePortalCapability;
use App\Http\Middleware\EnsurePortalMaintenanceClosure;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireConfirmedMfa;
use App\Migration\Console\Commands\ImportHistoricalDataCommand;
use App\Payments\Console\Commands\DetectOverdueInstallmentsCommand;
use App\Support\Surface;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: fn () => Surface::registerRoutes(),
    )
    // Domain commands live beside the feature they belong to (App\Payments\
    // Console\Commands, matching Actions/Events/Http\Controllers everywhere
    // else in this codebase) rather than in the conventional app/Console/
    // Commands, which this codebase has never used — so each has to be named
    // here explicitly; nothing in that directory is auto-discovered.
    ->withCommands([
        DetectOverdueInstallmentsCommand::class,
        ImportHistoricalDataCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/');

        $middleware->alias([
            'mfa' => RequireConfirmedMfa::class,
            'audience' => EnsureAudience::class,
            'portal.maintenance' => EnsurePortalMaintenanceClosure::class,
            'portal.capability' => EnsurePortalCapability::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

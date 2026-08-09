<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: fn () => \App\Support\Surface::registerRoutes(),
    )
    // Domain commands live beside the feature they belong to (App\Payments\
    // Console\Commands, matching Actions/Events/Http\Controllers everywhere
    // else in this codebase) rather than in the conventional app/Console/
    // Commands, which this codebase has never used — so each has to be named
    // here explicitly; nothing in that directory is auto-discovered.
    ->withCommands([
        \App\Payments\Console\Commands\DetectOverdueInstallmentsCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/');

        $middleware->alias([
            'mfa' => \App\Http\Middleware\RequireConfirmedMfa::class,
            'audience' => \App\Http\Middleware\EnsureAudience::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

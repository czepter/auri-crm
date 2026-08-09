<?php

use App\Media\Console\Commands\ReapIncompleteMultipartUploads;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: fn () => \App\Support\Surface::registerRoutes(),
    )
    // withRouting's `commands` above only loads routes/console.php's closure
    // commands, so a class-based command needs stating by hand here rather
    // than relying on the app/Console/Commands auto-discovery this skeleton
    // never enabled.
    ->withCommands([
        ReapIncompleteMultipartUploads::class,
    ])
    ->withSchedule(function (Schedule $schedule) {
        // G-01a's one lifecycle rule (issue #50) — see the command's own
        // docblock for why this is a schedule entry rather than a bucket
        // lifecycle policy. No production deploy configuration exists yet to
        // run `schedule:run` from (same gap docs/queue.md already flags for
        // the worker process); this is the seam it will hang off once one
        // does.
        $schedule->command(ReapIncompleteMultipartUploads::class)->daily();
    })
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

<?php

use App\Automation\Console\Commands\SweepDateOffsetAutomationRulesCommand;
use App\Gallery\Console\Commands\ImportLegacyGalleriesCommand;
use App\Http\Middleware\EnsureAudience;
use App\Http\Middleware\EnsureGalleryGate;
use App\Http\Middleware\EnsurePortalCapability;
use App\Http\Middleware\EnsurePortalMaintenanceClosure;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireConfirmedMfa;
use App\Media\Console\Commands\ReapIncompleteMultipartUploads;
use App\Migration\Console\Commands\CutoverRestoreCommand;
use App\Migration\Console\Commands\CutoverSnapshotCommand;
use App\Migration\Console\Commands\ImportHistoricalDataCommand;
use App\Payments\Console\Commands\DetectOverdueInstallmentsCommand;
use App\Support\Surface;
use Illuminate\Console\Scheduling\Schedule;
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
        ReapIncompleteMultipartUploads::class,
        ImportLegacyGalleriesCommand::class,
        CutoverSnapshotCommand::class,
        CutoverRestoreCommand::class,
        SweepDateOffsetAutomationRulesCommand::class,
    ])
    ->withSchedule(function (Schedule $schedule) {
        // G-01a's one lifecycle rule (issue #50) — see the command's own
        // docblock for why this is a schedule entry rather than a bucket
        // lifecycle policy. No production deploy configuration exists yet to
        // run `schedule:run` from (same gap docs/queue.md already flags for
        // the worker process); this is the seam it will hang off once one
        // does.
        $schedule->command(ReapIncompleteMultipartUploads::class)->daily();

        // E-08's date-offset half — see the command's own docblock. Same
        // "no production schedule:run yet" gap as the reaper above.
        $schedule->command(SweepDateOffsetAutomationRulesCommand::class)->daily();
    })
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
            'gallery.gate' => EnsureGalleryGate::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

<?php

use App\Automation\Console\Commands\SeedDefaultAutomationRulesCommand;
use App\Automation\Console\Commands\SweepDateOffsetAutomationRulesCommand;
use App\Engagement\Console\Commands\PollInboundMailbox;
use App\Gallery\Console\Commands\ImportLegacyGalleriesCommand;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureApiTokenScope;
use App\Http\Middleware\EnsureAudience;
use App\Http\Middleware\EnsureGalleryGate;
use App\Http\Middleware\EnsureIdempotentWrite;
use App\Http\Middleware\EnsureNoStaffExists;
use App\Http\Middleware\EnsurePortalCapability;
use App\Http\Middleware\EnsurePortalMaintenanceClosure;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireStudioOnboarding;
use App\Integration\Console\Commands\CreateApiTokenCommand;
use App\Integration\Console\Commands\CreateWebhookEndpointCommand;
use App\Integration\Console\Commands\RedeliverWebhookCommand;
use App\Integration\Console\Commands\RevokeApiTokenCommand;
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
use Sentry\Laravel\Integration as SentryIntegration;

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
        SeedDefaultAutomationRulesCommand::class,
        PollInboundMailbox::class,
        CreateApiTokenCommand::class,
        RevokeApiTokenCommand::class,
        CreateWebhookEndpointCommand::class,
        RedeliverWebhookCommand::class,
    ])
    ->withSchedule(function (Schedule $schedule) {
        // G-01a's one lifecycle rule (issue #50) — see the command's own
        // docblock for why this is a schedule entry rather than a bucket
        // lifecycle policy. The `scheduler` service in
        // docker-compose.coolify.yml is what runs these in a deployment.
        $schedule->command(ReapIncompleteMultipartUploads::class)->daily();

        // E-08's date-offset half — see the command's own docblock.
        $schedule->command(SweepDateOffsetAutomationRulesCommand::class)->daily();

        // E-12's inbound half. Every minute, without overlapping: a slow pass
        // must not have a second one reading the same unread messages behind
        // it. Harmless if it did — ingest is idempotent on Message-ID — but
        // two IMAP sessions against one mailbox is a way to get locked out.
        $schedule->command(PollInboundMailbox::class)->everyMinute()->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware) {
        // Every deployed surface sits behind a reverse proxy that terminates
        // TLS (Coolify's, see docker-compose.coolify.yml), so without this the
        // framework sees plain HTTP: url() would emit http:// links into
        // contract and gallery mails, and a secure session cookie would be
        // issued against a request Laravel believes is insecure. The proxy is
        // the only thing that can reach these containers — the published port
        // is the proxy's, not the app's — so trusting all of them is trusting
        // exactly one hop.
        $middleware->trustProxies(at: '*');

        // F-11: ahead of everything else in the stack, on all three
        // surfaces, so a request id exists before the first line this
        // request logs — see App\Http\Middleware\AssignRequestId.
        $middleware->prepend(AssignRequestId::class);

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/');

        // N-04: the MCP client calls this directly, off-browser — it holds
        // no session and so no CSRF token to present. The authorization code
        // and PKCE verifier OAuthTokenController checks are the credential
        // that route's CSRF token would otherwise have been.
        $middleware->validateCsrfTokens(except: ['oauth/token']);

        $middleware->alias([
            'setup' => EnsureNoStaffExists::class,
            'onboarding' => RequireStudioOnboarding::class,
            'audience' => EnsureAudience::class,
            'scope' => EnsureApiTokenScope::class,
            'idempotent' => EnsureIdempotentWrite::class,
            'portal.maintenance' => EnsurePortalMaintenanceClosure::class,
            'portal.capability' => EnsurePortalCapability::class,
            'gallery.gate' => EnsureGalleryGate::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // F-11: reports every unhandled exception to GlitchTip (a no-op
        // with no network call while SENTRY_LARAVEL_DSN is empty — see
        // config/sentry.php). Queued job failures go through the same
        // report() call, via Illuminate\Queue\Worker.
        SentryIntegration::handles($exceptions);
    })->create();

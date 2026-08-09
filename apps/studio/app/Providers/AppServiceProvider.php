<?php

namespace App\Providers;

use App\Catalog\Models\Package;
use App\Contracts\Events\ContractSigned;
use App\Contracts\Models\ContractTemplate;
use App\Crm\Events\ProjectBooked;
use App\Crm\Listeners\ApplyTemplatesOnBooking;
use App\Crm\Listeners\BookProjectOnContractAndDeposit;
use App\Crm\Models\Contact;
use App\Crm\Models\InternalNote;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Models\Task;
use App\Crm\Models\TaskTemplate;
use App\Gallery\Listeners\RecomputeGalleryBalanceSettled;
use App\Notifications\NotificationService;
use App\Notifications\Support\NotificationPreferences;
use App\Notifications\Support\QuietHours;
use App\Notifications\Transports\InAppTransport;
use App\Notifications\Transports\MailTransport;
use App\Payments\Events\InstallmentPaid;
use App\Policies\ContactPolicy;
use App\Policies\ContractTemplatePolicy;
use App\Policies\InternalNotePolicy;
use App\Policies\LeadPolicy;
use App\Policies\PackagePolicy;
use App\Policies\ProjectPolicy;
use App\Policies\ShootPolicy;
use App\Policies\TaskPolicy;
use App\Policies\TaskTemplatePolicy;
use App\Support\Surface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // E-07: the transport registry. A new channel is added here, never
        // wired to a domain event directly — see NotificationService's own
        // docblock. NotificationPreferences and QuietHours are T-11's own
        // gates, autowired the same as any other constructor dependency.
        $this->app->singleton(NotificationService::class, fn ($app) => new NotificationService(
            [
                $app->make(MailTransport::class),
                $app->make(InAppTransport::class),
            ],
            $app->make(NotificationPreferences::class),
            $app->make(QuietHours::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blueprint::macro('tenantId', function () {
            /** @var Blueprint $this */
            $column = $this->uuid('tenant_id')->default(config('tenancy.tenant_id'));
            $this->index('tenant_id');

            return $column;
        });

        Blueprint::macro('publicId', function () {
            /** @var Blueprint $this */
            return $this->char('public_id', 24)->unique();
        });

        // Registered by hand: auto-discovery only scans app/Listeners, and the
        // CRM keeps its listeners beside the events they answer to.
        Event::listen(ProjectBooked::class, ApplyTemplatesOnBooking::class);

        // P-05b: one listener, two triggers, converging on the same
        // BookProject the manual button calls — see the class docblock.
        Event::listen(ContractSigned::class, [BookProjectOnContractAndDeposit::class, 'handleContractSigned']);
        Event::listen(InstallmentPaid::class, [BookProjectOnContractAndDeposit::class, 'handleInstallmentPaid']);

        // G-05: keeps Gallery::$balance_settled current. Inert beyond that
        // write — nothing gates on it yet, since delivery/download flows are
        // future stories.
        Event::listen(InstallmentPaid::class, RecomputeGalleryBalanceSettled::class);

        // The Gate resolves users from the *default* guard, which ships as
        // `web`, while every staff session lives on `staff`. Left alone, every
        // policy would be asked about a null user — denying by accident rather
        // than by rule, which is the kind of security that stops working the
        // day someone adds a `before` callback.
        //
        // Set per surface rather than in config, because each surface has its
        // own guard and P2's portal will name a different one here.
        if (Surface::current() === Surface::ADMIN) {
            Auth::shouldUse('staff');
        }

        if (Surface::current() === Surface::PORTAL) {
            Auth::shouldUse('portal');
        }

        // `artisan serve` forwards only a whitelist of variables to the PHP
        // processes it spawns, and APP_SURFACE is not on it — so a portal dev
        // server would read .env instead and quietly serve the admin routes,
        // which is exactly the confusion the surface seam exists to prevent.
        // Production passes the environment normally; this is local only.
        ServeCommand::$passthroughVariables[] = 'APP_SURFACE';

        // Same gap, found by M-00: REDIS_HOST isn't on the whitelist either,
        // so `dev-server.sh`'s `artisan serve` (started via `docker compose
        // run`, which does set it) fell back to .env's own 127.0.0.1 and
        // could never reach the redis service a job dispatched from the
        // browser needs. DB_HOST has the identical gap, silent only because
        // .env's own default happens to already say "postgres".
        ServeCommand::$passthroughVariables[] = 'REDIS_HOST';

        // One cookie name per surface. Two audiences sharing a cookie name on
        // one host is how a browser comes to offer a staff session to the
        // client portal — the guard would refuse it, but only after it had
        // already been sent.
        config([
            'session.cookie' => Surface::COOKIES[Surface::current()]
                ?? config('session.cookie'),
        ]);

        // Policies live in app/Policies for App\Models, but the CRM keeps its
        // models in its own namespace, so the pairs are stated rather than
        // guessed at by convention.
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Shoot::class, ShootPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(InternalNote::class, InternalNotePolicy::class);
        Gate::policy(Lead::class, LeadPolicy::class);
        Gate::policy(Contact::class, ContactPolicy::class);
        Gate::policy(Package::class, PackagePolicy::class);
        Gate::policy(ContractTemplate::class, ContractTemplatePolicy::class);
        Gate::policy(TaskTemplate::class, TaskTemplatePolicy::class);

        // Filling a non-fillable attribute is silent by default, which turns a
        // factory state like withMfa() into a no-op that fails a test three tasks
        // later. Outside production, make it throw at the point of the mistake.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }
}

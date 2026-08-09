<?php

namespace App\Providers;

use App\Catalog\Models\Package;
use App\Crm\Events\ProjectBooked;
use App\Crm\Listeners\ApplyTemplatesOnBooking;
use App\Crm\Models\Contact;
use App\Crm\Models\InternalNote;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Models\Task;
use App\Policies\ContactPolicy;
use App\Policies\InternalNotePolicy;
use App\Policies\LeadPolicy;
use App\Policies\PackagePolicy;
use App\Policies\ProjectPolicy;
use App\Policies\ShootPolicy;
use App\Policies\TaskPolicy;
use App\Support\Surface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Database\Schema\Blueprint;
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
        //
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

        // Filling a non-fillable attribute is silent by default, which turns a
        // factory state like withMfa() into a no-op that fails a test three tasks
        // later. Outside production, make it throw at the point of the mistake.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }
}

<?php

namespace App\Providers;

use App\Automation\Listeners\FireAutomationRulesForEvent;
use App\Catalog\Models\Package;
use App\Contracts\Events\ContractSigned;
use App\Crm\Actions\RescheduleAnchoredTasks;
use App\Crm\Events\LeadCaptured;
use App\Crm\Events\LeadStageChanged;
use App\Crm\Events\ProjectBooked;
use App\Crm\Events\ShootStatusChanged;
use App\Crm\Listeners\ApplyTemplatesOnBooking;
use App\Crm\Listeners\ApplyTemplatesOnStage;
use App\Crm\Listeners\BookProjectOnContractAndDeposit;
use App\Crm\Models\Contact;
use App\Crm\Models\FormTemplate;
use App\Crm\Models\InternalNote;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\ProjectType;
use App\Crm\Models\Shoot;
use App\Crm\Models\Stage;
use App\Crm\Models\Task;
use App\Crm\Models\TaskTemplate;
use App\Crm\Support\CrmVocabulary;
use App\Documents\Models\DocumentTemplate;
use App\Gallery\Listeners\RecomputeGalleryBalanceSettled;
use App\Gallery\Models\Gallery;
use App\Integration\Listeners\DispatchWebhookNotifications;
use App\Notifications\Listeners\NotifyStudioOfDomainEvent;
use App\Notifications\NotificationService;
use App\Notifications\Support\NotificationPreferences;
use App\Notifications\Support\QuietHours;
use App\Notifications\Support\StudioMailFooter;
use App\Notifications\Transports\InAppTransport;
use App\Notifications\Transports\MailTransport;
use App\Notifications\Transports\WebhookTransport;
use App\Payments\Events\InstallmentOverdue;
use App\Payments\Events\InstallmentPaid;
use App\Policies\ContactPolicy;
use App\Policies\DocumentTemplatePolicy;
use App\Policies\FormTemplatePolicy;
use App\Policies\GalleryPolicy;
use App\Policies\InternalNotePolicy;
use App\Policies\LeadPolicy;
use App\Policies\PackagePolicy;
use App\Policies\ProjectPolicy;
use App\Policies\ProjectTypePolicy;
use App\Policies\ShootPolicy;
use App\Policies\StagePolicy;
use App\Policies\TaskPolicy;
use App\Policies\TaskTemplatePolicy;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\PersonalAccessToken;
use App\Support\Surface;
use Aws\S3\S3Client;
use Dedoc\Scramble\Scramble;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // G-01: Scramble's own docs UI and docs.json routes register
        // themselves on every surface regardless of Surface::registerRoutes()
        // — a package boots the same way in every process. Surface's own
        // rule is that a surface carries a route by putting it in that
        // surface's file, enforced by absence rather than by middleware (see
        // config/surface.php); a vendor package registering its own routes
        // behind our back is exactly the hole that rule exists to not have.
        // The committed packages/contracts/openapi.json is this app's only
        // exposure of the document — see the `scramble:export` CI step.
        Scramble::ignoreDefaultRoutes();

        // E-07: the transport registry. A new channel is added here, never
        // wired to a domain event directly — see NotificationService's own
        // docblock. NotificationPreferences and QuietHours are T-11's own
        // gates, autowired the same as any other constructor dependency.
        // I-04: WebhookTransport is the third — registered here rather than
        // subscribing to domain events on its own, per issue #86.
        $this->app->singleton(NotificationService::class, fn ($app) => new NotificationService(
            [
                $app->make(MailTransport::class),
                $app->make(InAppTransport::class),
                $app->make(WebhookTransport::class),
            ],
            $app->make(NotificationPreferences::class),
            $app->make(QuietHours::class),
        ));

        // G-02: the one seam App\Media\Services\MultipartUploadGateway uses
        // to reach past Flysystem, the same boundary
        // ReapIncompleteMultipartUploads already crosses (Storage::disk('s3')
        // ->getClient()) — bound here rather than constructed inline so a
        // test can swap it for an S3Client wired to Aws\MockHandler instead
        // of a live bucket.
        $this->app->singleton(S3Client::class, fn () => Storage::disk('s3')->getClient());

        $this->app->singleton(CrmVocabulary::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The 'mail::' namespace (<x-mail::layout>, <x-mail::button>) only
        // exists while Illuminate\Mail\Markdown::render() is on the stack —
        // it registers it there and nowhere else, pointed at
        // config('mail.markdown.paths').'/html' (see Markdown::
        // htmlComponentPaths()). That covers every notification using the
        // default markdown template, but
        // mail.product-message/mail.blocks-message are rendered through
        // MailMessage::view(), which never touches Markdown::render() at
        // all. Registered permanently at the same path, so both routes
        // resolve the one compiled layout.
        View::addNamespace('mail', resource_path('views/vendor/mail/html'));

        // Studio-branded by default: every existing notification's mail keeps
        // working unmodified, because this only fires when nobody has already
        // passed an explicit brand. The four product mails set 'brand'
        // explicitly and this returns immediately — StaffInvited's shared
        // blocks view passes none, so it falls through to the studio default —
        // see resources/views/mail/{product,blocks}-message.blade.php.
        View::composer('mail::layout', function ($view) {
            if (($view->getData()['brand'] ?? null) !== null) {
                // The product mails' footer, here for the same reason brand is
                // resolved here: one place, rather than the same string copied
                // into four notification classes.
                $view->with('mailFooter', 'Fragen: <a href="https://github.com/czepter/auri-crm/issues">github.com/czepter/auri-crm/issues</a>');

                return;
            }

            $settings = app(StudioSettingsResolver::class);

            $view->with([
                'brand' => $settings->displayName() ?? config('app.name'),
                'logoUrl' => $settings->logoPath()
                    ? rtrim((string) config('portal.url'), '/').'/branding/logo'
                    : null,
                // Signature and legal particulars belong in the footer zone,
                // not on the body as two more ->line()s. Resolved here for the
                // same reason brand is: Blade hands a view's data to no nested
                // component, so the layout's own scope is the only place that
                // can be reached.
                'mailFooter' => app(StudioMailFooter::class)($settings),
            ]);
        });

        // Accent is not brand-differentiated (spec §2's table has no Accent row):
        // every mail, product or studio, shows the current tenant's own accent, with
        // a neutral fallback before any studio has set one.
        View::composer('mail::button', function ($view) {
            $accent = app(StudioSettingsResolver::class)->attributes()['accent_color'] ?? null;
            $view->with('accent', $accent ?: '#111111');
        });

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

        // G-01: the api surface's tokens carry this app's own tenant_id and
        // public_id — see App\Support\PersonalAccessToken's own docblock.
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // The stage table is read through one memoised instance per request
        // (CrmVocabulary) and dropped the moment a stage is written, so a
        // rename lands in the same request that saved it.
        Stage::saved(fn () => app(CrmVocabulary::class)->forget());
        Stage::deleted(fn () => app(CrmVocabulary::class)->forget());
        ProjectType::saved(fn () => app(CrmVocabulary::class)->forget());
        ProjectType::deleted(fn () => app(CrmVocabulary::class)->forget());

        // Registered by hand: auto-discovery only scans app/Listeners, and the
        // CRM keeps its listeners beside the events they answer to.
        Event::listen(ProjectBooked::class, ApplyTemplatesOnBooking::class);

        // Every other stage of both pipelines. Booking stays above: it is
        // reachable without a lead stage ever changing.
        Event::listen(LeadStageChanged::class, [ApplyTemplatesOnStage::class, 'handleLeadStage']);
        Event::listen(ShootStatusChanged::class, [ApplyTemplatesOnStage::class, 'handleShootStatus']);

        // T-15: an anchored due date is written onto tasks.due_on, so every
        // list keeps reading one column. These three are where an anchor can
        // move — a stage entered, a status entered, a shoot date corrected.
        Event::listen(LeadStageChanged::class, fn (LeadStageChanged $event) => app(RescheduleAnchoredTasks::class)($event->lead->project));
        Event::listen(ShootStatusChanged::class, fn (ShootStatusChanged $event) => app(RescheduleAnchoredTasks::class)($event->shoot->project));
        Shoot::saved(function (Shoot $shoot) {
            if ($shoot->wasChanged('date')) {
                app(RescheduleAnchoredTasks::class)($shoot->project);
            }
        });

        // P-05b: one listener, two triggers, converging on the same
        // BookProject the manual button calls — see the class docblock.
        Event::listen(ContractSigned::class, [BookProjectOnContractAndDeposit::class, 'handleContractSigned']);
        Event::listen(InstallmentPaid::class, [BookProjectOnContractAndDeposit::class, 'handleInstallmentPaid']);

        // G-05: keeps Gallery::$balance_settled current. Inert beyond that
        // write — nothing gates on it yet, since delivery/download flows are
        // future stories.
        Event::listen(InstallmentPaid::class, RecomputeGalleryBalanceSettled::class);

        // I-04: wraps each domain event for NotificationService rather than
        // handing WebhookTransport the event directly — see that listener's
        // own docblock. ShootDelivered and GalleryPublished, the other two
        // events issue #86 names, don't exist as classes yet; wiring them is
        // one Event::listen line here once their own stories land.
        Event::listen(ProjectBooked::class, [DispatchWebhookNotifications::class, 'handleProjectBooked']);
        Event::listen(ContractSigned::class, [DispatchWebhookNotifications::class, 'handleContractSigned']);
        Event::listen(InstallmentPaid::class, [DispatchWebhookNotifications::class, 'handleInstallmentPaid']);
        Event::listen(InstallmentOverdue::class, [DispatchWebhookNotifications::class, 'handleInstallmentOverdue']);

        // T-11: the studio's own inbox. Three of the six events the profile
        // screen lists have a domain event; the other three call NotifyStudio
        // from their action — see the listener's own docblock.
        Event::listen(LeadCaptured::class, [NotifyStudioOfDomainEvent::class, 'handleLeadCaptured']);
        Event::listen(ContractSigned::class, [NotifyStudioOfDomainEvent::class, 'handleContractSigned']);
        Event::listen(InstallmentPaid::class, [NotifyStudioOfDomainEvent::class, 'handleInstallmentPaid']);

        // E-08: one wildcard subscription rather than one Event::listen()
        // per event class — an AutomationRule's own event_class column is
        // what names the subscription. See the listener's own docblock.
        Event::listen('*', FireAutomationRulesForEvent::class);

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

        if (Surface::current() === Surface::API) {
            Auth::shouldUse('api');
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
        Gate::policy(DocumentTemplate::class, DocumentTemplatePolicy::class);
        Gate::policy(TaskTemplate::class, TaskTemplatePolicy::class);
        Gate::policy(Stage::class, StagePolicy::class);
        Gate::policy(ProjectType::class, ProjectTypePolicy::class);
        Gate::policy(Gallery::class, GalleryPolicy::class);
        Gate::policy(FormTemplate::class, FormTemplatePolicy::class);

        // Filling a non-fillable attribute is silent by default, which turns a
        // factory state like withMfa() into a no-op that fails a test three tasks
        // later. Outside production, make it throw at the point of the mistake.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }
}

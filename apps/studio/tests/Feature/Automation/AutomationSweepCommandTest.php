<?php

use App\Automation\Actions\SweepDateOffsetAutomationRules;
use App\Automation\Enums\AutomationAnchor;
use App\Automation\Enums\AutomationOffsetDirection;
use App\Automation\Enums\AutomationRecipient;
use App\Automation\Models\AutomationRule;
use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\Shoot;
use App\Settings\Actions\UpdateStudioSetting;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('runs from the artisan command, reporting how many rules it fired', function () {
    Notification::fake();

    $project = ClientCase::factory()->create();
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);
    markBooked($project);
    Shoot::factory()->for($project, 'case')->create(['date' => now()->addDays(7)->toDateString()]);

    AutomationRule::factory()
        ->dateOffset(AutomationAnchor::PerShoot, 7, AutomationOffsetDirection::Before)
        ->create();

    $this->artisan('automation:sweep-date-offset-rules')
        ->expectsOutputToContain('Fired 1 automation rule(s).')
        ->assertSuccessful();
});

// 00:30 in Berlin is still yesterday in UTC; the sweep must count from the studio's day.
it('counts today on the studio clock', function () {
    Notification::fake();
    app(UpdateStudioSetting::class)('timezone', 'Europe/Berlin', null);
    $this->travelTo(CarbonImmutable::parse('2026-06-01 22:30', 'UTC'));

    $project = ClientCase::factory()->create();
    markBooked($project);
    Shoot::factory()->for($project, 'case')->create(['date' => '2026-06-09']);

    AutomationRule::factory()
        ->dateOffset(AutomationAnchor::PerShoot, 7, AutomationOffsetDirection::Before)
        ->create(['recipient_type' => AutomationRecipient::Studio]);
    staffMember();

    expect(app(SweepDateOffsetAutomationRules::class)())->toBe(1);
});

it('registers the sweep on the daily schedule', function () {
    // bootstrap/app.php's withSchedule() callback only runs once the console
    // kernel actually boots (Artisan::starting) — a plain app(Schedule::class)
    // in a feature test resolves an empty Schedule, since nothing here has
    // run an artisan command yet. `inspire` is the cheapest one to boot with
    // — see ReapIncompleteMultipartUploadsTest's own schedule test.
    Artisan::call('inspire');

    $schedule = app(Schedule::class);

    $events = collect($schedule->events())
        ->filter(fn ($event) => str_contains($event->command ?? '', 'automation:sweep-date-offset-rules'));

    expect($events)->not->toBeEmpty()
        ->and($events->first()->expression)->toBe('0 0 * * *');
});

it('runs the sweep and the overdue scan on the studio clock', function () {
    app(UpdateStudioSetting::class)('timezone', 'Pacific/Auckland', null);
    Artisan::call('inspire');
    app()->forgetInstance(Schedule::class);
    Facade::clearResolvedInstance(Schedule::class);
    require base_path('routes/console.php');

    $events = collect(app(Schedule::class)->events());
    $sweep = $events->first(fn ($event) => str_contains($event->command ?? '', 'automation:sweep-date-offset-rules'));
    $overdue = $events->first(fn ($event) => str_contains($event->command ?? '', 'payments:detect-overdue-installments'));

    expect($sweep->timezone)->toBe('Pacific/Auckland')
        ->and($overdue->timezone)->toBe('Pacific/Auckland');
});

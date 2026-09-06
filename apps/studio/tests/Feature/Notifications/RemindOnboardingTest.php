<?php

// apps/studio/tests/Feature/Notifications/RemindOnboardingTest.php

use App\Enums\StaffRole;
use App\Models\User;
use App\Notifications\Actions\RemindOnboarding;
use App\Notifications\Studio\StudioSetupPending;
use App\Settings\Models\StudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    StudioSettings::query()->update(['onboarding_completed_at' => null, 'onboarding_reminder_sent_at' => null]);
    Cache::store('redis')->flush();
});

it('reminds the earliest owner once the studio is unfinished after two days', function () {
    Notification::fake();
    $owner = User::factory()->create(['role' => StaffRole::Owner, 'created_at' => now()->subDays(3)]);

    $count = app(RemindOnboarding::class)();

    expect($count)->toBe(1);
    Notification::assertSentTo($owner, StudioSetupPending::class);
    expect(StudioSettings::query()->sole()->onboarding_reminder_sent_at)->not->toBeNull();
});

it('sends nothing before two days have passed', function () {
    Notification::fake();
    User::factory()->create(['role' => StaffRole::Owner, 'created_at' => now()->subHours(12)]);

    expect(app(RemindOnboarding::class)())->toBe(0);
    Notification::assertNothingSent();
});

it('sends nothing once onboarding is complete', function () {
    Notification::fake();
    User::factory()->create(['role' => StaffRole::Owner, 'created_at' => now()->subDays(3)]);
    StudioSettings::query()->update(['onboarding_completed_at' => now()]);
    Cache::store('redis')->flush();

    expect(app(RemindOnboarding::class)())->toBe(0);
    Notification::assertNothingSent();
});

it('does not send a second reminder on a repeated run', function () {
    Notification::fake();
    User::factory()->create(['role' => StaffRole::Owner, 'created_at' => now()->subDays(3)]);

    app(RemindOnboarding::class)();
    $count = app(RemindOnboarding::class)();

    expect($count)->toBe(0);
    Notification::assertSentToTimes(User::sole(), StudioSetupPending::class, 1);
});

it('registers the reminder on the daily schedule', function () {
    $schedule = app(Illuminate\Console\Scheduling\Schedule::class);
    $events = collect($schedule->events())
        ->filter(fn ($event) => str_contains($event->command ?? '', 'notifications:remind-onboarding'));

    expect($events)->not->toBeEmpty()
        ->and($events->first()->expression)->toBe('0 9 * * *');
});

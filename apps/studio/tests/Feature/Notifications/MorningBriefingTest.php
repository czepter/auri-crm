<?php

use App\Crm\Actions\RemindDueTasks;
use App\Crm\Models\ClientCase;
use App\Crm\Models\CrewAssignment;
use App\Crm\Models\Shoot;
use App\Crm\Models\Task;
use App\Notifications\Actions\BuildMorningBriefing;
use App\Notifications\Enums\NotificationMode;
use App\Notifications\Studio\MorningBriefing;
use App\Notifications\Studio\TasksDue;
use App\Notifications\Support\NotificationPreferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// Fällige Aufgaben stehen per Vorgabe im Tagesüberblick.
it('sends one mail with the sections that have something in them', function () {
    Notification::fake();

    $user = staffMember();
    Task::factory()->due(now()->toDateString())->create();

    expect(app(BuildMorningBriefing::class)())->toBe(1);

    Notification::assertSentTo($user, MorningBriefing::class);
});

it('sends nothing when every section is empty', function () {
    Notification::fake();
    staffMember();

    expect(app(BuildMorningBriefing::class)())->toBe(0);
});

it('sends nothing to somebody who reads the scans immediately', function () {
    Notification::fake();

    $user = staffMember();
    Task::factory()->due(now()->toDateString())->create();

    foreach (NotificationMode::BRIEFABLE as $class) {
        app(NotificationPreferences::class)->setMode($user, $class, NotificationMode::Immediate);
    }

    expect(app(BuildMorningBriefing::class)())->toBe(0);
});

// Der Zuschnitt ist derselbe wie bei jeder anderen Meldung.
it('leaves out what belongs to somebody else’s project', function () {
    Notification::fake();

    $crewed = staffMember();
    $uninvolved = staffMember();

    $project = ClientCase::factory()->create();
    $shoot = Shoot::factory()->create(['case_id' => $project->id]);
    CrewAssignment::factory()->create(['shoot_id' => $shoot->id, 'user_id' => $crewed->id]);

    Task::factory()->due(now()->toDateString())->create(['case_id' => $project->id]);

    app(BuildMorningBriefing::class)();

    Notification::assertSentTo($crewed, MorningBriefing::class);
    Notification::assertNotSentTo($uninvolved, MorningBriefing::class);
});

it('keeps the scan mail off the desk of somebody reading the briefing', function () {
    Notification::fake();

    $user = staffMember();
    Task::factory()->due(now()->toDateString())->create();

    app(RemindDueTasks::class)();

    Notification::assertNotSentTo($user, TasksDue::class, fn ($notification, $channels) => in_array('mail', $channels, true));
});

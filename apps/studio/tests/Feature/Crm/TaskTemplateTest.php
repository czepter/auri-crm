<?php

use App\Crm\Actions\ApplyTaskTemplates;
use App\Crm\Actions\BookProject;
use App\Crm\Enums\ShootKind;
use App\Crm\Enums\TaskAudience;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Models\Task;
use App\Crm\Models\TaskTemplate;
use App\Models\User;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::store('redis')->flush());

it('anchors each task to its own shoot date, not the project', function () {
    // The story's whole point: a project with an engagement session and a
    // wedding needs its briefing against the shoot it belongs to.
    $project = Project::factory()->create();
    $engagement = Shoot::factory()->for($project)->on('2026-03-22')->create(['kind' => ShootKind::Engagement]);
    $wedding = Shoot::factory()->for($project)->on('2026-06-14')->primary()->create(['kind' => ShootKind::Wedding]);

    TaskTemplate::factory()->offset(-1)->create(['title' => 'Crew-Briefing verschicken']);

    app(ApplyTaskTemplates::class)($project);

    expect(Task::where('shoot_id', $engagement->id)->sole()->due_on->format('Y-m-d'))->toBe('2026-03-21')
        ->and(Task::where('shoot_id', $wedding->id)->sole()->due_on->format('Y-m-d'))->toBe('2026-06-13');
});

it('applies a positive offset after the shoot', function () {
    $project = Project::factory()->create();
    Shoot::factory()->for($project)->on('2026-06-14')->create();
    TaskTemplate::factory()->offset(14)->create(['title' => 'Review erbitten']);

    app(ApplyTaskTemplates::class)($project);

    expect(Task::sole()->due_on->format('Y-m-d'))->toBe('2026-06-28');
});

it('applies a template only to the kind of shoot it names', function () {
    $project = Project::factory()->create();
    $wedding = Shoot::factory()->for($project)->on('2026-06-14')->create(['kind' => ShootKind::Wedding]);
    Shoot::factory()->for($project)->on('2026-03-22')->create(['kind' => ShootKind::Engagement]);

    TaskTemplate::factory()->forKind(ShootKind::Wedding)->create(['title' => 'Familienliste einholen']);

    app(ApplyTaskTemplates::class)($project);

    expect(Task::sole()->shoot_id)->toBe($wedding->id);
});

it('creates the task even when the shoot has no date yet', function () {
    // The work is owed either way; there is simply no honest day to put on it.
    $project = Project::factory()->create();
    Shoot::factory()->for($project)->on(null)->create();
    TaskTemplate::factory()->offset(-1)->create();

    app(ApplyTaskTemplates::class)($project);

    expect(Task::sole()->due_on)->toBeNull();
});

it('skips cancelled shoots and inactive templates', function () {
    $project = Project::factory()->create();
    Shoot::factory()->for($project)->cancelled('Abgesagt')->create();
    TaskTemplate::factory()->create();

    $live = Project::factory()->create();
    Shoot::factory()->for($live)->create();
    TaskTemplate::factory()->inactive()->create(['title' => 'Nicht mehr in Gebrauch']);

    app(ApplyTaskTemplates::class)($project);

    expect(Task::where('project_id', $project->id)->count())->toBe(0);
});

it('carries the template audience onto the task', function () {
    $project = Project::factory()->create();
    Shoot::factory()->for($project)->create();
    TaskTemplate::factory()->create(['audience' => TaskAudience::Client]);

    app(ApplyTaskTemplates::class)($project);

    expect(Task::sole()->audience)->toBe(TaskAudience::Client);
});

it('applies twice without duplicating a task', function () {
    // P-05b adds a second booking trigger and E-08 will re-apply templates.
    $project = Project::factory()->create();
    Shoot::factory()->for($project)->create();
    TaskTemplate::factory()->create();

    app(ApplyTaskTemplates::class)($project);
    app(ApplyTaskTemplates::class)($project);

    expect(Task::count())->toBe(1);
});

it('produces each template against the correct shoot when a project is booked', function () {
    // The story's acceptance, through the real trigger.
    $project = Project::factory()->create();
    Shoot::factory()->for($project)->on('2026-03-22')->create(['kind' => ShootKind::Engagement]);
    Shoot::factory()->for($project)->on('2026-06-14')->create(['kind' => ShootKind::Wedding]);

    TaskTemplate::factory()->offset(-1)->create(['title' => 'Crew-Briefing verschicken']);
    TaskTemplate::factory()->offset(-7)->forKind(ShootKind::Wedding)->create(['title' => 'Familienliste einholen']);

    app(BookProject::class)($project, User::factory()->create());

    expect(Task::count())->toBe(3)
        ->and(Task::where('title', 'Familienliste einholen')->sole()->due_on->format('Y-m-d'))->toBe('2026-06-07');
});

it('rolls a due date landing on a non-working day forward to the next working day', function () {
    // 2026-06-15 is a Monday; -2 days lands the raw offset on Saturday,
    // 2026-06-13. The seeded default (every day) does not roll it, so this
    // narrows the studio's working days to Monday-Friday first — the same
    // configuration T-13's issue names as the motivating example.
    $owner = User::factory()->create();
    app(StudioSettingsResolver::class)->set('working_days', [1, 2, 3, 4, 5], $owner);

    $project = Project::factory()->create();
    Shoot::factory()->for($project)->on('2026-06-15')->create();
    TaskTemplate::factory()->offset(-2)->create(['title' => 'Technik-Check']);

    app(ApplyTaskTemplates::class)($project);

    // Saturday and Sunday are both non-working; the roll lands on the
    // following Monday, one direction, all the way to the next working day.
    expect(Task::sole()->due_on->format('Y-m-d'))->toBe('2026-06-15');
});

it('rolls forward only as far as the next configured working day, even when a single day is excluded', function () {
    // The issue's own acceptance example: exclude only Saturday. The raw
    // offset lands on Saturday 2026-06-13; Sunday 2026-06-14 is still a
    // working day under this configuration, so the roll stops there.
    $owner = User::factory()->create();
    app(StudioSettingsResolver::class)->set('working_days', [1, 2, 3, 4, 5, 7], $owner);

    $project = Project::factory()->create();
    Shoot::factory()->for($project)->on('2026-06-15')->create();
    TaskTemplate::factory()->offset(-2)->create(['title' => 'Technik-Check']);

    app(ApplyTaskTemplates::class)($project);

    expect(Task::sole()->due_on->format('Y-m-d'))->toBe('2026-06-14');
});

it('leaves a due date that already lands on a working day untouched', function () {
    $owner = User::factory()->create();
    app(StudioSettingsResolver::class)->set('working_days', [1, 2, 3, 4, 5], $owner);

    $project = Project::factory()->create();
    Shoot::factory()->for($project)->on('2026-06-15')->create(); // Monday
    TaskTemplate::factory()->offset(0)->create();

    app(ApplyTaskTemplates::class)($project);

    expect(Task::sole()->due_on->format('Y-m-d'))->toBe('2026-06-15');
});

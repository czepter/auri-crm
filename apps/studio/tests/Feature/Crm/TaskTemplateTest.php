<?php

use App\Crm\Actions\ApplyTaskTemplates;
use App\Crm\Actions\BookProject;
use App\Crm\Enums\TaskAudience;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Shoot;
use App\Crm\Models\Task;
use App\Crm\Models\TaskTemplate;
use App\Models\User;
use App\Settings\Actions\UpdateStudioSetting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::store('redis')->flush());

it('anchors each task to its own shoot date, not the project', function () {
    // The story's whole point: a project with an engagement session and a
    // wedding needs its briefing against the shoot it belongs to.
    $project = ClientCase::factory()->create();
    $engagement = Shoot::factory()->for($project, 'case')->on('2026-03-22')->create(['kind' => 'Verlobungsshooting']);
    $wedding = Shoot::factory()->for($project, 'case')->on('2026-06-14')->primary()->create(['kind' => 'Hochzeitstag']);

    TaskTemplate::factory()->offset(-1)->create(['title' => 'Crew-Briefing verschicken']);

    app(ApplyTaskTemplates::class)($project);

    expect(Task::where('shoot_id', $engagement->id)->sole()->due_on->format('Y-m-d'))->toBe('2026-03-21')
        ->and(Task::where('shoot_id', $wedding->id)->sole()->due_on->format('Y-m-d'))->toBe('2026-06-13');
});

it('applies a positive offset after the shoot', function () {
    $project = ClientCase::factory()->create();
    Shoot::factory()->for($project, 'case')->on('2026-06-14')->create();
    TaskTemplate::factory()->offset(14)->create(['title' => 'Review erbitten']);

    app(ApplyTaskTemplates::class)($project);

    expect(Task::sole()->due_on->format('Y-m-d'))->toBe('2026-06-28');
});

it('applies a template only to the kind of shoot it names', function () {
    $project = ClientCase::factory()->create();
    $wedding = Shoot::factory()->for($project, 'case')->on('2026-06-14')->create(['kind' => 'Hochzeitstag']);
    Shoot::factory()->for($project, 'case')->on('2026-03-22')->create(['kind' => 'Verlobungsshooting']);

    TaskTemplate::factory()->forKind('Hochzeitstag')->create(['title' => 'Familienliste einholen']);

    app(ApplyTaskTemplates::class)($project);

    expect(Task::sole()->shoot_id)->toBe($wedding->id);
});

it('creates the task even when the shoot has no date yet', function () {
    // The work is owed either way; there is simply no honest day to put on it.
    $project = ClientCase::factory()->create();
    Shoot::factory()->for($project, 'case')->on(null)->create();
    TaskTemplate::factory()->offset(-1)->create();

    app(ApplyTaskTemplates::class)($project);

    expect(Task::sole()->due_on)->toBeNull();
});

it('skips cancelled shoots and inactive templates', function () {
    $project = ClientCase::factory()->create();
    Shoot::factory()->for($project, 'case')->cancelled('Abgesagt')->create();
    TaskTemplate::factory()->create();

    $live = ClientCase::factory()->create();
    Shoot::factory()->for($live, 'case')->create();
    TaskTemplate::factory()->inactive()->create(['title' => 'Nicht mehr in Gebrauch']);

    app(ApplyTaskTemplates::class)($project);

    expect(Task::where('case_id', $project->id)->count())->toBe(0);
});

it('carries the template audience onto the task', function () {
    $project = ClientCase::factory()->create();
    Shoot::factory()->for($project, 'case')->create();
    TaskTemplate::factory()->create(['audience' => TaskAudience::Client]);

    app(ApplyTaskTemplates::class)($project);

    expect(Task::sole()->audience)->toBe(TaskAudience::Client);
});

it('applies twice without duplicating a task', function () {
    // P-05b adds a second booking trigger and E-08 will re-apply templates.
    $project = ClientCase::factory()->create();
    Shoot::factory()->for($project, 'case')->create();
    TaskTemplate::factory()->create();

    app(ApplyTaskTemplates::class)($project);
    app(ApplyTaskTemplates::class)($project);

    expect(Task::count())->toBe(1);
});

it('keeps a day and a title moved by hand when the stage runs again', function () {
    $project = ClientCase::factory()->create();
    Shoot::factory()->for($project, 'case')->on('2026-06-14')->create();
    TaskTemplate::factory()->offset(-1)->create(['title' => 'Crew-Briefing verschicken']);

    app(ApplyTaskTemplates::class)($project);
    Task::sole()->update(['title' => 'Briefing mit Nina', 'due_on' => '2026-06-01']);

    app(ApplyTaskTemplates::class)($project);

    $task = Task::sole();

    expect($task->title)->toBe('Briefing mit Nina')
        ->and($task->due_on->format('Y-m-d'))->toBe('2026-06-01');
});

it('produces each template against the correct shoot when a project is booked', function () {
    // The story's acceptance, through the real trigger.
    $project = ClientCase::factory()->create();
    Shoot::factory()->for($project, 'case')->on('2026-03-22')->create(['kind' => 'Verlobungsshooting']);
    Shoot::factory()->for($project, 'case')->on('2026-06-14')->create(['kind' => 'Hochzeitstag']);

    TaskTemplate::factory()->offset(-1)->create(['title' => 'Crew-Briefing verschicken']);
    TaskTemplate::factory()->offset(-7)->forKind('Hochzeitstag')->create(['title' => 'Familienliste einholen']);

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
    app(UpdateStudioSetting::class)('working_days', [1, 2, 3, 4, 5], $owner);

    $project = ClientCase::factory()->create();
    Shoot::factory()->for($project, 'case')->on('2026-06-15')->create();
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
    app(UpdateStudioSetting::class)('working_days', [1, 2, 3, 4, 5, 7], $owner);

    $project = ClientCase::factory()->create();
    Shoot::factory()->for($project, 'case')->on('2026-06-15')->create();
    TaskTemplate::factory()->offset(-2)->create(['title' => 'Technik-Check']);

    app(ApplyTaskTemplates::class)($project);

    expect(Task::sole()->due_on->format('Y-m-d'))->toBe('2026-06-14');
});

it('previews the same due dates the real booking action produces for the same shoot date (T-14)', function () {
    // The story's own acceptance check: the settings preview must reuse
    // dueFor()/appliesTo() rather than a second copy of the offset math, so
    // it can never drift from what a real booking produces.
    TaskTemplate::factory()->offset(-1)->create(['title' => 'Crew-Briefing verschicken']);
    TaskTemplate::factory()->offset(-7)->forKind('Hochzeitstag')->create(['title' => 'Familienliste einholen']);
    TaskTemplate::factory()->offset(14)->forKind('Verlobungsshooting')->create(['title' => 'Nicht anwendbar']);

    $preview = app(ApplyTaskTemplates::class)->preview(CarbonImmutable::parse('2026-06-14'), 'Hochzeitstag');

    $project = ClientCase::factory()->create();
    Shoot::factory()->for($project, 'case')->on('2026-06-14')->create(['kind' => 'Hochzeitstag']);
    app(BookProject::class)($project, User::factory()->create());

    $actual = Task::query()->orderBy('title')->get()
        ->map(fn (Task $task) => ['title' => $task->title, 'dueOn' => $task->due_on?->format('Y-m-d')])
        ->values()->all();

    expect(collect($preview)->sortBy('title')->values()->all())->toBe($actual);
});

it('leaves a due date that already lands on a working day untouched', function () {
    $owner = User::factory()->create();
    app(UpdateStudioSetting::class)('working_days', [1, 2, 3, 4, 5], $owner);

    $project = ClientCase::factory()->create();
    Shoot::factory()->for($project, 'case')->on('2026-06-15')->create(); // Monday
    TaskTemplate::factory()->offset(0)->create();

    app(ApplyTaskTemplates::class)($project);

    expect(Task::sole()->due_on->format('Y-m-d'))->toBe('2026-06-15');
});

it('matches a template to a shoot kind regardless of case', function () {
    $template = TaskTemplate::factory()->forKind('hochzeitstag')->make();

    expect($template->appliesTo(new Shoot(['kind' => 'Hochzeitstag'])))->toBeTrue()
        ->and($template->appliesTo(new Shoot(['kind' => 'Feier'])))->toBeFalse();
});

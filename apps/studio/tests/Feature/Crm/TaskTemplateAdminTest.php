<?php

use App\Crm\Actions\BookProject;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Models\Task;
use App\Crm\Models\TaskTemplate;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::store('redis')->flush());

it('renders every template, active and inactive both, with a preview when a date is asked for', function () {
    TaskTemplate::factory()->create(['title' => 'Crew-Briefing verschicken']);
    TaskTemplate::factory()->inactive()->create(['title' => 'Nicht mehr in Gebrauch']);

    $props = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/vorlagen?typ=aufgaben&preview_date=2026-06-14')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['taskTemplates'])->toHaveCount(2)
        ->and($props['preview'])->toHaveCount(1)
        ->and($props['preview'][0]['title'])->toBe('Crew-Briefing verschicken')
        ->and($props['preview'][0]['dueOn'])->toBe('2026-06-13');
});

it('renders no preview until a date is asked for', function () {
    TaskTemplate::factory()->create();

    $props = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/vorlagen?typ=aufgaben')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['preview'])->toBeNull();
});

it('creates a template and leaves an audit row naming the actor', function () {
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')
        ->from('/einstellungen/aufgabenvorlagen')
        ->post('/einstellungen/aufgabenvorlagen', [
            'title' => 'Technik-Check',
            'audience' => 'internal',
            'day_offset' => -2,
            'shoot_kind' => null,
            'is_active' => true,
            'sort_order' => 0,
        ])
        ->assertRedirect('/einstellungen/aufgabenvorlagen');

    $template = TaskTemplate::sole();
    expect($template->title)->toBe('Technik-Check')
        ->and($template->day_offset)->toBe(-2)
        ->and(AuditLog::where('event', 'task_template.created')->sole()->actor_id)->toBe($owner->id);
});

it('rejects a title over the column limit', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post('/einstellungen/aufgabenvorlagen', [
            'title' => str_repeat('x', 256),
            'audience' => 'internal',
            'day_offset' => 0,
            'shoot_kind' => null,
            'is_active' => true,
            'sort_order' => 0,
        ])
        ->assertSessionHasErrors('title');
});

it("edits a template's offset and it reaches the next booking, never one already made — the story's own acceptance", function () {
    $owner = staffMember(StaffRole::Owner);
    $template = TaskTemplate::factory()->offset(-1)->create(['title' => 'Crew-Briefing verschicken']);

    $alreadyBooked = Project::factory()->create();
    Shoot::factory()->for($alreadyBooked)->on('2026-06-14')->create();
    app(BookProject::class)($alreadyBooked, $owner);

    $this->actingAs($owner, 'staff')
        ->from('/einstellungen/aufgabenvorlagen')
        ->patch("/einstellungen/aufgabenvorlagen/{$template->public_id}", [
            'title' => $template->title,
            'audience' => $template->audience->value,
            'day_offset' => -3,
            'shoot_kind' => null,
            'is_active' => true,
            'sort_order' => $template->sort_order,
        ])
        ->assertRedirect('/einstellungen/aufgabenvorlagen');

    $newlyBooked = Project::factory()->create();
    Shoot::factory()->for($newlyBooked)->on('2026-06-14')->create();
    app(BookProject::class)($newlyBooked, $owner);

    expect(Task::where('project_id', $alreadyBooked->id)->sole()->due_on->format('Y-m-d'))->toBe('2026-06-13')
        ->and(Task::where('project_id', $newlyBooked->id)->sole()->due_on->format('Y-m-d'))->toBe('2026-06-11');
});

it('deactivating a template leaves the tasks it already created alone', function () {
    $owner = staffMember(StaffRole::Owner);
    $template = TaskTemplate::factory()->create(['title' => 'Familienliste einholen']);

    $project = Project::factory()->create();
    Shoot::factory()->for($project)->on('2026-06-14')->create();
    app(BookProject::class)($project, $owner);

    $task = Task::sole();

    $this->actingAs($owner, 'staff')
        ->from('/einstellungen/aufgabenvorlagen')
        ->patch("/einstellungen/aufgabenvorlagen/{$template->public_id}", [
            'title' => $template->title,
            'audience' => $template->audience->value,
            'day_offset' => $template->day_offset,
            'shoot_kind' => null,
            'is_active' => false,
            'sort_order' => $template->sort_order,
        ])
        ->assertRedirect('/einstellungen/aufgabenvorlagen');

    expect($template->fresh()->is_active)->toBeFalse()
        ->and(Task::count())->toBe(1)
        ->and($task->fresh()->title)->toBe('Familienliste einholen')
        ->and($task->fresh()->due_on)->not->toBeNull();
});

it('deletes a template without touching the tasks it already created', function () {
    $owner = staffMember(StaffRole::Owner);
    $template = TaskTemplate::factory()->create();

    $project = Project::factory()->create();
    Shoot::factory()->for($project)->on('2026-06-14')->create();
    app(BookProject::class)($project, $owner);

    $this->actingAs($owner, 'staff')
        ->from('/einstellungen/aufgabenvorlagen')
        ->delete("/einstellungen/aufgabenvorlagen/{$template->public_id}")
        ->assertRedirect('/einstellungen/aufgabenvorlagen');

    expect(TaskTemplate::count())->toBe(0)
        ->and(Task::count())->toBe(1)
        ->and(Task::sole()->task_template_id)->toBeNull();
});

it('opens the hub to every role that manages tasks — the whole crew today', function (StaffRole $role, int $status) {
    $this->actingAs(staffMember($role), 'staff')
        ->get('/vorlagen?typ=aufgaben')
        ->assertStatus($status);
})->with([
    [StaffRole::Owner, 200],
    [StaffRole::Photographer, 200],
    [StaffRole::Editor, 200],
    [StaffRole::SecondShooter, 200],
    [StaffRole::Assistant, 200],
]);

it('reaches on ManageTasks alone — the same role a ManageSettings-only pane 404s out reaches this one fine', function () {
    $photographer = staffMember(StaffRole::Photographer);

    // Photographer has no ManageSettings (AuthorizesStaffSettings 404s it out
    // of every other T-0x settings pane), but does have ManageTasks.
    $this->actingAs($photographer, 'staff')->get('/einstellungen')->assertNotFound();
    $this->actingAs($photographer, 'staff')->get('/vorlagen?typ=aufgaben')->assertOk();
});

it('declares an audience on every task-template route, same as every other admin route', function () {
    $routes = routesForSurface('admin');

    $taskTemplateRoutes = collect($routes)->filter(
        fn ($route) => str_starts_with($route->uri(), 'einstellungen/aufgabenvorlagen')
    );

    expect($taskTemplateRoutes)->toHaveCount(4)
        ->and($taskTemplateRoutes->every(
            fn ($route) => collect($route->gatherMiddleware())->contains(fn ($m) => str_starts_with((string) $m, 'audience:'))
        ))->toBeTrue();
});

it('carries no integer identifier in the props', function () {
    TaskTemplate::factory()->create();

    $props = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/vorlagen?typ=aufgaben')
        ->assertOk()
        ->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
});

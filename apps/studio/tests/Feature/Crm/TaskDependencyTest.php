<?php

use App\Crm\Actions\ApplyTaskTemplates;
use App\Crm\Data\TaskRowData;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Models\Task;
use App\Crm\Models\TaskTemplate;
use App\Crm\Support\TaskDependencyGraph;
use App\Enums\StaffRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function chainedTasks(): array
{
    $project = bookedContactProject();

    $first = $project->tasks()->create(['title' => 'Vorschau senden', 'audience' => 'internal']);
    $second = $project->tasks()->create([
        'title' => 'Auswahl einsammeln',
        'audience' => 'internal',
        'depends_on_task_id' => $first->id,
    ]);

    return [$project, $first, $second];
}

it('blocks a task whose predecessor is still open, and names what it waits on', function () {
    [, $first, $second] = chainedTasks();

    expect($second->isBlocked())->toBeTrue()
        ->and($first->isBlocked())->toBeFalse();

    $row = TaskRowData::fromTask($second->fresh()->load('project', 'dependsOn'));

    expect($row->isBlocked)->toBeTrue()
        ->and($row->blockedBy)->toBe('Vorschau senden')
        // Shown, not hidden: what the studio is waiting on is what it needs
        // to see.
        ->and($row->title)->toBe('Auswahl einsammeln');
});

it('unblocks the dependent when its predecessor is completed', function () {
    [, $first, $second] = chainedTasks();

    $first->forceFill(['completed_at' => now()])->save();

    expect($second->fresh()->isBlocked())->toBeFalse();
});

it('refuses to complete a blocked task, and still allows reopening one', function () {
    [, $first, $second] = chainedTasks();

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post("/aufgaben/{$second->public_id}/erledigt")
        ->assertInvalid(['task']);

    expect($second->fresh()->isDone())->toBeFalse();

    // Completed while unblocked, then blocked again by reopening the
    // predecessor: undoing must not itself be blocked.
    $first->forceFill(['completed_at' => now()])->save();
    $second->forceFill(['completed_at' => now()])->save();
    $first->forceFill(['completed_at' => null])->save();

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post("/aufgaben/{$second->public_id}/erledigt")
        ->assertRedirect();

    expect($second->fresh()->isDone())->toBeFalse();
});

it('refuses a cycle at write time rather than discovering one at read time', function () {
    [, $first, $second] = chainedTasks();

    expect(TaskDependencyGraph::wouldCycle($first, $second))->toBeTrue()
        ->and(TaskDependencyGraph::wouldCycle($first, $first))->toBeTrue();

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post("/aufgaben/{$first->public_id}/abhaengigkeit", ['depends_on' => $second->public_id])
        ->assertInvalid(['depends_on']);

    expect($first->fresh()->depends_on_task_id)->toBeNull();
});

it('accepts a dependency that does not close a loop', function () {
    [$project, $first, $second] = chainedTasks();
    $third = $project->tasks()->create(['title' => 'Galerie liefern', 'audience' => 'internal']);

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post("/aufgaben/{$third->public_id}/abhaengigkeit", ['depends_on' => $second->public_id])
        ->assertRedirect();

    expect($third->fresh()->depends_on_task_id)->toBe($second->id);
});

it('refuses a dependency on another project\'s task', function () {
    [, , $second] = chainedTasks();
    // A plain project rather than a second bookedContactProject(): that helper
    // mints a contact at a fixed address, and C-01's unique index refuses a
    // second one.
    $stranger = Project::factory()->create()->tasks()->create(['title' => 'Fremd', 'audience' => 'internal']);

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post("/aufgaben/{$second->public_id}/abhaengigkeit", ['depends_on' => $stranger->public_id])
        ->assertStatus(422);
});

it('unblocks rather than deletes a dependent when its predecessor is removed', function () {
    [, $first, $second] = chainedTasks();

    $first->delete();

    expect(Task::find($second->id))->not->toBeNull()
        ->and($second->fresh()->isBlocked())->toBeFalse();
});

// --- templates produce chains -------------------------------------------

it('turns a template chain into a task chain when a project is booked', function () {
    $preview = TaskTemplate::factory()->create(['title' => 'Vorschau senden', 'day_offset' => 1, 'sort_order' => 0]);
    TaskTemplate::factory()->create([
        'title' => 'Auswahl einsammeln',
        'day_offset' => 7,
        'sort_order' => 1,
        'depends_on_template_id' => $preview->id,
    ]);

    $project = bookedContactProject();
    Shoot::factory()->for($project)->create(['is_primary' => true, 'date' => now()->addMonth()]);

    app(ApplyTaskTemplates::class)($project);

    $second = $project->tasks()->where('title', 'Auswahl einsammeln')->sole();
    $first = $project->tasks()->where('title', 'Vorschau senden')->sole();

    expect($second->depends_on_task_id)->toBe($first->id)
        ->and($second->isBlocked())->toBeTrue();
});

it('never crosses two shoots produced from the same template chain', function () {
    $preview = TaskTemplate::factory()->create(['title' => 'Vorschau senden', 'day_offset' => 1, 'sort_order' => 0]);
    TaskTemplate::factory()->create([
        'title' => 'Auswahl einsammeln',
        'day_offset' => 7,
        'sort_order' => 1,
        'depends_on_template_id' => $preview->id,
    ]);

    $project = bookedContactProject();
    Shoot::factory()->for($project)->create(['is_primary' => true, 'date' => now()->addMonth()]);
    Shoot::factory()->for($project)->create(['is_primary' => false, 'date' => now()->addMonths(2)]);

    app(ApplyTaskTemplates::class)($project);

    // Each shoot's chain stays inside that shoot: the second wedding's
    // selection must not wait on the first one's preview.
    foreach ($project->tasks()->where('title', 'Auswahl einsammeln')->get() as $task) {
        expect($task->dependsOn->shoot_id)->toBe($task->shoot_id);
    }
});

it('does not move due dates when a dependency completes late', function () {
    [, $first, $second] = chainedTasks();
    $second->update(['due_on' => '2027-01-15']);

    $first->forceFill(['completed_at' => now()->addYear()])->save();

    // Offsets anchor to the shoot's date, not to each other — cascading
    // would quietly contradict S-03's anchor.
    expect($second->fresh()->due_on->format('Y-m-d'))->toBe('2027-01-15');
});

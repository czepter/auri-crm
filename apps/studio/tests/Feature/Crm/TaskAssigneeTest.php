<?php

use App\Crm\Models\ClientCase;
use App\Crm\Models\CrewAssignment;
use App\Crm\Models\Shoot;
use App\Crm\Models\Task;
use App\Crm\Support\StudioAudience;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('assigns a task to a studio member', function () {
    $owner = staffMember();
    $task = Task::factory()->create();

    $this->actingAs($owner, 'staff')
        ->post("/aufgaben/{$task->public_id}/zustaendig", ['assignee' => $owner->public_id])
        ->assertRedirect();

    expect($task->fresh()->assignee->is($owner))->toBeTrue();
});

it('narrows the audience to the assignee', function () {
    $assignee = staffMember();
    $crewed = staffMember();

    $project = ClientCase::factory()->create();
    $shoot = Shoot::factory()->create(['case_id' => $project->id]);
    CrewAssignment::factory()->create(['shoot_id' => $shoot->id, 'user_id' => $crewed->id]);

    $task = Task::factory()->create(['case_id' => $project->id, 'assignee_id' => $assignee->id]);

    expect(app(StudioAudience::class)->forTask($task)->pluck('id')->all())->toBe([$assignee->id]);
});

// Der Rückfall ist der bestehende: ein Studio ohne Zuweisungen ändert nichts.
it('falls back to the project crew without an assignee', function () {
    $crewed = staffMember();
    staffMember();

    $project = ClientCase::factory()->create();
    $shoot = Shoot::factory()->create(['case_id' => $project->id]);
    CrewAssignment::factory()->create(['shoot_id' => $shoot->id, 'user_id' => $crewed->id]);

    $task = Task::factory()->create(['case_id' => $project->id]);

    expect(app(StudioAudience::class)->forTask($task)->pluck('id')->all())->toBe([$crewed->id]);
});

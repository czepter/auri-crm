<?php

use App\Crm\Models\Project;
use App\Crm\Models\Task;
use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use App\Models\User;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::API));

// #401: tasks on the api surface, mirroring App\Crm\Http\Controllers\
// TaskController.
it('creates a task on a project with a fixed due date', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageTasks)->create();
    $project = Project::factory()->create();

    $response = $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'task-1')
        ->postJson("/api/projects/{$project->public_id}/tasks", [
            'title' => 'Crew-Briefing verschicken',
            'audience' => 'internal',
            'due_on' => '2019-07-01',
        ]);

    $response->assertCreated();

    expect($response->json('data.title'))->toBe('Crew-Briefing verschicken')
        ->and($response->json('data.dueOn'))->toBe('2019-07-01')
        ->and($response->json('data.projectId'))->toBe($project->public_id)
        ->and(integerIdPaths($response->json()))->toBe([]);
});

it('refuses a task write to a token that does not hold ManageTasks', function () {
    $token = ApiToken::factory()->withScopes(Capability::ViewAllProjects)->create();
    $project = Project::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'task-forbidden')
        ->postJson("/api/projects/{$project->public_id}/tasks", ['title' => 'X', 'audience' => 'internal'])
        ->assertForbidden();
});

it('lists a project’s open tasks', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageTasks)->create();
    $project = Project::factory()->create();
    Task::factory()->for($project)->create(['title' => 'Offen']);
    Task::factory()->for($project)->done()->create(['title' => 'Erledigt']);

    $response = $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->getJson("/api/projects/{$project->public_id}/tasks?done=false");

    $response->assertOk();

    expect(collect($response->json('data'))->pluck('title')->all())->toBe(['Offen']);
});

it('completes a task, reopens it, and refuses completion while blocked', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageTasks)->create();
    $project = Project::factory()->create();
    $predecessor = Task::factory()->for($project)->create();
    $task = Task::factory()->for($project)->create(['depends_on_task_id' => $predecessor->id]);

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'complete-blocked')
        ->postJson("/api/tasks/{$task->public_id}/complete")
        ->assertUnprocessable();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'complete-predecessor')
        ->postJson("/api/tasks/{$predecessor->public_id}/complete")
        ->assertOk();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'complete-task')
        ->postJson("/api/tasks/{$task->public_id}/complete")
        ->assertOk();

    expect($task->fresh()->isDone())->toBeTrue();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'reopen-task')
        ->postJson("/api/tasks/{$task->public_id}/reopen")
        ->assertOk();

    expect($task->fresh()->isDone())->toBeFalse();
});

it('assigns a task and sets its dependency', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageTasks)->create();
    $project = Project::factory()->create();
    $user = User::factory()->create();
    $predecessor = Task::factory()->for($project)->create();
    $task = Task::factory()->for($project)->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'assign-1')
        ->postJson("/api/tasks/{$task->public_id}/assign", ['assignee' => $user->public_id])
        ->assertOk();

    expect($task->fresh()->assignee_id)->toBe($user->id);

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'depend-1')
        ->postJson("/api/tasks/{$task->public_id}/depend", ['depends_on' => $predecessor->public_id])
        ->assertOk();

    expect($task->fresh()->depends_on_task_id)->toBe($predecessor->id);
});

it('refuses a dependency that would cycle', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageTasks)->create();
    $project = Project::factory()->create();
    $a = Task::factory()->for($project)->create();
    $b = Task::factory()->for($project)->create(['depends_on_task_id' => $a->id]);

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'depend-cycle')
        ->postJson("/api/tasks/{$a->public_id}/depend", ['depends_on' => $b->public_id])
        ->assertUnprocessable();
});

it('deletes a task', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageTasks)->create();
    $task = Task::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'delete-task')
        ->deleteJson("/api/tasks/{$task->public_id}")
        ->assertNoContent();

    expect(Task::find($task->id))->toBeNull();
});

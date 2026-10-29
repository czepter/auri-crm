<?php

use App\Automation\Actions\FireAutomationRule;
use App\Automation\Enums\AutomationAction;
use App\Automation\Enums\AutomationAnchor;
use App\Automation\Enums\AutomationOffsetDirection;
use App\Automation\Enums\AutomationRecipient;
use App\Automation\Models\AutomationRule;
use App\Crm\Actions\AssignTask;
use App\Crm\Actions\CompleteTask;
use App\Crm\Actions\CreateTask;
use App\Crm\Actions\DeleteTask;
use App\Crm\Actions\SetTaskDependency;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\Task;
use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function apiAs(ApiToken $token, string $key)
{
    return test()->withHeader('Authorization', 'Bearer '.bearerToken($token))->withHeader('Idempotency-Key', $key);
}

it('creates project tasks through CreateTask from admin and api', function () {
    test()->partialMock(CreateTask::class, fn ($mock) => $mock->shouldReceive('forCase')->twice()->passthru());
    $project = ClientCase::factory()->create();
    $body = ['title' => 'Briefing', 'audience' => 'internal'];

    test()->actingAs(staffMember(), 'staff')->post("/auftraege/{$project->public_id}/aufgaben", $body)->assertRedirect();

    useSurface(Surface::API);
    apiAs(ApiToken::factory()->withScopes(Capability::ManageTasks)->create(), 'create')
        ->postJson("/api/cases/{$project->public_id}/tasks", $body)->assertCreated();

    expect(Task::query()->where('case_id', $project->id)->count())->toBe(2);
});

it('creates studio and portal tasks through CreateTask::__invoke', function () {
    test()->partialMock(CreateTask::class, fn ($mock) => $mock->shouldReceive('__invoke')->times(3)->passthru());

    test()->actingAs(staffMember(), 'staff')->post('/aufgaben', ['title' => 'Buchhaltung'])->assertRedirect();

    $rule = AutomationRule::factory()
        ->dateOffset(AutomationAnchor::PerShoot, 0, AutomationOffsetDirection::After)
        ->create(['recipient_type' => AutomationRecipient::Studio, 'action' => AutomationAction::Task, 'notification_subject' => 'Regel']);
    app(FireAutomationRule::class)($rule, Contact::factory()->create(), '2026-06-01');

    useSurface(Surface::PORTAL);
    test()->actingAs(Contact::factory()->create(), 'portal')->post('/einstellungen/datenschutz', ['kind' => 'export']);

    expect(Task::count())->toBe(3);
});

it('completes and reopens tasks through CompleteTask from admin and api', function () {
    test()->partialMock(CompleteTask::class, function ($mock) {
        $mock->shouldReceive('__invoke')->twice()->passthru();
        $mock->shouldReceive('reopen')->twice()->passthru();
    });
    $project = ClientCase::factory()->create();
    $adminTask = Task::factory()->for($project, 'case')->create();
    $apiTask = Task::factory()->for($project, 'case')->create();

    test()->actingAs(staffMember(), 'staff');
    test()->post("/aufgaben/{$adminTask->public_id}/erledigt")->assertRedirect();
    test()->post("/aufgaben/{$adminTask->public_id}/wieder-oeffnen")->assertRedirect();

    useSurface(Surface::API);
    $token = ApiToken::factory()->withScopes(Capability::ManageTasks)->create();
    apiAs($token, 'complete')->postJson("/api/tasks/{$apiTask->public_id}/complete")->assertOk();
    apiAs($token, 'reopen')->postJson("/api/tasks/{$apiTask->public_id}/reopen")->assertOk();

    expect($adminTask->fresh()->isDone())->toBeFalse()->and($apiTask->fresh()->isDone())->toBeFalse();
});

it('assigns tasks through AssignTask from admin and api', function () {
    test()->partialMock(AssignTask::class, fn ($mock) => $mock->shouldReceive('__invoke')->twice()->passthru());
    $staff = staffMember();
    $task = Task::factory()->for(ClientCase::factory(), 'case')->create();

    test()->actingAs($staff, 'staff')->post("/aufgaben/{$task->public_id}/zustaendig", ['assignee' => $staff->public_id])->assertRedirect();

    useSurface(Surface::API);
    apiAs(ApiToken::factory()->withScopes(Capability::ManageTasks)->create(), 'assign')
        ->postJson("/api/tasks/{$task->public_id}/assign", ['assignee' => null])->assertOk();

    expect($task->fresh()->assignee_id)->toBeNull();
});

it('sets dependencies through SetTaskDependency from admin and api', function () {
    test()->partialMock(SetTaskDependency::class, fn ($mock) => $mock->shouldReceive('__invoke')->twice()->passthru());
    $project = ClientCase::factory()->create();
    $predecessor = Task::factory()->for($project, 'case')->create();
    $task = Task::factory()->for($project, 'case')->create();

    test()->actingAs(staffMember(), 'staff')
        ->post("/aufgaben/{$task->public_id}/abhaengigkeit", ['depends_on' => $predecessor->public_id])->assertRedirect();

    useSurface(Surface::API);
    apiAs(ApiToken::factory()->withScopes(Capability::ManageTasks)->create(), 'depend')
        ->postJson("/api/tasks/{$task->public_id}/depend", ['depends_on' => null])->assertOk();

    expect($task->fresh()->depends_on_task_id)->toBeNull();
});

it('deletes tasks through DeleteTask from admin and api', function () {
    test()->partialMock(DeleteTask::class, fn ($mock) => $mock->shouldReceive('__invoke')->twice()->passthru());
    $project = ClientCase::factory()->create();
    [$adminTask, $apiTask] = Task::factory()->for($project, 'case')->count(2)->create();

    test()->actingAs(staffMember(), 'staff')->delete("/aufgaben/{$adminTask->public_id}")->assertRedirect();

    useSurface(Surface::API);
    apiAs(ApiToken::factory()->withScopes(Capability::ManageTasks)->create(), 'delete')
        ->deleteJson("/api/tasks/{$apiTask->public_id}")->assertNoContent();

    expect(Task::count())->toBe(0);
});

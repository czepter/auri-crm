<?php

use App\Contracts\Enums\ContractStatus;
use App\Contracts\Jobs\RenderContractJob;
use App\Contracts\Models\Contract;
use App\Contracts\Models\ContractTemplate;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('creates a pending contract and dispatches the render job onto the render queue, without blocking the request', function () {
    Queue::fake();

    $project = contractProject();
    $template = ContractTemplate::factory()->create();
    $staff = staffMember(StaffRole::Owner);

    $this->actingAs($staff, 'staff')
        ->post("/auftraege/{$project->public_id}/vertraege", ['template' => $template->public_id])
        ->assertRedirect();

    $contract = Contract::sole();
    expect($contract->status)->toBe(ContractStatus::Pending)
        ->and($contract->contract_template_id)->toBe($template->id)
        ->and($contract->requested_by_id)->toBe($staff->id);

    Queue::assertPushedOn('render', RenderContractJob::class, fn ($job) => $job->contract->is($contract));

    expect(AuditLog::where('event', 'contract.render_requested')->sole()->subject)->toBe($contract->public_id);
});

it('refuses generation for a role without ManageContracts', function () {
    Queue::fake();

    $project = contractProject();
    $template = ContractTemplate::factory()->create();

    $this->actingAs(staffMember(StaffRole::SecondShooter), 'staff')
        ->post("/auftraege/{$project->public_id}/vertraege", ['template' => $template->public_id])
        ->assertForbidden();

    Queue::assertNothingPushed();
});

it('404s generation against a superseded template version', function () {
    $project = contractProject();
    $template = ContractTemplate::factory()->create();
    $template->newVersion(['name' => $template->name, 'body' => '<p>v2</p>']);

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post("/auftraege/{$project->public_id}/vertraege", ['template' => $template->public_id])
        ->assertNotFound();
});

<?php

use App\Contracts\Models\Contract;
use App\Contracts\Models\ContractSignature;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Documents\Models\DocumentTemplate;
use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::API);
    $this->withHeader('Authorization', 'Bearer '.bearerToken(ApiToken::factory()->withScopes(Capability::ManageContracts)->create()));
});

it('refuses a payment plan without a signed contract, then creates one once signed', function () {
    $job = Job::factory()->create();

    $this->withHeader('Idempotency-Key', 'plan-refused')
        ->postJson("/api/projects/{$job->project->public_id}/payment-plan", [
            'total' => 200_000,
            'installments' => [['due_on' => '2026-10-01'], ['due_on' => '2026-11-01']],
        ])
        ->assertUnprocessable();

    $contract = Contract::factory()->for($job->project)->create();
    ContractSignature::factory()->for($contract)->create();

    $response = $this->withHeader('Idempotency-Key', 'plan-1')
        ->postJson("/api/projects/{$job->project->public_id}/payment-plan", [
            'total' => 200_000,
            'installments' => [['due_on' => '2026-10-01'], ['due_on' => '2026-11-01']],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.total', 200_000)
        ->assertJsonCount(2, 'data.installments');
});

it('records a payment and issues an invoice for an installment', function () {
    $plan = PaymentPlan::factory()->create(['total' => 100_000]);
    $installment = PaymentInstallment::factory()->for($plan, 'plan')->create(['amount' => 100_000]);

    $this->withHeader('Idempotency-Key', 'paid-1')
        ->postJson("/api/payment-installments/{$installment->public_id}/paid", [
            'paid_on' => now()->toDateString(),
            'method' => 'transfer',
            'reference' => 'Überweisung Müller',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.paymentReference', 'Überweisung Müller');

    expect($installment->fresh()->status->value)->toBe('paid');
});

it('refuses a paid_on date in the future', function () {
    $plan = PaymentPlan::factory()->create(['total' => 100_000]);
    $installment = PaymentInstallment::factory()->for($plan, 'plan')->create(['amount' => 100_000]);

    $this->withHeader('Idempotency-Key', 'paid-future')
        ->postJson("/api/payment-installments/{$installment->public_id}/paid", [
            'paid_on' => now()->addDay()->toDateString(),
            'method' => 'transfer',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('paid_on');
});

it('creates a contract from a template, sends it, sets a deadline, and manages signers', function () {
    Storage::fake('local');
    Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-fake-bytes', 200)]);

    $project = Project::factory()->create();
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);
    $templateId = DocumentTemplate::factory()->create()->public_id;

    $contractId = $this->withHeader('Idempotency-Key', 'contract-1')
        ->postJson("/api/projects/{$project->public_id}/contracts", ['template' => $templateId])
        ->assertCreated()
        ->assertJsonPath('data.state', 'draft')
        ->json('data.id');

    $this->withHeader('Idempotency-Key', 'contract-send')
        ->postJson("/api/contracts/{$contractId}/send")
        ->assertOk()
        ->assertJsonPath('data.state', 'valid');

    $this->withHeader('Idempotency-Key', 'contract-deadline')
        ->patchJson("/api/contracts/{$contractId}/deadline", ['sign_deadline_on' => '2026-12-01'])
        ->assertOk()
        ->assertJsonPath('data.signDeadlineOn', '2026-12-01');

    $signerId = $this->withHeader('Idempotency-Key', 'signer-add')
        ->postJson("/api/contracts/{$contractId}/signers", ['contact' => $contact->public_id])
        ->assertCreated()
        ->json('data.id');

    $this->withHeader('Idempotency-Key', 'signer-remind')
        ->postJson("/api/contract-signers/{$signerId}/remind")
        ->assertOk()
        ->assertJsonPath('data.remindedAt', fn ($value) => $value !== null);

    $this->withHeader('Idempotency-Key', 'signer-remove')
        ->deleteJson("/api/contract-signers/{$signerId}")
        ->assertNoContent();
});

it('reads a project\'s payment plan and contracts together', function () {
    $job = Job::factory()->create();
    $contract = Contract::factory()->for($job->project)->create();
    $plan = PaymentPlan::factory()->for($job)->create(['total' => 50_000]);
    PaymentInstallment::factory()->for($plan, 'plan')->create(['amount' => 50_000]);

    $this->getJson("/api/projects/{$job->project->public_id}/money")
        ->assertOk()
        ->assertJsonPath('data.paymentPlan.total', 50_000)
        ->assertJsonPath('data.contracts.0.id', $contract->public_id);
});

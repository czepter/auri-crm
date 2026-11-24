<?php

use App\Crm\Actions\ReadNextAction;
use App\Crm\Enums\ShootStatus;
use App\Crm\Models\FormInstance;
use App\Crm\Models\Shoot;
use App\Gallery\Models\Gallery;
use App\Models\StudioSettings;
use App\Models\User;
use App\Payments\Actions\IssueInvoice;
use App\Payments\Actions\MarkInstallmentPaid;
use App\Payments\Enums\SettledVia;
use App\Settings\Actions\UpdateStudioSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    app(UpdateStudioSetting::class)('tax_number', '22/123/45678', null);
    $this->actingAs(User::factory()->withMfa()->create(), 'staff');
    $this->first = invoiceablePlan();
    $this->project = $this->first->plan->case;
});

it('asks for the deposit first', function () {
    expect(app(ReadNextAction::class)($this->project))
        ->toMatchArray(['label' => 'project.depositIssueStep', 'post' => "/raten/{$this->first->public_id}/rechnung"]);
});

it('puts the contract before the deposit when contracts are always', function () {
    StudioSettings::query()->update(['contract_mode' => 'always']);
    StudioSettings::forgetCurrent();

    expect(app(ReadNextAction::class)($this->project)['label'])->toBe('project.contractSendStep');
});

it('names the open final payment once the work is delivered', function () {
    FormInstance::factory()->for($this->project, 'case')->create();
    Shoot::factory()->for($this->project, 'case')->primary()->atStatus(ShootStatus::Delivered)->on(now()->subMonth()->toDateString())->create();
    Gallery::factory()->for($this->project, 'case')->create();
    $deposit = $this->first->fresh();
    app(IssueInvoice::class)($deposit);
    app(MarkInstallmentPaid::class)($deposit->fresh(), SettledVia::Transfer, '2026-09-02', null);
    $rest = $this->project->paymentPlan->installments()->orderBy('sort_order')->get()->last();

    expect(app(ReadNextAction::class)($this->project->fresh()))
        ->toMatchArray(['label' => 'project.restIssueStep', 'post' => "/raten/{$rest->public_id}/rechnung"]);

    $invoice = app(IssueInvoice::class)($rest);

    expect(app(ReadNextAction::class)($this->project->fresh()))
        ->toMatchArray(['label' => 'project.paymentRecordStep', 'href' => "/rechnungen/{$invoice->public_id}"]);
});

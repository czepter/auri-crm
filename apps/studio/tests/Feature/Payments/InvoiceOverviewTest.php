<?php

use App\Enums\Capability;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Payments\Actions\IssueInvoice;
use App\Payments\Models\PaymentPlan;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(StudioSettingsResolver::class)->set('tax_number', '22/123/45678', null);
    Storage::fake('local');
    Bus::fake();
});

it('lists every plan of every project with its rates', function () {
    $first = invoiceablePlan();
    $second = PaymentPlan::factory()->create();
    app(IssueInvoice::class)($first);

    $plans = test()->actingAs(staffMember(), 'staff')->get('/rechnungen')
        ->assertOk()->viewData('page')['props']['plans'];

    expect(collect($plans)->pluck('plan.id')->sort()->values()->all())
        ->toBe(collect([$first->plan->public_id, $second->public_id])->sort()->values()->all());

    $issued = collect($plans)->firstWhere('plan.id', $first->plan->public_id);

    expect($issued['projectName'])->toBe($first->plan->job->project->name)
        ->and($issued['plan']['installments'])->toHaveCount(2)
        ->and($issued['plan']['installments'][0]['invoiceNumber'])->toBe($first->fresh()->invoice_number);
});

it('refuses a role that may not manage payment plans', function () {
    $role = collect(StaffRole::cases())->first(fn (StaffRole $role) => ! $role->allows(Capability::ManageContracts));

    test()->actingAs(staffMember($role), 'staff')->get('/rechnungen')->assertForbidden();
});

it('moves the due date of an issued invoice and audits it', function () {
    $installment = invoiceablePlan();
    app(IssueInvoice::class)($installment);

    test()->actingAs(staffMember(), 'staff')
        ->patch("/raten/{$installment->public_id}/faelligkeit", ['due_on' => '2026-12-24'])
        ->assertRedirect();

    expect($installment->fresh()->due_on->format('Y-m-d'))->toBe('2026-12-24')
        ->and(AuditLog::where('event', 'payments.installment.due_on_changed')->sole()->context['to'])->toBe('2026-12-24');
});

it('returns to the list after sending from it', function () {
    $installment = invoiceablePlan();
    app(IssueInvoice::class)($installment);

    test()->actingAs(staffMember(), 'staff')
        ->post("/raten/{$installment->public_id}/rechnung/versenden", ['subject' => 'Rechnung', 'liste' => true])
        ->assertRedirect('/rechnungen');
});

<?php

use App\Crm\Models\Contact;
use App\Enums\Capability;
use App\Enums\StaffRole;
use App\Payments\Actions\IssueInvoice;
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

it('lists invoices, not rates — with and without a project, newest first', function () {
    $rates = invoiceablePlan()->plan->installments()->orderBy('sort_order')->get();
    $project = app(IssueInvoice::class)($rates);

    $this->travel(1)->minute();
    $free = app(IssueInvoice::class)->free(
        Contact::factory()->create(['name' => 'Jonas Gast']),
        [['description' => 'Abzüge', 'quantity' => 30, 'unit_price' => 1_000, 'vat_rate' => 1900]],
        now()->addWeek()->toDateString(),
    );

    $rows = $this->actingAs(staffMember(), 'staff')->get('/rechnungen')
        ->assertOk()->viewData('page')['props']['invoices'];

    expect(collect($rows)->pluck('id')->all())->toBe([$free->public_id, $project->public_id])
        ->and($rows[0]['projectName'])->toBeNull()
        ->and($rows[0]['contact'])->toBe('Jonas Gast')
        ->and($rows[0]['amount'])->toBe(30_000)
        ->and($rows[1]['amount'])->toBe(150_000)
        ->and($rows[1]['openInstallments'])->toHaveCount(2);
});

it('refuses a role that may not manage payment plans', function () {
    $role = collect(StaffRole::cases())->first(fn (StaffRole $role) => ! $role->allows(Capability::ManageContracts));

    $this->actingAs(staffMember($role), 'staff')->get('/rechnungen')->assertForbidden();
});

it('offers the open rates of each plan on the new-invoice screen', function () {
    $installment = invoiceablePlan();
    app(IssueInvoice::class)($installment);

    $plans = $this->actingAs(staffMember(), 'staff')->get('/rechnungen/neu')
        ->assertOk()->viewData('page')['props']['plans'];

    expect($plans)->toHaveCount(1)
        ->and($plans[0]['installments'])->toHaveCount(1)
        ->and($plans[0]['installments'][0]['label'])->toBe('Restbetrag');
});

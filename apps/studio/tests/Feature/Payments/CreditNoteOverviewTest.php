<?php

use App\Enums\Capability;
use App\Enums\StaffRole;
use App\Payments\Actions\IssueCreditNote;
use App\Settings\Actions\UpdateStudioSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(UpdateStudioSetting::class)('tax_number', '22/123/45678', null);
    Storage::fake('local');
    Bus::fake();
});

it('lists every Gutschrift of an invoice, newest first', function () {
    $installment = creditableInstallment();

    app(IssueCreditNote::class)($installment->invoice, 25_000, 'Nachlass');
    $this->travel(1)->minute();
    app(IssueCreditNote::class)($installment->invoice, 10_000, 'Zweite Kamera nicht erbracht');

    $rows = $this->actingAs(staffMember(), 'staff')->get('/gutschriften')
        ->assertOk()->viewData('page')['props']['creditNotes'];

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['amount'])->toBe(10_000)
        ->and($rows[0]['reason'])->toBe('Zweite Kamera nicht erbracht')
        ->and($rows[0]['invoiceNumber'])->toBe($installment->invoice->number)
        ->and($rows[0]['number'])->toStartWith('GS-')
        ->and($rows[1]['amount'])->toBe(25_000);
});

it('refuses a role that may not manage payment plans', function () {
    $role = collect(StaffRole::cases())->first(fn (StaffRole $role) => ! $role->allows(Capability::ManageContracts));

    $this->actingAs(staffMember($role), 'staff')->get('/gutschriften')->assertForbidden();
});

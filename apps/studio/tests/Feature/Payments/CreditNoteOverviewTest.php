<?php

use App\Payments\Actions\IssueCreditNote;
use App\Payments\Models\CreditNoteDraft;
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

it('nests the Gutschriften under their invoice', function () {
    $installment = creditableInstallment();

    app(IssueCreditNote::class)($installment->invoice, 25_000, 'Nachlass');
    $this->travel(1)->minute();
    app(IssueCreditNote::class)($installment->invoice, 10_000, 'Zweite Kamera nicht erbracht');

    $row = collect($this->actingAs(staffMember(), 'staff')->get('/rechnungen')
        ->assertOk()->viewData('page')['props']['invoices'])->firstWhere('number', $installment->invoice->number);

    expect($row['creditNotes'])->toHaveCount(2)
        ->and(collect($row['creditNotes'])->pluck('amount')->sort()->values()->all())->toBe([10_000, 25_000])
        ->and(collect($row['creditNotes'])->firstWhere('amount', 10_000)['reason'])->toBe('Zweite Kamera nicht erbracht')
        ->and($row['creditNotes'][0]['number'])->toStartWith('GS-');
});

it('sends the former Gutschriften list to the invoice list filter', function () {
    $this->actingAs(staffMember(), 'staff')->get('/gutschriften')->assertRedirect('/rechnungen?filter=credits');
});

it('lists a Gutschrift draft with the gross of its ticked lines', function () {
    $invoice = creditableInstallment()->invoice;
    $line = (int) $invoice->items->first()->installment->amount;

    CreditNoteDraft::create(['invoice_id' => $invoice->id, 'payload' => ['taken' => [true]]]);

    $drafts = $this->actingAs(staffMember(), 'staff')->get('/rechnungen')
        ->assertOk()->viewData('page')['props']['creditNoteDrafts'];

    expect($drafts)->toHaveCount(1)
        ->and($drafts[0]['invoiceId'])->toBe($invoice->public_id)
        ->and($drafts[0]['amount'])->toBe($line);
});

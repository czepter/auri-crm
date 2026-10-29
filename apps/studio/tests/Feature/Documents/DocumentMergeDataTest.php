<?php

use App\Crm\Models\Shoot;
use App\Documents\Support\DocumentMergeData;
use App\Gallery\Models\Gallery;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\Invoice;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Payments\Support\InvoiceMergeData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// bookedContactProject() lives in tests/Pest.php — see the note there.

it('builds the same merge data shape any document type resolves against', function () {
    $project = bookedContactProject();

    $data = app(DocumentMergeData::class)->forCase($project);

    expect($data['project']['name'])->toBe('Hochzeit Meier & Kowalski')
        ->and($data['project']['type'])->toBe($project->type->name)
        ->and($data['contact']['name'])->toBe('Lena Meier')
        ->and($data['contact']['email'])->toBe('lena@example.test')
        ->and($data['project']['bookedAt'])->toBe('14.06.2026')
        ->and($data)->toHaveKey('studio');
});

/**
 * T-12's own acceptance criterion: the same merge field resolves identically
 * wherever it is read from. DocumentMergeData takes no document type
 * at all — it is the one place that array is built, so a contract, a quote
 * and a mail rendered from the same project read the exact same values.
 */
it('is the one place the array is built — nothing about it varies by document type', function () {
    $project = bookedContactProject();
    $builder = app(DocumentMergeData::class);

    $first = $builder->forCase($project);
    $second = $builder->forCase($project);

    expect($first)->toBe($second);
});

// The gallery link a client clicks from a document has to land on
// apps/gallery, the guest viewer — never this app's own host, which no
// guest can open (Gallery::publicUrl()).
it('points the gallery merge field at the viewer host, not this app', function () {
    config(['services.gallery.url' => 'http://gallery.test']);
    $project = bookedContactProject();
    Gallery::factory()->for($project, 'case')->create(['title' => 'Hochzeit Meier', 'slug' => 'hochzeit-meier']);

    $data = app(DocumentMergeData::class)->forCase($project);

    expect($data['gallery']['link'])->toBe('http://gallery.test/g/hochzeit-meier');
});

// § 14 Abs. 4 Nr. 6 UStG: an Anzahlung the client transferred before the
// invoice went out is taxed on receipt, so the Leistungszeitraum is the day
// the money arrived — the shoot is still weeks away.
it('prints the Vereinnahmungsdatum on an Anzahlung paid before it was issued', function () {
    $project = bookedContactProject();
    Shoot::factory()->for($project, 'case')->primary()->on('2026-06-20')->create();
    $plan = PaymentPlan::factory()->for($project, 'case')->create(['total' => 50_000]);
    $invoice = Invoice::create(['number' => 'RE-2026-1', 'issued_at' => '2026-03-05 09:00:00', 'case_id' => $project->id]);
    $installment = PaymentInstallment::factory()->for($plan, 'plan')->create([
        'amount' => 50_000,
        'status' => PaymentInstallmentStatus::Paid,
        'paid_on' => '2026-03-02',
        'invoice_id' => $invoice->id,
    ]);

    $data = app(InvoiceMergeData::class)->forInvoice($invoice);

    expect($data['invoice']['servicePeriod'])->toBe('02.03.2026');

    // Paid after issue: the shoot day is the supply, as before.
    $installment->update(['paid_on' => '2026-03-09']);

    $data = app(InvoiceMergeData::class)->forInvoice($invoice->fresh());

    expect($data['invoice']['servicePeriod'])->toBe('20.06.2026');
});

// #565: the blocks come from each module's MergeDataProvider. Without a case
// all of them are still there, so a field on the wrong document renders as
// nothing rather than as a raw placeholder.
it('gathers every block from the registered providers, even without a case', function () {
    $data = app(DocumentMergeData::class)->forCase(null);

    expect(array_keys($data))->toBe(['project', 'contact', 'shoot', 'invoice', 'creditNote', 'quote', 'gallery', 'studio'])
        ->and($data['gallery']['link'])->toBe('')
        ->and($data['invoice']['number'])->toBe('');
});

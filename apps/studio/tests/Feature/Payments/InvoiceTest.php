<?php

use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Documents\Support\DocumentBlockCatalog;
use App\Documents\Support\DocumentMergeDataBuilder;
use App\Documents\Support\DocumentRenderer;
use App\Documents\Support\MergeFieldResolver;
use App\Documents\Support\MergeFieldVocabulary;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Numbering\Enums\DocumentType;
use App\Numbering\Models\NumberCounter;
use App\Payments\Actions\CreatePaymentPlan;
use App\Payments\Actions\IssueInvoice;
use App\Payments\Data\PaymentPlanData;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Jobs\RenderInvoiceJob;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Support\InvoiceFigures;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\GotenbergClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

// IssueInvoice refuses a studio with neither identifier (§ 14 UStG), and the
// column is nullable with no default — a studio far enough along to invoice
// has filed one.
beforeEach(function () {
    app(StudioSettingsResolver::class)->set('tax_number', '22/123/45678', null);
});

// contractProject(), renderedContract() and staffMember() live in tests/Pest.php.

function invoiceablePlan(): PaymentInstallment
{
    $project = contractProject();
    Quote::factory()->for($project)->create(['status' => QuoteStatus::Accepted, 'accepted_at' => now()]);

    $plan = app(CreatePaymentPlan::class)($project->job()->sole(), 150_000, ['2026-09-01', '2026-10-01']);

    return $plan->installments()->orderBy('sort_order')->first();
}

it('refuses to issue without a Steuernummer or USt-IdNr., drawing no number', function () {
    $settings = app(StudioSettingsResolver::class);
    $settings->set('tax_number', null, null);
    $settings->set('vat_id', null, null);

    $installment = invoiceablePlan();

    expect(fn () => app(IssueInvoice::class)($installment))
        ->toThrow(ValidationException::class);

    expect($installment->fresh()->invoice_number)->toBeNull()
        ->and(NumberCounter::query()->where('document_type', DocumentType::Invoice->value)->exists())->toBeFalse();
});

it('issues on the USt-IdNr. alone', function () {
    $settings = app(StudioSettingsResolver::class);
    $settings->set('tax_number', null, null);
    $settings->set('vat_id', 'DE123456789', null);

    expect(app(IssueInvoice::class)(invoiceablePlan())->invoice_number)->not->toBeNull();
});

// --- T-15: invoice as the fourth document type --------------------------

it('carries invoice as a fourth document type, labelled Rechnung', function () {
    expect(DocumentTemplateType::Invoice->value)->toBe('invoice')
        ->and(DocumentTemplateType::Invoice->label())->toBe('Rechnung');
});

it('gives an invoice the print-document block set, not the mail compromise', function () {
    // A print document gets columns and spacer; mail does not. The rule the
    // issue names: "the same set a quote gets".
    expect(DocumentBlockCatalog::allows(DocumentTemplateType::Invoice, 'columns'))->toBeTrue()
        ->and(DocumentBlockCatalog::allows(DocumentTemplateType::Invoice, 'spacer'))->toBeTrue()
        ->and(DocumentBlockCatalog::allows(DocumentTemplateType::Mail, 'columns'))->toBeFalse();

    $palette = collect(DocumentBlockCatalog::for(DocumentTemplateType::Invoice))->pluck('type');
    expect($palette)->toContain('paragraph', 'table', 'columns', 'spacer');
});

it('documents every §14 UStG merge path with a sample, so a preview proves no unresolved placeholder', function () {
    $paths = MergeFieldVocabulary::paths();

    expect($paths)->toContain(
        'invoice.number', 'invoice.date', 'invoice.servicePeriod', 'invoice.description',
        'invoice.net', 'invoice.vatRate', 'invoice.vat', 'invoice.gross',
        'invoice.dueOn', 'invoice.paymentTerm',
        'contact.street', 'contact.postcode', 'contact.city',
        'studio.bankHolder', 'studio.bankIban', 'studio.bankBic',
    );

    // Every documented path resolves against the sample dataset — the
    // property T-15's acceptance line rests on.
    $body = collect($paths)->map(fn (string $path) => "{{ {$path} }}")->implode(' ');

    expect(MergeFieldResolver::resolve($body, MergeFieldVocabulary::sample()))
        ->not->toContain('{{');
});

// --- InvoiceFigures: the split that must add up -------------------------

it('splits gross backwards into net and VAT so the two always sum to the amount charged', function () {
    $figures = InvoiceFigures::fromGross(150_000, 1900);

    expect($figures->gross)->toBe(150_000)
        ->and($figures->net + $figures->vat)->toBe(150_000)
        ->and($figures->net)->toBe(126_050)
        ->and($figures->ratePercentage())->toBe('19 %');
});

it('never lets net plus VAT drift from gross, across many amounts and rates', function () {
    mt_srand(20260815);

    for ($run = 0; $run < 500; $run++) {
        $gross = mt_rand(0, 5_000_000);
        $rate = [0, 700, 1900][mt_rand(0, 2)];

        $figures = InvoiceFigures::fromGross($gross, $rate);

        expect($figures->net + $figures->vat)->toBe($gross)
            ->and($figures->vat)->toBeGreaterThanOrEqual(0);
    }
});

it('charges no VAT under the small-business rule', function () {
    $figures = InvoiceFigures::fromGross(150_000, 0);

    expect($figures->net)->toBe(150_000)->and($figures->vat)->toBe(0);
});

// --- M-06: issuing ------------------------------------------------------

it('stamps a number, a date and the Invoiced status when an installment is issued', function () {
    Storage::fake('local');
    Bus::fake();

    $installment = invoiceablePlan();
    expect($installment->isInvoiced())->toBeFalse();

    app(IssueInvoice::class)($installment, staffMember(StaffRole::Owner));

    $installment->refresh();

    expect($installment->invoice_number)->toStartWith('RE-')
        ->and($installment->invoiced_at)->not->toBeNull()
        ->and($installment->status)->toBe(PaymentInstallmentStatus::Invoiced);
});

it('allocates exactly one number when the same installment is issued twice', function () {
    Storage::fake('local');
    Bus::fake();

    $installment = invoiceablePlan();
    $issue = app(IssueInvoice::class);

    $issue($installment);
    $first = $installment->fresh()->invoice_number;

    $issue($installment->fresh());

    expect($installment->fresh()->invoice_number)->toBe($first)
        ->and(NumberCounter::where('document_type', DocumentType::Invoice->value)->count())->toBe(1);
});

it('leaves the invoice sequence gapless — every allocated invoice number belongs to an invoice', function () {
    Storage::fake('local');
    Bus::fake();

    $installment = invoiceablePlan();
    $sibling = $installment->plan->installments()->orderBy('sort_order')->get()->last();

    app(IssueInvoice::class)($installment);
    app(IssueInvoice::class)($sibling);

    $issued = PaymentInstallment::whereNotNull('invoice_number')->pluck('invoice_number')->sort()->values();
    $counters = NumberCounter::where('document_type', DocumentType::Invoice->value)->count();

    // Creating the plan itself must no longer burn a number: two invoices,
    // two counter rows, and nothing in between them.
    expect($issued)->toHaveCount(2)
        ->and($counters)->toBe(2)
        ->and($installment->plan->fresh()->reference)->toBeNull();
});

it('keeps a settled installment paid when its paperwork is issued afterwards', function () {
    Storage::fake('local');
    Bus::fake();

    $installment = invoiceablePlan();
    $installment->update(['status' => PaymentInstallmentStatus::Paid]);

    app(IssueInvoice::class)($installment);

    expect($installment->fresh()->status)->toBe(PaymentInstallmentStatus::Paid)
        ->and($installment->fresh()->invoice_number)->not->toBeNull();
});

it('records who issued the invoice', function () {
    Storage::fake('local');
    Bus::fake();

    app(IssueInvoice::class)(invoiceablePlan(), staffMember(StaffRole::Owner));

    expect(AuditLog::where('event', 'payments.invoice.issued')->count())->toBe(1);
});

// --- M-06: rendering ----------------------------------------------------

it('renders the invoice against the pinned template and stores the artifact', function () {
    Storage::fake('local');

    DocumentTemplate::factory()->create([
        'type' => DocumentTemplateType::Invoice,
        'body' => 'Rechnung {{ invoice.number }} über {{ invoice.gross }} an {{ contact.name }}, {{ contact.city }}.',
    ]);

    $this->mock(GotenbergClient::class)
        ->shouldReceive('htmlToPdf')
        ->once()
        ->andReturnUsing(function (string $html) {
            expect($html)->toContain('Rechnung RE-')
                ->and($html)->not->toContain('{{');

            return '%PDF-1.4 fake';
        });

    $installment = invoiceablePlan();
    app(IssueInvoice::class)($installment);

    $installment->refresh();

    expect($installment->invoice_template_id)->not->toBeNull()
        ->and($installment->invoice_path)->toBe("invoices/{$installment->tenant_id}/{$installment->public_id}.pdf");

    Storage::disk('local')->assertExists($installment->invoice_path);
});

it('never re-renders an invoice a client may already hold', function () {
    Storage::fake('local');

    DocumentTemplate::factory()->create([
        'type' => DocumentTemplateType::Invoice,
        'body' => 'Rechnung {{ invoice.number }}',
    ]);

    $this->mock(GotenbergClient::class)->shouldReceive('htmlToPdf')->once()->andReturn('%PDF-1.4 fake');

    $installment = invoiceablePlan();
    app(IssueInvoice::class)($installment);

    // A second run of the same job is a no-op, not a second Gotenberg call.
    (new RenderInvoiceJob($installment->fresh()))->handle(
        app(GotenbergClient::class),
        app(StudioSettingsResolver::class),
        app(DocumentMergeDataBuilder::class),
        app(DocumentRenderer::class),
    );
});

// --- M-06: the two surfaces ---------------------------------------------

it('lets staff issue and then download the invoice', function () {
    Storage::fake('local');

    DocumentTemplate::factory()->create([
        'type' => DocumentTemplateType::Invoice,
        'body' => 'Rechnung {{ invoice.number }}',
    ]);
    $this->mock(GotenbergClient::class)->shouldReceive('htmlToPdf')->andReturn('%PDF-1.4 fake');

    $installment = invoiceablePlan();

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post("/raten/{$installment->public_id}/rechnung")
        ->assertRedirect();

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get("/raten/{$installment->public_id}/rechnung")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('404s a download for an installment that was never issued', function () {
    Storage::fake('local');

    $installment = invoiceablePlan();

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get("/raten/{$installment->public_id}/rechnung")
        ->assertNotFound();
});

it('sends the invoice number to the client as the reference to transfer under', function () {
    Storage::fake('local');
    Bus::fake();

    $installment = invoiceablePlan();
    app(IssueInvoice::class)($installment);

    $data = PaymentPlanData::fromModel($installment->plan->fresh()->load('installments'));

    expect($data->installments[0]->invoiceNumber)->toStartWith('RE-')
        ->and($data->installments[0]->hasInvoiceDocument)->toBeFalse()
        ->and($data->installments[1]->invoiceNumber)->toBeNull();
});

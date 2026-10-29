<?php

use App\Crm\Models\Contact;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Documents\Support\DocumentBlockCatalog;
use App\Documents\Support\MergeFieldResolver;
use App\Documents\Support\MergeFieldVocabulary;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Numbering\Enums\DocumentType;
use App\Numbering\Models\NumberCounter;
use App\Payments\Actions\CancelInvoice;
use App\Payments\Actions\IssueInvoice;
use App\Payments\Actions\MarkInstallmentPaid;
use App\Payments\Actions\RenderInvoicePdf;
use App\Payments\Data\PaymentPlanData;
use App\Payments\Enums\CancellationKind;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Enums\SettledVia;
use App\Payments\Jobs\RenderInvoiceJob;
use App\Payments\Models\Invoice;
use App\Payments\Support\InvoiceFigures;
use App\Payments\Support\InvoiceMergeData;
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

// bookedContactProject(), staffMember() and invoiceablePlan()
// live in tests/Pest.php.

it('refuses to issue without a Steuernummer or USt-IdNr., drawing no number', function () {
    $settings = app(StudioSettingsResolver::class);
    $settings->set('tax_number', null, null);
    $settings->set('vat_id', null, null);

    $installment = invoiceablePlan();

    expect(fn () => app(IssueInvoice::class)($installment))
        ->toThrow(ValidationException::class);

    expect($installment->fresh()->invoice_id)->toBeNull()
        ->and(NumberCounter::query()->where('document_type', DocumentType::Invoice->value)->exists())->toBeFalse();
});

it('issues on the USt-IdNr. alone', function () {
    $settings = app(StudioSettingsResolver::class);
    $settings->set('tax_number', null, null);
    $settings->set('vat_id', 'DE123456789', null);

    expect(app(IssueInvoice::class)(invoiceablePlan())->number)->toStartWith('RE-');
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

it('stamps a number and a date on the invoice and moves the rate to Invoiced', function () {
    Storage::fake('local');
    Bus::fake();

    $installment = invoiceablePlan();
    expect($installment->isInvoiced())->toBeFalse();

    $invoice = app(IssueInvoice::class)($installment, staffMember(StaffRole::Admin));

    expect($invoice->number)->toStartWith('RE-')
        ->and($invoice->issued_at)->not->toBeNull()
        ->and($invoice->contact->name)->toBe('Lena Meier')
        ->and($installment->fresh()->invoice_id)->toBe($invoice->id)
        ->and($installment->fresh()->status)->toBe(PaymentInstallmentStatus::Invoiced);
});

it('allocates exactly one number when the same installment is issued twice', function () {
    Storage::fake('local');
    Bus::fake();

    $installment = invoiceablePlan();
    $issue = app(IssueInvoice::class);

    $first = $issue($installment);
    $second = $issue($installment->fresh());

    expect($second->id)->toBe($first->id)
        ->and(NumberCounter::where('document_type', DocumentType::Invoice->value)->count())->toBe(1);
});

// #480: Anzahlung and second rate on one document.
it('puts several rates of one plan on one invoice, one number and one sum', function () {
    Storage::fake('local');
    Bus::fake();

    $installment = invoiceablePlan();
    $rates = $installment->plan->installments()->orderBy('sort_order')->get();

    $invoice = app(IssueInvoice::class)($rates);

    expect(Invoice::count())->toBe(1)
        ->and($invoice->fresh('installments')->amount())->toBe(150_000)
        ->and($rates->map->fresh()->pluck('invoice_id')->unique()->all())->toBe([$invoice->id])
        ->and(app(InvoiceMergeData::class)->forInvoice($invoice->fresh())['invoice']['description'])
        ->toBe('Hochzeit Meier & Kowalski — Anzahlung, Restbetrag');
});

it('refuses a rate that already sits on another invoice', function () {
    Storage::fake('local');
    Bus::fake();

    $installment = invoiceablePlan();
    $rates = $installment->plan->installments()->orderBy('sort_order')->get();
    app(IssueInvoice::class)($rates->first());

    expect(fn () => app(IssueInvoice::class)($rates->fresh()))->toThrow(ValidationException::class);
});

it('leaves the invoice sequence gapless — every allocated invoice number belongs to an invoice', function () {
    Storage::fake('local');
    Bus::fake();

    $installment = invoiceablePlan();
    $sibling = $installment->plan->installments()->orderBy('sort_order')->get()->last();

    app(IssueInvoice::class)($installment);
    app(IssueInvoice::class)($sibling);

    // Creating the plan itself must no longer burn a number: two invoices,
    // two counter rows, and nothing in between them.
    expect(Invoice::pluck('number'))->toHaveCount(2)
        ->and(NumberCounter::where('document_type', DocumentType::Invoice->value)->count())->toBe(2)
        ->and($installment->plan->fresh()->reference)->toBeNull();
});

it('keeps a settled installment paid when its paperwork is issued afterwards', function () {
    Storage::fake('local');
    Bus::fake();

    $installment = invoiceablePlan();
    $installment->update(['status' => PaymentInstallmentStatus::Paid]);

    app(IssueInvoice::class)($installment);

    expect($installment->fresh()->status)->toBe(PaymentInstallmentStatus::Paid)
        ->and($installment->fresh()->invoice_id)->not->toBeNull();
});

it('records who issued the invoice', function () {
    Storage::fake('local');
    Bus::fake();

    app(IssueInvoice::class)(invoiceablePlan(), staffMember(StaffRole::Admin));

    expect(AuditLog::where('event', 'payments.invoice.issued')->count())->toBe(1);
});

// --- #480: an invoice without a project ---------------------------------

function freeInvoice(): Invoice
{
    Storage::fake('local');
    Bus::fake();

    $guest = Contact::factory()->create(['name' => 'Jonas Gast', 'city' => 'Jena']);

    return app(IssueInvoice::class)->positions($guest, null, [
        ['title' => 'Abzug 30×45', 'quantity' => 30, 'unit_price' => 1_000, 'vat_rate' => 1900],
        ['title' => 'Versand', 'quantity' => 1, 'unit_price' => 500],
    ], now()->addMonth()->toDateString());
}

it('issues an invoice without a project from its items, with one installment to book against', function () {
    $invoice = freeInvoice()->fresh(['installments', 'items']);

    expect($invoice->case_id)->toBeNull()
        ->and($invoice->number)->toStartWith('RE-')
        ->and($invoice->amount())->toBe(36_295)
        ->and($invoice->installments->sole()->payment_plan_id)->toBeNull()
        ->and($invoice->installments->sole()->due_on->format('Y-m-d'))->toBe(now()->addMonth()->toDateString());

    $figures = InvoiceFigures::forInvoice($invoice, app(StudioSettingsResolver::class));

    expect($figures->gross)->toBe(36_295)
        ->and($figures->net + $figures->vat)->toBe(36_295)
        ->and($figures->net)->toBe(30_500);
});

it('prints the items and the guest as recipient on an invoice without a project', function () {
    $invoice = freeInvoice();

    $data = app(InvoiceMergeData::class)->forInvoice($invoice->fresh());

    expect($data['contact']['name'])->toBe('Jonas Gast')
        ->and($data['project']['name'])->toBe('')
        ->and($data['invoice']['lines'])->toHaveCount(2)
        ->and($data['invoice']['lines'][0]['title'])->toBe('Abzug 30×45')
        ->and($data['invoice']['gross'])->toContain('362,95');
});

it('marks an invoice without a project paid, and retires it', function () {
    $invoice = freeInvoice();

    app(MarkInstallmentPaid::class)($invoice->installments()->sole(), SettledVia::Transfer, '2026-10-02', null, null, 10_000);

    expect($invoice->fresh()->status())->toBe('partial')
        ->and(fn () => app(CancelInvoice::class)($invoice->fresh(), CancellationKind::Void, 'Falsch'))->toThrow(ValidationException::class);

    $open = freeInvoice();
    app(CancelInvoice::class)($open, CancellationKind::Void, 'Doppelt');

    expect($open->fresh()->status())->toBe('cancelled')
        ->and($open->installments()->sole()->status)->toBe(PaymentInstallmentStatus::Cancelled);
});

// #480: a rate of the plan and a free position on one invoice.
it('bills a picked rate at its own gross and charges free positions on top', function () {
    Storage::fake('local');
    Bus::fake();

    $installment = invoiceablePlan();
    $project = $installment->plan->case;

    $invoice = app(IssueInvoice::class)->positions($project->primaryContact(), $project, [
        ['title' => 'Anzahlung', 'installment' => $installment],
        ['title' => 'Zusätzliche Abzüge', 'quantity' => 10, 'unit_price' => 1_000, 'discount_type' => 'percentage', 'discount_value' => 1000],
    ], '2026-10-15')->fresh(['installments', 'items']);

    $extra = $invoice->installments->firstWhere('payment_plan_id', null);
    $figures = InvoiceFigures::forInvoice($invoice, app(StudioSettingsResolver::class));

    expect($installment->fresh()->invoice_id)->toBe($invoice->id)
        ->and($extra->amount)->toBe(10_710)
        ->and($extra->due_on->format('Y-m-d'))->toBe('2026-10-15')
        ->and($invoice->amount())->toBe((int) $installment->amount + 10_710)
        ->and($figures->gross)->toBe((int) $installment->amount + 10_710);
});

it('refuses a rate from another project', function () {
    Storage::fake('local');
    Bus::fake();

    $installment = invoiceablePlan();

    expect(fn () => app(IssueInvoice::class)->positions(null, null, [['title' => 'Anzahlung', 'installment' => $installment]], null))
        ->toThrow(ValidationException::class);
});

it('lets staff issue an invoice without a project to a new contact', function () {
    Storage::fake('local');
    Bus::fake();

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->post('/rechnungen', [
            'contact' => ['name' => 'Jonas Gast', 'city' => 'Jena'],
            'due_on' => '2026-10-01',
            'items' => [['title' => 'Abzüge', 'quantity' => 2, 'unit_price' => 2_000]],
        ])
        ->assertRedirect("/rechnungen/{$this->app['db']->table('invoices')->value('public_id')}/versenden");

    expect(Invoice::sole()->contact->name)->toBe('Jonas Gast');
});

// --- M-06: rendering ----------------------------------------------------

it('renders the invoice with its own letter text and stores the artifact', function () {
    Storage::fake('local');

    DocumentTemplate::factory()->create([
        'type' => DocumentTemplateType::Invoice,
        'body' => 'Rechnung {{ invoice.number }} über {{ invoice.gross }} an {{ contact.name }}, {{ contact.city }}.',
    ]);

    $this->mock(GotenbergClient::class)
        ->shouldReceive('htmlToPdf')
        ->once()
        ->andReturnUsing(function (string $html) {
            expect($html)->toContain('an Lena Meier.')
                ->and($html)->not->toContain('{{');

            return '%PDF-1.4 fake';
        });

    $invoice = app(IssueInvoice::class)(invoiceablePlan(), null, ['intro' => 'Rechnung über {{ invoice.gross }} an {{ contact.name }}.'])->fresh();

    expect($invoice->template_id)->not->toBeNull()
        ->and($invoice->pdf_path)->toBe("invoices/{$invoice->tenant_id}/{$invoice->public_id}.pdf");

    Storage::disk('local')->assertExists($invoice->pdf_path);
});

it('never re-renders an invoice a client may already hold', function () {
    Storage::fake('local');

    DocumentTemplate::factory()->create([
        'type' => DocumentTemplateType::Invoice,
        'body' => 'Rechnung {{ invoice.number }}',
    ]);

    $this->mock(GotenbergClient::class)->shouldReceive('htmlToPdf')->once()->andReturn('%PDF-1.4 fake');

    $invoice = app(IssueInvoice::class)(invoiceablePlan());

    // A second run of the same job is a no-op, not a second Gotenberg call.
    (new RenderInvoiceJob($invoice->fresh()))->handle(
        app(GotenbergClient::class),
        app(RenderInvoicePdf::class),
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

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->post("/raten/{$installment->public_id}/rechnung")
        ->assertRedirect();

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get('/rechnungen/'.Invoice::sole()->public_id.'/pdf')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
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

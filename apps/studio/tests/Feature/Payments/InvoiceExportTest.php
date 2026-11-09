<?php

use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Enums\StaffRole;
use App\Payments\Actions\IssueCreditNote;
use App\Payments\Actions\IssueInvoice;
use App\Payments\Actions\RenderInvoicePdf;
use App\Payments\Jobs\RenderInvoiceJob;
use App\Payments\Support\InvoiceMergeData;
use App\Payments\Support\MergeDataSnapshot;
use App\Settings\Actions\UpdateStudioSetting;
use App\Support\GotenbergClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(UpdateStudioSetting::class)('tax_number', '22/123/45678', null);
});

it('exports one line per issued invoice of the year, with a BOM and the header row', function () {
    $this->travelTo('2026-05-04 10:00:00');

    $issued = app(IssueInvoice::class)(invoiceablePlan());

    $csv = $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get('/einstellungen/rechnungen/export?jahr=2026')
        ->assertOk()
        ->streamedContent();

    $lines = explode("\n", trim($csv));

    expect($csv)->toStartWith("\u{FEFF}")
        ->and($lines)->toHaveCount(2)
        ->and(str_getcsv(ltrim($lines[0], "\u{FEFF}"), ';'))->toBe([
            'Nummer', 'Datum', 'Kunde', 'Projekt', 'Netto', 'Steuersatz', 'Steuer',
            'Brutto', 'bezahlt am', 'Status', 'Gutschriften',
        ])
        ->and(str_getcsv($lines[1], ';'))->toBe([
            $issued->number, '04.05.2026', 'Lena Meier', 'Hochzeit Meier & Kowalski',
            '630,25', '19 %', '119,75', '750,00', '', 'Offen', '',
        ]);
});

it('leaves another year out', function () {
    $this->travelTo('2026-05-04 10:00:00');
    app(IssueInvoice::class)(invoiceablePlan());

    $csv = $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get('/einstellungen/rechnungen/export?jahr=2025')
        ->assertOk()
        ->streamedContent();

    expect(explode("\n", trim($csv)))->toHaveCount(1);
});

it('404s a role without ManageSettings', function () {
    $this->actingAs(staffMember(StaffRole::Contributor), 'staff')
        ->get('/einstellungen/rechnungen/export?jahr=2026')
        ->assertNotFound();
});

// GoBD Rz. 59 Beispiel 4: das PDF von damals muss aus den Daten von damals
// entstehen, auch wenn die Datei verloren ging.
it('re-renders a lost PDF from the snapshot, not from today settings', function () {
    Storage::fake('local');

    DocumentTemplate::factory()->create([
        'type' => DocumentTemplateType::Invoice,
        'body' => 'Rechnung {{ invoice.number }}',
    ]);

    app(UpdateStudioSetting::class)('bank_iban', 'DE02120300000000202051', null);

    $seen = [];
    $this->mock(GotenbergClient::class)
        ->shouldReceive('htmlToPdf')
        ->twice()
        ->andReturnUsing(function (string $html) use (&$seen) {
            $seen[] = $html;

            return '%PDF-1.4 fake';
        });

    $invoice = app(IssueInvoice::class)(invoiceablePlan());
    $path = $invoice->fresh()->pdf_path;

    expect(Storage::disk('local')->exists(MergeDataSnapshot::pathFor($path)))->toBeTrue();

    app(UpdateStudioSetting::class)('bank_iban', 'DE89370400440532013000', null);
    Storage::disk('local')->delete($path);

    (new RenderInvoiceJob($invoice->fresh()))->handle(
        app(GotenbergClient::class),
        app(RenderInvoicePdf::class),
    );

    expect($seen[1])->toContain('DE02120300000000202051')
        ->and($seen[1])->not->toContain('DE89370400440532013000');
});

it('freezes a Gutschrift beside its own PDF', function () {
    DocumentTemplate::factory()->create([
        'type' => DocumentTemplateType::CreditNote,
        'body' => 'Gutschrift {{ creditNote.number }}',
    ]);

    $this->mock(GotenbergClient::class)->shouldReceive('htmlToPdf')->andReturn('%PDF-1.4 fake');

    $installment = creditableInstallment();
    $note = app(IssueCreditNote::class)($installment->invoice, 20_000, 'Teilleistung entfallen');

    expect(Storage::disk('local')->exists(MergeDataSnapshot::pathFor($note->fresh()->pdf_path)))->toBeTrue();
});

// #565: the shape DocumentMergeDataBuilder wrote into every snapshot issued before it
// was split into providers. Field names are the contract with those files.
function legacyMergeShape(): array
{
    $invoiceKeys = ['number', 'date', 'servicePeriod', 'description', 'net', 'vatRate', 'vat', 'gross', 'vatNote', 'dueOn', 'paymentTerm', 'vatLines', 'lines', 'deposits', 'totalNet', 'totalVat', 'totalGross'];

    return [
        'project' => ['name', 'type', 'bookedAt'],
        'contact' => ['name', 'email', 'street', 'postcode', 'city'],
        'invoice' => $invoiceKeys,
        'creditNote' => ['number', 'date', 'servicePeriod', 'description', 'reason', 'net', 'vatRate', 'vat', 'gross', 'vatNote', 'invoiceNumber', 'invoiceDate'],
        'shoot' => ['date', 'list'],
        'payment' => ['depositDue', 'plan'],
        'quote' => ['validUntil', 'vatNote'],
        'gallery' => ['title', 'link', 'password', 'downloadPin', 'expiresOn'],
        'studio' => ['name', 'legalForm', 'street', 'postcode', 'city', 'country', 'taxNumber', 'vatId', 'registerNumber', 'registerCourt', 'representative', 'website', 'bankHolder', 'bankIban', 'bankBic', 'logoPath'],
    ];
}

it('builds the same blocks and fields a stored invoice snapshot carries', function () {
    $invoice = app(IssueInvoice::class)(invoiceablePlan());
    $data = app(InvoiceMergeData::class)->forInvoice($invoice);

    $expected = legacyMergeShape();
    ksort($expected);
    $actual = array_map(fn (array $block) => array_keys($block), $data);
    ksort($actual);

    foreach ($expected as $block => $keys) {
        sort($keys);
        sort($actual[$block]);
        expect($actual[$block])->toBe($keys, "block {$block}");
    }

    expect(array_keys($actual))->toBe(array_keys($expected));
});

it('renders an invoice stored before the merge data was split unchanged', function () {
    Storage::fake('local');
    $invoice = app(IssueInvoice::class)(invoiceablePlan());

    $frozen = array_map(fn (array $keys) => array_fill_keys($keys, ''), legacyMergeShape());
    $frozen['contact'] = ['name' => 'Frieda Alt', 'email' => 'alt@example.test', 'street' => 'Altweg 7', 'postcode' => '07743', 'city' => 'Jena'];
    $frozen['studio'] = ['name' => 'Altstudio GmbH', 'taxNumber' => '99/888/77777', 'bankHolder' => 'Altstudio GmbH', 'bankIban' => 'DE02120300000000202051', 'bankBic' => 'BYLADEM1001'] + $frozen['studio'];
    $frozen['invoice'] = [
        'number' => 'RE-2024-0042', 'date' => '03.02.2024', 'servicePeriod' => '01.02.2024', 'description' => 'Hochzeit Alt, Anzahlung',
        'net' => '1.000,00 €', 'vatRate' => '19 %', 'vat' => '190,00 €', 'gross' => '1.190,00 €', 'dueOn' => '17.02.2024', 'paymentTerm' => 'Zahlbar bis 17.02.2024 ohne Abzug.',
        'vatLines' => [['rate' => '19 %', 'net' => '1.000,00 €', 'vat' => '190,00 €']],
        'lines' => [['title' => 'Altes Paket', 'quantity' => '1', 'unitPrice' => '1.000,00 €', 'discount' => '', 'total' => '1.000,00 €', 'vatRate' => '19 %']],
        'deposits' => [['title' => 'Anzahlung', 'number' => 'RE-2024-0041', 'date' => '01.01.2024', 'net' => '500,00 €', 'vat' => '95,00 €', 'gross' => '595,00 €', 'vatAmount' => 9500]],
        'totalNet' => '1.000,00 €', 'totalVat' => '190,00 €', 'totalGross' => '1.190,00 €',
    ] + $frozen['invoice'];

    Storage::disk('local')->put(MergeDataSnapshot::pathFor('invoices/alt.pdf'), json_encode($frozen));

    $html = app(RenderInvoicePdf::class)->forInvoice($invoice, null, 'invoices/alt.pdf');

    expect($html)->toContain('RE-2024-0042', 'Frieda Alt', 'Altweg 7', '07743 Jena', 'Altes Paket', 'RE-2024-0041', '1.190,00 €', 'DE02120300000000202051', 'BYLADEM1001', '99/888/77777')
        ->and($html)->not->toContain($invoice->number);
});

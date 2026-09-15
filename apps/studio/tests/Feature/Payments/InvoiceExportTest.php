<?php

use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Enums\StaffRole;
use App\Payments\Actions\IssueCreditNote;
use App\Payments\Actions\IssueInvoice;
use App\Payments\Actions\RenderInvoicePdf;
use App\Payments\Jobs\RenderInvoiceJob;
use App\Payments\Support\MergeDataSnapshot;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\GotenbergClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(StudioSettingsResolver::class)->set('tax_number', '22/123/45678', null);
});

it('exports one line per issued invoice of the year, with a BOM and the header row', function () {
    $this->travelTo('2026-05-04 10:00:00');

    $issued = app(IssueInvoice::class)(invoiceablePlan());

    $csv = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
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
            $issued->invoice_number, '04.05.2026', 'Lena Meier', 'Hochzeit Meier & Kowalski',
            '630,25', '19 %', '119,75', '750,00', '', 'Gestellt', '',
        ]);
});

it('leaves another year out', function () {
    $this->travelTo('2026-05-04 10:00:00');
    app(IssueInvoice::class)(invoiceablePlan());

    $csv = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/einstellungen/rechnungen/export?jahr=2025')
        ->assertOk()
        ->streamedContent();

    expect(explode("\n", trim($csv)))->toHaveCount(1);
});

it('404s a role without ManageSettings', function () {
    $this->actingAs(staffMember(StaffRole::Assistant), 'staff')
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

    $settings = app(StudioSettingsResolver::class);
    $settings->set('bank_iban', 'DE02120300000000202051', null);

    $seen = [];
    $this->mock(GotenbergClient::class)
        ->shouldReceive('htmlToPdf')
        ->twice()
        ->andReturnUsing(function (string $html) use (&$seen) {
            $seen[] = $html;

            return '%PDF-1.4 fake';
        });

    $installment = app(IssueInvoice::class)(invoiceablePlan());
    $path = $installment->fresh()->invoice_path;

    expect(Storage::disk('local')->exists(MergeDataSnapshot::pathFor($path)))->toBeTrue();

    $settings->set('bank_iban', 'DE89370400440532013000', null);
    Storage::disk('local')->delete($path);

    (new RenderInvoiceJob($installment->fresh()))->handle(
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
    $note = app(IssueCreditNote::class)($installment, 20_000, 'Teilleistung entfallen');

    expect(Storage::disk('local')->exists(MergeDataSnapshot::pathFor($note->fresh()->pdf_path)))->toBeTrue();
});

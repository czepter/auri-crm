<?php

use App\Crm\Models\Shoot;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Payments\Actions\IssueCreditNote;
use App\Payments\Actions\IssueInvoice;
use App\Payments\Actions\MarkInstallmentPaid;
use App\Payments\Actions\RenderInvoicePdf;
use App\Payments\Enums\SettledVia;
use App\Payments\Models\CreditNote;
use App\Payments\Support\InvoiceDocument;
use App\Quotes\Enums\QuoteLayout;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(StudioSettingsResolver::class)->set('tax_number', '22/123/45678', null);
});

function layoutInvoice(): App\Payments\Models\PaymentInstallment
{
    Storage::fake('local');
    Bus::fake();

    $installment = invoiceablePlan();

    // The Leistungszeitraum is the primary shoot's date — § 14's supply date,
    // and empty without one.
    Shoot::factory()->for($installment->plan->job->project)->primary()->create(['date' => '2026-06-14']);

    app(IssueInvoice::class)($installment);

    return $installment->fresh();
}

// --- #419: every layout carries the § 14 particulars itself --------------

it('prints the number, the gross and the § 19 note in every layout under the small-business rule', function (QuoteLayout $layout) {
    $settings = app(StudioSettingsResolver::class);
    $settings->set('vat_mode', 'small_business', null);
    $settings->set('vat_note', 'Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.', null);

    $installment = layoutInvoice();

    $html = app(RenderInvoicePdf::class)->forInstallment($installment, $layout);

    expect($html)->toContain($installment->invoice_number)
        ->and($html)->toContain('750,00')
        ->and($html)->toContain('§ 19 UStG')
        // Kein Umsatzsteuerausweis: § 19 verbietet die Zeile.
        ->and($html)->not->toContain('Umsatzsteuer 0')
        ->and($html)->not->toContain('{{');
})->with([
    'classic' => QuoteLayout::Classic,
    'minimal' => QuoteLayout::Minimal,
    'letter' => QuoteLayout::Letter,
    'header band' => QuoteLayout::HeaderBand,
    'structured' => QuoteLayout::Structured,
]);

it('prints the Netto → Umsatzsteuer → Gesamtbetrag chain in every layout at the standard rate', function (QuoteLayout $layout) {
    $installment = layoutInvoice();

    $html = app(RenderInvoicePdf::class)->forInstallment($installment, $layout);

    expect($html)->toContain('Netto')
        ->and($html)->toContain('Umsatzsteuer 19 %')
        ->and($html)->toContain('Gesamtbetrag')
        // Die Nummer steht in jedem Layout; das Label „Rechnungsnummer" nur
        // in denen mit einer Meta-Tabelle — Minimal und Kopfband tragen sie
        // in der Kopfzeile, wie beim Angebot.
        ->and($html)->toContain($installment->invoice_number)
        ->and($html)->toContain('14.06.2026')
        ->and($html)->not->toContain('{{');
})->with([
    'classic' => QuoteLayout::Classic,
    'minimal' => QuoteLayout::Minimal,
    'letter' => QuoteLayout::Letter,
    'header band' => QuoteLayout::HeaderBand,
    'structured' => QuoteLayout::Structured,
]);

it('reads the layout from the same setting the quote reads', function () {
    app(StudioSettingsResolver::class)->set('quote_layout', 'header_band', null);

    $html = app(RenderInvoicePdf::class)->forInstallment(layoutInvoice());

    expect($html)->toContain('doc-layout-header_band');
});

it('carries the Verzugshinweis once, in every layout', function (QuoteLayout $layout) {
    $html = app(RenderInvoicePdf::class)->forInstallment(layoutInvoice(), $layout);

    expect(substr_count($html, InvoiceDocument::DEFAULT_NOTICE))->toBe(1);
})->with([
    'classic' => QuoteLayout::Classic,
    'minimal' => QuoteLayout::Minimal,
    'letter' => QuoteLayout::Letter,
    'header band' => QuoteLayout::HeaderBand,
    'structured' => QuoteLayout::Structured,
]);

it('carries the bank details and the Verwendungszweck without a template block', function () {
    $settings = app(StudioSettingsResolver::class);
    $settings->set('bank_account_holder', 'Atelier Nordlicht', null);
    $settings->set('bank_iban', 'DE02120300000000202051', null);

    $installment = layoutInvoice();

    $html = app(RenderInvoicePdf::class)->forInstallment($installment);

    expect($html)->toContain('Zahlung')
        ->and($html)->toContain('Atelier Nordlicht')
        ->and($html)->toContain('IBAN DE02120300000000202051')
        ->and($html)->toContain('Verwendungszweck '.$installment->invoice_number);
});

it('renders the Gutschrift through the same layouts, negative and referencing the invoice', function () {
    $installment = layoutInvoice();
    app(MarkInstallmentPaid::class)($installment, SettledVia::Transfer, '2026-09-02', null);

    app(IssueCreditNote::class)($installment->fresh(), 25_000, 'Nachlass');

    $html = app(RenderInvoicePdf::class)->forCreditNote(CreditNote::sole());

    expect($html)->toContain('Gutschrift')
        ->and($html)->toContain('Zu Rechnung '.$installment->invoice_number)
        ->and($html)->toContain('−250,00')
        ->and($html)->toContain('doc-layout-classic')
        // Eine Gutschrift wird ausgezahlt, nicht überwiesen.
        ->and($html)->not->toContain('Verwendungszweck');
});

// --- the migration over existing tenant templates ------------------------

it('strips the layout blocks from an existing invoice template and keeps the prose', function () {
    $template = DocumentTemplate::factory()->create([
        'type' => DocumentTemplateType::Invoice,
        'body' => '<p>Guten Tag {{ contact.name }},</p>'
            .'<table><tr><td>Netto</td><td>{{ invoice.net }}</td></tr></table>'
            .'<p>{{ studio.bankHolder }}<br>IBAN {{ studio.bankIban }}</p>'
            .'<p>'.InvoiceDocument::DEFAULT_NOTICE.'</p>'
            .'<p>Vielen Dank für den Auftrag.</p>',
        'blocks' => ['blocks' => [
            ['type' => 'paragraph', 'data' => ['text' => 'Guten Tag {{ contact.name }},']],
            ['type' => 'table', 'data' => ['content' => [['Netto', '{{ invoice.net }}']]]],
            ['type' => 'paragraph', 'data' => ['text' => InvoiceDocument::DEFAULT_NOTICE]],
            ['type' => 'paragraph', 'data' => ['text' => 'Vielen Dank für den Auftrag.']],
        ]],
    ]);

    // The migration ran before this row existed, so re-run it against it.
    (require database_path('migrations/2026_09_14_000500_strip_layout_blocks_from_invoice_templates.php'))->up();

    $fresh = $template->fresh();

    expect($fresh->body)->toContain('Guten Tag')
        ->and($fresh->body)->toContain('Vielen Dank für den Auftrag.')
        ->and($fresh->body)->not->toContain('invoice.net')
        ->and($fresh->body)->not->toContain('studio.bankIban')
        // #422's sentence is the layout's now; a second copy would print twice.
        ->and($fresh->body)->not->toContain('tritt Verzug ein')
        ->and($fresh->blocks['blocks'])->toHaveCount(2);
});

it('leaves an older template version alone — an issued invoice pinned it', function () {
    $current = DocumentTemplate::factory()->create([
        'type' => DocumentTemplateType::Invoice,
        'body' => '<p>{{ invoice.net }}</p>',
    ]);

    DB::table('document_templates')->where('id', $current->id)->update(['is_current' => false]);

    (require database_path('migrations/2026_09_14_000500_strip_layout_blocks_from_invoice_templates.php'))->up();

    expect($current->fresh()->body)->toContain('invoice.net');
});

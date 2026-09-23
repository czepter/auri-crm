<?php

use App\Documents\Support\DocumentMergeDataBuilder;
use App\Payments\Actions\CancelInvoice;
use App\Payments\Actions\CreatePaymentPlan;
use App\Payments\Actions\IssueCreditNote;
use App\Payments\Actions\IssueInvoice;
use App\Payments\Actions\MarkInstallmentPaid;
use App\Payments\Actions\RenderInvoicePdf;
use App\Payments\Enums\CancellationKind;
use App\Payments\Enums\SettledVia;
use App\Payments\Models\PaymentPlan;
use App\Payments\Support\InvoiceFigures;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Bus::fake();

    $settings = app(StudioSettingsResolver::class);
    $settings->set('tax_number', '22/123/45678', null);
    $settings->set('vat_mode', 'standard', null);
    $settings->set('vat_rate', 1900, null);
});

/**
 * A booked project with an accepted classic quote and a plan of $dueDates
 * installments. The quote's positions are what the Endrechnung has to list.
 *
 * @param  list<string>  $dueDates
 * @param  array<string, mixed>  $slider  set while the quote is still a draft
 */
function planBehindQuote(array $dueDates, int $discountValue = 0, array $slider = []): PaymentPlan
{
    $project = bookedContactProject();

    $quote = Quote::factory()->create([
        'project_id' => $project->id,
        'status' => QuoteStatus::Draft,
        'discount_type' => $discountValue > 0 ? 'percentage' : null,
        'discount_value' => $discountValue > 0 ? $discountValue : null,
    ]);

    $quote->items()->createMany([
        ['title' => 'Hochzeitsreportage', 'quantity' => 1, 'unit_price' => 200_000, 'sort_order' => 0],
        ['title' => 'Fotobuch', 'quantity' => 2, 'unit_price' => 25_000, 'sort_order' => 1],
    ]);

    $quote->recalculateTotal();
    $quote->refresh()->forceFill($slider)->save();
    $quote->refresh()->snapshotVat(app(StudioSettingsResolver::class));
    $quote->forceFill(['status' => QuoteStatus::Accepted, 'accepted_at' => now()])->save();

    $quote = $quote->fresh();

    return app(CreatePaymentPlan::class)($project, $quote->net + $quote->vat_amount, $dueDates);
}

// --- Which installment is the Endrechnung ---------------------------------

it('treats the last live installment as the Endrechnung and no other', function () {
    $plan = planBehindQuote(['2026-09-01', '2026-10-01']);
    [$first, $last] = $plan->installments()->orderBy('sort_order')->get()->all();

    expect($first->isFinal())->toBeFalse()
        ->and($last->isFinal())->toBeTrue();
});

it('treats a single installment as the Endrechnung with nothing to deduct', function () {
    $plan = planBehindQuote(['2026-09-01']);
    $only = $plan->installments()->sole();

    $invoice = app(IssueInvoice::class)($only);

    $html = app(RenderInvoicePdf::class)->forInvoice($invoice->fresh());

    expect($only->isFinal())->toBeTrue()
        ->and($html)->toContain('Hochzeitsreportage')
        ->and($html)->toContain('Gesamtbetrag der Leistung')
        ->and($html)->not->toContain('Abzüglich Anzahlungen');
});

// --- The document ---------------------------------------------------------

it('lists the quote lines, deducts the Anzahlung and sums to the cent', function () {
    $plan = planBehindQuote(['2026-09-01', '2026-10-01']);
    [$deposit, $final] = $plan->installments()->orderBy('sort_order')->get()->all();

    $depositInvoice = app(IssueInvoice::class)($deposit);
    $finalInvoice = app(IssueInvoice::class)($final)->fresh();

    $html = app(RenderInvoicePdf::class)->forInvoice($finalInvoice);

    expect($html)->toContain('Hochzeitsreportage')
        ->and($html)->toContain('Fotobuch')
        ->and($html)->toContain('Abzüglich Anzahlungen')
        ->and($html)->toContain($depositInvoice->number)
        ->and($html)->toContain('Restbetrag')
        ->and($html)->not->toContain('{{');

    // Netto + Steuer des Restbetrags ergeben genau die Rate.
    $figures = InvoiceFigures::forInvoice($finalInvoice, app(StudioSettingsResolver::class));
    expect($figures->net + $figures->vat)->toBe((int) $final->amount);
});

it('deducts the VAT of each Anzahlung by amount', function () {
    $plan = planBehindQuote(['2026-09-01', '2026-10-01']);
    [$deposit, $final] = $plan->installments()->orderBy('sort_order')->get()->all();

    app(IssueInvoice::class)($deposit);
    $invoice = app(IssueInvoice::class)($final);

    $vat = InvoiceFigures::fromGross((int) $deposit->amount, 1900)->vat;
    $html = app(RenderInvoicePdf::class)->forInvoice($invoice->fresh());

    expect($html)->toContain('USt '.Number::currency($vat / 100, in: 'EUR', locale: 'de'))
        ->and($html)->toContain('davon USt');
});

it('prints the quote discount as its own Rabatt line', function () {
    $plan = planBehindQuote(['2026-09-01'], discountValue: 1000);
    $only = $plan->installments()->sole();

    $invoice = app(IssueInvoice::class)($only);

    expect(app(RenderInvoicePdf::class)->forInvoice($invoice->fresh()))->toContain('Rabatt');
});

it('leaves a cancelled earlier installment out of the deposits', function () {
    $plan = planBehindQuote(['2026-09-01', '2026-10-01', '2026-11-01']);
    [$first, $second, $final] = $plan->installments()->orderBy('sort_order')->get()->all();

    $firstInvoice = app(IssueInvoice::class)($first);
    $secondInvoice = app(IssueInvoice::class)($second);
    app(CancelInvoice::class)($firstInvoice, CancellationKind::Void, 'Leistung entfallen');

    $finalInvoice = app(IssueInvoice::class)($final->fresh());
    $deposits = app(DocumentMergeDataBuilder::class)->fromInvoice($finalInvoice->fresh(), app(StudioSettingsResolver::class))['invoice']['deposits'];

    expect(array_column($deposits, 'number'))->toBe([$secondInvoice->number]);
});

// #480: two Anzahlungen on one document are one Teilentgelt to deduct.
it('deducts an Anzahlungsrechnung carrying two rates as one line', function () {
    $plan = planBehindQuote(['2026-09-01', '2026-10-01', '2026-11-01']);
    [$first, $second, $final] = $plan->installments()->orderBy('sort_order')->get()->all();

    $deposit = app(IssueInvoice::class)(collect([$first, $second]));
    $finalInvoice = app(IssueInvoice::class)($final);

    $deposits = app(DocumentMergeDataBuilder::class)->fromInvoice($finalInvoice->fresh(), app(StudioSettingsResolver::class))['invoice']['deposits'];

    expect($deposits)->toHaveCount(1)
        ->and($deposits[0]['number'])->toBe($deposit->number)
        ->and($deposits[0]['gross'])->toBe(Number::currency(((int) $first->amount + (int) $second->amount) / 100, in: 'EUR', locale: 'de'));
});

it('keeps an Anzahlungsrechnung free of both blocks', function () {
    $plan = planBehindQuote(['2026-09-01', '2026-10-01']);
    $deposit = $plan->installments()->orderBy('sort_order')->first();

    $invoice = app(IssueInvoice::class)($deposit);

    $html = app(RenderInvoicePdf::class)->forInvoice($invoice->fresh());

    expect($html)->not->toContain('Gesamtbetrag der Leistung')
        ->and($html)->not->toContain('Abzüglich Anzahlungen')
        ->and($html)->not->toContain('Hochzeitsreportage');
});

it('renders a slider quote as the hours line', function () {
    $plan = planBehindQuote(['2026-09-01'], slider: [
        'pricing_mode' => 'choice',
        'hourly_rate' => 25_000,
        'min_hours' => 4,
        'max_hours' => 10,
        'hours' => 6,
        'time_title' => 'Begleitung',
    ]);
    $only = $plan->installments()->sole();

    $invoice = app(IssueInvoice::class)($only);

    $html = app(RenderInvoicePdf::class)->forInvoice($invoice->fresh());

    expect($html)->toContain('Begleitung')
        ->and($html)->not->toContain('Hochzeitsreportage');
});

it('cancels a cancelled installment out of isFinal', function () {
    $plan = planBehindQuote(['2026-09-01', '2026-10-01']);
    [$first, $last] = $plan->installments()->orderBy('sort_order')->get()->all();

    $invoice = app(IssueInvoice::class)($last);
    app(CancelInvoice::class)($invoice, CancellationKind::Void, 'Restleistung entfallen');

    expect($first->fresh()->isFinal())->toBeTrue();
});

it('deducts a credited Anzahlung net of its Gutschrift', function () {
    $plan = planBehindQuote(['2026-09-01', '2026-10-01']);
    [$deposit, $final] = $plan->installments()->orderBy('sort_order')->get()->all();

    $depositInvoice = app(IssueInvoice::class)($deposit);
    app(MarkInstallmentPaid::class)(
        $deposit->fresh(),
        SettledVia::Transfer,
        '2026-09-02',
        null,
    );
    app(IssueCreditNote::class)($depositInvoice->fresh(), 10_000, 'Nachlass');

    $mergeData = app(DocumentMergeDataBuilder::class)->fromInvoice(
        app(IssueInvoice::class)($final)->fresh(),
        app(StudioSettingsResolver::class),
    );

    $gross = $mergeData['invoice']['deposits'][0]['gross'];

    expect($gross)->not->toContain((string) ((int) $deposit->fresh()->amount / 100));
});

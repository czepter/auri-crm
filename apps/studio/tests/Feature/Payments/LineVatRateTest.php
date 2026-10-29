<?php

use App\Payments\Actions\CreatePaymentPlan;
use App\Payments\Actions\IssueInvoice;
use App\Payments\Actions\RenderInvoicePdf;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Support\InvoiceFigures;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Settings\Actions\UpdateStudioSetting;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Bus::fake();
});

/**
 * A booked project whose accepted quote carries the two rates a photographer
 * actually bills: 19 % on the reportage, 7 % on the printed book.
 *
 * @param  list<array{int, ?int}>  $lines  net line total in minor units, rate in basis points or null
 */
function mixedRateInstallment(array $lines, int $deposit, string $vatMode = 'standard'): PaymentInstallment
{
    $settings = app(StudioSettingsResolver::class);
    app(UpdateStudioSetting::class)('vat_mode', $vatMode, null);
    app(UpdateStudioSetting::class)('vat_rate', 1900, null);
    // § 14 Abs. 4 Nr. 2: IssueInvoice refuses without one.
    app(UpdateStudioSetting::class)('tax_number', '22/123/45678', null);

    $project = bookedContactProject();

    $quote = Quote::factory()->create([
        'case_id' => $project->id,
        'status' => QuoteStatus::Draft,
    ]);

    foreach ($lines as $index => [$amount, $rate]) {
        $quote->items()->create([
            'title' => "Position {$index}",
            'quantity' => 1,
            'unit_price' => $amount,
            'vat_rate' => $rate,
            'sort_order' => $index,
        ]);
    }

    $quote->recalculateTotal();
    $quote->refresh()->snapshotVat($settings);
    $quote->forceFill(['status' => QuoteStatus::Accepted, 'accepted_at' => now()])->save();

    $total = $quote->fresh()->net + $quote->fresh()->vat_amount;
    $plan = app(CreatePaymentPlan::class)($project, $total, ['2026-09-01', '2026-10-01']);
    $plan->installments()->orderBy('sort_order')->first()->update(['amount' => $deposit]);
    $plan->installments()->orderBy('sort_order')->skip(1)->first()->update(['amount' => $total - $deposit]);

    return $plan->installments()->orderBy('sort_order')->first()->fresh();
}

// --- Quote::vatLines() -----------------------------------------------------

it('sums net and VAT per rate and keeps vat_amount the sum', function () {
    $installment = mixedRateInstallment([[100_000, null], [10_000, 700]], 55_350);
    $quote = $installment->plan->case->quotes()->sole();

    expect($quote->vatLines())->toBe([
        ['rate' => 1900, 'net' => 100_000, 'vat' => 19_000],
        ['rate' => 700, 'net' => 10_000, 'vat' => 700],
    ])
        ->and($quote->vat_amount)->toBe(19_700)
        ->and($quote->net)->toBe(110_000);
});

// --- InvoiceFigures: one installment shared over the rates -----------------

it('splits an Anzahlung pro rata over both rates, summing to the installment exactly', function () {
    // 1.000 € at 19 % and 100 € at 7 % gross to 1.297,00 €; half of that is
    // the Anzahlung below.
    $installment = mixedRateInstallment([[100_000, null], [10_000, 700]], 64_850);

    $figures = InvoiceFigures::forInvoice(app(IssueInvoice::class)($installment), app(StudioSettingsResolver::class));

    expect(array_column($figures->rates, 'rate'))->toBe([1900, 700])
        ->and(array_sum(array_column($figures->rates, 'net')) + array_sum(array_column($figures->rates, 'vat')))
        ->toBe(64_850)
        ->and($figures->net + $figures->vat)->toBe(64_850);
});

it('puts the rounding difference on the largest rate so the shares still sum to the gross', function () {
    // 3 cent over three unequal shares: the division cannot come out even.
    $installment = mixedRateInstallment([[100_000, null], [33_333, 700]], 99_999);

    $figures = InvoiceFigures::forInvoice(app(IssueInvoice::class)($installment), app(StudioSettingsResolver::class));

    $shares = array_map(fn (array $line) => $line['net'] + $line['vat'], $figures->rates);

    expect(array_sum($shares))->toBe(99_999)
        ->and($figures->rates[0]['rate'])->toBe(1900);
});

it('leaves the small-business case at one zero rate', function () {
    $settings = app(StudioSettingsResolver::class);
    $installment = mixedRateInstallment([[100_000, null], [10_000, 700]], 55_000, vatMode: 'small_business');

    $figures = InvoiceFigures::forInvoice(app(IssueInvoice::class)($installment), $settings);

    expect($figures->rate)->toBe(0)
        ->and($figures->vat)->toBe(0)
        ->and($figures->rates)->toBe([['rate' => 0, 'net' => 55_000, 'vat' => 0]]);
});

// --- The document: a row per rate ------------------------------------------

it('prints one Umsatzsteuer row per rate on the invoice', function () {
    $installment = mixedRateInstallment([[100_000, null], [10_000, 700]], 64_850);

    $invoice = app(IssueInvoice::class)($installment);

    $html = app(RenderInvoicePdf::class)->forInvoice($invoice->fresh());

    expect($html)->toContain('Umsatzsteuer 19 %')
        ->and($html)->toContain('Umsatzsteuer 7 %')
        ->and($html)->not->toContain('{{');
});

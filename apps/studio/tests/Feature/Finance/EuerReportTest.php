<?php

use App\Finance\Actions\ChangeFiscalYearVatMode;
use App\Finance\Models\Expense;
use App\Finance\Models\ExpenseCategory;
use App\Finance\Models\FinanceDocument;
use App\Finance\Models\FiscalYear;
use App\Finance\Models\FixedAsset;
use App\Finance\Support\EuerReport;
use App\Payments\Actions\IssueCreditNote;
use App\Payments\Actions\IssueInvoice;
use App\Payments\Actions\MarkInstallmentPaid;
use App\Payments\Enums\SettledVia;
use App\Payments\Models\PaymentInstallment;
use App\Settings\Actions\UpdateStudioSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function paidInvoice(string $issued, ?string $paidOn): PaymentInstallment
{
    $invoice = app(IssueInvoice::class)->positions(null, null, [['title' => 'Shooting', 'unit_price' => 63025, 'vat_rate' => 1900]], $issued, ['issued_on' => $issued]);
    $installment = $invoice->installments()->sole();

    if ($paidOn !== null) {
        app(MarkInstallmentPaid::class)($installment, SettledVia::Transfer, $paidOn, null);
    }

    return $installment->fresh();
}

function bookedExpense(string $key, array $attributes = []): Expense
{
    return Expense::factory()->create([
        'category_id' => ExpenseCategory::query()->where('key', $key)->value('id'),
        ...$attributes,
    ]);
}

/**
 * 2026: two rates of 750,00 paid (one invoiced in December 2025), a Gutschrift
 * of 119,00, a third invoice left open, hosting, a half-private phone, a laptop
 * and a flash set sold in June, plus a draft, a USD charge without euro amount
 * and an ignored expense.
 */
function euerFixture(): void
{
    Bus::fake();
    Storage::fake('local');
    app(UpdateStudioSetting::class)('tax_number', '22/123/45678', null);

    paidInvoice('2025-11-01', '2025-12-30');
    $boundary = paidInvoice('2025-12-20', '2026-01-05');
    $a = paidInvoice('2026-09-02', '2026-09-02');
    app(IssueCreditNote::class)($a->invoice, 11900, 'Nachlass', null, '2026-10-01');
    paidInvoice('2026-11-01', null);
    app(IssueCreditNote::class)($boundary->invoice, 5000, 'Nachlass', null, '2027-01-10');

    $receipt = FinanceDocument::factory()->create();
    bookedExpense('hosting_domains', ['paid_at' => '2026-03-04'])->documents()->attach($receipt);
    bookedExpense('sonstiges', ['paid_at' => '2026-04-01', 'net' => 5000, 'vat' => 950, 'gross' => 5950, 'gross_eur' => 5950, 'business_share' => 50])
        ->documents()->attach(FinanceDocument::factory()->create());

    $laptop = bookedExpense('buero', ['paid_at' => '2026-04-10', 'net' => 145000, 'vat' => 27550, 'gross' => 172550, 'gross_eur' => 172550]);
    $laptop->documents()->attach(FinanceDocument::factory()->create());
    $laptop->asset()->associate(FixedAsset::factory()->create(['acquired_at' => '2026-04-10', 'cost_net' => 145000, 'cost_gross' => 172550, 'useful_life_months' => 12]))->save();

    $flash = bookedExpense('fotozubehoer', ['paid_at' => '2025-03-01', 'net' => 90000, 'vat' => 17100, 'gross' => 107100, 'gross_eur' => 107100]);
    $flash->asset()->associate(FixedAsset::factory()->create([
        'acquired_at' => '2025-03-01', 'cost_net' => 90000, 'cost_gross' => 107100, 'useful_life_months' => 36,
        'disposed_at' => '2026-06-30', 'disposal_proceeds' => 30000,
    ]))->save();

    Expense::factory()->draft()->create(['paid_at' => '2026-05-05']);
    Expense::factory()->create(['status' => 'ignored', 'paid_at' => '2026-05-06']);
    bookedExpense('software_abos', ['paid_at' => '2026-06-01', 'currency' => 'USD', 'net' => 4900, 'vat_rate' => 0, 'vat' => 0, 'gross' => 4900, 'gross_eur' => null]);
}

it('computes the regular year from payments, credit notes, expenses and depreciation', function () {
    euerFixture();

    $report = app(EuerReport::class)->forYear(2026);

    expect($report->vatMode)->toBe('standard')
        ->and($report->operatingIncome)->toBe(116050)
        ->and($report->collectedVat)->toBe(22050)
        ->and($report->assetSales)->toBe(30000)
        ->and($report->income)->toBe(168100)
        ->and($report->categories)->toBe([
            ['key' => 'software_abos', 'label' => 'Software & Abos', 'euerLine' => 'sonstige_betriebsausgaben', 'amount' => 0, 'count' => 1],
            ['key' => 'hosting_domains', 'label' => 'Hosting & Domains', 'euerLine' => 'sonstige_betriebsausgaben', 'amount' => 10000, 'count' => 1],
            ['key' => 'sonstiges', 'label' => 'Sonstiges', 'euerLine' => 'sonstige_betriebsausgaben', 'amount' => 2500, 'count' => 1],
        ])
        ->and($report->depreciation)->toBe(123750)
        ->and($report->residualBookValue)->toBe(50000)
        ->and($report->paidInputVat)->toBe(29925)
        ->and($report->expenses)->toBe(216175)
        ->and($report->profit)->toBe(-48075);
});

it('computes the small business year in gross figures without VAT lines', function () {
    euerFixture();
    app(ChangeFiscalYearVatMode::class)(2026, 'small_business');

    $report = app(EuerReport::class)->forYear(2026);

    expect($report->operatingIncome)->toBe(138100)
        ->and($report->collectedVat)->toBe(0)
        ->and($report->income)->toBe(168100)
        ->and(array_column($report->categories, 'amount'))->toBe([0, 11900, 2975])
        ->and($report->depreciation)->toBe(144413)
        ->and($report->residualBookValue)->toBe(50000)
        ->and($report->paidInputVat)->toBe(0)
        ->and($report->profit)->toBe(168100 - (11900 + 2975 + 144413 + 50000));
});

it('lists what the year still needs in the checklist', function () {
    euerFixture();

    $checklist = app(EuerReport::class)->forYear(2026)->checklist;

    expect($checklist->toArray())->toBe([
        'withoutReceipt' => 1,
        'drafts' => 1,
        'openInvoices' => 1,
        'openInvoicesRemaining' => 75000,
        'foreignWithoutEur' => 1,
    ]);
});

it('counts a payment in the year it arrived and a Gutschrift in the year it was issued', function () {
    euerFixture();

    $report = app(EuerReport::class);

    expect($report->forYear(2025)->operatingIncome)->toBe(63025)
        ->and($report->forYear(2027)->operatingIncome)->toBe(-4202);
});

it('serves the snapshot of a locked year after a category is renamed', function () {
    euerFixture();
    $before = app(EuerReport::class)->forYear(2026)->toArray();
    FiscalYear::forYear(2026)->update(['snapshot' => ['report' => $before], 'locked_at' => now()]);

    ExpenseCategory::query()->where('key', 'hosting_domains')->update(['label' => 'Umbenannt']);
    Expense::query()->where('status', 'booked')->toBase()->update(['gross_eur' => 1]);

    $after = app(EuerReport::class)->forYear(2026)->toArray();
    expect(array_column($after['categories'], 'label', 'key')['hosting_domains'])->toBe('Hosting & Domains')
        ->and($after['expenses'])->toBe($before['expenses']);
});

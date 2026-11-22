<?php

use App\Finance\Enums\ExpenseStatus;
use App\Finance\Models\Expense;
use App\Finance\Models\ExpenseCategory;
use App\Finance\Models\FinanceDocument;
use App\Finance\Models\FiscalYear;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-22 10:00:00');
    $this->admin = staffMember();
    $this->category = ExpenseCategory::query()->where('key', 'hosting_domains')->firstOrFail();
});

function expensePayload(array $overrides = []): array
{
    return [
        'vendor' => 'Nordhost Hosting GmbH',
        'paid_at' => '2026-03-04',
        'invoice_date' => '2026-03-01',
        'net' => 10000,
        'vat_rate' => 1900,
        'vat' => 1900,
        'gross' => 11900,
        'currency' => 'EUR',
        'category_id' => ExpenseCategory::query()->where('key', 'hosting_domains')->value('public_id'),
        'business_share' => 100,
        ...$overrides,
    ];
}

it('saves a draft, sets the euro amount for euro and keeps it as entered for another currency', function () {
    $this->actingAs($this->admin, 'staff')->post('/finanzen/ausgaben', expensePayload(['category_id' => null, 'paid_at' => null]))->assertRedirect();
    $this->post('/finanzen/ausgaben', expensePayload([
        'currency' => 'usd', 'net' => 4900, 'vat_rate' => 0, 'vat' => 0, 'gross' => 4900, 'gross_eur' => 4512,
    ]))->assertRedirect();

    $eur = Expense::query()->where('currency', 'EUR')->sole();
    $usd = Expense::query()->where('currency', 'USD')->sole();

    expect($eur->status)->toBe(ExpenseStatus::Draft)
        ->and($eur->gross_eur)->toBe(11900)
        ->and($usd->gross_eur)->toBe(4512)
        ->and($usd->created_by)->toBe($this->admin->id);
});

it('refuses an expense whose net and VAT do not add up to the gross', function () {
    $this->actingAs($this->admin, 'staff')->post('/finanzen/ausgaben', expensePayload(['gross' => 12000]))
        ->assertSessionHasErrors(['gross' => __('finance.grossMismatch')]);

    expect(Expense::query()->count())->toBe(0);
});

it('books without a receipt and refuses to book without a vendor, payment date or category', function () {
    $this->actingAs($this->admin, 'staff')->post('/finanzen/ausgaben', expensePayload(['book' => true]))->assertRedirect();

    expect(Expense::query()->sole()->status)->toBe(ExpenseStatus::Booked);

    $this->post('/finanzen/ausgaben', expensePayload(['book' => true, 'vendor' => '', 'paid_at' => null, 'category_id' => null]))
        ->assertSessionHasErrors(['vendor', 'paid_at', 'category_id']);

    $draft = Expense::factory()->draft()->create(['vendor' => null, 'paid_at' => null, 'category_id' => null]);
    $this->post("/finanzen/ausgaben/{$draft->public_id}/status", ['status' => 'booked'])
        ->assertSessionHasErrors(['vendor', 'paid_at', 'category_id']);
});

it('moves an expense between statuses in an open year and deletes only drafts and ignored ones', function () {
    $expense = Expense::factory()->create();

    $this->actingAs($this->admin, 'staff');
    $this->delete("/finanzen/ausgaben/{$expense->public_id}")->assertSessionHasErrors('status');

    $this->post("/finanzen/ausgaben/{$expense->public_id}/status", ['status' => 'ignored'])->assertRedirect();
    expect($expense->fresh()->status)->toBe(ExpenseStatus::Ignored);

    $this->delete("/finanzen/ausgaben/{$expense->public_id}")->assertRedirect('/finanzen/ausgaben');
    expect(Expense::query()->count())->toBe(0);
});

it('refuses every write dated in a locked year', function () {
    $expense = Expense::factory()->create(['paid_at' => '2025-05-05']);
    FiscalYear::factory()->locked()->create(['year' => 2025]);

    $this->actingAs($this->admin, 'staff');
    $this->post('/finanzen/ausgaben', expensePayload(['paid_at' => '2025-06-01']))->assertSessionHasErrors('paid_at');
    $this->put("/finanzen/ausgaben/{$expense->public_id}", expensePayload(['paid_at' => '2025-05-05', 'note' => 'x']))->assertSessionHasErrors('paid_at');
    $this->post("/finanzen/ausgaben/{$expense->public_id}/status", ['status' => 'ignored'])->assertSessionHasErrors('paid_at');
    $this->put("/finanzen/ausgaben/{$expense->public_id}", expensePayload(['paid_at' => '2025-05-05', 'document_ids' => [FinanceDocument::factory()->create()->public_id]]))
        ->assertSessionHasErrors('paid_at');

    expect(props("/finanzen/ausgaben/{$expense->public_id}")['locked'])->toBeTrue();
});

it('lists a year with drafts first and totals over the whole year, whatever the filter', function () {
    $category = $this->category;
    Expense::factory()->create(['vendor' => 'A', 'paid_at' => '2026-02-01', 'gross' => 11900, 'gross_eur' => 11900, 'category_id' => $category->id]);
    Expense::factory()->create(['vendor' => 'B', 'paid_at' => '2026-04-01', 'gross' => 5950, 'net' => 5000, 'vat' => 950, 'gross_eur' => 5950, 'business_share' => 50]);
    Expense::factory()->draft()->create(['vendor' => 'C', 'paid_at' => null, 'invoice_date' => '2026-05-01']);
    Expense::factory()->create(['vendor' => 'D', 'paid_at' => '2025-12-31']);
    $withReceipt = Expense::query()->where('vendor', 'A')->sole();
    $withReceipt->documents()->attach(FinanceDocument::factory()->create());

    $this->actingAs($this->admin, 'staff');
    $page = props('/finanzen/ausgaben');

    expect(array_column($page['rows'], 'vendor'))->toBe(['C', 'B', 'A'])
        ->and($page['totals'])->toBe(['sum' => 17850, 'business' => 14875, 'drafts' => 1]);

    $partial = props('/finanzen/ausgaben?anteil=anteilig&jahr=2026');
    expect(array_column($partial['rows'], 'vendor'))->toBe(['B'])
        ->and($partial['totals']['sum'])->toBe(17850);

    expect(array_column(props('/finanzen/ausgaben?ohne-beleg=1')['rows'], 'vendor'))->toBe(['C', 'B'])
        ->and(array_column(props('/finanzen/ausgaben?kategorie=hosting_domains')['rows'], 'vendor'))->toBe(['A'])
        ->and(array_column(props('/finanzen/ausgaben?status=draft')['rows'], 'vendor'))->toBe(['C'])
        ->and(array_column(props('/finanzen/ausgaben?jahr=2025')['rows'], 'vendor'))->toBe(['D']);
});

it('never exposes an integer id on the expense pages', function () {
    $expense = Expense::factory()->create();
    $expense->documents()->attach(FinanceDocument::factory()->create());

    $this->actingAs($this->admin, 'staff');

    foreach (['/finanzen/ausgaben', '/finanzen/ausgaben/neu', "/finanzen/ausgaben/{$expense->public_id}"] as $path) {
        expect(integerIdPaths(props($path)))->toBe([]);
    }
});

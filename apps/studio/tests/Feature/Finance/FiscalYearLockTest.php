<?php

use App\Finance\Actions\ChangeFiscalYearVatMode;
use App\Finance\Actions\LockFiscalYear;
use App\Finance\Actions\SaveExpense;
use App\Finance\Jobs\RenderEuerPdfJob;
use App\Finance\Models\Expense;
use App\Finance\Models\FinanceDocument;
use App\Finance\Models\FiscalYear;
use App\Finance\Models\FixedAsset;
use App\Support\GotenbergClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-22 10:00:00');
    Storage::fake('s3');
    $this->admin = staffMember();
});

it('refuses the current year, a future year and a year with open drafts', function () {
    Expense::factory()->draft()->create(['paid_at' => '2025-02-01']);

    $this->actingAs($this->admin, 'staff');
    $this->post('/finanzen/euer/2026/sperren')->assertSessionHasErrors(['year' => __('finance.lockYearOpen')]);
    $this->post('/finanzen/euer/2027/sperren')->assertSessionHasErrors(['year' => __('finance.lockYearOpen')]);
    $this->post('/finanzen/euer/2025/sperren')->assertSessionHasErrors(['year' => __('finance.lockOpenDrafts', ['count' => 1])]);

    expect(FiscalYear::query()->whereNotNull('locked_at')->count())->toBe(0);
});

it('stores the snapshot, who locked and queues the PDF, once', function () {
    Bus::fake();
    Expense::factory()->create(['paid_at' => '2025-02-01']);

    $this->actingAs($this->admin, 'staff')->post('/finanzen/euer/2025/sperren')->assertSessionHasNoErrors();

    $year = FiscalYear::query()->where('year', 2025)->sole();
    expect($year->locked_at)->not->toBeNull()
        ->and($year->locked_by)->toBe($this->admin->id)
        ->and($year->snapshot['report']['year'])->toBe(2025)
        ->and($year->snapshot['expenses'])->toHaveCount(1)
        ->and($year->snapshot['assets'])->toBe([]);
    Bus::assertDispatched(RenderEuerPdfJob::class);

    $this->post('/finanzen/euer/2025/sperren')->assertSessionHasErrors(['year' => __('finance.yearLocked', ['year' => 2025])]);
});

it('keeps the lock when the PDF renderer is down', function () {
    $this->mock(GotenbergClient::class)->shouldReceive('htmlToPdf')->andThrow(new RuntimeException('down'));
    Expense::factory()->create(['paid_at' => '2025-02-01']);

    app(LockFiscalYear::class)(2025, $this->admin);

    expect(FiscalYear::query()->where('year', 2025)->sole()->pdf_path)->toBeNull()
        ->and(FiscalYear::query()->where('year', 2025)->sole()->isLocked())->toBeTrue();
});

it('refuses writes dated in the locked year but not in the others', function () {
    Bus::fake();
    $old = FixedAsset::factory()->create(['acquired_at' => '2025-03-10']);
    $other = Expense::factory()->create(['paid_at' => '2026-02-01']);
    app(LockFiscalYear::class)(2025, $this->admin);

    expect(fn () => Expense::factory()->create(['paid_at' => '2025-12-31']))->toThrow(ValidationException::class)
        ->and(fn () => FixedAsset::factory()->create(['acquired_at' => '2025-01-01']))->toThrow(ValidationException::class);

    $other->update(['note' => 'ok']);
    $old->update(['disposed_at' => '2026-05-05', 'disposal_proceeds' => 1000]);

    expect($other->fresh()->note)->toBe('ok')
        ->and($old->fresh()->disposed_at->toDateString())->toBe('2026-05-05');
});

it('changes the VAT mode only while the year is open', function () {
    Bus::fake();

    $this->actingAs($this->admin, 'staff')->patch('/finanzen/euer/2025/ust', ['vat_mode' => 'small_business'])->assertSessionHasNoErrors();
    expect(FiscalYear::query()->where('year', 2025)->value('vat_mode'))->toBe('small_business');

    app(LockFiscalYear::class)(2025, $this->admin);

    expect(fn () => app(ChangeFiscalYearVatMode::class)(2025, 'standard'))->toThrow(ValidationException::class);
});

it('refuses a receipt link in a locked year even when nothing else on the expense changes', function () {
    Bus::fake();
    $expense = Expense::factory()->create(['paid_at' => '2025-05-05']);
    $expense->documents()->attach(FinanceDocument::factory()->create());
    app(LockFiscalYear::class)(2025, $this->admin);

    $stored = [
        ...$expense->only(['vendor', 'description', 'invoice_no', 'net', 'vat_rate', 'vat', 'gross', 'currency', 'gross_eur', 'business_share', 'note']),
        'invoice_date' => $expense->invoice_date->format('Y-m-d'),
        'paid_at' => '2025-05-05',
        'category_id' => $expense->category->public_id,
    ];
    $other = FinanceDocument::factory()->create();

    expect(fn () => app(SaveExpense::class)(SaveExpense::validate([...$stored, 'document_ids' => [$other->public_id]], true), $expense->fresh()))
        ->toThrow(ValidationException::class, __('finance.yearLocked', ['year' => 2025]));
    expect($expense->documents()->count())->toBe(1)
        ->and($expense->documents()->whereKey($other->id)->exists())->toBeFalse();
});

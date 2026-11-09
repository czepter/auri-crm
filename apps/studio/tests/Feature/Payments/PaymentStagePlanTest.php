<?php

use App\Crm\Models\ClientCase;
use App\Payments\Actions\CreatePaymentPlan;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('local'));

function bookedProjectWithStages(int $total): ClientCase
{
    $project = bookedContactProject();

    $quote = Quote::factory()->create([
        'case_id' => $project->id,
        'status' => QuoteStatus::Accepted,
        'accepted_at' => now(),
        'total' => $total,
    ]);

    $quote->paymentStages()->createMany([
        ['percent' => 25, 'label' => 'Bei der Zusage', 'minimum' => 80_000, 'sort_order' => 0],
        ['percent' => 50, 'label' => 'Vier Wochen vorher', 'minimum' => 0, 'sort_order' => 1],
        ['percent' => 25, 'label' => 'Nach der Lieferung', 'minimum' => 0, 'sort_order' => 2],
    ]);

    return $project;
}

it('materialises the installments from the accepted quote’s stages', function () {
    $project = bookedProjectWithStages(215_000);

    $plan = app(CreatePaymentPlan::class)($project, 215_000, [
        now()->toDateString(),
        now()->addMonth()->toDateString(),
        now()->addMonths(2)->toDateString(),
    ]);

    $installments = $plan->installments()->orderBy('sort_order')->get();

    expect($installments->pluck('amount')->all())->toBe([80_000, 107_500, 27_500])
        ->and($installments->pluck('title')->all())
        ->toBe(['Bei der Zusage', 'Vier Wochen vorher', 'Nach der Lieferung'])
        ->and($installments->sum('amount'))->toBe(215_000);
});

// Der bestehende Weg: ohne Stufen bleibt es die gleichmaessige Teilung.
it('splits evenly where the project has no stages', function () {
    $project = bookedProjectWithStages(120_000);
    Quote::query()->sole()->paymentStages()->delete();

    $plan = app(CreatePaymentPlan::class)($project, 120_000, [
        now()->toDateString(),
        now()->addMonth()->toDateString(),
    ]);

    expect($plan->installments()->pluck('amount')->all())->toBe([60_000, 60_000])
        ->and($plan->installments()->pluck('title')->filter()->all())->toBe([]);
});

it('takes a stage’s own date and note over to the installment', function () {
    $project = bookedProjectWithStages(215_000);
    Quote::query()->sole()->paymentStages()->orderBy('sort_order')->first()
        ->update(['note' => 'wird mit Schlussrechnung verrechnet', 'due_on' => '2026-11-27']);

    $plan = app(CreatePaymentPlan::class)($project, 215_000, [
        '2026-12-01',
        '2027-01-01',
        '2027-02-01',
    ]);

    $installments = $plan->installments()->orderBy('sort_order')->get();

    expect($installments->first()->note)->toBe('wird mit Schlussrechnung verrechnet')
        ->and($installments->pluck('due_on')->map->toDateString()->all())->toBe(['2026-11-27', '2027-01-01', '2027-02-01'])
        ->and($installments->last()->note)->toBeNull();
});

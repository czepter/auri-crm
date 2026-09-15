<?php

use App\Crm\Models\Job;
use App\Payments\Actions\CreatePaymentPlan;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('local'));

function bookedJobWithStages(int $total): Job
{
    $project = bookedContactProject();

    $quote = Quote::factory()->create([
        'project_id' => $project->id,
        'status' => QuoteStatus::Accepted,
        'accepted_at' => now(),
        'total' => $total,
    ]);

    $quote->paymentStages()->createMany([
        ['percent' => 25, 'label' => 'Bei der Zusage', 'minimum' => 80_000, 'sort_order' => 0],
        ['percent' => 50, 'label' => 'Vier Wochen vorher', 'minimum' => 0, 'sort_order' => 1],
        ['percent' => 25, 'label' => 'Nach der Lieferung', 'minimum' => 0, 'sort_order' => 2],
    ]);

    return $project->job()->sole();
}

it('materialises the installments from the accepted quote’s stages', function () {
    $job = bookedJobWithStages(215_000);

    $plan = app(CreatePaymentPlan::class)($job, 215_000, [
        now()->toDateString(),
        now()->addMonth()->toDateString(),
        now()->addMonths(2)->toDateString(),
    ]);

    $installments = $plan->installments()->orderBy('sort_order')->get();

    expect($installments->pluck('amount')->all())->toBe([80_000, 108_000, 27_000])
        ->and($installments->pluck('title')->all())
        ->toBe(['Bei der Zusage', 'Vier Wochen vorher', 'Nach der Lieferung'])
        ->and($installments->sum('amount'))->toBe(215_000);
});

// Der bestehende Weg: ohne Stufen bleibt es die gleichmaessige Teilung.
it('splits evenly where the project has no stages', function () {
    $job = bookedJobWithStages(120_000);
    Quote::query()->update(['status' => QuoteStatus::Sent]);

    $plan = app(CreatePaymentPlan::class)($job, 120_000, [
        now()->toDateString(),
        now()->addMonth()->toDateString(),
    ]);

    expect($plan->installments()->pluck('amount')->all())->toBe([60_000, 60_000])
        ->and($plan->installments()->pluck('title')->filter()->all())->toBe([]);
});

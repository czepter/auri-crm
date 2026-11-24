<?php

use App\Crm\Actions\BookCase;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Shoot;
use App\Models\User;
use App\Payments\Models\PaymentPlan;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function acceptedQuote(ClientCase $project, bool $withStages = true): Quote
{
    $quote = Quote::factory()->for($project, 'case')->create([
        'status' => QuoteStatus::Accepted,
        'accepted_at' => now(),
        'net' => 100_000,
        ...Quote::computeVat(100_000),
    ]);

    if ($withStages) {
        $quote->paymentStages()->create(['percent' => 30, 'label' => 'Anzahlung', 'sort_order' => 0]);
        $quote->paymentStages()->create(['percent' => 70, 'label' => 'Restzahlung', 'sort_order' => 1]);
    }

    return $quote;
}

it('creates the plan from the accepted quote when the project is booked', function () {
    $project = ClientCase::factory()->create();
    Shoot::factory()->for($project, 'case')->primary()->on('2027-06-12')->create();
    $quote = acceptedQuote($project);

    app(BookCase::class)($project, User::factory()->create());

    $rows = $project->paymentPlan->installments()->orderBy('sort_order')->get();

    expect($project->paymentPlan->total)->toBe($quote->gross())
        ->and($rows->pluck('title')->all())->toBe(['Anzahlung', 'Restzahlung'])
        ->and($rows->first()->due_on->toDateString())->toBe(today()->toDateString())
        ->and($rows->last()->due_on->toDateString())->toBe('2027-06-12');
});

it('leaves the project alone without stages, without a quote or with a plan', function (string $case) {
    $project = ClientCase::factory()->create();

    match ($case) {
        'no quote' => null,
        'no stages' => acceptedQuote($project, withStages: false),
        'has plan' => (function () use ($project) {
            acceptedQuote($project);
            PaymentPlan::create(['case_id' => $project->id, 'total' => 0]);
        })(),
    };

    app(BookCase::class)($project, User::factory()->create());

    expect($project->paymentPlan()->count())->toBe($case === 'has plan' ? 1 : 0)
        ->and($project->paymentPlan?->installments()->count() ?? 0)->toBe(0);
})->with(['no quote', 'no stages', 'has plan']);

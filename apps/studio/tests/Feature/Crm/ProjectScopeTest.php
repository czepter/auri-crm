<?php

use App\Crm\Actions\ReadProjectScreen;
use App\Crm\Models\ClientCase;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function scopeOf(ClientCase $project): array
{
    return app(ReadProjectScreen::class)->scope($project->fresh());
}

function quoteWith(ClientCase $project, QuoteStatus $status, array $items, int $total = 0): Quote
{
    $quote = Quote::factory()->for($project, 'case')->create(['status' => $status, 'total' => $total]);

    foreach ($items as $title => $price) {
        QuoteItem::factory()->for($quote)->create(['title' => $title, 'unit_price' => $price]);
    }

    return $quote;
}

it('has nothing to show while no quote exists', function () {
    expect(scopeOf(ClientCase::factory()->create()))
        ->toBe(['items' => [], 'delta' => 0, 'hasDraft' => false]);
});

// Without a draft, what stands is what was ordered — no line may read as
// changed against itself.
it('marks nothing while only the accepted quote exists', function () {
    $project = ClientCase::factory()->create();
    quoteWith($project, QuoteStatus::Accepted, ['Ganztag' => 440000], 440000);

    $scope = scopeOf($project);

    expect($scope['hasDraft'])->toBeFalse()
        ->and($scope['delta'])->toBe(0)
        ->and(collect($scope['items'])->pluck('state')->all())->toBe([null]);
});

it('names a raised line changed and keeps the old price under it', function () {
    $project = ClientCase::factory()->create();
    quoteWith($project, QuoteStatus::Accepted, ['Ganztag' => 440000], 440000);
    quoteWith($project, QuoteStatus::Draft, ['Ganztag' => 490000], 490000);

    $scope = scopeOf($project);

    expect($scope['items'][0]['state'])->toBe('changed')
        ->and($scope['items'][0]['wasUnitPrice'])->toBe(440000)
        ->and($scope['delta'])->toBe(50000)
        ->and($scope['hasDraft'])->toBeTrue();
});

it('names a line the accepted quote never had new', function () {
    $project = ClientCase::factory()->create();
    quoteWith($project, QuoteStatus::Accepted, ['Ganztag' => 440000], 440000);
    quoteWith($project, QuoteStatus::Draft, ['Ganztag' => 440000, 'Zweitfotograf' => 90000], 530000);

    $scope = scopeOf($project);

    expect(collect($scope['items'])->pluck('title', 'state')->all())
        ->toBe([null => 'Ganztag', 'new' => 'Zweitfotograf'])
        ->and($scope['delta'])->toBe(90000);
});

// A draft with nothing accepted before it is the first order, not a revision:
// every line is what stands, and there is no difference to state.
it('states no difference for a draft with nothing accepted before it', function () {
    $project = ClientCase::factory()->create();
    quoteWith($project, QuoteStatus::Draft, ['Ganztag' => 440000], 440000);

    $scope = scopeOf($project);

    expect($scope['delta'])->toBe(0)
        ->and($scope['items'][0]['state'])->toBe('new');
});

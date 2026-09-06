<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Quotes\Actions\AcceptQuote;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Quotes\Support\QuoteRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function sliderQuote(): Quote
{
    $project = Project::factory()->create(['portal_visible' => true]);
    $project->attachContact(Contact::factory()->create(), ProjectContactRole::Primary);

    $quote = Quote::factory()->slider()->create([
        'project_id' => $project->id,
        'status' => QuoteStatus::Sent,
    ]);

    $quote->items()->createMany([
        ['title' => 'Reportage', 'quantity' => 1, 'unit_price' => null, 'sort_order' => 0],
        ['title' => 'Anfahrt', 'quantity' => 1, 'unit_price' => 15_000, 'sort_order' => 1],
        ['title' => 'Album', 'quantity' => 1, 'unit_price' => 40_000, 'sort_order' => 2, 'selectable' => true, 'selected' => true],
        ['title' => 'Analog', 'quantity' => 1, 'unit_price' => 25_000, 'sort_order' => 3, 'selectable' => true],
    ]);

    return $quote->fresh(['items', 'project']);
}

it('spans from the fewest hours without blocks to the most with all of them', function () {
    $quote = sliderQuote();

    // 4 h * 250 € + 150 € Anfahrt; 10 h * 250 € + 150 € + 400 € + 250 €.
    expect(QuoteRange::min($quote))->toBe(115_000)
        ->and(QuoteRange::max($quote))->toBe(330_000)
        ->and(QuoteRange::preselected($quote))->toBe(155_000);
});

it('writes hours, the chosen blocks and the total on acceptance', function () {
    Notification::fake();
    staffMember();

    $quote = sliderQuote();
    $album = $quote->items->firstWhere('title', 'Album');

    $accepted = app(AcceptQuote::class)(
        $quote,
        $quote->project->primaryContact(),
        '127.0.0.1',
        hours: 6,
        selectedItemIds: [$album->public_id],
    );

    // 6 h * 250 € + 150 € Anfahrt + 400 € Album.
    expect($accepted->hours)->toBe(6)
        ->and($accepted->total)->toBe(205_000)
        ->and($accepted->status)->toBe(QuoteStatus::Accepted)
        ->and($album->fresh()->selected)->toBeTrue()
        ->and($quote->items->firstWhere('title', 'Analog')->fresh()->selected)->toBeFalse();
});

it('refuses hours outside the range', function () {
    Notification::fake();
    $quote = sliderQuote();

    app(AcceptQuote::class)($quote, $quote->project->primaryContact(), '127.0.0.1', hours: 12);
})->throws(ValidationException::class);

it('refuses a line the studio never made switchable', function () {
    Notification::fake();
    $quote = sliderQuote();
    $fixed = $quote->items->firstWhere('title', 'Anfahrt');

    app(AcceptQuote::class)(
        $quote,
        $quote->project->primaryContact(),
        '127.0.0.1',
        hours: 5,
        selectedItemIds: [$fixed->public_id],
    );
})->throws(ValidationException::class);

// Die Weiche: ohne Stundensatz aendert sich nichts.
it('leaves a classic quote untouched', function () {
    Notification::fake();
    staffMember();

    $project = Project::factory()->create(['portal_visible' => true]);
    $project->attachContact(Contact::factory()->create(), ProjectContactRole::Primary);

    $quote = Quote::factory()->create([
        'project_id' => $project->id,
        'status' => QuoteStatus::Sent,
        'total' => 120_000,
    ]);

    $accepted = app(AcceptQuote::class)($quote, $project->primaryContact(), '127.0.0.1');

    expect($accepted->total)->toBe(120_000)
        ->and($accepted->hours)->toBeNull()
        ->and($quote->hasSlider())->toBeFalse();
});

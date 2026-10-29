<?php

use App\Catalog\Models\Package;
use App\Quotes\Actions\SeedQuoteFromPackage;
use App\Quotes\Data\QuoteItemData;
use App\Quotes\Enums\PricingMode;
use App\Quotes\Models\Quote;
use App\Quotes\Support\QuoteRange;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('legt ein Angebot klassisch an', function () {
    $quote = Quote::factory()->create();

    expect($quote->pricing_mode)->toBe(PricingMode::Classic);
});

it('nimmt den Modus vom Formular entgegen', function () {
    [$user, $project] = studioWithProject();

    $quote = Quote::factory()->for($project, 'case')->create();

    $this->actingAs($user, 'staff')
        ->patch("/angebote/{$quote->public_id}", [
            'pricing_mode' => 'choice',
            'hourly_rate' => 25000,
            'min_hours' => 3,
            'max_hours' => 12,
            'hours' => 5,
            'items' => [],
            'stages' => [],
        ])
        ->assertRedirect();

    expect($quote->fresh()->pricing_mode)->toBe(PricingMode::Choice);
});

it('speichert Menge und Rabatt auch im Auswahlmodus', function () {
    [$user, $project] = studioWithProject();
    $quote = Quote::factory()->for($project, 'case')->create();

    $this->actingAs($user, 'staff')->patch("/angebote/{$quote->public_id}", [
        'pricing_mode' => 'choice',
        'hourly_rate' => 25000,
        'min_hours' => 3,
        'max_hours' => 12,
        'hours' => 5,
        'items' => [[
            'title' => 'Fine-Art-Album',
            'quantity' => 2,
            'unit_price' => 69000,
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'selectable' => true,
            'selected' => false,
        ]],
        'stages' => [
            ['percent' => 30, 'label' => 'Bei der Zusage', 'minimum' => 50000],
            ['percent' => 70, 'label' => 'Am Termin', 'minimum' => 0],
        ],
    ])->assertRedirect();

    $item = $quote->fresh()->items()->first();

    // 2 x 690 € abzueglich 0,1 % Zeilenrabatt, plus 5 h x 250 €.
    $fresh = $quote->fresh('items');

    expect($item->quantity)->toBe(2)
        ->and($item->discount_value)->toBe(10)
        ->and($item->discountedLineTotal())->toBe(137_862)
        // Die Zeilensumme, die Adminansicht und Portal drucken.
        ->and(QuoteItemData::fromModel($item)->netTotal)->toBe(137_862)
        ->and(QuoteRange::preselected($fresh))->toBe(125_000)
        ->and(QuoteRange::max($fresh))->toBe(437_862)
        ->and($quote->fresh()->paymentStages()->count())->toBe(2);
});

it('speichert ein Auswahlangebot, bevor ein Stundensatz steht', function () {
    [$user, $project] = studioWithProject();
    $quote = Quote::factory()->for($project, 'case')->create();

    $this->actingAs($user, 'staff')->patch("/angebote/{$quote->public_id}", [
        'pricing_mode' => 'choice',
        'hourly_rate' => null,
        'min_hours' => null,
        'max_hours' => null,
        'hours' => null,
        'items' => [['title' => 'Fotobox buchen', 'quantity' => 1, 'unit_price' => null]],
        'stages' => [],
    ])->assertSessionHasNoErrors();

    expect($quote->fresh()->pricing_mode)->toBe(PricingMode::Choice)
        ->and($quote->fresh()->items()->pluck('title')->all())->toBe(['Fotobox buchen']);
});

it('raeumt die Reglerspalten beim Umschalten auf klassisch', function () {
    [$user, $project] = studioWithProject();
    $quote = Quote::factory()->slider()->create(['case_id' => $project->id]);

    $this->actingAs($user, 'staff')->patch("/angebote/{$quote->public_id}", [
        'pricing_mode' => 'classic',
        'items' => [['title' => 'Shooting', 'quantity' => 1, 'unit_price' => 90000]],
        'stages' => [],
    ])->assertRedirect();

    $quote->refresh();

    expect($quote->hourly_rate)->toBeNull()
        ->and($quote->min_hours)->toBeNull()
        ->and($quote->max_hours)->toBeNull()
        ->and($quote->images_per_hour)->toBeNull()
        ->and($quote->hours)->toBeNull()
        ->and($quote->hasSlider())->toBeFalse()
        ->and($quote->total)->toBe(90000);
});

it('rechnet optionale Positionen im klassischen Angebot nicht mit', function () {
    [$user, $project] = studioWithProject();
    $quote = Quote::factory()->for($project, 'case')->create();

    $this->actingAs($user, 'staff')->patch("/angebote/{$quote->public_id}", [
        'pricing_mode' => 'classic',
        'items' => [
            ['title' => 'Begleitung', 'quantity' => 1, 'unit_price' => 43000],
            ['title' => 'Album', 'quantity' => 1, 'unit_price' => 39000, 'selectable' => true],
        ],
        'stages' => [],
    ])->assertRedirect();

    $quote->refresh();

    expect($quote->total)->toBe(43000)
        ->and($quote->net)->toBe(43000)
        ->and($quote->items->last()->label())->toBe('Optional: Album');
});

it('uebernimmt den Modus aus dem Paket', function () {
    $withRate = Package::factory()->create(['hourly_rate' => 25000, 'min_hours' => 4, 'max_hours' => 10]);
    $withoutRate = Package::factory()->create(['hourly_rate' => null]);

    $choice = Quote::factory()->create();
    app(SeedQuoteFromPackage::class)($choice, $withRate);

    $classic = Quote::factory()->create();
    app(SeedQuoteFromPackage::class)($classic, $withoutRate);

    expect($choice->fresh()->pricing_mode)->toBe(PricingMode::Choice)
        ->and($classic->fresh()->pricing_mode)->toBe(PricingMode::Classic);
});

it('rechnet die Spanne mit dem Zeilenrabatt', function () {
    $quote = Quote::factory()->slider()->create(['hourly_rate' => 0, 'min_hours' => 1, 'max_hours' => 1, 'hours' => 1]);

    $quote->items()->createMany([
        ['title' => 'Anfahrt', 'quantity' => 1, 'unit_price' => 50000, 'discount_type' => 'percentage', 'discount_value' => 1000, 'sort_order' => 0],
        ['title' => 'Album', 'quantity' => 2, 'unit_price' => 50000, 'discount_type' => 'amount', 'discount_value' => 10000, 'sort_order' => 1, 'selectable' => true],
    ]);

    $quote = $quote->fresh('items');

    expect(QuoteRange::min($quote))->toBe(45000)
        ->and(QuoteRange::max($quote))->toBe(135000);
});

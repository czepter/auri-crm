<?php

use App\Quotes\Actions\RenderQuotePdf;
use App\Quotes\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('weist eine Vorauswahl ausserhalb der Spanne zurueck', function () {
    [$user, $project] = studioWithProject();
    $quote = Quote::factory()->for($project, 'case')->create();

    $this->actingAs($user, 'staff')
        ->patch("/angebote/{$quote->public_id}", [
            'pricing_mode' => 'choice',
            'hourly_rate' => 25000,
            'min_hours' => 3,
            'max_hours' => 12,
            'hours' => 20,
            'items' => [],
            'stages' => [],
        ])
        ->assertSessionHasErrors('hours');
});

it('weist max_hours unter min_hours zurueck', function () {
    [$user, $project] = studioWithProject();
    $quote = Quote::factory()->for($project, 'case')->create();

    $this->actingAs($user, 'staff')
        ->patch("/angebote/{$quote->public_id}", [
            'pricing_mode' => 'choice',
            'hourly_rate' => 25000,
            'min_hours' => 8,
            'max_hours' => 4,
            'items' => [],
            'stages' => [],
        ])
        ->assertSessionHasErrors('max_hours');
});

it('schreibt Titel und Stunden ins PDF', function () {
    [, $project] = studioWithProject();

    $quote = Quote::factory()->for($project, 'case')->create([
        'pricing_mode' => 'choice',
        'hourly_rate' => 25000,
        'min_hours' => 3,
        'max_hours' => 12,
        'hours' => 8,
        'images_per_hour' => 40,
        'time_title' => 'Hochzeitsreportage',
        'time_description' => 'Trauung, Empfang, Paarshooting',
    ]);

    $html = app(RenderQuotePdf::class)->html($quote);

    expect($html)->toContain('Hochzeitsreportage')
        ->and($html)->toContain('8 Stunden')
        ->and($html)->toContain('ca. 320');
});

it('speichert Titel und Beschreibung der Zeitzeile', function () {
    [$user, $project] = studioWithProject();
    $quote = Quote::factory()->for($project, 'case')->create();

    $this->actingAs($user, 'staff')->patch("/angebote/{$quote->public_id}", [
        'pricing_mode' => 'choice',
        'hourly_rate' => 25000,
        'min_hours' => 3,
        'max_hours' => 12,
        'hours' => 5,
        'time_title' => 'Hochzeitsreportage',
        'time_description' => 'Trauung, Empfang, Paarshooting',
        'items' => [],
        'stages' => [],
    ])->assertRedirect();

    expect($quote->fresh()->time_title)->toBe('Hochzeitsreportage')
        ->and($quote->fresh()->time_description)->toBe('Trauung, Empfang, Paarshooting');
});

it('druckt die Zeitzeile an ihrer Stelle zwischen den Positionen', function () {
    [, $project] = studioWithProject();

    $quote = Quote::factory()->for($project, 'case')->create([
        'pricing_mode' => 'choice',
        'hourly_rate' => 25000,
        'min_hours' => 3,
        'max_hours' => 12,
        'hours' => 8,
        'time_title' => 'Hochzeitsreportage',
        'time_sort_order' => 1,
    ]);

    $quote->items()->createMany([
        ['title' => 'Anfahrt', 'quantity' => 1, 'unit_price' => 5000, 'sort_order' => 0],
        ['title' => 'Fine-Art-Album', 'quantity' => 1, 'unit_price' => 69000, 'sort_order' => 1],
    ]);

    $html = app(RenderQuotePdf::class)->html($quote->fresh());

    expect(strpos($html, 'Anfahrt'))->toBeLessThan(strpos($html, 'Hochzeitsreportage'))
        ->and(strpos($html, 'Hochzeitsreportage'))->toBeLessThan(strpos($html, 'Fine-Art-Album'));
});

<?php

use App\Crm\Models\Project;
use App\Models\User;
use App\Quotes\Enums\PricingMode;
use App\Quotes\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('legt ein Angebot klassisch an', function () {
    $quote = Quote::factory()->create();

    expect($quote->pricing_mode)->toBe(PricingMode::Classic);
});

it('nimmt den Modus vom Formular entgegen', function () {
    $user = User::factory()->withMfa()->create();
    $project = Project::factory()->create();

    $quote = Quote::factory()->for($project)->create();

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
    $user = User::factory()->withMfa()->create();
    $project = Project::factory()->create();
    $quote = Quote::factory()->for($project)->create();

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

    expect($item->quantity)->toBe(2)
        ->and($item->discount_value)->toBe(10)
        ->and($quote->fresh()->paymentStages()->count())->toBe(2);
});

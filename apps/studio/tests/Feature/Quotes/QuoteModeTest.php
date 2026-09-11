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

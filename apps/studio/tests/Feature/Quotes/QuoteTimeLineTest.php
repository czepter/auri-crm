<?php

use App\Crm\Models\Project;
use App\Models\User;
use App\Quotes\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function studioWithProjectForTimeLine(): array
{
    $user = User::factory()->withMfa()->create();
    $project = Project::factory()->create();

    return [$user, $project];
}

it('weist eine Vorauswahl ausserhalb der Spanne zurueck', function () {
    [$user, $project] = studioWithProjectForTimeLine();
    $quote = Quote::factory()->for($project)->create();

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
    [$user, $project] = studioWithProjectForTimeLine();
    $quote = Quote::factory()->for($project)->create();

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
    [, $project] = studioWithProjectForTimeLine();

    $quote = Quote::factory()->for($project)->create([
        'pricing_mode' => 'choice',
        'hourly_rate' => 25000,
        'min_hours' => 3,
        'max_hours' => 12,
        'hours' => 8,
        'images_per_hour' => 40,
        'time_title' => 'Hochzeitsreportage',
        'time_description' => 'Trauung, Empfang, Paarshooting',
    ]);

    $html = app(App\Quotes\Actions\RenderQuotePdf::class)->html($quote);

    expect($html)->toContain('Hochzeitsreportage')
        ->and($html)->toContain('8 Stunden')
        ->and($html)->toContain('ca. 320');
});

it('speichert Titel und Beschreibung der Zeitzeile', function () {
    [$user, $project] = studioWithProjectForTimeLine();
    $quote = Quote::factory()->for($project)->create();

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

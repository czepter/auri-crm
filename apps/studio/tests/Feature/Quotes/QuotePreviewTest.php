<?php

use App\Crm\Models\Project;
use App\Models\User;
use App\Quotes\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function studioWithProjectForPreview(): array
{
    $user = User::factory()->withMfa()->create();
    $project = Project::factory()->create();

    return [$user, $project];
}

it('rendert die Vorschau als HTML', function () {
    [$user, $project] = studioWithProjectForPreview();
    $quote = Quote::factory()->for($project)->create(['time_title' => 'Hochzeitsreportage']);

    $this->actingAs($user, 'staff')
        ->post("/angebote/{$quote->public_id}/vorschau", ['intro' => 'Liebe Lena,'])
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=utf-8')
        ->assertSee('Liebe Lena,', escape: false);
});

it('haelt Fremde von der Vorschau fern', function () {
    [, $project] = studioWithProjectForPreview();
    $quote = Quote::factory()->for($project)->create();

    $this->post("/angebote/{$quote->public_id}/vorschau")->assertRedirect('/login');
});

it('speichert beim Vorschau-Rendern nichts', function () {
    [$user, $project] = studioWithProjectForPreview();
    $quote = Quote::factory()->for($project)->create(['intro' => 'Original']);
    $updatedAt = $quote->updated_at;

    $this->actingAs($user, 'staff')
        ->post("/angebote/{$quote->public_id}/vorschau", [
            'intro' => 'Geaendert',
            'items' => [['title' => 'Shooting', 'quantity' => 1, 'unit_price' => 10000]],
            'stages' => [['percent' => 100, 'label' => 'Gesamt']],
        ])
        ->assertOk();

    $quote->refresh();
    expect($quote->intro)->toBe('Original');
    expect($quote->items()->count())->toBe(0);
    expect($quote->paymentStages()->count())->toBe(0);
    expect($quote->updated_at)->toEqual($updatedAt);
});

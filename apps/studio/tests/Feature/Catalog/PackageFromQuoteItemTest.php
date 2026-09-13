<?php

use App\Catalog\Models\Package;
use App\Quotes\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('legt aus einer Position ein Paket an', function () {
    [$user, $project] = studioWithProject();
    $quote = Quote::factory()->for($project)->create();

    $this->actingAs($user, 'staff')->post("/angebote/{$quote->public_id}/positionen/als-paket", [
        'name' => 'Fotobox',
        'item' => ['title' => 'Fotobox buchen', 'description' => 'Mit Drucker', 'quantity' => 1, 'unit_price' => 25000, 'selectable' => true, 'selected' => false],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $package = Package::with('items')->where('name', 'Fotobox')->sole();

    expect($package->items->pluck('title')->all())->toBe(['Fotobox buchen'])
        ->and($package->items->first()->unit_price)->toBe(25000)
        ->and($package->items->first()->selectable)->toBeTrue();
});

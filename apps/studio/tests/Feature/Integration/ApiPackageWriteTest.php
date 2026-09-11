<?php

use App\Catalog\Models\Package;
use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::API);
    $this->withHeader('Authorization', 'Bearer '.bearerToken(ApiToken::factory()->withScopes(Capability::ManagePackages)->create()));
});

it('creates a package, renames it without losing its lines, and deletes it', function () {
    $id = $this->withHeader('Idempotency-Key', 'package-1')
        ->postJson('/api/packages', [
            'name' => 'Reportage 2',
            'items' => [['title' => 'Reportage 10 Stunden', 'quantity' => 1, 'unit_price' => 179_000]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.items.0.unitPrice', 179_000)
        ->json('data.id');

    $this->withHeader('Idempotency-Key', 'package-2')
        ->patchJson("/api/packages/{$id}", ['name' => 'Reportage 2 (2019)'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Reportage 2 (2019)')
        ->assertJsonPath('data.items.0.title', 'Reportage 10 Stunden');

    $this->withHeader('Idempotency-Key', 'package-3')
        ->deleteJson("/api/packages/{$id}")
        ->assertNoContent();

    expect(Package::query()->count())->toBe(0);
});

// The deactivated ones stay listed: a caller that may edit a package has to
// find the one it is meant to switch back on.
it('lists packages with their active flag', function () {
    Package::factory()->create(['name' => 'Alt', 'is_active' => false]);

    $this->getJson('/api/packages')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Alt')
        ->assertJsonPath('data.0.isActive', false);
});

it('refuses a package write to a token without ManagePackages', function () {
    $this->withHeader('Authorization', 'Bearer '.bearerToken(ApiToken::factory()->withScopes(Capability::ManageQuotes)->create()))
        ->withHeader('Idempotency-Key', 'package-forbidden')
        ->postJson('/api/packages', ['name' => 'Reportage 2'])
        ->assertForbidden();
});

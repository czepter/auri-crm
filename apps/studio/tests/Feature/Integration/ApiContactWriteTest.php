<?php

use App\Crm\Models\Contact;
use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::API);
});

it('creates a contact', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageContacts)->create();

    $response = $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'contact-1')
        ->postJson('/api/contacts', ['name' => 'Lena Meier', 'email' => 'lena@example.test', 'phone' => '0171 1234567']);

    $response->assertCreated()
        ->assertJson(['name' => 'Lena Meier', 'email' => 'lena@example.test', 'phone' => '0171 1234567']);

    expect($response->json('id'))->toBeString()
        ->and(Contact::where('email', 'lena@example.test')->count())->toBe(1);

    expect(integerIdPaths($response->json()))->toBe([]);
});

it('rejects a contact create missing the required name', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageContacts)->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'contact-invalid')
        ->postJson('/api/contacts', ['email' => 'lena@example.test'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('refuses to create a contact without the manage_contacts scope', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'contact-scope')
        ->postJson('/api/contacts', ['name' => 'Lena Meier'])
        ->assertForbidden();

    expect(Contact::count())->toBe(0);
});

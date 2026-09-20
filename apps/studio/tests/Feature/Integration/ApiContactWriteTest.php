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

// #400: a caller resending only the phone number must not wipe the billing
// address beside it — the merge the issue asks the api surface to hold to.
it('updates a contact, leaving fields the request omits untouched', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageContacts)->create();
    $contact = Contact::factory()->create(['name' => 'Lena Meier', 'street' => 'Hauptstraße 1', 'postcode' => '12345', 'city' => 'Berlin']);

    $response = $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'contact-update-1')
        ->patchJson("/api/contacts/{$contact->public_id}", ['phone' => '0171 9999999']);

    $response->assertOk()->assertJson(['name' => 'Lena Meier', 'phone' => '0171 9999999', 'street' => 'Hauptstraße 1']);

    expect($contact->fresh()->street)->toBe('Hauptstraße 1')
        ->and($contact->fresh()->phone)->toBe('0171 9999999')
        ->and(integerIdPaths($response->json()))->toBe([]);
});

it('refuses to update a contact without the manage_contacts scope', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();
    $contact = Contact::factory()->create(['name' => 'Lena Meier']);

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'contact-update-scope')
        ->patchJson("/api/contacts/{$contact->public_id}", ['name' => 'Someone Else'])
        ->assertForbidden();

    expect($contact->fresh()->name)->toBe('Lena Meier');
});

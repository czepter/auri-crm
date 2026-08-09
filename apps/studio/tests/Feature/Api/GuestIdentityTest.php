<?php

use App\Crm\Models\Contact;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::API));

it('rejects a request carrying no bearer token', function () {
    $this->getJson('/api/whoami')->assertUnauthorized();
});

it('rejects a request carrying a bogus bearer token', function () {
    $this->withHeader('Authorization', 'Bearer nonsense')
        ->getJson('/api/whoami')
        ->assertUnauthorized();
});

it('authenticates a guest token minted on a contact and returns its identity', function () {
    $contact = Contact::factory()->create(['name' => 'Lena Meier']);
    $token = $contact->createToken('guest')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/whoami')
        ->assertOk()
        ->assertJson(['contact' => [
            'public_id' => $contact->public_id,
            'name' => 'Lena Meier',
        ]]);
});

it('mints a token row carrying this app\'s own tenant_id and public_id', function () {
    $contact = Contact::factory()->create();
    $token = $contact->createToken('guest');

    expect($token->accessToken->tenant_id)->not->toBeNull();
    expect($token->accessToken->public_id)->not->toBeNull();
});

<?php

use App\Catalog\Models\Package;
use App\Catalog\Models\ServiceSuggestion;
use App\Crm\Models\Contact;
use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::API);
    $this->withHeader('Authorization', 'Bearer '.bearerToken(ApiToken::factory()->withScopes(Capability::ManageContacts)->create()));
});

it('suggests a portal-offered package to a contact and lists it', function () {
    $contact = Contact::factory()->create();
    $package = Package::factory()->create(['portal_offered' => true]);

    $this->withHeader('Idempotency-Key', 'suggest-1')
        ->postJson("/api/contacts/{$contact->public_id}/service-suggestions", [
            'package' => $package->public_id,
            'note' => 'Passt zu eurer Hochzeit.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.note', 'Passt zu eurer Hochzeit.');

    $this->getJson("/api/contacts/{$contact->public_id}/service-suggestions")
        ->assertOk()
        ->assertJsonPath('data.0.package', $package->public_id);
});

it('refuses a package the studio does not offer in the portal', function () {
    $contact = Contact::factory()->create();
    $package = Package::factory()->create(['portal_offered' => false]);

    $this->withHeader('Idempotency-Key', 'suggest-2')
        ->postJson("/api/contacts/{$contact->public_id}/service-suggestions", ['package' => $package->public_id])
        ->assertUnprocessable();
});

it('deletes a service suggestion', function () {
    $contact = Contact::factory()->create();
    $package = Package::factory()->create(['portal_offered' => true]);
    $suggestion = ServiceSuggestion::create(['contact_id' => $contact->id, 'package_id' => $package->id]);

    $this->withHeader('Idempotency-Key', 'suggest-3')
        ->deleteJson("/api/service-suggestions/{$suggestion->public_id}")
        ->assertNoContent();

    expect(ServiceSuggestion::query()->count())->toBe(0);
});

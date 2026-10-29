<?php

use App\Crm\Actions\CreateContact;
use App\Crm\Actions\UpdateContact;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function contactApi(Capability $scope, string $key)
{
    $token = ApiToken::factory()->withScopes($scope)->create();

    return test()->withHeader('Authorization', 'Bearer '.bearerToken($token))->withHeader('Idempotency-Key', $key);
}

it('creates contacts through CreateContact from admin and api', function () {
    test()->partialMock(CreateContact::class, fn ($mock) => $mock->shouldReceive('__invoke')->twice()->passthru());

    test()->actingAs(staffMember(), 'staff')->post('/kontakte', ['name' => 'Admin Kontakt'])->assertRedirect();

    useSurface(Surface::API);
    contactApi(Capability::ManageContacts, 'contact')->postJson('/api/contacts', ['name' => 'Api Kontakt'])->assertCreated();

    expect(Contact::count())->toBe(2);
});

it('resolves contacts through CreateContact from the lead screen and the api', function () {
    test()->partialMock(CreateContact::class, fn ($mock) => $mock->shouldReceive('resolve')->twice()->passthru());
    $project = ClientCase::factory()->create();

    test()->actingAs(staffMember(), 'staff')
        ->post("/anfragen/{$project->public_id}/kontakte", ['name' => 'Partner Eins'])->assertRedirect();

    useSurface(Surface::API);
    contactApi(Capability::ManageLeads, 'case-contact')
        ->postJson("/api/cases/{$project->public_id}/contacts", ['name' => 'Partner Zwei'])->assertCreated();

    expect(Contact::count())->toBe(2);
});

it('keeps a phone number the studio lacks when an address on file resolves', function () {
    $existing = Contact::factory()->create(['email' => 'lena@example.test', 'phone' => null, 'name' => 'Lena Meier']);

    $contact = app(CreateContact::class)->resolve(['name' => 'L. Meier', 'email' => 'lena@example.test', 'phone' => '0171 1']);

    expect($contact->is($existing))->toBeTrue()
        ->and($contact->fresh()->phone)->toBe('0171 1')
        ->and($contact->name)->toBe('Lena Meier');
});

it('updates contacts through UpdateContact from admin, api and portal', function () {
    test()->partialMock(UpdateContact::class, fn ($mock) => $mock->shouldReceive('__invoke')->times(3)->passthru());
    $contact = Contact::factory()->create(['email' => 'a@example.test']);

    test()->actingAs(staffMember(), 'staff')
        ->patch("/kontakte/{$contact->public_id}", ['name' => 'Admin', 'email' => 'a@example.test'])->assertRedirect();

    useSurface(Surface::API);
    contactApi(Capability::ManageContacts, 'update')
        ->patchJson("/api/contacts/{$contact->public_id}", ['name' => 'Api'])->assertOk();

    useSurface(Surface::PORTAL);
    test()->actingAs($contact, 'portal')
        ->patch('/einstellungen', ['name' => 'Portal', 'email' => 'a@example.test'])->assertRedirect();

    expect($contact->fresh()->name)->toBe('Portal');
});

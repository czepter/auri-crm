<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
});

it('matches on a name fragment, case insensitively', function () {
    // Case-insensitive is load-bearing: a typeahead that misses "Steffens"
    // when someone types "steff" creates the duplicate it exists to prevent.
    Contact::factory()->create(['name' => 'Steffi Wehrmann']);
    Contact::factory()->create(['name' => 'Annika Steffens']);
    Contact::factory()->create(['name' => 'Paul Rieck']);

    $this->actingAs($this->staff, 'staff')
        ->getJson('/kontakte/suche?q=stef')
        ->assertOk()
        ->assertJsonCount(2, 'contacts');
});

it('matches on an email fragment', function () {
    Contact::factory()->create(['name' => 'Annika Steffens', 'email' => 'a.steffens@posteo.de']);

    $this->actingAs($this->staff, 'staff')
        ->getJson('/kontakte/suche?q=posteo')
        ->assertOk()
        ->assertJsonPath('contacts.0.name', 'Annika Steffens');
});

it('reports how many projects a contact already has', function () {
    $contact = Contact::factory()->create(['name' => 'Annika Steffens']);
    Project::factory()->create()->attachContact($contact, ProjectContactRole::Primary);

    $this->actingAs($this->staff, 'staff')
        ->getJson('/kontakte/suche?q=annika')
        ->assertOk()
        ->assertJsonPath('contacts.0.projectCount', 1);
});

it('exposes the public id and never the primary key', function () {
    $contact = Contact::factory()->create(['name' => 'Annika Steffens']);

    $this->actingAs($this->staff, 'staff')
        ->getJson('/kontakte/suche?q=annika')
        ->assertOk()
        ->assertJsonPath('contacts.0.id', $contact->public_id);
});

it('returns nothing for a query shorter than two characters', function () {
    Contact::factory()->create(['name' => 'Annika Steffens']);

    $this->actingAs($this->staff, 'staff')
        ->getJson('/kontakte/suche?q=a')
        ->assertOk()
        ->assertJsonCount(0, 'contacts');
});

it('rejects an unauthenticated search', function () {
    $this->get('/kontakte/suche?q=annika')->assertRedirect('/login');
});

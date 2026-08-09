<?php

use App\Crm\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
});

it('lists contacts alphabetically with their project counts', function () {
    Contact::factory()->create(['name' => 'Zora Winter']);
    Contact::factory()->create(['name' => 'Annika Steffens']);

    $props = $this->actingAs($this->staff, 'staff')
        ->get('/kontakte')->assertOk()->viewData('page')['props'];

    expect(array_column($props['contacts'], 'name'))->toBe(['Annika Steffens', 'Zora Winter'])
        ->and(integerIdPaths($props))->toBe([]);
});

it('binds the edit route on the public id, not the primary key', function () {
    $contact = Contact::factory()->create();

    $this->actingAs($this->staff, 'staff')->get("/kontakte/{$contact->public_id}")->assertOk();
    $this->actingAs($this->staff, 'staff')->get("/kontakte/{$contact->id}")->assertNotFound();
});

it('updates a contact', function () {
    $contact = Contact::factory()->create(['name' => 'Anika Steffens']);

    $this->actingAs($this->staff, 'staff')
        ->patch("/kontakte/{$contact->public_id}", [
            'name' => 'Annika Steffens',
            'email' => $contact->email,
            'phone' => $contact->phone,
        ])
        ->assertRedirect('/kontakte');

    expect($contact->fresh()->name)->toBe('Annika Steffens');
});

it('refuses an email that already belongs to someone else', function () {
    // Mirrors the partial unique index. Without the rule the database throws
    // where the form should show a field error.
    Contact::factory()->create(['email' => 'taken@example.test']);
    $contact = Contact::factory()->create();

    $this->actingAs($this->staff, 'staff')
        ->patch("/kontakte/{$contact->public_id}", [
            'name' => $contact->name,
            'email' => 'taken@example.test',
        ])
        ->assertSessionHasErrors('email');
});

it('lets a contact keep its own email', function () {
    $contact = Contact::factory()->create(['email' => 'mine@example.test']);

    $this->actingAs($this->staff, 'staff')
        ->patch("/kontakte/{$contact->public_id}", [
            'name' => 'Neuer Name',
            'email' => 'mine@example.test',
        ])
        ->assertRedirect('/kontakte')
        ->assertSessionHasNoErrors();
});

it('allows clearing an email', function () {
    $contact = Contact::factory()->create();

    $this->actingAs($this->staff, 'staff')
        ->patch("/kontakte/{$contact->public_id}", ['name' => $contact->name, 'email' => null])
        ->assertRedirect('/kontakte');

    expect($contact->fresh()->email)->toBeNull();
});

it('rejects unauthenticated access to every contact route', function () {
    $contact = Contact::factory()->create();

    $this->get('/kontakte')->assertRedirect('/login');
    $this->get("/kontakte/{$contact->public_id}")->assertRedirect('/login');
    $this->patch("/kontakte/{$contact->public_id}", [])->assertRedirect('/login');
});

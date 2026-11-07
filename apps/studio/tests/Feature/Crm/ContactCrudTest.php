<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\Shoot;
use App\Enums\StaffRole;
use App\Gallery\Models\Gallery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
});

it('shows the dsgvo actions only to the owner', function () {
    $contact = Contact::factory()->create();

    $owner = User::factory()->withMfa()->create(['role' => StaffRole::Admin]);
    $props = $this->actingAs($owner, 'staff')->get("/kontakte/{$contact->public_id}")->viewData('page')['props'];
    expect($props['canManageDsgvo'])->toBeTrue()
        ->and($props['dsgvoErasedAt'])->toBeNull()
        ->and(integerIdPaths($props))->toBe([]);

    $photographer = User::factory()->withMfa()->create(['role' => StaffRole::Manager]);
    expect($this->actingAs($photographer, 'staff')->get("/kontakte/{$contact->public_id}")->viewData('page')['props']['canManageDsgvo'])
        ->toBeFalse();
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

it('creates a contact', function () {
    $this->actingAs($this->staff, 'staff')
        ->post('/kontakte', ['name' => 'Annika Steffens', 'email' => 'annika@example.test', 'phone' => '0170 1234567'])
        ->assertSessionHasNoErrors();

    expect(Contact::where('email', 'annika@example.test')->first()?->name)->toBe('Annika Steffens');
});

it('creates a contact with the address the screen offers', function () {
    $this->actingAs($this->staff, 'staff')
        ->post('/kontakte', [
            'name' => 'Annika Steffens',
            'street' => 'Museumstraße 4',
            'postcode' => '22765',
            'city' => 'Hamburg',
        ])
        ->assertSessionHasNoErrors();

    expect(Contact::firstWhere('name', 'Annika Steffens'))
        ->street->toBe('Museumstraße 4')
        ->postcode->toBe('22765')
        ->city->toBe('Hamburg');
});

// P-09 (#228): only what departs from the studio's default is stored, and
// the greeting the model copies in survives beside it.
it('creates a contact with its portal departures', function () {
    $this->actingAs($this->staff, 'staff')
        ->post('/kontakte', [
            'name' => 'Annika Steffens',
            'portal' => ['accept_quotes' => false, 'messaging_mode' => 'any'],
        ])
        ->assertSessionHasNoErrors();

    $overrides = Contact::firstWhere('name', 'Annika Steffens')->portal_overrides;

    expect($overrides['accept_quotes'])->toBeFalse()
        ->and($overrides['messaging_mode'])->toBe('any')
        ->and($overrides)->not->toHaveKey('payment_status');
});

it('refuses a portal value the sheet would refuse too', function () {
    $this->actingAs($this->staff, 'staff')
        ->post('/kontakte', ['name' => 'Annika Steffens', 'portal' => ['messaging_mode' => 'alles']])
        ->assertSessionHasErrors('portal.messaging_mode');

    expect(Contact::where('name', 'Annika Steffens')->exists())->toBeFalse();
});

it('refuses to create a second contact on an address already on file', function () {
    // The same person enquiring again, and the partial unique index would
    // throw where the form should show a field error.
    Contact::factory()->create(['email' => 'taken@example.test']);

    $this->actingAs($this->staff, 'staff')
        ->post('/kontakte', ['name' => 'Wer Anders', 'email' => 'taken@example.test'])
        ->assertSessionHasErrors('email');

    expect(Contact::where('email', 'taken@example.test')->count())->toBe(1);
});

it('updates a contact', function () {
    $contact = Contact::factory()->create(['name' => 'Anika Steffens']);

    $this->actingAs($this->staff, 'staff')
        ->patch("/kontakte/{$contact->public_id}", [
            'name' => 'Annika Steffens',
            'email' => $contact->email,
            'phone' => $contact->phone,
        ])
        ->assertRedirect("/kontakte/{$contact->public_id}");

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
        ->assertRedirect("/kontakte/{$contact->public_id}")
        ->assertSessionHasNoErrors();
});

it('allows clearing an email', function () {
    $contact = Contact::factory()->create();

    $this->actingAs($this->staff, 'staff')
        ->patch("/kontakte/{$contact->public_id}", ['name' => $contact->name, 'email' => null])
        ->assertRedirect("/kontakte/{$contact->public_id}");

    expect($contact->fresh()->email)->toBeNull();
});

it('rejects unauthenticated access to every contact route', function () {
    $contact = Contact::factory()->create();

    $this->get('/kontakte')->assertRedirect('/login');
    $this->get('/kontakte/neu')->assertRedirect('/login');
    $this->get("/kontakte/{$contact->public_id}")->assertRedirect('/login');
    $this->patch("/kontakte/{$contact->public_id}", [])->assertRedirect('/login');
});

// C-15 (#251): a contact is the sum of their projects, so the list groups by
// standing rather than by the alphabet.
it('groups contacts by whether the studio is working with them', function () {
    test()->actingAs(test()->staff, 'staff');

    $running = Contact::factory()->create(['name' => 'Läuft']);
    $project = ClientCase::factory()->booked()->create();
    $project->attachContact($running, CaseContactRole::Primary);
    Shoot::factory()->for($project, 'case')->primary()->on(now()->addMonth()->format('Y-m-d'))->create();

    $past = Contact::factory()->create(['name' => 'Bestand']);
    $old = ClientCase::factory()->booked()->create();
    $old->attachContact($past, CaseContactRole::Primary);
    Shoot::factory()->for($old, 'case')->primary()->on(now()->subYear()->format('Y-m-d'))->create();

    Contact::factory()->create(['name' => 'Ohne']);

    $rows = collect(props('/kontakte')['contacts'])->keyBy('name');

    expect($rows['Läuft']['standing'])->toBe('running')
        ->and($rows['Läuft']['nextDate'])->toBe(now()->addMonth()->format('Y-m-d'))
        ->and($rows['Bestand']['standing'])->toBe('past')
        ->and($rows['Ohne']['standing'])->toBe('none');
});

// A couple is two contacts, never one row with a composed name: each has
// their own address and their own portal access.
it('lists both halves of a couple as their own rows', function () {
    test()->actingAs(test()->staff, 'staff');

    $project = ClientCase::factory()->booked()->create();
    $project->attachContact(Contact::factory()->create(['name' => 'Lena Meier']), CaseContactRole::Primary);
    $project->attachContact(Contact::factory()->create(['name' => 'Jan Kowalski']), CaseContactRole::Partner);

    expect(collect(props('/kontakte')['contacts'])->pluck('name')->all())
        ->toBe(['Jan Kowalski', 'Lena Meier']);
});

// C-18 (#254): a project is an order with money and dates, a gallery a
// delivery with an expiry. Mixed into one list they read as the same thing.
it('carries projects, galleries and connected people as their own sections', function () {
    test()->actingAs(test()->staff, 'staff');

    $contact = Contact::factory()->create();
    $partner = Contact::factory()->create(['name' => 'Jan Kowalski']);

    $project = ClientCase::factory()->create(['name' => 'Meier & Kowalski']);
    $project->attachContact($contact, CaseContactRole::Primary);
    $project->attachContact($partner, CaseContactRole::Partner);
    Shoot::factory()->for($project, 'case')->primary()->on('2026-06-14')->create();

    $gallery = Gallery::factory()->for($project, 'case')->create([
        'title' => 'Hochzeitstag',
        'expires_at' => now()->addMonth(),
    ]);
    $gallery->publish();

    $props = props("/kontakte/{$contact->public_id}");

    expect($props['projects'])->toHaveCount(1)
        ->and($props['projects'][0]['role'])->toBe('primary')
        ->and($props['projects'][0]['date'])->toBe('2026-06-14')
        ->and($props['galleries'])->toHaveCount(1)
        ->and($props['galleries'][0]['title'])->toBe('Hochzeitstag')
        ->and(collect($props['connected'])->pluck('name')->all())->toBe(['Jan Kowalski']);
});

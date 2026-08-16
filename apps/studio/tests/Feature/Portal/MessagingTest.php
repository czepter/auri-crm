<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Engagement\Models\Message;
use App\Engagement\Models\Thread;
use App\Settings\Models\StudioSettings;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

// T-08's "messaging" switch defaults to off (see
// tests/Feature/Portal/CapabilitySwitchesTest.php for that default and the
// 404 it produces while off) — every test below exercises the mailbox itself,
// so the switch is on for all of them. Cache::flush() first: the resolver
// caches the row (T-01), and a row already cached by an earlier test in the
// same suite run would otherwise still read as off.
beforeEach(function () {
    useSurface(Surface::PORTAL);
    Cache::store('redis')->flush();
    StudioSettings::query()->update(['portal_messaging_enabled' => true]);
});

function bookedContact(): array
{
    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create(['name' => 'Lena Meier']);
    $project->attachContact($contact, ProjectContactRole::Primary);

    return [$project, $contact];
}

it('shows an empty mailbox before the client has written anything', function () {
    [, $contact] = bookedContact();

    $props = test()->actingAs($contact, 'portal')
        ->get('/nachrichten')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['threads'])->toBe([])
        ->and($props['openThread'])->toBeNull();
});

// E-06's own acceptance criterion, now without a project in the path.
it('lets a client start a thread about one of their bookings', function () {
    [$project, $contact] = bookedContact();

    test()->actingAs($contact, 'portal')
        ->post('/nachrichten', [
            'subject' => 'Frage zum Ablauf',
            'body' => 'Dürfen wir während des Ringtauschs nah an den Altar?',
            'project' => $project->public_id,
        ])
        ->assertRedirect();

    $thread = Thread::where('project_id', $project->id)->sole();

    expect($thread->subject)->toBe('Frage zum Ablauf')
        ->and($thread->messages)->toHaveCount(1)
        ->and($thread->messages->first()->contact->is($contact))->toBeTrue();
});

// P-04's whole point: the question that comes before the booking.
it('lets a client start a thread with no booking at all', function () {
    $contact = Contact::factory()->create();

    test()->actingAs($contact, 'portal')
        ->post('/nachrichten', [
            'subject' => 'Im Herbst noch etwas frei?',
            'body' => 'Wir würden gern im Oktober mit den Eltern fotografieren.',
        ])
        ->assertRedirect();

    $thread = Thread::sole();

    expect($thread->project_id)->toBeNull()
        ->and($thread->subject)->toBe('Im Herbst noch etwas frei?');
});

it('refuses a booking the contact is not on, as a 404 rather than a 403', function () {
    $other = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();

    test()->actingAs($contact, 'portal')
        ->post('/nachrichten', ['subject' => 'Thema', 'body' => 'Text.', 'project' => $other->public_id])
        ->assertNotFound();

    expect(Thread::count())->toBe(0);
});

it('refuses a booking the studio has not made portal-visible', function () {
    $project = Project::factory()->create(['portal_visible' => false]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    test()->actingAs($contact, 'portal')
        ->post('/nachrichten', ['subject' => 'Thema', 'body' => 'Text.', 'project' => $project->public_id])
        ->assertNotFound();
});

it('requires a subject to start a thread', function () {
    [, $contact] = bookedContact();

    test()->actingAs($contact, 'portal')
        ->post('/nachrichten', ['body' => 'Ohne Thema.'])
        ->assertSessionHasErrors('subject');

    expect(Thread::count())->toBe(0);
});

it('replies on a thread without repeating its subject', function () {
    [, $contact] = bookedContact();

    test()->actingAs($contact, 'portal')
        ->post('/nachrichten', ['subject' => 'Thema', 'body' => 'Erste Nachricht.'])
        ->assertRedirect();

    $thread = Thread::sole();

    test()->post("/nachrichten/{$thread->public_id}", ['body' => 'Zweite Nachricht.'])->assertRedirect();

    expect(Thread::count())->toBe(1)->and(Message::count())->toBe(2);
});

it('refuses a reply on a thread the contact is not a participant on', function () {
    [$project, $owner] = bookedContact();
    $thread = app(App\Engagement\Actions\StartThread::class)($project, $owner, 'Thema', 'Text.');

    $stranger = Contact::factory()->create();

    test()->actingAs($stranger, 'portal')
        ->post("/nachrichten/{$thread->public_id}", ['body' => 'Fremde Nachricht.'])
        ->assertNotFound();

    expect(Message::count())->toBe(1);
});

it('lists every thread of the contact, booked and general alike, with its reference', function () {
    [$project, $contact] = bookedContact();

    app(App\Engagement\Actions\StartThread::class)($project, $contact, 'Zum Ablauf', 'Text.');
    app(App\Engagement\Actions\StartThread::class)(null, $contact, 'Im Herbst frei?', 'Text.');

    $props = test()->actingAs($contact, 'portal')
        ->get('/nachrichten')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['threads'])->toHaveCount(2)
        ->and(collect($props['threads'])->pluck('reference')->all())
        ->toEqualCanonicalizing([$project->name, null]);
});

it('shows the studio\'s reply beside the client\'s own message', function () {
    [$project, $contact] = bookedContact();
    $staff = App\Models\User::factory()->create(['name' => 'Christian']);

    $thread = app(App\Engagement\Actions\StartThread::class)($project, $contact, 'Thema', 'Erste Nachricht.');
    app(App\Engagement\Actions\PostMessage::class)($thread, $staff, 'Antwort vom Studio.');

    $props = test()->actingAs($contact, 'portal')
        ->get('/nachrichten')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['openThread']['messages'])->toHaveCount(2)
        ->and($props['openThread']['messages'][1]['author'])->toBe('Christian')
        ->and($props['openThread']['messages'][1]['fromStaff'])->toBeTrue();
});

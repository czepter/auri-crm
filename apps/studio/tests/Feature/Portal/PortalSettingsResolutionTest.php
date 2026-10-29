<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Engagement\Models\Thread;
use App\Enums\StaffRole;
use App\Gallery\Models\Gallery;
use App\Models\StudioClosure;
use App\Models\StudioSettings;
use App\Portal\Support\PortalSettings;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::PORTAL);
    Cache::store('redis')->flush();
});

function settings(): PortalSettings
{
    return app(PortalSettings::class);
}

it('falls back to the studio default when the contact says nothing', function () {
    StudioSettings::query()->update(['portal_payment_status_enabled' => true]);

    expect(settings()->paymentStatusEnabled(Contact::factory()->create()))->toBeTrue();
});

// Absent means inherit. A nullable boolean column would have blurred that
// into "off", which is the whole reason the overrides are one JSON blob.
it('lets a contact depart from the default in both directions', function () {
    StudioSettings::query()->update(['portal_payment_status_enabled' => true]);

    $off = Contact::factory()->create(['portal_overrides' => ['payment_status' => false]]);
    $silent = Contact::factory()->create(['portal_overrides' => ['accept_quotes' => true]]);

    expect(settings()->paymentStatusEnabled($off))->toBeFalse()
        ->and(settings()->paymentStatusEnabled($silent))->toBeTrue();
});

it('follows the studio when the default moves, for a contact that never overrode it', function () {
    $contact = Contact::factory()->create();

    expect(settings()->acceptQuotesEnabled($contact))->toBeFalse();

    Cache::store('redis')->flush();
    StudioSettings::query()->update(['portal_accept_quotes_enabled' => true]);

    expect(app(PortalSettings::class)->acceptQuotesEnabled($contact))->toBeTrue();
});

// P-04 made a thread without a booking possible, which is a third state.
it('reads messaging as three states rather than a switch', function () {
    StudioSettings::query()->update(['portal_messaging_mode' => 'booking']);

    $contact = Contact::factory()->create();

    expect(settings()->messagingEnabled($contact))->toBeTrue()
        ->and(settings()->generalThreadsAllowed($contact))->toBeFalse();

    Cache::store('redis')->flush();
    StudioSettings::query()->update(['portal_messaging_mode' => 'any']);

    expect(app(PortalSettings::class)->generalThreadsAllowed($contact))->toBeTrue();
});

it('closes the mailbox entirely for a contact overridden to off', function () {
    StudioSettings::query()->update(['portal_messaging_mode' => 'any']);

    $contact = Contact::factory()->create(['portal_overrides' => ['messaging_mode' => 'off']]);

    test()->actingAs($contact, 'portal')->get('/nachrichten')->assertNotFound();
});

it('refuses a thread without a booking while messaging is booking-only', function () {
    StudioSettings::query()->update(['portal_messaging_mode' => 'booking']);

    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    test()->actingAs($contact, 'portal')
        ->post('/nachrichten', ['subject' => 'Thema', 'body' => 'Text.'])
        ->assertSessionHasErrors('project');

    expect(Thread::count())->toBe(0);

    test()->post('/nachrichten', [
        'subject' => 'Thema', 'body' => 'Text.', 'project' => $project->public_id,
    ])->assertRedirect();

    expect(Thread::count())->toBe(1);
});

// One sentence cannot greet two different rooms.
it('greets each opening state with its own text', function () {
    StudioSettings::query()->update([
        'portal_welcome_inquiry' => 'Wir haben eure Anfrage.',
        'portal_welcome_active' => 'Alle eure Termine auf einen Blick.',
        'portal_welcome_done' => 'Eure Bilder liegen weiter hier.',
    ]);

    // Ohne eigene Begrüßung: die des Studios, je Zustand.
    $contact = Contact::factory()->create(['portal_overrides' => null]);

    expect(test()->actingAs($contact, 'portal')->get('/')->viewData('page')['props']['welcomeText'])->toBe('Wir haben eure Anfrage.');

    // A single booking is opened directly, so the home's other rooms need
    // two. Ein Termin steht noch aus: das ist „Laufend".
    foreach ([ClientCase::factory()->create(['portal_visible' => true]), ClientCase::factory()->create(['portal_visible' => true])] as $project) {
        $project->attachContact($contact, CaseContactRole::Primary);
    }

    expect(test()->actingAs($contact, 'portal')->get('/')->viewData('page')['props']['welcomeText'])->toBe('Alle eure Termine auf einen Blick.');
});

// Neuanlage: the studio's text becomes the contact's own, so it can be
// rewritten for them without touching anybody else's.
// Der Fremde mit einer Anfrage und der Kunde, dessen Bilder fertig sind,
// bekamen früher denselben Satz.
it('greets a client whose bookings are all delivered with the closing text', function () {
    StudioSettings::query()->update([
        'portal_welcome_active' => 'Alle eure Termine auf einen Blick.',
        'portal_welcome_done' => 'Eure Bilder liegen weiter hier.',
    ]);

    $contact = Contact::factory()->create(['portal_overrides' => null]);

    foreach ([ClientCase::factory()->create(['portal_visible' => true]), ClientCase::factory()->create(['portal_visible' => true])] as $project) {
        $project->attachContact($contact, CaseContactRole::Primary);
        Gallery::factory()->for($project, 'case')->create()->publish();
    }

    expect(test()->actingAs($contact, 'portal')->get('/')->viewData('page')['props']['welcomeText'])->toBe('Eure Bilder liegen weiter hier.');
});

it('gives a new contact the studio greetings as their own', function () {
    StudioSettings::query()->update([
        'portal_welcome_inquiry' => 'Schön, dass ihr da seid.',
        'portal_welcome_active' => 'Hier liegen eure Termine.',
        'portal_welcome_done' => null,
    ]);

    expect(Contact::factory()->create()->portal_overrides)
        ->toBe(['welcome_inquiry' => 'Schön, dass ihr da seid.', 'welcome_active' => 'Hier liegen eure Termine.']);
});

it('leaves a contact created with their own greeting alone', function () {
    StudioSettings::query()->update(['portal_welcome_inquiry' => 'Schön, dass ihr da seid.']);

    expect(Contact::factory()->create(['portal_overrides' => ['welcome_inquiry' => 'Hallo ihr zwei.']])->portal_overrides)
        ->toBe(['welcome_inquiry' => 'Hallo ihr zwei.']);
});

// Der Kunde hat dieselben drei Zustände wie das Studio: was für ihn
// geschrieben ist, gilt in seinem Zustand und nur dort.
it('lets a contact carry their own greeting per state', function () {
    StudioSettings::query()->update([
        'portal_welcome_inquiry' => 'Wir haben eure Anfrage.',
        'portal_welcome_active' => 'Alle eure Termine auf einen Blick.',
    ]);

    $contact = Contact::factory()->create(['portal_overrides' => ['welcome_inquiry' => 'Hallo ihr zwei.']]);

    expect(test()->actingAs($contact, 'portal')->get('/')->viewData('page')['props']['welcomeText'])->toBe('Hallo ihr zwei.');

    foreach ([ClientCase::factory()->create(['portal_visible' => true]), ClientCase::factory()->create(['portal_visible' => true])] as $project) {
        $project->attachContact($contact, CaseContactRole::Primary);
    }

    expect(test()->actingAs($contact, 'portal')->get('/')->viewData('page')['props']['welcomeText'])->toBe('Alle eure Termine auf einen Blick.');
});

// Written keys only: a "Standard" choice must be removed, never frozen as a
// copy of today's value, or a studio changing its default leaves this
// contact behind on the old one.
it('stores only the keys that actually depart from the default', function () {
    useSurface(Surface::ADMIN);

    $contact = Contact::factory()->create();

    test()->actingAs(staffMember(), 'staff')
        ->patch("/kontakte/{$contact->public_id}/portal", [
            'accept_quotes' => true,
            'messaging_mode' => null,
            'welcome_inquiry' => '',
        ])
        ->assertRedirect();

    expect($contact->fresh()->portal_overrides)->toBe(['accept_quotes' => true]);
});

it('clears the whole override set when every row is back on Standard', function () {
    useSurface(Surface::ADMIN);

    $contact = Contact::factory()->create(['portal_overrides' => ['accept_quotes' => true]]);

    test()->actingAs(staffMember(), 'staff')
        ->patch("/kontakte/{$contact->public_id}/portal", ['accept_quotes' => null]);

    expect($contact->fresh()->portal_overrides)->toBeNull();
});

// The studio-wide field wins; the owner's own number is what a studio that
// never opened /einstellungen/portal is reached on.
it('takes the contact number from the owner when the studio names none', function () {
    StudioSettings::query()->update(['portal_contact_phone' => null]);
    staffMember(StaffRole::Admin)->update(['phone' => '0171 3948820']);

    expect(settings()->contactLine()['phone'])->toBe('0171 3948820');

    StudioSettings::query()->update(['portal_contact_phone' => '030 220120']);
    Cache::store('redis')->flush();

    expect(settings()->contactLine()['phone'])->toBe('030 220120');
});

it('shows the notice of a closure that is running or ahead in place of the response time', function () {
    $this->travelTo('2026-12-27 10:00:00');
    StudioSettings::query()->update(['portal_contact_response_time' => 'binnen 24 Stunden']);

    expect(settings()->contactLine()['responseTime'])->toBe('binnen 24 Stunden');

    StudioClosure::factory()->spanning('2026-12-24', '2026-12-31')->create(['message' => 'Zurück am 2. Januar.']);
    expect(settings()->contactLine()['responseTime'])->toBe('24.12. bis 31.12.: Zurück am 2. Januar.');

    StudioClosure::query()->delete();
    StudioClosure::factory()->spanning('2027-02-08', '2027-02-22')->create(['message' => 'Lofoten.']);
    expect(settings()->contactLine()['responseTime'])->toBe('08.02. bis 22.02.: Lofoten.');
});

it('keeps the response time for a closure that is over or has no notice', function () {
    $this->travelTo('2027-01-05 10:00:00');
    StudioSettings::query()->update(['portal_contact_response_time' => 'binnen 24 Stunden']);

    StudioClosure::factory()->spanning('2026-12-24', '2027-01-04')->create(['message' => 'Vorbei.']);
    StudioClosure::factory()->spanning('2027-01-04', '2027-01-10')->create(['message' => null]);

    expect(settings()->contactLine()['responseTime'])->toBe('binnen 24 Stunden');
});

it('takes the closure that starts first when several carry a notice', function () {
    $this->travelTo('2026-12-01 10:00:00');

    StudioClosure::factory()->spanning('2027-02-08', '2027-02-22')->create(['message' => 'Später.']);
    StudioClosure::factory()->spanning('2026-12-24', '2026-12-31')->create(['message' => 'Zuerst.']);

    expect(settings()->contactLine()['responseTime'])->toBe('24.12. bis 31.12.: Zuerst.');
});

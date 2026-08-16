<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Engagement\Models\Thread;
use App\Portal\Support\PortalSettings;
use App\Settings\Models\StudioSettings;
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

    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    test()->actingAs($contact, 'portal')
        ->post('/nachrichten', ['subject' => 'Thema', 'body' => 'Text.'])
        ->assertSessionHasErrors('project');

    expect(Thread::count())->toBe(0);

    test()->post('/nachrichten', [
        'subject' => 'Thema', 'body' => 'Text.', 'project' => $project->public_id,
    ])->assertRedirect();

    expect(Thread::count())->toBe(1);
});

// One sentence cannot greet three different rooms.
it('greets each opening state with its own text', function () {
    StudioSettings::query()->update([
        'portal_welcome_inquiry' => 'Wir haben eure Anfrage.',
        'portal_welcome_text' => 'Schön, dass ihr da seid.',
        'portal_welcome_many' => 'Alle eure Termine auf einen Blick.',
    ]);

    $contact = Contact::factory()->create();

    expect(test()->actingAs($contact, 'portal')->get('/')->viewData('page')['props']['welcomeText'])->toBe('Wir haben eure Anfrage.');

    $first = Project::factory()->create(['portal_visible' => true]);
    $first->attachContact($contact, ProjectContactRole::Primary);

    expect(test()->actingAs($contact, 'portal')->get('/')->viewData('page')['props']['welcomeText'])->toBe('Schön, dass ihr da seid.');

    $second = Project::factory()->create(['portal_visible' => true]);
    $second->attachContact($contact, ProjectContactRole::Primary);

    expect(test()->actingAs($contact, 'portal')->get('/')->viewData('page')['props']['welcomeText'])->toBe('Alle eure Termine auf einen Blick.');
});

it('lets a contact carry their own greeting whatever state they are in', function () {
    StudioSettings::query()->update(['portal_welcome_text' => 'Schön, dass ihr da seid.']);

    $contact = Contact::factory()->create(['portal_overrides' => ['welcome' => 'Hallo ihr zwei.']]);

    expect(test()->actingAs($contact, 'portal')->get('/')->viewData('page')['props']['welcomeText'])->toBe('Hallo ihr zwei.');
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
            'sign_contracts' => null,
            'messaging_mode' => null,
            'welcome' => '',
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

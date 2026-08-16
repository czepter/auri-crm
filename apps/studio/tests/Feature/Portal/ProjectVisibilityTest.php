<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\CrewAssignment;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Models\Absence;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // A-03 lives entirely on the client surface, so the process has to be the
    // one that serves it — route table, guard and cookie together, same as
    // MagicLinkTest.
    useSurface(Surface::PORTAL);
});

/**
 * The one visibility scope, exercised the same way HomeController and
 * ProjectController call it: projects joined through project_contacts for
 * the authenticated contact, where portal_visible. A second, differently
 * worded rule anywhere in this file would be the bug A-03 exists to prevent.
 */
it('gives a contact with zero visible projects an empty state, not an error', function () {
    $contact = Contact::factory()->create();
    $this->actingAs($contact, 'portal');

    $response = $this->get('/');

    $response->assertOk();
    expect($response->viewData('page')['props']['bookings'])->toBe([]);
});

// P-01 (#220): the home stopped being a switcher, so a single-booking
// contact stays on it — it carries what is open, their galleries and their
// contact, none of which the project screen answers across bookings.
it('keeps a single-booking contact on the home rather than redirecting', function () {
    $contact = Contact::factory()->create();
    $project = Project::factory()->create(['portal_visible' => true]);
    $project->attachContact($contact, ProjectContactRole::Primary);

    $this->actingAs($contact, 'portal');

    expect(props('/')['bookings'])->toHaveCount(1);
});

it('lists every visible project in the switcher for a multi-project contact', function () {
    $contact = Contact::factory()->create();
    $wedding = Project::factory()->wedding()->create(['portal_visible' => true, 'name' => 'Meier & Kowalski']);
    $portrait = Project::factory()->create(['portal_visible' => true, 'name' => 'Familie Voss']);
    $wedding->attachContact($contact, ProjectContactRole::Primary);
    $portrait->attachContact($contact, ProjectContactRole::Primary);

    $this->actingAs($contact, 'portal');

    $projects = props('/')['bookings'];

    expect($projects)->toHaveCount(2)
        ->and(collect($projects)->pluck('id')->all())
        ->toEqualCanonicalizing([$wedding->public_id, $portrait->public_id])
        ->and(collect($projects)->pluck('name')->all())
        ->toContain('Meier & Kowalski', 'Familie Voss');
});

it('excludes a project the studio has not made portal_visible from the switcher', function () {
    $contact = Contact::factory()->create();
    $visibleA = Project::factory()->create(['portal_visible' => true]);
    $visibleB = Project::factory()->create(['portal_visible' => true]);
    $hidden = Project::factory()->create(['portal_visible' => false]);
    $visibleA->attachContact($contact, ProjectContactRole::Primary);
    $visibleB->attachContact($contact, ProjectContactRole::Primary);
    $hidden->attachContact($contact, ProjectContactRole::Primary);

    $this->actingAs($contact, 'portal');

    $ids = collect(props('/')['bookings'])->pluck('id')->all();

    expect($ids)->toEqualCanonicalizing([$visibleA->public_id, $visibleB->public_id])
        ->and($ids)->not->toContain($hidden->public_id);
});

it('opens a visible project the contact is on', function () {
    $contact = Contact::factory()->create();
    $project = Project::factory()->create(['portal_visible' => true, 'name' => 'Meier & Kowalski']);
    $project->attachContact($contact, ProjectContactRole::Primary);

    $this->actingAs($contact, 'portal');

    $response = $this->get("/projekte/{$project->public_id}");

    $response->assertOk();
    expect($response->viewData('page')['props']['project']['name'])->toBe('Meier & Kowalski');
});

it('404s on a project the contact is not on, rather than 403', function () {
    $contact = Contact::factory()->create();
    $someoneElses = Project::factory()->create(['portal_visible' => true]);

    $this->actingAs($contact, 'portal');

    $this->get("/projekte/{$someoneElses->public_id}")->assertNotFound();
});

it('404s on a project the contact is on but the studio has not made visible', function () {
    $contact = Contact::factory()->create();
    $project = Project::factory()->create(['portal_visible' => false]);
    $project->attachContact($contact, ProjectContactRole::Primary);

    $this->actingAs($contact, 'portal');

    $this->get("/projekte/{$project->public_id}")->assertNotFound();
});

it('404s on an unknown project id', function () {
    $contact = Contact::factory()->create();
    $this->actingAs($contact, 'portal');

    $this->get('/projekte/does-not-exist')->assertNotFound();
});

it('reflects a portal_visible flip on the very next request, without a re-login', function () {
    $contact = Contact::factory()->create();
    $projects = Project::factory()->count(3)->create(['portal_visible' => true]);
    $projects->each(fn (Project $project) => $project->attachContact($contact, ProjectContactRole::Primary));

    $this->actingAs($contact, 'portal');

    expect(props('/')['bookings'])->toHaveCount(3);

    $dropped = $projects->first();
    $dropped->update(['portal_visible' => false]);

    expect(props('/')['bookings'])->toHaveCount(2);
    $this->get("/projekte/{$dropped->public_id}")->assertNotFound();
});

it('reflects a detached contact on the very next request, without a re-login', function () {
    $contact = Contact::factory()->create();
    $projects = Project::factory()->count(3)->create(['portal_visible' => true]);
    $projects->each(fn (Project $project) => $project->attachContact($contact, ProjectContactRole::Primary));

    $this->actingAs($contact, 'portal');

    expect(props('/')['bookings'])->toHaveCount(3);

    $dropped = $projects->first();
    $dropped->contacts()->detach($contact);

    expect(props('/')['bookings'])->toHaveCount(2);
    $this->get("/projekte/{$dropped->public_id}")->assertNotFound();
});

it('offers the switcher link on a project screen only when there is more than one visible project', function () {
    $contact = Contact::factory()->create();
    $project = Project::factory()->create(['portal_visible' => true]);
    $project->attachContact($contact, ProjectContactRole::Primary);

    $this->actingAs($contact, 'portal');

    expect(props("/projekte/{$project->public_id}")['showSwitcher'])->toBeFalse();

    $second = Project::factory()->create(['portal_visible' => true]);
    $second->attachContact($contact, ProjectContactRole::Primary);

    expect(props("/projekte/{$project->public_id}")['showSwitcher'])->toBeTrue();
});

/**
 * T-10's portal-isolation requirement, proven against the real response
 * rather than by inspecting the controller: seed an absence for someone
 * crewed on this project's own shoot, then hash the whole JSON body for any
 * trace of it — the category, the date range, or the person's name — not
 * only the crew list ProjectController already never sends.
 */
it('never lets an absence category or date reach the portal, even for a shoot crewed by someone away on it', function () {
    $contact = Contact::factory()->create();
    $project = Project::factory()->create(['portal_visible' => true]);
    $project->attachContact($contact, ProjectContactRole::Primary);
    $shoot = Shoot::factory()->for($project)->on('2026-09-03')->create();

    $away = User::factory()->create(['name' => 'Nina Sturm']);
    CrewAssignment::factory()->for($shoot)->for($away)->create();
    Absence::factory()->for($away)->spanning('2026-09-01', '2026-09-07')->create(['category' => 'holiday']);

    $this->actingAs($contact, 'portal');

    $body = $this->get("/projekte/{$project->public_id}")->assertOk()->getContent();

    expect($body)->not->toContain('Nina Sturm')
        ->not->toContain('holiday')
        ->not->toContain('Urlaub')
        ->not->toContain('2026-09-01')
        ->not->toContain('2026-09-07');

    // Structural, not just textual: the shoot the portal does show carries
    // none of the keys an absence or a crew list would need.
    $shootProps = props("/projekte/{$project->public_id}")['project']['shoots'][0];
    expect($shootProps)->not->toHaveKey('crew')
        ->not->toHaveKey('crewConflicts');
});

/**
 * T-09's other half of the activity feed's boundary: Task 8's admin
 * ProjectActivity prop is admin-only by omission, since Portal's own
 * ProjectController never references it. Proven here against the real
 * response, not by absence of a reference in the controller — an internal
 * audit trail on a client-facing page would disclose staff names, IP
 * addresses and internal event vocabulary the client has no business
 * seeing.
 */
it('does not hand the client an internal audit trail', function () {
    $contact = Contact::factory()->create();
    $project = Project::factory()->create(['portal_visible' => true, 'name' => 'Meier & Kowalski']);
    $project->attachContact($contact, ProjectContactRole::Primary);

    AuditLogger::record('quote.sent', staffMember(), [], null, $project->id);

    $this->actingAs($contact, 'portal');

    $props = props("/projekte/{$project->public_id}");

    // Proves the request actually reaches the project screen and returns
    // real props, rather than 404ing or redirecting to the login screen —
    // an empty or errored response would satisfy the assertion below for
    // the wrong reason.
    expect($props['project']['name'])->toBe('Meier & Kowalski');

    expect($props)->not->toHaveKey('activity');
});

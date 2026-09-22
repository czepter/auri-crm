<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Enums\StaffRole;
use App\Models\User;
use App\Portal\Models\ClientAccess;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // A-04's kill switch lives entirely on the client surface — same as
    // A-03 and MagicLinkTest — so the process has to be the one that
    // serves it.
    useSurface(Surface::PORTAL);
});

/**
 * Crosses into the admin surface to perform the real revoke through the
 * real route, then switches back — useSurface() is built for exactly this
 * (see tests/Pest.php). Proves the whole seam end to end rather than
 * reaching around it by mutating the contact directly.
 */
function revokeFromAdmin(Contact $contact): void
{
    useSurface(Surface::ADMIN);

    $staff = User::factory()->withMfa()->create(['role' => StaffRole::Admin]);

    test()->actingAs($staff, 'staff')
        ->post("/kontakte/{$contact->public_id}/portal-zugang/widerruf")
        ->assertRedirect();

    useSurface(Surface::PORTAL);
}

it('ends a live portal session on the very next request, without a re-login', function () {
    $contact = Contact::factory()->create();
    $project = Project::factory()->create(['portal_visible' => true]);
    $project->attachContact($contact, ProjectContactRole::Primary);

    // Stands in for a session already open in another tab: the guard is
    // already resolved to this contact — with the old, not-yet-revoked
    // state — before the revoke happens.
    $this->actingAs($contact, 'portal');
    $this->get("/projekte/{$project->public_id}")->assertOk();

    revokeFromAdmin($contact);

    // The same "already open" session gets nothing on its next click.
    $this->get("/projekte/{$project->public_id}")->assertRedirect('/anmelden');
    expect(auth('portal')->check())->toBeFalse();
});

it("removes every one of a contact's projects from the switcher once revoked", function () {
    $contact = Contact::factory()->create();
    $projectA = Project::factory()->create(['portal_visible' => true]);
    $projectB = Project::factory()->create(['portal_visible' => true]);
    $projectA->attachContact($contact, ProjectContactRole::Primary);
    $projectB->attachContact($contact, ProjectContactRole::Primary);

    $this->actingAs($contact, 'portal');

    revokeFromAdmin($contact);

    $this->get("/projekte/{$projectA->public_id}")->assertRedirect('/anmelden');
    $this->get("/projekte/{$projectB->public_id}")->assertRedirect('/anmelden');
});

it('leaves an unconsumed magic link dead once its contact is revoked', function () {
    // Stands in for a second tab holding its own still-live link — nothing
    // stops two devices each holding one for the same contact.
    $contact = Contact::factory()->create();
    $token = 'raw-'.bin2hex(random_bytes(8));
    $access = ClientAccess::create([
        'contact_id' => $contact->id,
        'token_hash' => ClientAccess::hash($token),
        'expires_at' => now()->addMinutes(15),
    ]);

    revokeFromAdmin($contact);

    // Issued before the revoke and never consumed — no longer opens
    // anything, and is not falsely stamped as having been used.
    $this->get("/zugang/{$token}")->assertRedirect('/anmelden');
    expect(auth('portal')->check())->toBeFalse()
        ->and($access->fresh()->consumed_at)->toBeNull();
});

it('does not disturb an unrevoked contact sharing the same internal id space', function () {
    // Contacts and staff users are separate tables with independent
    // sequences; nothing about the revoked-check may key off a raw id
    // shared across them.
    $revoked = Contact::factory()->create();
    $untouched = Contact::factory()->create();
    $project = Project::factory()->create(['portal_visible' => true]);
    $project->attachContact($untouched, ProjectContactRole::Primary);

    revokeFromAdmin($revoked);

    $this->actingAs($untouched, 'portal');
    $this->get("/projekte/{$project->public_id}")->assertOk();
});

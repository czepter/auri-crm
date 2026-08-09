<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Portal\Models\ClientAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create(['role' => StaffRole::Owner]);
});

/**
 * A-04's second, deliberately separate action: scoped to one contact, never
 * to a project. See tests/Feature/Portal/RevocationTest.php for what this
 * does to a live session and an unconsumed link from the portal side.
 */
it("revokes a contact's portal access", function () {
    $contact = Contact::factory()->create();

    $this->actingAs($this->staff, 'staff')
        ->post("/kontakte/{$contact->public_id}/portal-zugang/widerruf")
        ->assertRedirect();

    expect($contact->fresh()->portal_access_revoked_at)->not->toBeNull();
});

it('does not delete the project_contacts row on revoke', function () {
    $contact = Contact::factory()->create();
    $project = Project::factory()->create(['portal_visible' => true]);
    $project->attachContact($contact, ProjectContactRole::Primary);

    $this->actingAs($this->staff, 'staff')
        ->post("/kontakte/{$contact->public_id}/portal-zugang/widerruf");

    // The pivot row is what the project screen reads to name the client —
    // revocation is not the same as never having been involved.
    expect($project->contacts()->whereKey($contact->id)->exists())->toBeTrue()
        ->and($project->fresh()->primaryContact()?->id)->toBe($contact->id);
});

it("leaves another contact's access untouched", function () {
    $revoked = Contact::factory()->create();
    $other = Contact::factory()->create();

    $this->actingAs($this->staff, 'staff')
        ->post("/kontakte/{$revoked->public_id}/portal-zugang/widerruf");

    expect($other->fresh()->portal_access_revoked_at)->toBeNull();
});

it('invalidates an unconsumed magic link for the contact without marking it consumed', function () {
    $contact = Contact::factory()->create();
    $token = 'raw-'.bin2hex(random_bytes(8));
    $access = ClientAccess::create([
        'contact_id' => $contact->id,
        'token_hash' => ClientAccess::hash($token),
        'expires_at' => now()->addMinutes(15),
    ]);

    $this->actingAs($this->staff, 'staff')
        ->post("/kontakte/{$contact->public_id}/portal-zugang/widerruf");

    expect(ClientAccess::forToken($token))->toBeNull()
        ->and($access->fresh()->consumed_at)->toBeNull();
});

it('records a distinct audit event', function () {
    $contact = Contact::factory()->create();

    $this->actingAs($this->staff, 'staff')
        ->post("/kontakte/{$contact->public_id}/portal-zugang/widerruf");

    expect(
        AuditLog::where('event', 'contact.portal_access_revoked')->where('subject', $contact->public_id)->exists()
    )->toBeTrue();
});

it('distinguishes hiding a project from revoking a contact in the audit log', function () {
    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();

    $this->actingAs($this->staff, 'staff')->post("/auftraege/{$project->public_id}/portal-sichtbarkeit");
    $this->actingAs($this->staff, 'staff')->post("/kontakte/{$contact->public_id}/portal-zugang/widerruf");

    $events = AuditLog::query()->pluck('event')->all();

    expect($events)->toContain('project.portal_hidden')
        ->and($events)->toContain('contact.portal_access_revoked')
        ->and(array_unique($events))->toHaveCount(2);
});

it('refuses a role without portal access management', function () {
    $contact = Contact::factory()->create();

    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Assistant]), 'staff')
        ->post("/kontakte/{$contact->public_id}/portal-zugang/widerruf")
        ->assertForbidden();
});

it('rejects unauthenticated access', function () {
    $contact = Contact::factory()->create();

    $this->post("/kontakte/{$contact->public_id}/portal-zugang/widerruf")->assertRedirect('/login');
});

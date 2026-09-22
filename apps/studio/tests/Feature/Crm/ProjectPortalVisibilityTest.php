<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create(['role' => StaffRole::Admin]);
});

/**
 * A-04's first of two separate actions: flips one project's portal_visible.
 * Never touches a contact or a project_contacts row — Contact::visibleProjects()
 * (A-03) is the one place visibility is read, so nothing here needs to
 * duplicate that rule.
 */
it('hides a visible project from the portal', function () {
    $project = Project::factory()->create(['portal_visible' => true]);

    $this->actingAs($this->staff, 'staff')
        ->post("/auftraege/{$project->public_id}/portal-sichtbarkeit")
        ->assertRedirect();

    expect($project->fresh()->portal_visible)->toBeFalse();
});

it('shows a hidden project in the portal', function () {
    $project = Project::factory()->create(['portal_visible' => false]);

    $this->actingAs($this->staff, 'staff')
        ->post("/auftraege/{$project->public_id}/portal-sichtbarkeit")
        ->assertRedirect();

    expect($project->fresh()->portal_visible)->toBeTrue();
});

it('leaves every contact on the project intact when hiding it', function () {
    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    $this->actingAs($this->staff, 'staff')
        ->post("/auftraege/{$project->public_id}/portal-sichtbarkeit");

    expect($project->contacts()->whereKey($contact->id)->exists())->toBeTrue();
});

it("leaves a contact's other projects reachable when one of them is hidden", function () {
    $contact = Contact::factory()->create();
    $hidden = Project::factory()->create(['portal_visible' => true]);
    $stillVisible = Project::factory()->create(['portal_visible' => true]);
    $hidden->attachContact($contact, ProjectContactRole::Primary);
    $stillVisible->attachContact($contact, ProjectContactRole::Primary);

    $this->actingAs($this->staff, 'staff')
        ->post("/auftraege/{$hidden->public_id}/portal-sichtbarkeit");

    expect($contact->visibleProjects()->pluck('projects.id')->all())->toBe([$stillVisible->id]);
});

it('records a distinct audit event naming which state the project ended up in', function () {
    $project = Project::factory()->create(['portal_visible' => true]);

    $this->actingAs($this->staff, 'staff')
        ->post("/auftraege/{$project->public_id}/portal-sichtbarkeit");

    expect(
        AuditLog::where('event', 'project.portal_hidden')->where('subject', $project->public_id)->exists()
    )->toBeTrue();
});

it('refuses a role without portal access management', function () {
    $project = Project::factory()->create(['portal_visible' => true]);

    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Contributor]), 'staff')
        ->post("/auftraege/{$project->public_id}/portal-sichtbarkeit")
        ->assertForbidden();
});

it('rejects unauthenticated access', function () {
    $project = Project::factory()->create();

    $this->post("/auftraege/{$project->public_id}/portal-sichtbarkeit")->assertRedirect('/login');
});

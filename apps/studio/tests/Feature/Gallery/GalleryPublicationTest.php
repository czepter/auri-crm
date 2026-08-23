<?php

use App\Crm\Models\Project;
use App\Enums\StaffRole;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Media\Models\Asset;
use App\Models\User;
use App\Policies\ProjectPolicy;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
    $this->gallery = Gallery::factory()->create();
    $this->set = GallerySet::where('gallery_id', $this->gallery->id)->first();
    Asset::factory()->create([
        'gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->set->id,
        'key' => 'galleries/g/a.jpg', 'filename' => 'a.jpg',
    ]);
});

it('writes a first version', function () {
    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen")->assertRedirect();

    expect($this->gallery->fresh()->currentVersion->version)->toBe(1);
});

it('reopens the standing version when nothing moved', function () {
    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen");
    $this->delete("/galerien/{$this->gallery->public_id}/veroeffentlichung");
    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen");

    expect($this->gallery->fresh()->currentVersion->version)->toBe(1)
        ->and($this->gallery->fresh()->unpublished_at)->toBeNull();
});

it('writes a second version when the draft moved', function () {
    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen");

    Asset::factory()->create([
        'gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->set->id,
        'key' => 'galleries/g/b.jpg', 'filename' => 'b.jpg',
    ]);

    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen");

    expect($this->gallery->fresh()->currentVersion->version)->toBe(2);
});

it('sees a swap of equal count as a change', function () {
    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen");

    $this->gallery->assetRows()->update(['removed_from_draft_at' => now()]);
    Asset::factory()->create([
        'gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->set->id,
        'key' => 'galleries/g/c.jpg', 'filename' => 'c.jpg',
    ]);

    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen");

    expect($this->gallery->fresh()->currentVersion->version)->toBe(2);
});

it('closes the gate on withdrawal and leaves the version standing', function () {
    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen");
    $this->delete("/galerien/{$this->gallery->public_id}/veroeffentlichung")->assertRedirect();

    expect($this->gallery->fresh()->unpublished_at)->not->toBeNull()
        ->and($this->gallery->fresh()->versions()->count())->toBe(1);
});

it('refuses to publish a gallery with no draft assets', function () {
    $empty = Gallery::factory()->create();

    $this->post("/galerien/{$empty->public_id}/veroeffentlichen")->assertSessionHasErrors('gallery');

    expect($empty->fresh()->currentVersion)->toBeNull();
});

it('keeps publishing away from a role that may not show things to clients', function () {
    // Editor, not SecondShooter: Editor holds ManageShoots (reaches
    // manageMedia) but not ManagePortalAccess, so this pins publish() to
    // the right capability rather than merely to "has no capabilities at
    // all". SecondShooter holds neither and would pass just as well if
    // the policy checked ManageShoots by mistake.
    $editor = User::factory()->withMfa()->create(['role' => StaffRole::Editor]);

    $this->actingAs($editor, 'staff')
        ->post("/galerien/{$this->gallery->public_id}/veroeffentlichen")
        ->assertForbidden();
});

// Important 4: the policy's own docblock says everything with a project
// routes through ProjectPolicy, the same shape manageMedia already uses —
// publish() alone checked the bare capability. Not exploitable today (every
// role holding ManagePortalAccess also holds ViewAllProjects, so ordinary
// roles can't show the gap end to end), which is exactly why this pins the
// call itself rather than a role combination that doesn't exist yet.
it('routes a project-bound gallery\'s publish check through the project policy, like manageMedia', function () {
    $project = Project::factory()->create();
    $gallery = Gallery::factory()->create(['project_id' => $project->id]);

    $this->mock(ProjectPolicy::class, function ($mock) {
        $mock->shouldReceive('manageVisibility')->once()->andReturn(true);
    });

    expect($this->staff->can('publish', $gallery))->toBeTrue();
});

it('shuts guests out on withdrawal without touching the standing version', function () {
    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen");
    $this->delete("/galerien/{$this->gallery->public_id}/veroeffentlichung");

    useSurface(Surface::PORTAL);

    $this->get("/galerien/{$this->gallery->public_id}/beteiligung")->assertNotFound();
});

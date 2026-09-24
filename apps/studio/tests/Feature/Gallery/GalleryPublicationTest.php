<?php

use App\Crm\Models\ClientCase;
use App\Enums\StaffRole;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryPrereleaseSignup;
use App\Gallery\Models\GallerySet;
use App\Gallery\Notifications\GalleryPrereleasePublished;
use App\Media\Models\Asset;
use App\Models\User;
use App\Policies\ClientCasePolicy;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

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

    $this->gallery->assets()->update(['removed_from_draft_at' => now()]);
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
    $editor = User::factory()->withMfa()->create(['role' => StaffRole::Contributor]);

    $this->actingAs($editor, 'staff')
        ->post("/galerien/{$this->gallery->public_id}/veroeffentlichen")
        ->assertForbidden();
});

// Important 4: the policy's own docblock says everything with a project
// routes through ClientCasePolicy, the same shape manageMedia already uses —
// publish() alone checked the bare capability. Not exploitable today (every
// role holding ManagePortalAccess also holds ViewAllCases, so ordinary
// roles can't show the gap end to end), which is exactly why this pins the
// call itself rather than a role combination that doesn't exist yet.
it('routes a project-bound gallery\'s publish check through the project policy, like manageMedia', function () {
    $project = ClientCase::factory()->create();
    $gallery = Gallery::factory()->create(['case_id' => $project->id]);

    $this->mock(ClientCasePolicy::class, function ($mock) {
        $mock->shouldReceive('manageVisibility')->once()->andReturn(true);
    });

    expect($this->staff->can('publish', $gallery))->toBeTrue();
});

// ---------- R42: one mail per pre-release signup, on the publish that ends it ----------

it('clears prerelease_at, mails every unnotified signup on publish, and removes the row', function () {
    Notification::fake();
    $this->gallery->forceFill(['prerelease_at' => now()])->save();
    GalleryPrereleaseSignup::query()->insert([
        'tenant_id' => $this->gallery->tenant_id, 'gallery_id' => $this->gallery->id,
        'email' => 'anna@example.test', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen");

    expect($this->gallery->fresh()->prerelease_at)->toBeNull();
    Notification::assertSentOnDemand(
        GalleryPrereleasePublished::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'anna@example.test',
    );
    // R45/personal-data decision: notified means gone, not merely marked —
    // see GalleryPrereleaseSignup's own docblock.
    expect(GalleryPrereleaseSignup::count())->toBe(0);
});

// R45: the reopen branch (no draft deviation, nothing new to publish) used
// to return before notifyPrereleaseSignups() was ever called — a publish
// that raced a failed one, or simply found nothing to change, silently
// dropped the whole waiting list.
it('mails a still-pending signup on a publish that only reopens, writing no new version', function () {
    Notification::fake();
    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen");
    $this->delete("/galerien/{$this->gallery->public_id}/veroeffentlichung");
    // Withdrawal has already rotated the preview pass, not prerelease_at —
    // set directly, as a signup could only ever have been collected before
    // this gallery's first (already-happened) publish, so this simulates a
    // row that survived from before by construction rather than reality.
    GalleryPrereleaseSignup::query()->insert([
        'tenant_id' => $this->gallery->tenant_id, 'gallery_id' => $this->gallery->id,
        'email' => 'late@example.test', 'created_at' => now(), 'updated_at' => now(),
    ]);

    // Draft unchanged since the first publish: this is the reopen branch.
    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen");

    Notification::assertSentOnDemandTimes(GalleryPrereleasePublished::class, 1);
    expect(GalleryPrereleaseSignup::count())->toBe(0);
});

it('never mails a signup twice, even across two publishes', function () {
    Notification::fake();
    $this->gallery->forceFill(['prerelease_at' => now()])->save();
    GalleryPrereleaseSignup::query()->insert([
        'tenant_id' => $this->gallery->tenant_id, 'gallery_id' => $this->gallery->id,
        'email' => 'anna@example.test', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen");

    Asset::factory()->create([
        'gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->set->id,
        'key' => 'galleries/g/second.jpg', 'filename' => 'second.jpg',
    ]);
    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen");

    Notification::assertSentOnDemandTimes(GalleryPrereleasePublished::class, 1);
});

it('shuts guests out on withdrawal without touching the standing version', function () {
    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen");
    $this->delete("/galerien/{$this->gallery->public_id}/veroeffentlichung");

    useSurface(Surface::PORTAL);

    $this->get("/galerien/{$this->gallery->public_id}/beteiligung")->assertNotFound();
});

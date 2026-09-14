<?php

use App\Crm\Models\Project;
use App\Enums\Capability;
use App\Gallery\Models\AssetFavorite;
use App\Gallery\Models\Gallery;
use App\Integration\Models\ApiToken;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::API);
    $this->withHeader('Authorization', 'Bearer '.bearerToken(ApiToken::factory()->withScopes(Capability::ManageShoots, Capability::ManagePortalAccess)->create()));
});

/** A draft picture, ready and undeleted, in a gallery's first (and only) set — see Asset::scopeInDraft(). */
function addDraftAsset(Gallery $gallery, string $key = 'originals/a/one.jpg'): void
{
    $gallery->sets()->first()->assets()->create([
        'gallery_id' => $gallery->id,
        'key' => $key,
        'filename' => basename($key),
        'status' => 'ready',
    ]);
}

it('lists and gets a gallery with its project, version and draft count', function () {
    $gallery = Gallery::factory()->create(['title' => 'Hochzeit Weber']);
    addDraftAsset($gallery);

    $this->getJson('/api/galleries')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Hochzeit Weber')
        ->assertJsonPath('data.0.projectId', $gallery->project->public_id)
        ->assertJsonPath('data.0.currentVersion', null)
        ->assertJsonPath('data.0.draftAssetCount', 1)
        ->assertJsonPath('data.0.status', 'draft');

    $this->getJson("/api/gallery-records/{$gallery->public_id}")
        ->assertOk()
        ->assertJsonPath('data.id', $gallery->public_id)
        ->assertJsonPath('data.slug', $gallery->slug)
        ->assertJsonPath('data.downloadsEnabled', true);
});

it('creates a gallery on a project with expiry and features', function () {
    $project = Project::factory()->create();

    $response = $this->withHeader('Idempotency-Key', 'gallery-1')
        ->postJson('/api/galleries', [
            'project_id' => $project->public_id,
            'title' => 'Verlobung',
            'is_external' => false,
            'expires_at' => '2030-01-01',
            'downloads_enabled' => false,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Verlobung')
        ->assertJsonPath('data.projectId', $project->public_id)
        ->assertJsonPath('data.downloadsEnabled', false);
});

it('updates only expiry and features, never the password or the design', function () {
    $gallery = Gallery::factory()->create();

    $this->withHeader('Idempotency-Key', 'gallery-update')
        ->patchJson("/api/gallery-records/{$gallery->public_id}", [
            'expires_at' => '2030-06-01',
            'comments_enabled' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.expiresAt', '2030-06-01T00:00:00+00:00')
        ->assertJsonPath('data.commentsEnabled', false);

    expect($gallery->fresh()->view_password)->toBeNull();
});

it('publishes a gallery, snapshotting the draft into a version', function () {
    $gallery = Gallery::factory()->create();
    addDraftAsset($gallery);

    $this->withHeader('Idempotency-Key', 'gallery-publish')
        ->postJson("/api/gallery-records/{$gallery->public_id}/publish")
        ->assertOk()
        ->assertJsonPath('data.currentVersion', 1)
        ->assertJsonPath('data.status', 'published');
});

it('withdraws a published gallery and rotates its preview pass', function () {
    $gallery = Gallery::factory()->create();
    addDraftAsset($gallery);
    $gallery->publish();
    $priorVersion = $gallery->preview_pass_version;

    $this->withHeader('Idempotency-Key', 'gallery-withdraw')
        ->postJson("/api/gallery-records/{$gallery->public_id}/withdraw")
        ->assertOk()
        ->assertJsonPath('data.status', 'withdrawn');

    expect($gallery->fresh()->preview_pass_version)->toBeGreaterThan($priorVersion);
});

it('starts and stops a pre-release, deleting waiting signups on stop', function () {
    $gallery = Gallery::factory()->create();

    $this->withHeader('Idempotency-Key', 'prerelease-start')
        ->postJson("/api/gallery-records/{$gallery->public_id}/prerelease/start")
        ->assertOk()
        ->assertJsonPath('data.status', 'prerelease');

    $gallery->prereleaseSignups()->create(['email' => 'gast@example.com']);

    $this->withHeader('Idempotency-Key', 'prerelease-stop')
        ->postJson("/api/gallery-records/{$gallery->public_id}/prerelease/stop")
        ->assertOk()
        ->assertJsonPath('data.status', 'draft');

    expect($gallery->prereleaseSignups()->count())->toBe(0);
});

it('mints a preview link that rotates any earlier one', function () {
    $gallery = Gallery::factory()->create();
    $priorVersion = $gallery->preview_pass_version;

    $response = $this->withHeader('Idempotency-Key', 'preview-link')
        ->postJson("/api/gallery-records/{$gallery->public_id}/preview-link")
        ->assertOk();

    expect($response->json('url'))->toContain('?preview=')
        ->and($gallery->fresh()->preview_pass_version)->toBeGreaterThan($priorVersion);
});

it('lists the filenames a client favorited in the published version', function () {
    $gallery = Gallery::factory()->create();
    addDraftAsset($gallery, 'originals/a/liebling.jpg');
    $version = $gallery->publish();
    AssetFavorite::query()->create([
        'gallery_version_id' => $version->id,
        'asset_key' => 'originals/a/liebling.jpg',
        'guest_identifier' => 'guest-1',
    ]);

    $this->getJson("/api/gallery-records/{$gallery->public_id}/favorites")
        ->assertOk()
        ->assertJsonPath('data.0', 'liebling.jpg');
});

it('refuses record edits to a token without ManageShoots', function () {
    $gallery = Gallery::factory()->create();
    $token = ApiToken::factory()->withScopes(Capability::ManagePortalAccess)->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->getJson("/api/gallery-records/{$gallery->public_id}")
        ->assertForbidden();
});

it('refuses publishing to a token without ManagePortalAccess', function () {
    $gallery = Gallery::factory()->create();
    $token = ApiToken::factory()->withScopes(Capability::ManageShoots)->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->postJson("/api/gallery-records/{$gallery->public_id}/publish")
        ->assertForbidden();
});

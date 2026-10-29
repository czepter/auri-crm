<?php

use App\Enums\Capability;
use App\Gallery\Actions\CreateGallery;
use App\Gallery\Actions\PublishGallery;
use App\Gallery\Actions\StartGalleryPrerelease;
use App\Gallery\Actions\StopGalleryPrerelease;
use App\Gallery\Actions\UpdateGallery;
use App\Gallery\Actions\WithdrawGallery;
use App\Gallery\Models\Gallery;
use App\Integration\Models\ApiToken;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function galleryApi(Capability $scope, string $key)
{
    $token = ApiToken::factory()->withScopes($scope)->create();

    return test()->withHeader('Authorization', 'Bearer '.bearerToken($token))->withHeader('Idempotency-Key', $key);
}

it('creates galleries through CreateGallery from admin and api', function () {
    test()->partialMock(CreateGallery::class, fn ($mock) => $mock->shouldReceive('__invoke')->twice()->passthru());
    $body = ['title' => 'Hochzeit', 'is_external' => false];

    test()->actingAs(staffMember(), 'staff')->post('/galerien', $body)->assertRedirect();

    useSurface(Surface::API);
    galleryApi(Capability::ManageShoots, 'create')->postJson('/api/galleries', $body)->assertCreated();

    expect(Gallery::count())->toBe(2);
});

it('updates galleries through UpdateGallery from admin and api', function () {
    test()->partialMock(UpdateGallery::class, fn ($mock) => $mock->shouldReceive('__invoke')->twice()->passthru());
    $gallery = Gallery::factory()->create();

    test()->actingAs(staffMember(), 'staff')->patch("/galerien/{$gallery->public_id}", ['downloads_enabled' => false])->assertRedirect();

    useSurface(Surface::API);
    galleryApi(Capability::ManageShoots, 'update')
        ->patchJson("/api/gallery-records/{$gallery->public_id}", ['downloads_enabled' => true])->assertOk();

    expect($gallery->fresh()->downloads_enabled)->toBeTrue();
});

it('publishes galleries through PublishGallery::announce from admin and api', function () {
    test()->partialMock(PublishGallery::class, fn ($mock) => $mock->shouldReceive('announce')->twice()->andReturn(null));
    $gallery = Gallery::factory()->create();

    test()->actingAs(staffMember(), 'staff')->post("/galerien/{$gallery->public_id}/veroeffentlichen")->assertRedirect();

    useSurface(Surface::API);
    galleryApi(Capability::ManagePortalAccess, 'publish')->postJson("/api/gallery-records/{$gallery->public_id}/publish")->assertOk();
});

it('withdraws galleries through WithdrawGallery from admin and api', function () {
    test()->partialMock(WithdrawGallery::class, fn ($mock) => $mock->shouldReceive('__invoke')->twice()->passthru());
    $gallery = Gallery::factory()->create();

    test()->actingAs(staffMember(), 'staff')->delete("/galerien/{$gallery->public_id}/veroeffentlichung")->assertRedirect();

    useSurface(Surface::API);
    galleryApi(Capability::ManagePortalAccess, 'withdraw')->postJson("/api/gallery-records/{$gallery->public_id}/withdraw")->assertOk();

    expect($gallery->fresh()->unpublished_at)->not->toBeNull();
});

it('starts and stops pre-release through the same actions from admin and api', function () {
    test()->partialMock(StartGalleryPrerelease::class, fn ($mock) => $mock->shouldReceive('__invoke')->twice()->passthru());
    test()->partialMock(StopGalleryPrerelease::class, fn ($mock) => $mock->shouldReceive('__invoke')->twice()->passthru());
    $gallery = Gallery::factory()->create();

    test()->actingAs(staffMember(), 'staff');
    test()->post("/galerien/{$gallery->public_id}/pre-release")->assertRedirect();
    test()->delete("/galerien/{$gallery->public_id}/pre-release")->assertRedirect();

    useSurface(Surface::API);
    galleryApi(Capability::ManagePortalAccess, 'start')->postJson("/api/gallery-records/{$gallery->public_id}/prerelease/start")->assertOk();
    galleryApi(Capability::ManagePortalAccess, 'stop')->postJson("/api/gallery-records/{$gallery->public_id}/prerelease/stop")->assertOk();

    expect($gallery->fresh()->prerelease_at)->toBeNull();
});

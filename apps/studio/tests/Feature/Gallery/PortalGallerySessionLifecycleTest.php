<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Enums\StaffRole;
use App\Gallery\Models\AssetComment;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Media\Models\Asset;
use App\Models\User;
use App\Portal\Models\ClientAccess;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::PORTAL));

function lifecycleGallery(): array
{
    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);
    $gallery = Gallery::factory()->for($project, 'case')->create();
    $set = GallerySet::where('gallery_id', $gallery->id)->first();
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/l/p.jpg', 'filename' => 'p.jpg']);
    $gallery->fresh()->publish();
    $gallery->fresh()->rotateViewPassword('pw-lifecycle');

    return [$gallery->fresh(), $contact];
}

function lifecycleLink(Contact $contact, ?User $previewBy = null): string
{
    $token = bin2hex(random_bytes(32));
    ClientAccess::create([
        'contact_id' => $contact->id,
        'token_hash' => ClientAccess::hash($token),
        'expires_at' => now()->addMinutes(ClientAccess::VALID_FOR_MINUTES),
        'preview_by_id' => $previewBy?->id,
    ]);

    return "/zugang/{$token}";
}

it('unlocks the gallery for a signed-in client and attributes the comment', function () {
    [$gallery, $contact] = lifecycleGallery();
    test()->get(lifecycleLink($contact))->assertRedirect('/');

    expect(test()->get("/galerien/{$gallery->public_id}")->viewData('page')['props']['unlocked'])->toBeTrue();

    test()->postJson("/galerien/{$gallery->public_id}/kommentar", ['key' => 'galleries/l/p.jpg', 'body' => 'ok'])->assertCreated();
    expect(AssetComment::sole()->contact_id)->toBe($contact->id);
});

it('treats a revoked session as guest on the gallery', function () {
    [$gallery, $contact] = lifecycleGallery();
    test()->get(lifecycleLink($contact))->assertRedirect('/');
    $contact->revokePortalAccess();

    expect(test()->get("/galerien/{$gallery->public_id}")->viewData('page')['props']['unlocked'])->toBeFalse();
});

it('treats an ended session as guest on the gallery', function () {
    [$gallery, $contact] = lifecycleGallery();
    test()->get(lifecycleLink($contact))->assertRedirect('/');
    test()->travel(5)->seconds();
    $contact->forceFill(['sessions_valid_from' => now()])->save();

    expect(test()->get("/galerien/{$gallery->public_id}")->viewData('page')['props']['unlocked'])->toBeFalse();
});

it('refuses a gallery comment from a preview session', function () {
    [$gallery, $contact] = lifecycleGallery();
    test()->get(lifecycleLink($contact, staffMember(StaffRole::Admin)))->assertRedirect('/');

    test()->postJson("/galerien/{$gallery->public_id}/kommentar", ['key' => 'galleries/l/p.jpg', 'body' => 'x'])->assertForbidden();
    expect(AssetComment::count())->toBe(0);
});

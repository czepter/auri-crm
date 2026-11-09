<?php

use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\Task;
use App\Enums\StaffRole;
use App\Gallery\Actions\SetAssetHidden;
use App\Gallery\Models\AssetComment;
use App\Gallery\Models\AssetFavorite;
use App\Gallery\Models\AssetHide;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryView;
use App\Media\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create(['role' => StaffRole::Admin]);
    $this->actingAs($this->staff, 'staff');
    $this->gallery = Gallery::factory()->for(ClientCase::factory(), 'case')->create(['title' => 'Hochzeit']);
    foreach (['a', 'b', 'c'] as $letter) {
        Asset::factory()->create([
            'gallery_id' => $this->gallery->id,
            'gallery_set_id' => $this->gallery->sets()->first()->id,
            'key' => "galleries/g/{$letter}.jpg", 'filename' => "{$letter}.jpg",
        ]);
    }
    $this->version = $this->gallery->publish();
});

function activity(Gallery $gallery): array
{
    return props("/galerien/{$gallery->public_id}/aktivitaet")['activity'];
}

it('counts what the first 50 rows would not show and merges one timeline', function () {
    $contact = Contact::factory()->create(['name' => 'Viola Neumann']);
    AssetFavorite::factory()->count(60)->create(['gallery_version_id' => $this->version->id, 'asset_key' => 'galleries/g/a.jpg']);
    AssetFavorite::factory()->create(['gallery_version_id' => $this->version->id, 'asset_key' => 'galleries/g/b.jpg', 'contact_id' => $contact->id]);
    AssetComment::factory()->create(['gallery_version_id' => $this->version->id, 'asset_key' => 'galleries/g/b.jpg', 'contact_id' => $contact->id, 'body' => 'Schön']);
    app(SetAssetHidden::class)($this->gallery, $contact, 'galleries/g/c.jpg', true);
    GalleryView::create(['gallery_id' => $this->gallery->id, 'visitor_identifier' => 'x', 'viewed_on' => today()]);
    GalleryView::create(['gallery_id' => $this->gallery->id, 'visitor_identifier' => 'y', 'viewed_on' => today()]);

    $data = activity($this->gallery);
    $types = collect($data['events'])->pluck('type');

    expect($data['counts'])->toMatchArray(['favorites' => 61, 'comments' => 1, 'hidden' => 1, 'downloads' => 0])
        ->and($types->unique()->sort()->values()->all())->toBe(['comment', 'favorites', 'hide', 'views'])
        ->and(collect($data['events'])->firstWhere('type', 'views')['count'])->toBe(2)
        ->and(collect($data['events'])->firstWhere('type', 'comment')['photo']['n'])->toBe(2)
        ->and(json_encode($data['events']))->not->toContain('guest_identifier');
});

it('opens one task per gallery for the favourites until it is done', function () {
    $this->post("/galerien/{$this->gallery->public_id}/favoriten-aufgabe")->assertRedirect();
    $this->post("/galerien/{$this->gallery->public_id}/favoriten-aufgabe")->assertRedirect();

    $task = Task::sole();
    expect($task->title)->toBe('Auswahl bearbeiten: Hochzeit')
        ->and($task->case_id)->toBe($this->gallery->case_id)
        ->and($task->assignee_id)->toBe($this->staff->id)
        ->and(activity($this->gallery)['task']['url'])->toBe("/auftraege/{$this->gallery->case->public_id}/aufgaben");

    $task->update(['completed_at' => now()]);
    $this->post("/galerien/{$this->gallery->public_id}/favoriten-aufgabe");

    expect(Task::count())->toBe(2);
});

it('refuses a favourites task for a gallery without a project', function () {
    $orphan = Gallery::factory()->create(['case_id' => null]);

    $this->post("/galerien/{$orphan->public_id}/favoriten-aufgabe")->assertStatus(422);
});

it('only restores a hide of the gallery named in the path and only for staff who manage it', function () {
    $contact = Contact::factory()->create();
    $other = Gallery::factory()->create();
    app(SetAssetHidden::class)($other, $contact, 'k', true);
    $hide = AssetHide::sole();

    $this->delete("/galerien/{$this->gallery->public_id}/ausgeblendet/{$hide->public_id}")->assertNotFound();

    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Reader]), 'staff')
        ->delete("/galerien/{$other->public_id}/ausgeblendet/{$hide->public_id}")
        ->assertForbidden();

    expect(AssetHide::count())->toBe(1);
});

it('narrows the pictures to the marked ones on request', function () {
    AssetFavorite::factory()->create(['gallery_version_id' => $this->version->id, 'asset_key' => 'galleries/g/b.jpg']);

    expect(props("/galerien/{$this->gallery->public_id}/bilder?favoriten=1")['favoriteKeys'])->toBe(['galleries/g/b.jpg'])
        ->and(props("/galerien/{$this->gallery->public_id}/bilder")['favoriteKeys'])->toBeNull();
});

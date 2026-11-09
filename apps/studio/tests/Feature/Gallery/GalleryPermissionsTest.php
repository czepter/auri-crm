<?php

use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Enums\StaffRole;
use App\Gallery\Actions\SetAssetHidden;
use App\Gallery\Models\AssetHide;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Admin]), 'staff');
});

it('starts with the client allowed everything and guests allowed hearts and sharing', function () {
    $gallery = Gallery::factory()->create()->fresh();

    expect($gallery->permissions)->toEqual([
        'client' => ['favorites' => true, 'comments' => true, 'hide' => true, 'share' => true],
        'guest' => ['favorites' => true, 'comments' => false, 'hide' => false, 'share' => true],
    ]);
});

it('never lets a guest hide, whatever is stored', function () {
    $gallery = Gallery::factory()->create(['permissions' => ['guest' => ['hide' => true]]])->fresh();

    expect($gallery->allows('hide', false))->toBeFalse();
});

it('keeps the old switches as the sum of the matrix', function () {
    $gallery = Gallery::factory()->create(['permissions' => [
        'client' => ['favorites' => false, 'comments' => false],
        'guest' => ['favorites' => true, 'comments' => false],
    ]])->fresh();

    expect($gallery->lists_enabled)->toBeTrue()->and($gallery->comments_enabled)->toBeFalse();
});

it('turns an action off for both audiences when only the old switch is written', function () {
    $gallery = Gallery::factory()->create()->fresh();

    $gallery->update(['comments_enabled' => false]);
    expect($gallery->fresh()->permissions['client']['comments'])->toBeFalse();

    $gallery->update(['comments_enabled' => true]);
    expect($gallery->fresh()->permissions['client']['comments'])->toBeTrue()
        ->and($gallery->fresh()->permissions['guest']['comments'])->toBeFalse();
});

it('saves the matrix and the intro switch from the settings pane', function () {
    $gallery = Gallery::factory()->create();

    $this->patch("/galerien/{$gallery->public_id}", [
        'permissions' => ['guest' => ['comments' => true], 'client' => ['hide' => false]],
        'intro_enabled' => true,
    ])->assertRedirect();

    $fresh = $gallery->fresh();
    expect($fresh->allows('comments', false))->toBeTrue()
        ->and($fresh->allows('hide', true))->toBeFalse()
        ->and($fresh->allows('favorites', true))->toBeTrue()
        ->and($fresh->comments_enabled)->toBeTrue()
        ->and($fresh->intro_enabled)->toBeTrue();
});

it('sets a set\'s access and refuses anything else', function () {
    $gallery = Gallery::factory()->create();
    $set = GallerySet::where('gallery_id', $gallery->id)->first();
    $set->update(['access' => GallerySet::INTERNAL]);

    $this->patch("/galerien/{$gallery->public_id}/sets/{$set->public_id}", ['access' => 'guests'])->assertRedirect();
    expect($set->fresh()->isForGuests())->toBeTrue();

    $this->patch("/galerien/{$gallery->public_id}/sets/{$set->public_id}", ['access' => 'public'])->assertSessionHasErrors('access');
});

it('gives a new set internal access and the record the set link', function () {
    $gallery = Gallery::factory()->create();
    $this->post("/galerien/{$gallery->public_id}/sets", ['name' => 'Feier'])->assertRedirect();

    $set = $gallery->sets()->where('name', 'Feier')->first();
    expect($set->access)->toBe(GallerySet::INTERNAL);

    $this->get("/galerien/{$gallery->public_id}/bilder")->assertInertia(fn ($page) => $page
        ->where('sets.1.access', 'internal')
        ->where('sets.1.url', $gallery->publicUrl().'?set='.$set->public_id));
});

it('lists what clients hid and lets the studio bring it back', function () {
    $project = ClientCase::factory()->create();
    $contact = Contact::factory()->create(['name' => 'Viola Neumann']);
    $gallery = Gallery::factory()->for($project, 'case')->create();
    app(SetAssetHidden::class)($gallery, $contact, 'galleries/g/a.jpg', true);

    $this->getJson("/galerien/{$gallery->public_id}/ausgeblendet")
        ->assertOk()
        ->assertJsonPath('hidden.0.key', 'galleries/g/a.jpg')
        ->assertJsonPath('hidden.0.contact', 'Viola Neumann');

    $hide = AssetHide::sole();
    $this->delete("/galerien/{$gallery->public_id}/ausgeblendet/{$hide->public_id}")->assertRedirect();

    expect(AssetHide::count())->toBe(0)
        ->and(DB::table('audit_logs')->where('event', 'gallery.asset_restored')->count())->toBe(1);
});

it('hides once however often it is asked, and shows again', function () {
    $contact = Contact::factory()->create();
    $gallery = Gallery::factory()->create();
    $set = app(SetAssetHidden::class);

    $set($gallery, $contact, 'k', true);
    $set($gallery, $contact, 'k', true);
    expect(AssetHide::count())->toBe(1);

    $set($gallery, $contact, 'k', false);
    expect(AssetHide::count())->toBe(0);
});

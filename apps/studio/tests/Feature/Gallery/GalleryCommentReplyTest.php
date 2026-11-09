<?php

use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Enums\StaffRole;
use App\Gallery\Models\AssetComment;
use App\Gallery\Models\Gallery;
use App\Gallery\Notifications\AssetCommentReplied;
use App\Media\Models\Asset;
use App\Models\StudioSettings;
use App\Models\User;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->staff = User::factory()->withMfa()->create(['role' => StaffRole::Admin, 'name' => 'Christian']);
    $this->actingAs($this->staff, 'staff');
    $this->gallery = Gallery::factory()->for(ClientCase::factory(), 'case')->create(['title' => 'Hochzeit']);
    Asset::factory()->create([
        'gallery_id' => $this->gallery->id,
        'gallery_set_id' => $this->gallery->sets()->first()->id,
        'key' => 'galleries/g/a.jpg', 'filename' => 'a.jpg',
    ]);
    $this->version = $this->gallery->publish();
    $this->contact = Contact::factory()->create(['email' => 'jana@example.test']);
    $this->comment = AssetComment::factory()->create([
        'gallery_version_id' => $this->version->id, 'asset_key' => 'galleries/g/a.jpg', 'contact_id' => $this->contact->id,
    ]);
    $this->url = "/galerien/{$this->gallery->public_id}/kommentare/{$this->comment->public_id}/antwort";
});

it('stores the reply as a studio comment on the same photo and mails the commenting contact', function () {
    $this->post($this->url, ['body' => 'Gern als Leinwand.'])->assertRedirect();

    $reply = AssetComment::query()->whereNotNull('user_id')->sole();
    expect($reply->asset_key)->toBe('galleries/g/a.jpg')
        ->and($reply->user_id)->toBe($this->staff->id)
        ->and($reply->guest_identifier)->toBeNull();

    Notification::assertSentTo($this->contact, AssetCommentReplied::class);

    $event = collect(props("/galerien/{$this->gallery->public_id}/aktivitaet")['activity']['events'])->firstWhere('studio', true);
    expect($event['who'])->toBe('Christian');
});

it('shows the studio name as author on the portal and never mails a guest', function () {
    $this->gallery->update(['permissions' => ['guest' => ['comments' => true]]]);
    StudioSettings::current()->forceFill(['display_name' => 'Atelier Auri'])->save();
    $guest = AssetComment::factory()->create([
        'gallery_version_id' => $this->version->id, 'asset_key' => 'galleries/g/a.jpg',
    ]);

    $this->post("/galerien/{$this->gallery->public_id}/kommentare/{$guest->public_id}/antwort", ['body' => 'Danke'])->assertRedirect();

    Notification::assertNothingSent();
    useSurface(Surface::API);
    $authors = $this->getJson("/api/gallery-versions/{$this->version->public_id}/comments?asset_key=galleries/g/a.jpg")
        ->json('comments.*.author');
    expect($authors)->toContain('Atelier Auri');
});

it('refuses a reply from staff who cannot manage the gallery or when clients cannot comment', function () {
    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Reader]), 'staff')
        ->post($this->url, ['body' => 'x'])
        ->assertForbidden();

    $this->actingAs($this->staff, 'staff');
    $this->gallery->update(['permissions' => ['client' => ['comments' => false]]]);
    $this->post($this->url, ['body' => 'x'])->assertStatus(422);

    expect(AssetComment::count())->toBe(1);
});

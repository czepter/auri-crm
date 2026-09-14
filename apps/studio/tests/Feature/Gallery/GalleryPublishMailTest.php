<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Enums\StaffRole;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Gallery\Notifications\GalleryPublished;
use App\Media\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Owner]), 'staff');
    $this->contact = Contact::factory()->create(['name' => 'Lena Brandt', 'email' => 'lena@example.test']);
    $this->gallery = Gallery::factory()->create();
    $this->gallery->project->attachContact($this->contact, ProjectContactRole::Primary);
    Asset::factory()->create([
        'gallery_id' => $this->gallery->id,
        'gallery_set_id' => GallerySet::where('gallery_id', $this->gallery->id)->first()->id,
        'key' => 'galleries/g/a.jpg', 'filename' => 'a.jpg',
    ]);
});

it('opens the compose screen with the primary contact as recipient', function () {
    $this->get("/galerien/{$this->gallery->public_id}/veroeffentlichen")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Galleries/Send')
            ->where('recipients.0.email', 'lena@example.test'));
});

it('publishes and mails the compose screen\'s subject and message', function () {
    Notification::fake();

    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen", [
        'subject' => 'Eure Galerie',
        'message' => "Hallo Lena,\n\neure Bilder sind da.",
    ])->assertRedirect("/galerien/{$this->gallery->public_id}");

    expect($this->gallery->fresh()->currentVersion->version)->toBe(1);

    Notification::assertSentTo($this->contact, function (GalleryPublished $notification) {
        $mail = $notification->toMail($this->contact);

        return $mail->subject === 'Eure Galerie'
            && $mail->actionUrl === $this->gallery->publicUrl()
            && str_contains((string) $mail->render(), 'eure Bilder sind da.');
    });
});

it('publishes without a mail when posted without a subject', function () {
    Notification::fake();

    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen")->assertRedirect();

    expect($this->gallery->fresh()->currentVersion->version)->toBe(1);
    Notification::assertNothingSent();
});

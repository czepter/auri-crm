<?php

use App\Crm\Enums\CaseContactRole;
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
    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Admin]), 'staff');
    $this->contact = Contact::factory()->create(['name' => 'Lena Brandt', 'email' => 'lena@example.test']);
    $this->gallery = Gallery::factory()->create();
    $this->gallery->case->attachContact($this->contact, CaseContactRole::Primary);
    Asset::factory()->create([
        'gallery_id' => $this->gallery->id,
        'gallery_set_id' => GallerySet::where('gallery_id', $this->gallery->id)->first()->id,
        'key' => 'galleries/g/a.jpg', 'filename' => 'a.jpg',
    ]);
});

it('publishes and mails the dialog\'s subject and message', function () {
    Notification::fake();

    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen", [
        'subject' => 'Eure Galerie',
        'message' => "Hallo Lena,\n\neure Bilder sind da.",
    ])->assertRedirect();

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

it('puts the view password in the mail only when asked', function (bool $include, bool $expected) {
    Notification::fake();
    $this->gallery->rotateViewPassword('Wiese-4821');

    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen", [
        'subject' => 'Eure Galerie',
        'message' => 'Hallo Lena',
        'include_password' => $include,
    ]);

    Notification::assertSentTo($this->contact, fn (GalleryPublished $notification) => str_contains((string) $notification->toMail($this->contact)->render(), 'Wiese-4821') === $expected);
})->with([[true, true], [false, false]]);

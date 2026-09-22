<?php

use App\Crm\Models\Contact;
use App\Enums\StaffRole;
use App\Models\User;
use App\Portal\Actions\ConsumeClientAccess;
use App\Portal\Models\ClientAccess;
use App\Portal\Notifications\PortalAccessInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Admin]), 'staff');
    $this->contact = Contact::factory()->create(['name' => 'Lena Brandt', 'email' => 'lena@example.test']);
});

it('mails a single-use link that is still live six days later', function () {
    Notification::fake();

    $this->post("/kontakte/{$this->contact->public_id}/portal-zugang/versenden", [
        'subject' => 'Euer Kundenbereich',
        'message' => "Hallo Lena,\n\nhier euer Zugang zum Kundenbereich.",
    ])->assertRedirect("/kontakte/{$this->contact->public_id}");

    $url = null;
    Notification::assertSentTo($this->contact, function (PortalAccessInvitation $notification) use (&$url) {
        $mail = $notification->toMail($this->contact);
        $url = $mail->actionUrl;

        return $mail->subject === 'Euer Kundenbereich'
            && str_contains((string) $mail->render(), 'hier euer Zugang zum Kundenbereich.');
    });

    $this->travel(6)->days();

    $consume = app(ConsumeClientAccess::class);
    $token = basename($url);

    expect($consume($token)?->contact_id)->toBe($this->contact->id)
        ->and($consume($token))->toBeNull()
        ->and(ClientAccess::count())->toBe(1);
});

it('refuses a contact whose access was revoked', function () {
    $this->contact->revokePortalAccess();

    $this->get("/kontakte/{$this->contact->public_id}/portal-zugang/senden")->assertNotFound();
    $this->post("/kontakte/{$this->contact->public_id}/portal-zugang/versenden", ['subject' => 'x'])->assertNotFound();
});

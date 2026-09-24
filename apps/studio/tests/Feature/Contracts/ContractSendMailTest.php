<?php

use App\Contracts\Enums\ContractState;
use App\Contracts\Jobs\RenderContractJob;
use App\Contracts\Models\Contract;
use App\Contracts\Notifications\ContractSent;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Documents\Models\DocumentTemplate;
use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Only the render: MailTransport queues the mail itself.
    Queue::fake([RenderContractJob::class]);

    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Admin]), 'staff');
    $this->case = ClientCase::factory()->create(['portal_visible' => true]);
    $this->contract = Contract::factory()
        ->for($this->case, 'case')
        ->for(DocumentTemplate::factory()->create(['body' => '<p>Vorlage</p>']), 'template')
        ->create(['state' => ContractState::Draft, 'version' => 1]);
    $this->lena = Contact::factory()->create(['name' => 'Lena Brandt', 'email' => 'lena@example.test']);
    $this->tom = Contact::factory()->create(['name' => 'Tom Brandt', 'email' => 'tom@example.test']);
    $this->contract->signers()->create(['contact_id' => $this->lena->id]);
    $this->contract->signers()->create(['contact_id' => $this->tom->id]);
});

it('opens the compose screen with every signer as recipient', function () {
    $this->get("/vertraege/{$this->contract->public_id}/senden")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Contracts/Send')
            ->where('recipients.0.email', 'lena@example.test')
            ->where('recipients.1.email', 'tom@example.test'));
});

it('sends the Fassung and mails every signer', function () {
    Notification::fake();

    $this->post("/vertraege/{$this->contract->public_id}/versenden", [
        'subject' => 'Euer Vertrag',
        'message' => "Hallo Lena und Tom,\n\nhier euer Vertrag zur Unterschrift.",
    ])->assertRedirect("/auftraege/{$this->case->public_id}/vertrag");

    expect($this->contract->fresh()->state)->toBe(ContractState::Valid);

    foreach ([$this->lena, $this->tom] as $contact) {
        Notification::assertSentTo($contact, function (ContractSent $notification) use ($contact) {
            $mail = $notification->toMail($contact);

            return $mail->subject === 'Euer Vertrag'
                && $mail->actionUrl === config('portal.url')."/projekte/{$this->case->public_id}/vertrag"
                && str_contains((string) $mail->render(), 'hier euer Vertrag zur Unterschrift.');
        });
    }
});

it('sends without a mail when posted without a subject', function () {
    Notification::fake();

    $this->post("/vertraege/{$this->contract->public_id}/versenden")->assertRedirect();

    expect($this->contract->fresh()->state)->toBe(ContractState::Valid);
    Notification::assertNothingSent();
});

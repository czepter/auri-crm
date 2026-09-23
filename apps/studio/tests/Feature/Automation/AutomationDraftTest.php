<?php

use App\Automation\Actions\FireAutomationRule;
use App\Automation\Enums\AutomationRecipient;
use App\Automation\Enums\AutomationRunStatus;
use App\Automation\Models\AutomationRule;
use App\Automation\Models\AutomationRun;
use App\Automation\Notifications\AutomationReminder;
use App\Crm\Enums\CaseContactRole;
use App\Crm\Events\ProjectBooked;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Enums\StaffRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();

    $this->owner = staffMember(StaffRole::Admin);
    $this->contact = Contact::factory()->create();
    $project = ClientCase::factory()->create(['name' => 'Hochzeit Meier', 'portal_visible' => true]);
    $project->attachContact($this->contact, CaseContactRole::Primary);

    $rule = AutomationRule::factory()->event(ProjectBooked::class)->create([
        'recipient_type' => AutomationRecipient::Client,
        'notification_subject' => 'Ihre Buchung',
        'notification_body' => 'Wir freuen uns auf {{ project.name }}.',
    ]);

    $this->draft = app(FireAutomationRule::class)($rule, $project, '2026-06-01');
});

it('lists the draft in the Posteingang', function () {
    $this->actingAs($this->owner, 'staff')
        ->get('/posteingang')
        ->assertInertia(fn ($page) => $page
            ->where('drafts.0.subject', 'Ihre Buchung')
            ->where('drafts.0.projectName', 'Hochzeit Meier'));
});

it('opens the draft on a compose screen', function () {
    $this->actingAs($this->owner, 'staff')
        ->get("/posteingang/entwuerfe/{$this->draft->public_id}/senden")
        ->assertInertia(fn ($page) => $page
            ->component('Automation/SendDraft')
            ->where('body', 'Wir freuen uns auf Hochzeit Meier.')
            ->where('recipients.0.email', $this->contact->email));
});

it('sends the edited text and records it', function () {
    $this->actingAs($this->owner, 'staff')
        ->post("/posteingang/entwuerfe/{$this->draft->public_id}/versenden", [
            'subject' => 'Eure Buchung',
            'message' => 'Bis bald.',
        ])
        ->assertRedirect('/posteingang');

    Notification::assertSentTo($this->contact, AutomationReminder::class, fn ($mail) => $mail->subject() === 'Eure Buchung' && $mail->body() === 'Bis bald.');

    expect($this->draft->fresh())->status->toBe(AutomationRunStatus::Sent)->subject->toBe('Eure Buchung');

    $this->actingAs($this->owner, 'staff')
        ->post("/posteingang/entwuerfe/{$this->draft->public_id}/versenden", ['subject' => 'Eure Buchung', 'message' => 'Bis bald.'])
        ->assertNotFound();

    Notification::assertSentToTimes($this->contact, AutomationReminder::class, 1);
});

it('discards a draft without sending', function () {
    $this->actingAs($this->owner, 'staff')
        ->post("/posteingang/entwuerfe/{$this->draft->public_id}/verwerfen")
        ->assertRedirect();

    expect(AutomationRun::sole()->status)->toBe(AutomationRunStatus::Skipped);
    Notification::assertNothingSent();
});

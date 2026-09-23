<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withMfa()->create(), 'staff');
});

it('writes in what the client said elsewhere and shows it on the case\'s Nachrichten tab', function () {
    Notification::fake();

    $project = ClientCase::factory()->create();
    $project->attachContact(Contact::factory()->create(['name' => 'Jan Martin Keil']), CaseContactRole::Primary);
    $lead = Lead::factory()->for($project, 'case')->create();

    $this->from("/anfragen/{$lead->case->public_id}/nachrichten")
        ->post("/auftraege/{$project->public_id}/nachrichten/nachtrag", [
            'body' => 'Wie wäre es diesen Samstag?',
            'sent_at' => '2026-06-25T18:32',
        ])
        ->assertRedirect("/anfragen/{$lead->case->public_id}/nachrichten");

    $props = props("/anfragen/{$lead->case->public_id}/nachrichten");

    expect($props['tab'])->toBe('messages')
        ->and($props['conversation']['messages'][0]['author'])->toBe('Jan Martin Keil')
        ->and($props['conversation']['messages'][0]['fromStaff'])->toBeFalse();

    Notification::assertNothingSent();
});

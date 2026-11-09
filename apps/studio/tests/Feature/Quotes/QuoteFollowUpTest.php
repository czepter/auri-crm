<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\Contact;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Quotes\Notifications\QuoteFollowUp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('fasst ein versendetes Angebot nach', function () {
    Notification::fake();

    [$user, $project] = studioWithProject();
    $contact = Contact::factory()->create(['email' => 'kunde@example.test']);
    $project->attachContact($contact, CaseContactRole::Primary);
    $quote = Quote::factory()->for($project, 'case')->create(['status' => QuoteStatus::Sent, 'sent_at' => now()->subDays(5)]);

    $this->actingAs($user, 'staff')->get("/angebote/{$quote->public_id}/nachfassen")->assertOk();

    $this->actingAs($user, 'staff')
        ->post("/angebote/{$quote->public_id}/nachfassen", ['subject' => 'Ihr Angebot', 'message' => "Hallo\n\nGibt es Fragen?"])
        ->assertRedirect();

    Notification::assertSentTo($contact, QuoteFollowUp::class);
});

it('fasst nur versendete Angebote nach', function () {
    [$user, $project] = studioWithProject();
    $quote = Quote::factory()->for($project, 'case')->create(['status' => QuoteStatus::Draft]);

    $this->actingAs($user, 'staff')->get("/angebote/{$quote->public_id}/nachfassen")->assertNotFound();
});

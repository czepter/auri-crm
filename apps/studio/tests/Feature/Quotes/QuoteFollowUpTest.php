<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\Contact;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
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

    DocumentTemplate::factory()->create([
        'type' => DocumentTemplateType::Mail,
        'name' => 'Nachfassen',
        'body' => '<p>Das Angebot {{ quote.number }} liegt seit {{ quote.sentOn }} bei Ihnen.</p>',
    ]);

    $this->actingAs($user, 'staff')->get("/angebote/{$quote->public_id}/nachfassen")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('mailTemplates', 1, fn ($template) => $template
            ->where('name', 'Nachfassen')
            ->where('body', "Das Angebot {$quote->number} liegt seit {$quote->sent_at->format('d.m.Y')} bei Ihnen.")
            ->etc()));

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

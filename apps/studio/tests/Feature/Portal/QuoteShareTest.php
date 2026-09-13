<?php

use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Models\AuditLog;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Settings\Models\StudioSettings;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::PORTAL);
    Cache::store('redis')->flush();
    StudioSettings::query()->update(['portal_accept_quotes_enabled' => true]);
});

function sharedSentQuote(array $attributes = []): Quote
{
    $project = Project::factory()->create(['portal_visible' => false]);
    $contact = Contact::factory()->create();

    return Quote::factory()->for($project)->create(array_merge([
        'status' => QuoteStatus::Sent,
        'sent_at' => now(),
        'valid_until' => now()->addDays(14),
        'recipient_contact_id' => $contact->id,
    ], $attributes));
}

it('shows a quote through its share link with no session and portal_visible off', function () {
    $quote = sharedSentQuote();

    $props = $this->get("/angebot/{$quote->share_token}/teilen")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['quote']['id'])->toBe($quote->public_id);
});

it('404s the share link for an unknown token', function () {
    sharedSentQuote();

    $this->get('/angebot/does-not-exist/teilen')->assertNotFound();
});

it('accepts a quote through its share link, crediting the recipient contact', function () {
    $quote = sharedSentQuote();

    $this->post("/angebot/{$quote->share_token}/teilen/annehmen")->assertRedirect();

    $quote->refresh();

    expect($quote->status)->toBe(QuoteStatus::Accepted)
        ->and($quote->accepted_by_contact_id)->toBe($quote->recipientContact->id);

    expect(AuditLog::where('event', 'quote.accepted')->where('subject', $quote->public_id)->exists())->toBeTrue();
});

it('404s an accept attempt for a quote with no recipient contact set', function () {
    $quote = sharedSentQuote(['recipient_contact_id' => null]);

    $this->post("/angebot/{$quote->share_token}/teilen/annehmen")->assertNotFound();

    expect($quote->fresh()->status)->toBe(QuoteStatus::Sent);
});

it('404s an accept attempt while the studio has accept_quotes switched off', function () {
    StudioSettings::query()->update(['portal_accept_quotes_enabled' => false]);
    $quote = sharedSentQuote();

    $this->post("/angebot/{$quote->share_token}/teilen/annehmen")->assertNotFound();

    expect($quote->fresh()->status)->toBe(QuoteStatus::Sent);
});

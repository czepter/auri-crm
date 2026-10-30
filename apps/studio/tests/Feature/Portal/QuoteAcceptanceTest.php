<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Models\AuditLog;
use App\Models\StudioSettings;
use App\Quotes\Actions\ReviseQuote;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

// T-08's "accept quotes online" switch defaults to off (see
// tests/Feature/Portal/CapabilitySwitchesTest.php for that default and the
// 404 it produces while off) — every test below exercises the accept action
// itself, so the switch is on for all of them. Cache::flush() first:
// StudioSettings::current() caches the row (T-01), and a row already cached by
// an earlier test in the same suite run would otherwise still read as off.
beforeEach(function () {
    useSurface(Surface::PORTAL);
    Cache::store('redis')->flush();
    StudioSettings::query()->update(['portal_accept_quotes_enabled' => true]);
});

/** A project, a contact attached to it, and a quote already sent to the client. */
function sentQuote(ClientCase $project, array $attributes = []): Quote
{
    return Quote::factory()->for($project, 'case')->create(array_merge([
        'status' => QuoteStatus::Sent,
        'number' => 'AN-2026-1',
        'sent_at' => now(),
        'valid_until' => now()->addDays(14),
    ], $attributes));
}

// --- The screen: reading the current quote --------------------------------

it('shows the project\'s currently sent quote on the portal screen', function () {
    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $quote = sentQuote($project);
    QuoteItem::factory()->for($quote)->create(['title' => 'Hochzeitsreportage', 'quantity' => 1, 'unit_price' => 440000]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/angebot")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['quote']['id'])->toBe($quote->public_id)
        ->and($props['quote']['status'])->toBe('sent')
        ->and($props['quote']['number'])->toBe('AN-2026-1')
        ->and($props['quote']['items'][0]['title'])->toBe('Hochzeitsreportage')
        ->and($props['quote']['expired'])->toBeFalse();
});

it('shows nothing sent yet as an empty state rather than an error', function () {
    $project = ClientCase::factory()->create(['portal_visible' => true]);
    Quote::factory()->for($project, 'case')->create(); // draft — never sent
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/angebot")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['quote'])->toBeNull();
});

it('flags a quote past its own valid_until as expired for display, without hiding it', function () {
    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $quote = sentQuote($project, ['valid_until' => now()->subDay()]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/angebot")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['quote']['id'])->toBe($quote->public_id)
        ->and($props['quote']['expired'])->toBeTrue();
});

// --- Acceptance: the happy path -------------------------------------------

it('accepts a sent, still-valid quote, recording accepted_at, accepted_ip and the acting contact', function () {
    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $quote = sentQuote($project);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    $this->actingAs($contact, 'portal')
        ->from("/projekte/{$project->public_id}/angebot")
        ->post("/projekte/{$project->public_id}/angebot/annehmen")
        ->assertRedirect();

    $quote->refresh();

    expect($quote->status)->toBe(QuoteStatus::Accepted)
        ->and($quote->accepted_at)->not->toBeNull()
        ->and($quote->accepted_ip)->toBe('127.0.0.1')
        ->and($quote->accepted_by_contact_id)->toBe($contact->id);

    $row = AuditLog::where('event', 'quote.accepted')->sole();
    expect($row->subject)->toBe($quote->public_id)
        ->and($row->context['contact'])->toBe($contact->public_id);
});

it('requires the AGB ticked once the studio attaches them', function () {
    StudioSettings::query()->update(['portal_legal_agb_url' => 'https://studio.test/agb', 'portal_legal_agb_attach' => true]);
    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $quote = sentQuote($project);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/angebot/annehmen")
        ->assertSessionHasErrors(['terms' => 'Bitte die AGB akzeptieren.']);
    expect($quote->fresh()->status)->toBe(QuoteStatus::Sent);

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/angebot/annehmen", ['terms' => true])
        ->assertSessionHasNoErrors();
    expect($quote->fresh()->status)->toBe(QuoteStatus::Accepted);
});

it('leaves the AGB tick out while they are not attached', function () {
    StudioSettings::query()->update(['portal_legal_agb_url' => 'https://studio.test/agb']);
    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $quote = sentQuote($project);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/angebot/annehmen")
        ->assertSessionHasNoErrors();
    expect($quote->fresh()->status)->toBe(QuoteStatus::Accepted);
});

it('is idempotent: a double submit accepts once', function () {
    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $quote = sentQuote($project);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    $this->actingAs($contact, 'portal')->post("/projekte/{$project->public_id}/angebot/annehmen")->assertRedirect();
    $firstAcceptedAt = $quote->fresh()->accepted_at;

    $this->actingAs($contact, 'portal')->post("/projekte/{$project->public_id}/angebot/annehmen")->assertRedirect();

    expect($quote->fresh()->accepted_at->eq($firstAcceptedAt))->toBeTrue()
        ->and(AuditLog::where('event', 'quote.accepted')->count())->toBe(1);
});

// --- The re-check happens on submit, not on render ------------------------
// The exact scenario Q-07 asks for: a quote renders fine while it is still
// valid, is invalidated afterwards, and only then is the accept request
// sent — the render already happened and cannot be undone, so only the
// submit's own re-check stands between an invalid quote and an acceptance.

it('rejects acceptance of a quote superseded after it was rendered, even though the render itself succeeded', function () {
    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $quote = sentQuote($project);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    // The render: still sent, still valid — nothing here has any reason to object.
    $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/angebot")
        ->assertOk();

    // Invalidated in the gap between render and submit: a staff member revises it.
    app(ReviseQuote::class)($quote);
    expect($quote->fresh()->status)->toBe(QuoteStatus::Superseded);

    // The same tab submits against the quote it already rendered.
    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/angebot/annehmen")
        ->assertSessionHasErrors(['quote' => 'Dieses Angebot wurde bereits durch eine neuere Version ersetzt.']);

    expect($quote->fresh()->status)->toBe(QuoteStatus::Superseded)
        ->and($quote->fresh()->accepted_at)->toBeNull();
});

it('rejects acceptance of a quote that expired after it was rendered, even though the render itself succeeded', function () {
    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $quote = sentQuote($project, ['valid_until' => now()->addDay()]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    // The render: still within validity — nothing here has any reason to object.
    $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/angebot")
        ->assertOk();

    // Time passes past valid_until before the same tab ever submits.
    $this->travelTo(now()->addDays(3));

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/angebot/annehmen")
        ->assertSessionHasErrors(['quote' => 'Dieses Angebot ist nicht mehr gültig.']);

    expect($quote->fresh()->status)->toBe(QuoteStatus::Sent)
        ->and($quote->fresh()->accepted_at)->toBeNull();
});

// --- Visibility: the 404-not-403 rule --------------------------------------

it('404s the quote screen for a contact not on the project', function () {
    $project = ClientCase::factory()->create(['portal_visible' => true]);
    sentQuote($project);
    $contact = Contact::factory()->create();

    $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/angebot")
        ->assertNotFound();
});

it('404s the quote screen for a project the studio has not made portal_visible', function () {
    $project = ClientCase::factory()->create(['portal_visible' => false]);
    sentQuote($project);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/angebot")
        ->assertNotFound();
});

it('404s an accept attempt for a contact not on the project', function () {
    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $quote = sentQuote($project);
    $contact = Contact::factory()->create();

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/angebot/annehmen")
        ->assertNotFound();

    expect($quote->fresh()->status)->toBe(QuoteStatus::Sent);
});

it('404s an accept attempt for a project the studio has not made portal_visible', function () {
    $project = ClientCase::factory()->create(['portal_visible' => false]);
    $quote = sentQuote($project);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/angebot/annehmen")
        ->assertNotFound();

    expect($quote->fresh()->status)->toBe(QuoteStatus::Sent);
});

it('404s an accept attempt when the project has no sent quote at all', function () {
    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/angebot/annehmen")
        ->assertNotFound();
});

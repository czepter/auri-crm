<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Documents\Models\DocumentView;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::PORTAL);
    Cache::store('redis')->flush();
});

function viewedQuote(): Quote
{
    return Quote::factory()->for(ClientCase::factory()->create(['portal_visible' => true]), 'case')->create([
        'status' => QuoteStatus::Sent,
        'sent_at' => now(),
    ]);
}

it('records nothing on the share link GET and one hashed row on the POST', function () {
    $quote = viewedQuote();

    $props = $this->get("/angebot/{$quote->share_token}/teilen")->assertOk()->viewData('page')['props'];

    expect(DocumentView::count())->toBe(0)
        ->and($props['viewUrl'])->toBe("/angebot/{$quote->share_token}/teilen/gesehen");

    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone)'])
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->post("/angebot/{$quote->share_token}/teilen/gesehen")
        ->assertNoContent();

    $view = DocumentView::sole();

    expect($view->quote_id)->toBe($quote->id)
        ->and($view->via)->toBe('link')
        ->and($view->counts)->toBeTrue()
        ->and($view->user_agent)->toBe('Mozilla/5.0 (iPhone)')
        ->and($view->ip_hash)->toHaveLength(16)->not->toContain('203.0.113.7');
});

it('records a portal opening as not counting inside a preview session', function () {
    $quote = viewedQuote();
    $contact = Contact::factory()->create();
    $quote->case->attachContact($contact, CaseContactRole::Primary);

    $this->actingAs($contact, 'portal')->get("/projekte/{$quote->case->public_id}/angebot")->assertOk();
    $this->actingAs($contact, 'portal')->withSession(['portal_preview' => true])
        ->get("/projekte/{$quote->case->public_id}/angebot")->assertOk();

    expect(DocumentView::orderBy('id')->pluck('counts')->all())->toBe([true, false])
        ->and(DocumentView::first()->via)->toBe('portal');
});

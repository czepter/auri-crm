<?php

use App\Crm\Models\ClientCase;
use App\Enums\StaffRole;
use App\Quotes\Actions\MarkQuoteSent;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Settings\Models\StudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    // tests/Pest.php hinterlegt für jeden Feature-Test eine Adresse; hier ist
    // genau deren Fehlen der Gegenstand.
    StudioSettings::query()->update([
        'portal_legal_widerruf_url' => null,
        'portal_legal_widerruf_family' => null,
    ]);
    Cache::store('redis')->flush();
});

function draftQuote(): Quote
{
    return Quote::factory()->for(ClientCase::factory(), 'case')->create(['status' => QuoteStatus::Draft]);
}

it('refuses to send while no Widerrufsbelehrung is set', function () {
    $quote = draftQuote();

    expect(fn () => app(MarkQuoteSent::class)($quote, 'mail', staffMember(StaffRole::Admin)))
        ->toThrow(ValidationException::class);

    expect($quote->fresh()->status)->toBe(QuoteStatus::Draft);
});

it('refuses the self-sent channel for the same reason', function () {
    $quote = draftQuote();

    expect(fn () => app(MarkQuoteSent::class)($quote, 'self', staffMember(StaffRole::Admin)))
        ->toThrow(ValidationException::class);
});

it('lets the quote out once an address is on file', function () {
    StudioSettings::query()->update(['portal_legal_widerruf_url' => 'https://example.test/widerruf']);
    Cache::store('redis')->flush();

    $quote = draftQuote();
    app(MarkQuoteSent::class)($quote, 'mail', staffMember(StaffRole::Admin));

    expect($quote->fresh()->status)->toBe(QuoteStatus::Sent);
});

it('takes a template as the source just as well', function () {
    StudioSettings::query()->update(['portal_legal_widerruf_family' => 'fam-widerruf-000000']);
    Cache::store('redis')->flush();

    $quote = draftQuote();
    app(MarkQuoteSent::class)($quote, 'mail', staffMember(StaffRole::Admin));

    expect($quote->fresh()->status)->toBe(QuoteStatus::Sent);
});

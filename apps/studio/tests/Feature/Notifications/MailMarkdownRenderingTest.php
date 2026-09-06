<?php

use App\Crm\Models\Contact;
use App\Gallery\Models\Gallery;
use App\Gallery\Notifications\GalleryGuestCode;
use App\Quotes\Models\Quote;
use App\Quotes\Notifications\QuoteSent;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders greeting markdown as a real heading instead of a literal hash', function () {
    $contact = Contact::factory()->create(['name' => 'Sophie Krause']);

    $html = (string) (new App\Portal\Notifications\ClientAccessLink($contact, 'https://example.test/zugang/abc', app(StudioSettingsResolver::class)))
        ->toMail($contact)
        ->render();

    expect((string) $html)
        ->toContain('>Hallo Sophie Krause,')
        ->not->toContain('># Hallo');
});

it('bolds the gallery guest code instead of leaving literal asterisks', function () {
    $gallery = Gallery::factory()->create(['title' => 'Testgalerie']);

    $html = (string) (new GalleryGuestCode($gallery, '482913', 'https://example.test/code', app(StudioSettingsResolver::class)))
        ->toMail((object) [])
        ->render();

    expect((string) $html)
        ->toContain('>482913</strong>')
        ->not->toContain('**482913**');
});

it('turns the unsubscribe markdown link into a real anchor', function () {
    $contact = Contact::factory()->create();
    $quote = Quote::factory()->create();

    $html = (string) (new QuoteSent($quote))->toMail($contact)->render();

    expect((string) $html)
        ->toContain('<a href="'.config('portal.url')."/abmelden/{$contact->public_id}".'"')
        ->not->toContain('[Benachrichtigungen abbestellen](');
});

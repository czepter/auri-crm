<?php

use App\Crm\Models\Contact;
use App\Portal\Notifications\ClientAccessLink;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('translates the action button subcopy instead of leaving Laravel\'s English default', function () {
    $contact = Contact::factory()->create(['name' => 'Sophie Krause']);

    $html = (new ClientAccessLink($contact, 'https://example.test/zugang/abc'))
        ->toMail($contact)
        ->render();

    expect((string) $html)
        ->toContain('Wenn der Button')
        ->not->toContain("If you're having trouble clicking");
});

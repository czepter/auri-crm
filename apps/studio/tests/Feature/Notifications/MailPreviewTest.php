<?php

use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Notifications\Support\MailPreview;
use App\Portal\Notifications\PortalAccessInvitation;
use App\Quotes\Models\Quote;
use App\Quotes\Notifications\QuoteSent;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the real mail with the message stand-in as its own paragraph', function () {
    $html = MailPreview::html(
        new PortalAccessInvitation('https://example.test/zugang/abc', '', MailPreview::MESSAGE, app(StudioSettingsResolver::class)),
        Contact::factory()->create(),
    );

    expect($html)
        ->toMatch('/<p[^>]*>'.MailPreview::MESSAGE.'<\/p>/')
        ->toMatch('/<p[^>]*>Der Link gilt 7 Tage und funktioniert einmal\.<\/p>/')
        ->toContain('href="https://example.test/zugang/abc"')
        ->not->toContain('&lt;table')
        ->not->toContain('Hello!')
        ->not->toContain('Regards,');
});

it('parses the lines after a button as markdown', function () {
    $contact = Contact::factory()->create();
    $project = Project::factory()->create(['portal_visible' => true]);

    $html = MailPreview::html(new QuoteSent(Quote::factory()->for($project)->create(), null, MailPreview::MESSAGE), $contact);

    expect($html)
        ->toContain('<a href="'.config('portal.url')."/abmelden/{$contact->public_id}".'"')
        ->not->toContain('[Benachrichtigungen abbestellen](');
});

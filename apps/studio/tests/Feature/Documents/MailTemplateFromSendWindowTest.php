<?php

use App\Documents\Models\DocumentTemplate;
use App\Models\StudioSettings;
use App\Notifications\Support\StudioMailFooter;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Settings\Actions\UpdateStudioSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('saves the send window text as a template with fields and makes it the standard', function () {
    [$user, $project] = studioWithProject();
    $quote = Quote::factory()->for($project, 'case')->create(['status' => QuoteStatus::Sent, 'sent_at' => now(), 'number' => 'AN-2026-7']);

    $this->actingAs($user, 'staff')->post('/dokumentvorlagen/email', [
        'name' => 'Kurz',
        'subject' => "Ihr Angebot {$quote->number}",
        'message' => "Das Angebot {$quote->number} liegt bei Ihnen.",
        'quote' => $quote->public_id,
        'occasion' => 'quote_follow_up',
    ])->assertRedirect();

    $template = DocumentTemplate::query()->where('name', 'Kurz')->firstOrFail();

    expect($template->body)->toContain('{{ quote.number }}')
        ->and($template->subject)->toBe('Ihr Angebot {{ quote.number }}')
        ->and(StudioSettings::current()->mail_defaults['quote_follow_up'])->toBe($template->family_id);

    $this->actingAs($user, 'staff')->get("/angebote/{$quote->public_id}/nachfassen")
        ->assertInertia(fn ($page) => $page
            ->where('subject', "Ihr Angebot {$quote->number}")
            ->where('message', "Das Angebot {$quote->number} liegt bei Ihnen."));
});

it('builds the mail footer from the frame settings', function () {
    app(UpdateStudioSetting::class)('mail_signature', 'Viele Grüße', null);
    app(UpdateStudioSetting::class)('mail_footer_legal', false, null);
    app(UpdateStudioSetting::class)('mail_footer_extra', 'Telefon 040 123', null);

    expect((new StudioMailFooter)())->toBe('Viele Grüße<br>Telefon 040 123');
});

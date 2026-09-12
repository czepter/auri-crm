<?php

use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('setzt versendet ohne Mail', function () {
    Notification::fake();

    [$user, $project] = studioWithProject();
    $quote = Quote::factory()->for($project)->create(['status' => QuoteStatus::Draft]);

    $this->actingAs($user, 'staff')->post("/angebote/{$quote->public_id}/versendet")->assertRedirect();

    $quote->refresh();

    expect($quote->status)->toBe(QuoteStatus::Sent)
        ->and($quote->sent_channel)->toBe('self')
        ->and($quote->vat_mode)->not->toBeNull();

    Notification::assertNothingSent();
});

<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Quotes\Actions\AcceptQuote;
use App\Quotes\Actions\MarkQuoteSent;
use App\Quotes\Actions\RenderQuotePdf;
use App\Quotes\Actions\UpdateQuote;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Quotes\Support\QuoteRange;
use App\Settings\Actions\UpdateStudioSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function sliderQuote(): Quote
{
    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $project->attachContact(Contact::factory()->create(), CaseContactRole::Primary);

    $quote = Quote::factory()->slider()->create([
        'case_id' => $project->id,
        'status' => QuoteStatus::Sent,
    ]);

    $quote->items()->createMany([
        ['title' => 'Reportage', 'quantity' => 1, 'unit_price' => null, 'sort_order' => 0],
        ['title' => 'Anfahrt', 'quantity' => 1, 'unit_price' => 15_000, 'sort_order' => 1],
        ['title' => 'Album', 'quantity' => 1, 'unit_price' => 40_000, 'sort_order' => 2, 'selectable' => true, 'selected' => true],
        ['title' => 'Analog', 'quantity' => 1, 'unit_price' => 25_000, 'sort_order' => 3, 'selectable' => true],
    ]);

    return $quote->fresh(['items', 'case']);
}

it('spans from the fewest hours without blocks to the most with all of them', function () {
    $quote = sliderQuote();

    // 4 h * 250 € + 150 € Anfahrt; 10 h * 250 € + 150 € + 400 € + 250 €.
    expect(QuoteRange::min($quote))->toBe(115_000)
        ->and(QuoteRange::max($quote))->toBe(330_000)
        ->and(QuoteRange::preselected($quote))->toBe(155_000);
});

it('writes hours, the chosen blocks and the total on acceptance', function () {
    Notification::fake();
    staffMember();

    $quote = sliderQuote();
    $album = $quote->items->firstWhere('title', 'Album');

    $accepted = app(AcceptQuote::class)(
        $quote,
        $quote->case->primaryContact(),
        '127.0.0.1',
        hours: 6,
        selectedItemIds: [$album->public_id],
    );

    // 6 h * 250 € + 150 € Anfahrt + 400 € Album.
    expect($accepted->hours)->toBe(6)
        ->and($accepted->total)->toBe(205_000)
        ->and($accepted->status)->toBe(QuoteStatus::Accepted)
        ->and($album->fresh()->selected)->toBeTrue()
        ->and($quote->items->firstWhere('title', 'Analog')->fresh()->selected)->toBeFalse();
});

it('refuses hours outside the range', function () {
    Notification::fake();
    $quote = sliderQuote();

    app(AcceptQuote::class)($quote, $quote->case->primaryContact(), '127.0.0.1', hours: 12);
})->throws(ValidationException::class);

it('refuses a line the studio never made switchable', function () {
    Notification::fake();
    $quote = sliderQuote();
    $fixed = $quote->items->firstWhere('title', 'Anfahrt');

    app(AcceptQuote::class)(
        $quote,
        $quote->case->primaryContact(),
        '127.0.0.1',
        hours: 5,
        selectedItemIds: [$fixed->public_id],
    );
})->throws(ValidationException::class);

it('taxes the preselection at send and the chosen amount at acceptance', function () {
    Notification::fake();
    staffMember();
    app(UpdateStudioSetting::class)('vat_mode', 'standard');
    app(UpdateStudioSetting::class)('vat_rate', 1900);

    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $project->attachContact(Contact::factory()->create(), CaseContactRole::Primary);
    $quote = Quote::factory()->slider()->create(['case_id' => $project->id]);

    app(UpdateQuote::class)($quote, [
        'pricing_mode' => 'choice',
        'hourly_rate' => 25_000,
        'min_hours' => 4,
        'max_hours' => 10,
        'hours' => 8,
        'items' => [['title' => 'Album', 'quantity' => 1, 'unit_price' => 40_000, 'selectable' => true, 'selected' => true]],
    ]);
    app(MarkQuoteSent::class)($quote->fresh(), 'email');

    // 8 h * 250 € + 400 € Album.
    expect($quote->fresh()->vat_amount)->toBe(45_600);

    $accepted = app(AcceptQuote::class)($quote->fresh(['items', 'case']), $project->primaryContact(), '127.0.0.1', hours: 6);

    // 6 h * 250 €, Album abgewaehlt.
    expect($accepted->net)->toBe(150_000)
        ->and($accepted->vat_amount)->toBe(28_500);
});

// Die Weiche: ohne Stundensatz aendert sich nichts.
it('leaves a classic quote untouched', function () {
    Notification::fake();
    staffMember();

    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $project->attachContact(Contact::factory()->create(), CaseContactRole::Primary);

    $quote = Quote::factory()->create([
        'case_id' => $project->id,
        'status' => QuoteStatus::Sent,
        'total' => 120_000,
    ]);

    $accepted = app(AcceptQuote::class)($quote, $project->primaryContact(), '127.0.0.1');

    expect($accepted->total)->toBe(120_000)
        ->and($accepted->hours)->toBeNull()
        ->and($quote->hasSlider())->toBeFalse();
});

it('takes the quote discount off the range and the accepted amount, and hides it in print on request', function () {
    Notification::fake();
    staffMember();

    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $project->attachContact(Contact::factory()->create(), CaseContactRole::Primary);
    $quote = Quote::factory()->slider()->create(['case_id' => $project->id]);

    app(UpdateQuote::class)($quote, [
        'pricing_mode' => 'choice',
        'hourly_rate' => 25_000,
        'min_hours' => 4,
        'max_hours' => 10,
        'hours' => 8,
        'discount_type' => 'percentage',
        'discount_value' => 1000,
        'discount_hidden' => true,
        'items' => [['title' => 'Album', 'quantity' => 1, 'unit_price' => 40_000, 'selectable' => true, 'selected' => true]],
    ]);

    // 4 h * 250 € = 1.000 €, 10 % runter.
    expect(QuoteRange::min($quote->fresh('items')))->toBe(90_000);

    app(MarkQuoteSent::class)($quote->fresh(), 'email');
    $accepted = app(AcceptQuote::class)($quote->fresh(['items', 'case']), $project->primaryContact(), '127.0.0.1', hours: 6);

    expect($accepted->total)->toBe(150_000)
        ->and($accepted->discount_amount)->toBe(15_000)
        ->and($accepted->net)->toBe(135_000);

    $html = app(RenderQuotePdf::class)->html($accepted->fresh(['items', 'case', 'paymentStages']));

    expect($html)->not->toContain('Rabatt')
        ->and($html)->toContain('1.350,00');
});

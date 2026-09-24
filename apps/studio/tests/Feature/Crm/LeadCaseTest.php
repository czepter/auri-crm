<?php

use App\Crm\Enums\LeadStage;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Models\User;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
});

function caseFor(LeadStage $stage = LeadStage::New, ?string $date = '2026-09-05'): Lead
{
    $project = Project::factory()->wedding()->create(['name' => 'Brandt & Erdmann']);
    Shoot::factory()->for($project)->primary()->on($date)->create();

    return Lead::factory()->for($project)->atStage($stage)->create([
        'inquiry_text' => 'Wir heiraten im September auf Hof Eggers.',
    ]);
}

// C-08 (#243): six stages are the view of the funnel, three groups the view
// of the day.
it('groups the queue by working state, not by funnel stage', function () {
    $open = caseFor(LeadStage::New);
    caseFor(LeadStage::QuoteSent, '2026-07-11');
    caseFor(LeadStage::QuoteAccepted, '2026-08-02');

    $groups = collect(props("/anfragen/{$open->public_id}")['queue'])->keyBy('key');

    expect($groups['unbeantwortet']['rows'])->toHaveCount(1)
        ->and($groups['angebot']['rows'])->toHaveCount(1)
        ->and($groups['angenommen']['rows'])->toHaveCount(1)
        ->and($groups['unbeantwortet']['rows'][0]['open'])->toBeTrue();
});

it('offers the stages to move to and lands back on the case', function () {
    $lead = caseFor();

    expect(collect(props("/anfragen/{$lead->public_id}")['stages'])->pluck('value'))
        ->toContain('quote_sent');

    $this->from("/anfragen/{$lead->public_id}")
        ->post("/anfragen/{$lead->public_id}/stufe", ['stage' => 'quote_sent'])
        ->assertRedirect("/anfragen/{$lead->public_id}");
});

it('carries what they wrote, who they are and when they want it', function () {
    $lead = caseFor();
    $lead->project->attachContact(
        Contact::factory()->create(['name' => 'Sarah Brandt', 'phone' => '0170 998877']),
        ProjectContactRole::Primary,
    );

    $props = props("/anfragen/{$lead->public_id}");

    expect($props['lead']['inquiryText'])->toContain('Hof Eggers')
        ->and($props['people'][0]['name'])->toBe('Sarah Brandt')
        ->and($props['people'][0]['role'])->toBe('primary')
        ->and($props['shoots'])->toHaveCount(1)
        ->and($props['shoots'][0]['date'])->toBe('2026-09-05');
});

// Without a quote the next move is choosing a package, so the screen offers
// the choice rather than an empty section.
it('offers the active packages until a quote exists, then the quote', function () {
    $lead = caseFor();

    expect(props("/anfragen/{$lead->public_id}")['quote'])->toBeNull();

    Quote::factory()->for($lead->project)->create(['total' => 590000, 'sent_at' => now()->subDay()]);

    $props = props("/anfragen/{$lead->public_id}");

    expect($props['quote']['total'])->toBe(590000)
        ->and($props['packages'])->toBe([]);
});

// A date is answered by what is already on it, not by a rule.
it('shows the week around the requested day, and says so when it is free', function () {
    $lead = caseFor(date: '2026-09-05');

    expect(props("/anfragen/{$lead->public_id}")['week'])->toBe([]);

    $other = Project::factory()->create(['name' => 'Petersen']);
    Shoot::factory()->for($other)->on('2026-09-06')->create();

    $week = props("/anfragen/{$lead->public_id}")['week'];

    expect($week)->toHaveCount(1)
        ->and($week[0]['what'])->toBe('Petersen')
        ->and($week[0]['projectId'])->toBe($other->public_id);
});

it('sends an unbooked project to its inquiry', function () {
    $lead = caseFor();

    $this->get("/auftraege/{$lead->project->public_id}")
        ->assertRedirect("/anfragen/{$lead->public_id}");
});

// C-10 (#245): editing is a state of the view, so it posts back to the same
// case. What it changes is the case's own fields.
it('saves occasion, source, text and the people from the case itself', function () {
    $lead = caseFor();
    $lead->project->attachContact(
        $contact = Contact::factory()->create(['name' => 'Sarah Brandt']),
        ProjectContactRole::Primary,
    );

    test()->patch("/anfragen/{$lead->public_id}", [
        'type' => 'portrait',
        'source' => 'website',
        'inquiry_text' => 'Neu diktiert.',
        'people' => [[
            'id' => $contact->public_id,
            'name' => 'Sarah Brandt-Erdmann',
            'email' => 's.brandt@example.test',
            'phone' => null,
        ]],
    ])->assertRedirect();

    expect($lead->fresh()->inquiry_text)->toBe('Neu diktiert.')
        ->and($lead->fresh()->source->value)->toBe('website')
        ->and($lead->project->fresh()->type->key)->toBe('portrait')
        ->and($contact->fresh()->name)->toBe('Sarah Brandt-Erdmann')
        ->and($contact->fresh()->email)->toBe('s.brandt@example.test');
});

it('saves the Sonstige detail from the case and drops it with the case', function () {
    $lead = caseFor();

    test()->patch("/anfragen/{$lead->public_id}", [
        'type' => 'other',
        'type_other' => 'Tierfotografie',
        'source' => 'other',
        'source_other' => 'Zeitungsartikel',
    ])->assertRedirect();

    expect($lead->project->fresh()->type_other)->toBe('Tierfotografie')
        ->and($lead->fresh()->source_other)->toBe('Zeitungsartikel');

    test()->patch("/anfragen/{$lead->public_id}", [
        'type' => 'wedding',
        'source' => 'website',
    ])->assertRedirect();

    expect($lead->project->fresh()->type_other)->toBeNull()
        ->and($lead->fresh()->source_other)->toBeNull();
});

// The rail saves one select at a time.
it('saves the source alone and leaves the occasion', function () {
    $lead = caseFor();
    $type = $lead->project->type->key;

    test()->patch("/anfragen/{$lead->public_id}", [
        'source' => 'other',
        'source_other' => 'Messe',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($lead->fresh()->source_other)->toBe('Messe')
        ->and($lead->project->fresh()->type->key)->toBe($type);
});

it('moves the shoot while it is an inquiry', function () {
    $lead = caseFor();
    $shoot = $lead->project->primaryShoot;

    test()->patch("/anfragen/{$lead->public_id}", [
        'type' => 'wedding',
        'source' => $lead->source->value,
        'shoots' => [[
            'id' => $shoot->public_id,
            'date' => '2026-09-12',
            'venue_name' => 'Hof Eggers',
            'venue_city' => 'Winsen',
            'call_time' => '13:00',
            'end_time' => null,
        ]],
    ])->assertRedirect();

    expect($shoot->fresh()->date->format('Y-m-d'))->toBe('2026-09-12')
        ->and($shoot->fresh()->venue_name)->toBe('Hof Eggers');
});

// Eine Verschiebung ist der häufigste Vorgang überhaupt. Sie war bis hierher
// ein 422, sobald gebucht war — das Datum stand dann als Vertragsinhalt fest
// und ließ sich nur auf einem anderen Bildschirm ändern.
it('moves a shoot after the project is booked', function () {
    $lead = caseFor();
    markBooked($lead->project);

    test()->patch("/anfragen/{$lead->public_id}", [
        'shoots' => [[
            'id' => $lead->project->primaryShoot->public_id,
            'date' => '2026-09-12',
            'venue_name' => 'Hof Eggers',
            'venue_city' => null,
            'call_time' => null,
            'end_time' => null,
        ]],
    ])->assertRedirect();

    expect($lead->project->primaryShoot->fresh()->date->format('Y-m-d'))->toBe('2026-09-12');
});

// --- The quote block carries every version, not only the current one ----

it('sends the superseded quote versions alongside the current one', function () {
    $lead = caseFor();
    $old = Quote::factory()->for($lead->project)->create([
        'status' => QuoteStatus::Superseded,
        'number' => 'AN-2026-001',
        'net' => 298000,
    ]);
    Quote::factory()->for($lead->project)->create(['status' => QuoteStatus::Sent, 'net' => 257200]);

    $props = props("/anfragen/{$lead->public_id}");

    expect($props['supersededQuotes'])->toHaveCount(1)
        ->and($props['supersededQuotes'][0]['id'])->toBe($old->public_id)
        ->and($props['supersededQuotes'][0]['number'])->toBe('AN-2026-001')
        ->and($props['supersededQuotes'][0]['net'])->toBe(298000);
});

it('sends an empty superseded list when a project has only one quote', function () {
    $lead = caseFor();
    Quote::factory()->for($lead->project)->create();

    expect(props("/anfragen/{$lead->public_id}")['supersededQuotes'])->toBe([]);
});

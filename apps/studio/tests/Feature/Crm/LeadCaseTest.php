<?php

use App\Crm\Enums\LeadStage;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Models\User;
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
    caseFor(LeadStage::ContractSent, '2026-08-02');

    $groups = collect(props("/anfragen/{$open->public_id}")['queue'])->keyBy('key');

    expect($groups['unbeantwortet']['rows'])->toHaveCount(1)
        ->and($groups['angebot']['rows'])->toHaveCount(1)
        ->and($groups['angenommen']['rows'])->toHaveCount(1)
        ->and($groups['unbeantwortet']['rows'][0]['open'])->toBeTrue();
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
        ->and($week[0]['what'])->toBe('Petersen');
});

<?php

use App\Crm\Actions\BookProject;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Lead;
use App\Models\User;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withMfa()->create(), 'staff');
});

it('writes the quote on the lead and stays there', function () {
    $project = ClientCase::factory()->create();
    $lead = Lead::factory()->for($project, 'case')->create();

    $this->from("/anfragen/{$lead->case->public_id}/angebot")
        ->post("/auftraege/{$project->public_id}/angebote")
        ->assertRedirect("/anfragen/{$lead->case->public_id}/angebot");

    $props = props("/anfragen/{$lead->case->public_id}/angebot");

    expect($props['tab'])->toBe('quotes')
        ->and($props['quotes'])->toHaveCount(1)
        ->and($props['header']['canManageQuotes'])->toBeTrue();
});

it('takes the payments from the accepted quote’s stages', function () {
    $project = ClientCase::factory()->create();
    $lead = Lead::factory()->for($project, 'case')->create();

    Quote::factory()->create(['case_id' => $project->id, 'net' => 90_000]);
    $accepted = Quote::factory()->create([
        'case_id' => $project->id,
        'status' => QuoteStatus::Accepted,
        'accepted_at' => now(),
        'net' => 215_000,
        'vat_mode' => 'small_business',
        'vat_rate' => 0,
        'vat_amount' => 0,
    ]);
    $accepted->paymentStages()->createMany([
        ['percent' => 25, 'label' => 'Bei der Zusage', 'minimum' => 80_000, 'sort_order' => 0],
        ['percent' => 75, 'label' => 'Nach der Lieferung', 'sort_order' => 1],
    ]);

    expect(props("/anfragen/{$lead->case->public_id}/angebot")['payments'])->toBe([
        'number' => $accepted->number,
        'rows' => [
            ['label' => 'Bei der Zusage', 'amount' => 80_000],
            ['label' => 'Nach der Lieferung', 'amount' => 135_000],
        ],
    ]);
});

it('shows the inquiry tabs until the booking and sends the booked inquiry to its project', function () {
    $project = ClientCase::factory()->create();
    $lead = Lead::factory()->for($project, 'case')->create();

    $header = props("/anfragen/{$lead->case->public_id}")['header'];

    expect(collect($header['tabs'])->pluck('key')->all())->toBe(['overview', 'quotes', 'messages'])
        ->and($header['queue'])->toBe([]);

    app(BookProject::class)($project, auth('staff')->user());

    test()->get("/anfragen/{$lead->case->public_id}")->assertRedirect("/auftraege/{$project->public_id}");
});

it('shows the draft quote on the overview', function () {
    $project = ClientCase::factory()->create();
    $lead = Lead::factory()->for($project, 'case')->create();
    $draft = Quote::factory()->create(['case_id' => $project->id, 'status' => QuoteStatus::Draft, 'sent_at' => null]);

    expect(props("/anfragen/{$lead->case->public_id}")['quote']['id'])->toBe($draft->public_id);
});

<?php

use App\Crm\Models\Contact;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// The sidebar's Angebote entry: every quote across every project, not one
// project's own tab.
it('lists quotes from every project, newest first', function () {
    [$user, $projectA] = studioWithProject();
    $projectB = App\Crm\Models\Project::factory()->create();

    $older = Quote::factory()->for($projectA)->create(['status' => QuoteStatus::Draft, 'created_at' => now()->subDay()]);
    $newer = Quote::factory()->for($projectB)->create(['status' => QuoteStatus::Sent]);

    $rows = test()->actingAs($user, 'staff')->get('/angebote')->assertOk()->viewData('page')['props']['quotes'];

    expect(collect($rows)->pluck('id')->all())->toBe([$newer->public_id, $older->public_id])
        ->and($rows[0]['statusLabel'])->toBe('Versendet')
        ->and($rows[1]['statusLabel'])->toBe('Entwurf');
});

// The overview links a row to its own screen, not into the project's
// Angebote tab — that page lists every quote of the project and the tools to
// write a new one, which is not what clicking one existing quote should open.
// It renders the very same QuoteWorkspace the tab does, scoped to this one
// quote, so a draft is still editable and a sent quote reads exactly as it
// does there — never a lesser, read-only summary of it.
it('shows a single quote on its own screen through the same workspace', function () {
    [$user, $project] = studioWithProject();
    $recipient = Contact::factory()->create(['name' => 'Nina Kaltenbach']);
    $quote = Quote::factory()->for($project)->create([
        'status' => QuoteStatus::Draft,
        'recipient_contact_id' => $recipient->id,
    ]);

    $page = test()->actingAs($user, 'staff')->get("/angebote/{$quote->public_id}")
        ->assertOk()->viewData('page');

    expect($page['component'])->toBe('Quotes/Show')
        ->and($page['props']['projectId'])->toBe($project->public_id)
        ->and($page['props']['projectName'])->toBe($project->name)
        ->and($page['props']['quotes'])->toHaveCount(1)
        ->and($page['props']['quotes'][0]['id'])->toBe($quote->public_id)
        ->and($page['props']['canManageQuotes'])->toBeTrue()
        ->and($page['props']['recipientName'])->toBe('Nina Kaltenbach');
});

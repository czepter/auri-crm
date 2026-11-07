<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\Shoot;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::PORTAL));

it('gives the quote page the booking it hangs off: date, venue and the way back', function () {
    $contact = Contact::factory()->create();
    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $project->attachContact($contact, CaseContactRole::Primary);
    Shoot::factory()->for($project, 'case')->on('2026-05-30')->create(['is_primary' => true, 'venue_name' => 'Elbspeicher', 'venue_city' => 'Hamburg']);
    Quote::factory()->for($project, 'case')->create(['status' => QuoteStatus::Sent, 'sent_at' => now()]);

    $props = test()->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/angebot")
        ->assertOk()->viewData('page')['props'];

    expect($props['project'])->toMatchArray([
        'date' => '2026-05-30',
        'venue' => 'Elbspeicher, Hamburg',
        'href' => "/projekte/{$project->public_id}",
    ]);
});

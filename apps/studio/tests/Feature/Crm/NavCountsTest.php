<?php

use App\Crm\Enums\LeadStage;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// The sidebar badges are the two lists' own counts. Counting rows the list
// then refuses to show is how the badge and the screen start disagreeing.
it('counts the inquiries and the projects the way their lists do', function () {
    $booked = ClientCase::factory()->wedding()->create();
    Lead::factory()->for($booked, 'case')->atStage(LeadStage::QuoteAccepted)->create();
    markBooked($booked);

    $open = ClientCase::factory()->wedding()->create();
    Lead::factory()->for($open, 'case')->create();

    $nav = test()->actingAs(User::factory()->withMfa()->create(), 'staff')
        ->get('/anfragen')->assertOk()->viewData('page')['props']['nav'];

    expect($nav)->toBe(['leads' => 1, 'unread' => 0, 'projects' => 1]);
});

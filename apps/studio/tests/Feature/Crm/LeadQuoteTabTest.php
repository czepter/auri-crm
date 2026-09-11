<?php

use App\Crm\Actions\BookProject;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withMfa()->create(), 'staff');
});

it('writes the quote on the lead and stays there', function () {
    $project = Project::factory()->create();
    $lead = Lead::factory()->for($project)->create();

    $this->from("/anfragen/{$lead->public_id}/angebot")
        ->post("/auftraege/{$project->public_id}/angebote")
        ->assertRedirect("/anfragen/{$lead->public_id}/angebot");

    $props = props("/anfragen/{$lead->public_id}/angebot");

    expect($props['tab'])->toBe('quotes')
        ->and($props['workspace']['quotes'])->toHaveCount(1)
        ->and($props['header']['canManageQuotes'])->toBeTrue();
});

it('shows the inquiry tabs until the booking and the project tabs after it', function () {
    $project = Project::factory()->create();
    $lead = Lead::factory()->for($project)->create();

    $header = props("/anfragen/{$lead->public_id}")['header'];

    expect(collect($header['tabs'])->pluck('key')->all())->toBe(['overview', 'quotes', 'messages'])
        ->and($header['queue'])->toBe([]);

    app(BookProject::class)($project, auth('staff')->user());

    expect(collect(props("/anfragen/{$lead->public_id}")['header']['tabs'])->pluck('key'))
        ->toContain('lead', 'shoots', 'contract');
});

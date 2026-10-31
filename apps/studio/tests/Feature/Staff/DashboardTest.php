<?php

use App\Crm\Enums\LeadStage;
use App\Crm\Enums\ShootStatus;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Lead;
use App\Crm\Models\Shoot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('puts the open inquiries and projects on the Übersicht', function () {
    $inquiry = Lead::factory()->for(ClientCase::factory(), 'case')->atStage(LeadStage::New, 5)->create();

    $project = ClientCase::factory()->create();
    markBooked($project);
    Shoot::factory()->for($project, 'case')->primary()->atStatus(ShootStatus::Editing)->on(now()->subDays(20)->toDateString())->create();

    $props = $this->actingAs(User::factory()->withMfa()->create(), 'staff')
        ->get('/')->assertOk()->viewData('page')['props'];

    $ids = fn (array $groups) => collect($groups)->flatMap(fn (array $group) => array_column($group['rows'], 'id'))->all();

    expect($ids($props['leads']))->toBe([$inquiry->case->public_id])
        ->and($ids($props['projects']))->toBe([$project->public_id]);
});

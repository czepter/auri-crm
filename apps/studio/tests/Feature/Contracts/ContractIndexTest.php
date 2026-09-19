<?php

use App\Contracts\Models\Contract;
use App\Enums\StaffRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists the contracts that went out and leaves the drafts at the project', function () {
    $project = bookedContactProject();

    $sent = Contract::factory()->for($project)->create();
    Contract::factory()->draft()->for($project)->create(['version' => 2]);

    $this->actingAs(staffMember(StaffRole::Owner), 'staff');

    $rows = props('/vertraege')['contracts'];

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['id'])->toBe($sent->public_id)
        ->and($rows[0]['projectName'])->toBe($project->name);
});

it('refuses the screen to a role without ManageContracts', function () {
    $this->actingAs(staffMember(StaffRole::SecondShooter), 'staff')
        ->get('/vertraege')
        ->assertForbidden();
});

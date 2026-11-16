<?php

use App\Contracts\Models\Contract;
use App\Enums\StaffRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists sent contracts and drafts with the date a draft is sorted by', function () {
    $project = bookedContactProject();

    $sent = Contract::factory()->for($project, 'case')->create();
    $draft = Contract::factory()->draft()->for($project, 'case')->create(['version' => 2]);

    $this->actingAs(staffMember(StaffRole::Admin), 'staff');

    $rows = collect(props('/vertraege')['contracts'])->keyBy('id');

    expect($rows)->toHaveCount(2)
        ->and($rows[$sent->public_id]['state'])->toBe('valid')
        ->and($rows[$draft->public_id]['state'])->toBe('draft')
        ->and($rows[$draft->public_id]['sentOn'])->toBeNull()
        ->and($rows[$draft->public_id]['createdOn'])->toBe($draft->created_at->format('Y-m-d'))
        ->and($rows[$sent->public_id]['projectName'])->toBe($project->name);
});

it('refuses the screen to a role without ManageContracts', function () {
    $this->actingAs(staffMember(StaffRole::Contributor), 'staff')
        ->get('/vertraege')
        ->assertForbidden();
});

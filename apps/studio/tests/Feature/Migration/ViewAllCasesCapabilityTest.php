<?php

use App\Crm\Models\ClientCase;
use App\Enums\StaffRole;
use App\Integration\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('keeps every case visible to a role and a token that held view_all_projects', function () {
    $migration = require database_path('migrations/2026_09_23_110100_rename_view_all_projects_capability.php');
    $migration->down();

    $reader = User::factory()->create(['role' => StaffRole::Reader]);
    $token = ApiToken::factory()->create();
    // Raw: the model refuses a scope Capability no longer names.
    DB::table('api_tokens')->where('id', $token->id)->update(['scopes' => json_encode(['manage_leads', 'view_all_projects'])]);
    ClientCase::factory()->create();
    ClientCase::factory()->booked()->create();

    $migration->up();

    expect(ClientCase::query()->visibleTo($reader->fresh())->count())->toBe(2)
        ->and($token->fresh()->scopes)->toBe(['manage_leads', 'view_all_cases']);
});

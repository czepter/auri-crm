<?php

use App\Crm\Enums\BookedVia;
use App\Crm\Models\ClientCase;
use App\Documents\Models\DocumentTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('moves every booking and its payment plan onto the right project', function () {
    $staff = User::factory()->create();
    $booked = ClientCase::factory()->create();
    $signed = ClientCase::factory()->create();
    $inquiry = ClientCase::factory()->create();

    $rename = require database_path('migrations/2026_09_23_110000_rename_projects_to_cases.php');
    $rename->down();
    $migration = require database_path('migrations/2026_09_23_100000_move_booking_onto_projects.php');
    $migration->down();

    $jobFor = fn (ClientCase $project, ?User $by, string $via, string $at) => DB::table('jobs')->insertGetId([
        'public_id' => 'job_'.$project->id,
        'project_id' => $project->id,
        'booked_at' => $at,
        'booked_by_id' => $by?->id,
        'booked_via' => $via,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $jobFor($booked, $staff, 'manual', '2026-05-02 10:00:00');
    $signedJob = $jobFor($signed, null, 'contract', '2026-06-01 09:00:00');

    $plan = DB::table('payment_plans')->insertGetId([
        'public_id' => 'plan_signed',
        'job_id' => $signedJob,
        'total' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // The sum trigger is deferred; ALTER TABLE refuses to run while its check is pending.
    DB::statement('SET CONSTRAINTS ALL IMMEDIATE');

    $migration->up();
    $rename->up();

    expect($booked->fresh()->booked_at->toDateString())->toBe('2026-05-02')
        ->and($booked->fresh()->booked_by_id)->toBe($staff->id)
        ->and($booked->fresh()->booked_via)->toBe(BookedVia::Manual)
        ->and($signed->fresh()->booked_via)->toBe(BookedVia::Contract)
        ->and($signed->fresh()->booked_by_id)->toBeNull()
        ->and($signed->fresh()->paymentPlan->id)->toBe($plan)
        ->and($booked->fresh()->paymentPlan)->toBeNull()
        ->and($inquiry->fresh()->isBooked())->toBeFalse();
});

it('rewrites job.bookedAt in stored templates to project.bookedAt', function () {
    $template = DocumentTemplate::factory()->create(['body' => '<p>gebucht am {{ job.bookedAt }}</p>']);

    (require database_path('migrations/2026_09_23_100100_rename_job_booked_at_merge_field.php'))->up();

    expect($template->fresh()->body)->toBe('<p>gebucht am {{ project.bookedAt }}</p>');
});

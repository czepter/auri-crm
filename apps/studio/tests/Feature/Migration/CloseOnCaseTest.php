<?php

use App\Crm\Enums\LeadOutcome;
use App\Crm\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('moves each closed lead\'s moment, outcome and reason onto its case and leaves open ones open', function () {
    $migration = require database_path('migrations/2026_09_23_120000_move_close_onto_cases.php');
    $lost = Lead::factory()->create();
    $declined = Lead::factory()->create();
    $open = Lead::factory()->create();
    $migration->down();

    DB::table('leads')->where('id', $lost->id)->update(['outcome' => 'lost', 'lost_at' => '2026-05-02 10:00:00+00', 'lost_reason' => 'Budget']);
    DB::table('leads')->where('id', $declined->id)->update(['outcome' => 'declined', 'lost_at' => '2026-06-01 09:00:00+00']);

    $migration->up();

    $lostCase = $lost->case->fresh();
    $declinedCase = $declined->case->fresh();

    expect($lostCase->closed_at->toIso8601String())->toBe('2026-05-02T10:00:00+00:00')
        ->and($lostCase->close_outcome)->toBe(LeadOutcome::Lost)
        ->and($lostCase->close_reason)->toBe('Budget')
        ->and($declinedCase->close_outcome)->toBe(LeadOutcome::Declined)
        ->and($declinedCase->close_reason)->toBeNull()
        ->and($open->case->fresh()->closed_at)->toBeNull();
});

<?php

namespace Database\Factories\Crm;

use App\Crm\Enums\CaptureChannel;
use App\Crm\Enums\LeadOutcome;
use App\Crm\Enums\LeadSource;
use App\Crm\Enums\LeadStage;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Lead;
use App\Crm\Models\Stage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'case_id' => ClientCase::factory(),
            'source' => fake()->randomElement(LeadSource::cases()),
            'stage' => LeadStage::New,
            'stage_entered_at' => now(),
            'inquiry_text' => fake()->paragraph(),
            'captured_via' => CaptureChannel::Phone,
            'captured_by_id' => User::factory(),
            'captured_at' => now(),
        ];
    }

    /** $daysAgo drives the board's stale colouring, so it is time in stage. */
    public function atStage(LeadStage|Stage|string $stage, ?int $daysAgo = null): static
    {
        return $this->state(fn () => [
            'stage' => $stage,
            'stage_entered_at' => now()->subDays($daysAgo ?? 0),
        ]);
    }

    public function lost(?string $reason = 'Budget', LeadOutcome $outcome = LeadOutcome::Lost): static
    {
        return $this->afterCreating(fn (Lead $lead) => $lead->case->forceFill([
            'closed_at' => now(),
            'close_outcome' => $outcome,
            'close_reason' => $reason,
        ])->save());
    }
}

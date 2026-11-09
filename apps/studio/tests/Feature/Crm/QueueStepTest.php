<?php

use App\Crm\Actions\OpenLeads;
use App\Crm\Enums\LeadStage;
use App\Crm\Enums\ShootStatus;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Lead;
use App\Crm\Models\Shoot;
use App\Crm\Screens\OpenCases;
use App\Models\User;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function stepOfLead(LeadStage $stage, ?string $date = '2026-09-05', ?QuoteStatus $quote = null): string
{
    $project = ClientCase::factory()->wedding()->create();
    Shoot::factory()->for($project, 'case')->primary()->on($date)->create();
    $lead = Lead::factory()->for($project, 'case')->atStage($stage)->create();
    $quote && Quote::factory()->for($project, 'case')->create(['status' => $quote]);

    return collect(app(OpenLeads::class)->queue())->pluck('rows')->flatten()
        ->firstWhere('id', $lead->case->public_id)->step;
}

it('names the next step of an inquiry by its group', function () {
    expect(stepOfLead(LeadStage::New))->toBe('reply')
        ->and(stepOfLead(LeadStage::Contacted, quote: QuoteStatus::Draft))->toBe('sendQuote')
        ->and(stepOfLead(LeadStage::QuoteSent, quote: QuoteStatus::Sent))->toBe('followUp')
        ->and(stepOfLead(LeadStage::QuoteAccepted, quote: QuoteStatus::Accepted))->toBe('book');
});

it('asks for the date before anything else', function () {
    expect(stepOfLead(LeadStage::QuoteAccepted, null))->toBe('date');
});

function stepOfCase(ShootStatus $status, string $date, ?string $label = null, bool $lateRate = false): string
{
    $project = ClientCase::factory()->create();
    markBooked($project);
    Shoot::factory()->for($project, 'case')->primary()->atStatus($status)->on($date)->create(['label' => $label]);

    if ($lateRate) {
        PaymentInstallment::factory()->create([
            'payment_plan_id' => PaymentPlan::factory()->create(['case_id' => $project->id]),
            'due_on' => now()->subDays(3)->toDateString(),
        ]);
    }

    return collect(app(OpenCases::class)->queue(User::factory()->withMfa()->create()))
        ->pluck('rows')->flatten()->firstWhere('id', $project->public_id)->step;
}

it('names the next step of a project by its group', function () {
    expect(stepOfCase(ShootStatus::Booked, now()->subDays(5)->toDateString()))->toBe('Fotografiert')
        ->and(stepOfCase(ShootStatus::Prep, now()->addDays(3)->toDateString(), 'Hochzeitstag'))->toBe('Fotografiert')
        ->and(stepOfCase(ShootStatus::Shot, now()->subDays(20)->toDateString()))->not->toBe('Fotografiert')
        ->and(stepOfCase(ShootStatus::Booked, now()->addMonths(4)->toDateString()))->toBe('Gebucht');
});

it('keeps the stage name on a project with a late rate', function () {
    expect(stepOfCase(ShootStatus::Editing, now()->subDays(20)->toDateString(), lateRate: true))->toBe('Bearbeitung');
});

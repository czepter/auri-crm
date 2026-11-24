<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\Shoot;
use App\Models\StudioSettings;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Portal\Actions\ReadClientNextStep;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::PORTAL);
    Cache::store('redis')->flush();
    StudioSettings::query()->update([
        'portal_payment_status_enabled' => true,
        'portal_accept_quotes_enabled' => true,
        'portal_sign_contracts_enabled' => true,
        'payments_stripe_enabled' => false,
        'contract_mode' => 'on_demand',
        'bank_iban' => 'DE89370400440532013000',
    ]);
    Cache::store('redis')->flush();
});

function nextStepsFor(ClientCase $project, Contact $contact): array
{
    return app(ReadClientNextStep::class)($project->fresh(), $contact);
}

function stepProject(?Contact &$contact = null): ClientCase
{
    $contact = Contact::factory()->create();
    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $project->attachContact($contact, CaseContactRole::Primary);

    return $project;
}

function stepQuote(ClientCase $project): void
{
    Quote::factory()->for($project, 'case')->create(['status' => QuoteStatus::Sent, 'sent_at' => now(), 'valid_until' => now()->addWeek()]);
}

it('offers the quote only while online acceptance is on', function () {
    $project = stepProject($contact);
    stepQuote($project);

    expect(collect(nextStepsFor($project, $contact))->pluck('kind')->all())->toBe(['quote']);

    StudioSettings::query()->update(['portal_accept_quotes_enabled' => false]);
    Cache::store('redis')->flush();

    expect(nextStepsFor($project, $contact))->toBe([]);
});

it('offers the contract only with a contract, a mode above off and online signing', function () {
    Storage::fake('local');
    $project = stepProject($contact);

    expect(nextStepsFor($project, $contact))->toBe([]);

    renderedContract($project);
    expect(collect(nextStepsFor($project, $contact))->pluck('kind')->all())->toBe(['contract']);

    StudioSettings::query()->update(['portal_sign_contracts_enabled' => false]);
    Cache::store('redis')->flush();
    expect(nextStepsFor($project, $contact))->toBe([]);

    StudioSettings::query()->update(['portal_sign_contracts_enabled' => true, 'contract_mode' => 'off']);
    Cache::store('redis')->flush();
    expect(nextStepsFor($project, $contact))->toBe([]);
});

it('offers an installment only once it is payable', function () {
    $project = stepProject($contact);
    markBooked($project);
    $plan = PaymentPlan::factory()->for($project, 'case')->create();
    $installment = PaymentInstallment::factory()->for($plan, 'plan')->create([
        'amount' => 50000,
        'due_on' => now()->addDays(30)->format('Y-m-d'),
        'status' => PaymentInstallmentStatus::Scheduled,
    ]);

    // Without Stripe the transfer details are the payment.
    expect(collect(nextStepsFor($project, $contact))->pluck('kind')->all())->toBe(['installment']);

    StudioSettings::query()->update(['payments_stripe_enabled' => true]);
    Cache::store('redis')->flush();
    expect(nextStepsFor($project, $contact))->toBe([]);

    $installment->update(['due_on' => now()->addDays(14)->format('Y-m-d')]);
    expect(collect(nextStepsFor($project, $contact))->pluck('kind')->all())->toBe(['installment']);
});

it('names the next appointment when there is no step', function () {
    $project = stepProject($contact);
    Shoot::factory()->for($project, 'case')->on(now()->subWeek()->format('Y-m-d'))->create();
    $ahead = Shoot::factory()->for($project, 'case')->on(now()->addWeek()->format('Y-m-d'))->create(['call_time' => '14:30:00']);

    $appointment = app(ReadClientNextStep::class)->appointment($project->fresh());

    expect(nextStepsFor($project, $contact))->toBe([])
        ->and($appointment['date'])->toBe($ahead->date->format('Y-m-d'))
        ->and($appointment['callTime'])->toBe('14:30');

    $project->shoots()->delete();
    expect(app(ReadClientNextStep::class)->appointment($project->fresh()))->toBeNull();
});

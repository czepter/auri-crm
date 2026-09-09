<?php

use App\Crm\Enums\LeadSource;
use App\Crm\Enums\LeadStage;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Enums\StaffRole;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Quotes\Support\QuoteAcceptanceRate;
use App\Reporting\Support\BookingFunnel;
use App\Reporting\Support\CashFlowForecast;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- S-13: the acceptance rate has ONE definition -----------------------

it('gives the Übersicht tile and the Auswertungen screen the same acceptance rate', function () {
    Quote::factory()->count(4)->create(['sent_at' => now()->subMonths(2), 'status' => QuoteStatus::Sent]);
    Quote::factory()->create(['sent_at' => now()->subMonths(2), 'status' => QuoteStatus::Accepted]);

    $tile = QuoteAcceptanceRate::trailingTwelveMonths();
    $screen = QuoteAcceptanceRate::between(now()->subYear(), now());

    expect($tile)->toBe(20)->and($screen)->toBe($tile);
});

it('keys the acceptance rate on when the quote was SENT, so it can never exceed 100%', function () {
    // Sent thirteen months ago, accepted last week: outside the window on
    // both sides, never inside on the numerator alone.
    Quote::factory()->create([
        'sent_at' => now()->subMonths(13),
        'accepted_at' => now()->subWeek(),
        'status' => QuoteStatus::Accepted,
    ]);

    expect(QuoteAcceptanceRate::trailingTwelveMonths())->toBeNull();
});

it('reports null rather than zero when nothing went out', function () {
    expect(QuoteAcceptanceRate::trailingTwelveMonths())->toBeNull();
});

// --- BookingFunnel ------------------------------------------------------

it('keys the booking rate on capture, so a month cannot exceed 100%', function () {
    // Captured last month, booked today. Both sides of the ratio belong to
    // last month; a booking-dated numerator would give this month 100% off
    // an empty denominator.
    $captured = now()->subMonth();

    Lead::factory()->count(3)->create(['captured_at' => $captured, 'stage' => LeadStage::New]);
    Lead::factory()->create(['captured_at' => $captured, 'stage' => LeadStage::Booked]);

    $months = BookingFunnel::byMonth(now()->subYear(), now());
    $row = collect($months)->firstWhere('month', $captured->format('Y-m'));

    expect($row['captured'])->toBe(4)
        ->and($row['booked'])->toBe(1)
        ->and($row['rate'])->toBe(25)
        ->and(collect($months)->pluck('rate')->max())->toBeLessThanOrEqual(100);
});

it('counts a lead lost without a reason in the funnel and leaves it out of the breakdown', function () {
    Lead::factory()->lost()->create(['stage' => LeadStage::New]);
    Lead::factory()->lost(reason: '')->create(['lost_reason' => null, 'stage' => LeadStage::New]);

    $reasons = BookingFunnel::lostReasons(now()->subYear(), now());
    $captured = collect(BookingFunnel::byMonth(now()->subYear(), now()))->sum('captured');

    expect($captured)->toBe(2)
        ->and($reasons)->toHaveCount(1)
        ->and($reasons[0])->toBe([
            'outcome' => 'lost',
            'outcomeLabel' => 'Verloren',
            'reason' => 'Budget',
            'count' => 1,
        ]);
});

it('keys the loss breakdown on capture, like every other panel of the funnel', function () {
    Lead::factory()->lost()->create([
        'captured_at' => now()->subMonths(2),
        'stage' => LeadStage::New,
    ]);

    $reasons = BookingFunnel::lostReasons(now()->subMonths(3), now()->subMonth());

    expect($reasons)->toHaveCount(1);
});

it('labels each source from the enum rather than echoing the column', function () {
    Lead::factory()->create(['source' => LeadSource::Referral, 'stage' => LeadStage::New]);

    $sources = BookingFunnel::bySource(now()->subYear(), now());

    expect($sources[0]['source'])->toBe('referral')
        ->and($sources[0]['label'])->toBe(LeadSource::Referral->label());
});

it('splits Sonstige on its detail rather than pooling every odd source', function () {
    Lead::factory()->create(['source' => LeadSource::Other, 'source_other' => 'Zeitungsartikel', 'stage' => LeadStage::New]);
    Lead::factory()->count(2)->create(['source' => LeadSource::Other, 'source_other' => 'Firmenevent', 'stage' => LeadStage::New]);

    $sources = collect(BookingFunnel::bySource(now()->subYear(), now()));

    expect($sources)->toHaveCount(2)
        ->and($sources->firstWhere('source', 'other:Firmenevent')['captured'])->toBe(2)
        ->and($sources->firstWhere('source', 'other:Zeitungsartikel')['label'])
        ->toBe('Sonstige — Zeitungsartikel');
});

it('names who referred the work', function () {
    $referrer = Contact::factory()->create(['name' => 'Marie Vogt']);
    Lead::factory()->count(2)->create(['referred_by_contact_id' => $referrer->id, 'stage' => LeadStage::New]);

    expect(BookingFunnel::referrers(now()->subYear(), now()))
        ->toBe([['name' => 'Marie Vogt', 'count' => 2]]);
});

// --- M-07: the cash-flow forecast ---------------------------------------

it('keeps overdue money out of the current month', function () {
    $plan = PaymentPlan::factory()->create(['total' => 30_000]);
    PaymentInstallment::factory()->for($plan, 'plan')->create([
        'amount' => 30_000,
        'due_on' => now()->subMonths(3),
        'status' => PaymentInstallmentStatus::Scheduled,
    ]);

    $forecast = CashFlowForecast::build();

    expect($forecast['overdue'])->toBe(30_000)
        ->and($forecast['months'][0]['due'])->toBe(0);
});

it('books received money in its settlement month, not its due month', function () {
    $plan = PaymentPlan::factory()->create(['total' => 50_000]);
    PaymentInstallment::factory()->for($plan, 'plan')->create([
        'amount' => 50_000,
        'due_on' => now()->startOfMonth(),
        'paid_on' => now()->addMonth()->startOfMonth(),
        'status' => PaymentInstallmentStatus::Paid,
    ]);

    $forecast = CashFlowForecast::build();

    expect($forecast['months'][0]['due'])->toBe(0)
        ->and($forecast['months'][0]['received'])->toBe(0)
        ->and($forecast['months'][1]['received'])->toBe(50_000);
});

it('forecasts twelve months and every unpaid installment lands in exactly one bucket', function () {
    $plan = PaymentPlan::factory()->create(['total' => 90_000]);
    foreach ([-2, 1, 4] as $index => $offset) {
        PaymentInstallment::factory()->for($plan, 'plan')->create([
            'amount' => 30_000,
            'due_on' => now()->addMonths($offset),
            'sort_order' => $index,
            'status' => PaymentInstallmentStatus::Scheduled,
        ]);
    }

    $forecast = CashFlowForecast::build();
    $total = $forecast['overdue'] + collect($forecast['months'])->sum('due');

    expect($forecast['months'])->toHaveCount(12)->and($total)->toBe(90_000);
});

// --- the screen ---------------------------------------------------------

it('renders the Auswertungen screen for a role that sees every project', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/auswertungen')
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];

            expect($props['cashFlow']['months'])->toHaveCount(12)
                ->and($props)->toHaveKeys(['funnelByMonth', 'sources', 'lostReasons', 'perContact', 'revenuePrior']);
        });
});

it('refuses a role that only reaches the projects it is crewed on', function () {
    $this->actingAs(staffMember(StaffRole::SecondShooter), 'staff')
        ->get('/auswertungen')
        ->assertForbidden();
});

it('shows an empty studio empty figures rather than zeroes', function () {
    Project::query()->delete();

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/auswertungen')
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];

            expect($props['acceptanceRate'])->toBeNull()
                ->and($props['revenue']['averagePerProject'])->toBeNull()
                ->and($props['funnelByMonth'])->toBe([]);
        });
});

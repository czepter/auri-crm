<?php

use App\Crm\Enums\LeadSource;
use App\Crm\Enums\LeadStage;
use App\Crm\Enums\ProjectType;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Crm\Models\Shoot;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Quotes\Support\QuoteAcceptanceRate;
use App\Reporting\Enums\ReportPeriod;
use App\Reporting\Support\BookingFunnel;
use App\Reporting\Support\CashFlowForecast;
use App\Reporting\Support\FunnelTimes;
use App\Reporting\Support\RevenueReport;
use App\Reporting\Support\ShootReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Ein Schrittwechsel im Trail, so wie AdvanceLeadStage ihn schreibt. */
function stageMove(Lead $lead, LeadStage $from, LeadStage $to, $at): void
{
    AuditLog::create([
        'event' => 'lead.stage_changed',
        'subject' => $lead->case->public_id,
        'case_id' => $lead->case_id,
        'context' => ['from' => $from->value, 'to' => $to->value],
        'created_at' => $at,
    ]);
}

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

it('puts every month of the window on the funnel, including the ones without a lead', function () {
    Lead::factory()->create(['captured_at' => now(), 'stage' => LeadStage::New]);

    $months = collect(BookingFunnel::byMonth(now()->subMonths(3), now()));

    expect($months)->toHaveCount(4)
        ->and($months->pluck('month')->all())->toBe([
            now()->subMonths(3)->format('Y-m'),
            now()->subMonths(2)->format('Y-m'),
            now()->subMonth()->format('Y-m'),
            now()->format('Y-m'),
        ])
        ->and($months->first()['captured'])->toBe(0)
        ->and($months->first()['rate'])->toBeNull();
});

it('counts a lead lost without a reason in the funnel and leaves it out of the breakdown', function () {
    Lead::factory()->lost()->create(['stage' => LeadStage::New]);
    Lead::factory()->lost(reason: null)->create(['stage' => LeadStage::New]);

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

it('books the past series on the settlement month and counts the projects behind it', function () {
    $paid = PaymentPlan::factory()->create(['total' => 60_000]);
    PaymentInstallment::factory()->for($paid, 'plan')->create([
        'amount' => 60_000,
        'due_on' => now()->subMonths(4),
        'paid_on' => now()->subMonths(3),
        'sort_order' => 0,
        'status' => PaymentInstallmentStatus::Paid,
    ]);

    // Unbezahlt und im selben Fenster fällig: gehört in die Prognose, nicht
    // in das, was hereinkam.
    PaymentInstallment::factory()->for(PaymentPlan::factory()->create(['total' => 30_000]), 'plan')->create([
        'amount' => 30_000,
        'due_on' => now()->subMonths(3),
        'sort_order' => 0,
        'status' => PaymentInstallmentStatus::Scheduled,
    ]);

    $past = CashFlowForecast::past();

    expect($past['months'])->toHaveCount(12)
        ->and(collect($past['months'])->sum('received'))->toBe(60_000)
        ->and(collect($past['months'])->firstWhere('month', now()->subMonths(3)->format('Y-m'))['received'])->toBe(60_000)
        ->and($past['projects'])->toBe(1);
});

it('renders the Auswertungen screen for a role that sees every project', function () {
    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get('/auswertungen')
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];

            expect($props['cashFlow']['months'])->toHaveCount(12)
                ->and($props)->toHaveKeys(['funnelByMonth', 'sources', 'lostReasons', 'perContact', 'revenuePrior']);
        });
});

it('refuses a role that only reaches the projects it is crewed on', function () {
    $this->actingAs(staffMember(StaffRole::Contributor), 'staff')
        ->get('/auswertungen')
        ->assertForbidden();
});

it('shows an empty studio empty figures rather than zeroes', function () {
    ClientCase::query()->delete();

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get('/auswertungen')
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];

            expect($props['acceptanceRate'])->toBeNull()
                ->and($props['revenue']['averagePerProject'])->toBeNull()
                ->and(collect($props['funnelByMonth'])->sum('captured'))->toBe(0);
        });
});

// --- Zeitraum und Projekttyp -------------------------------------------

it('compares against the window before it, not against a fixed prior year', function () {
    $now = CarbonImmutable::parse('2026-09-18 12:00:00');

    ['from' => $from, 'to' => $to] = ReportPeriod::SixMonths->window($now);
    ['from' => $priorFrom, 'to' => $priorTo] = ReportPeriod::SixMonths->priorWindow($now);

    expect($from->toDateString())->toBe('2026-03-18')
        ->and($to->toDateString())->toBe('2026-09-18')
        ->and($priorTo->toDateString())->toBe($from->toDateString())
        ->and($priorFrom->toDateString())->toBe('2025-09-18');
});

it('narrows every figure to one project type when the filter asks for one', function () {
    $wedding = ClientCase::factory()->create(['type' => ProjectType::Wedding]);
    $portrait = ClientCase::factory()->create(['type' => ProjectType::Portrait]);

    $second = ClientCase::factory()->create(['type' => ProjectType::Wedding]);

    foreach ([$wedding, $second, $portrait] as $project) {
        Lead::factory()->create(['case_id' => $project->id, 'captured_at' => now()->subMonth()]);
    }

    $all = collect(BookingFunnel::byMonth(now()->subYear(), now()))->sum('captured');
    $only = collect(BookingFunnel::byMonth(now()->subYear(), now(), ProjectType::Wedding))->sum('captured');

    expect($all)->toBe(3)->and($only)->toBe(2);
});

it('keeps the filter in the URL so a filtered report is a link', function () {
    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get('/auswertungen?zeitraum=6m&typ=portrait')
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];

            expect($props['filters'])->toBe(['period' => '6m', 'type' => 'portrait'])
                ->and($props)->toHaveKeys(['steps', 'dwell', 'responseTimes', 'shootsByMonth', 'leadTimes', 'revenueByType']);
        });
});

// --- FunnelTimes --------------------------------------------------------

it('counts a lead that skipped a step as having passed it', function () {
    // Am Telefon zugesagt: Neu -> Gebucht, ohne Angebot. Ein Trichter, der
    // "Angebot" mit 0 ausweist, stünde unter seinem eigenen Ergebnis.
    $lead = Lead::factory()->create(['captured_at' => now()->subMonths(2), 'stage' => LeadStage::Booked]);

    stageMove($lead, LeadStage::New, LeadStage::Booked, now()->subMonth());

    $steps = collect(FunnelTimes::steps(now()->subYear(), now()))->keyBy('key');

    expect($steps['new']['leads'])->toBe(1)
        ->and($steps['quote_sent']['leads'])->toBe(1)
        ->and($steps['booked']['leads'])->toBe(1);
});

it('measures the first answer from capture, and counts the unanswered separately', function () {
    $answered = Lead::factory()->create(['captured_at' => now()->subDays(10)]);
    stageMove($answered, LeadStage::New, LeadStage::Contacted, now()->subDays(10)->addHours(5));

    Lead::factory()->create(['captured_at' => now()->subDays(9)]);

    $response = FunnelTimes::responseTimes(now()->subYear(), now());

    expect(collect($response['buckets'])->firstWhere('key', 'day')['leads'])->toBe(1)
        ->and($response['unanswered'])->toBe(1)
        ->and($response['medianHours'])->toBe(5.0);
});

it('reads the time in a step as the gap to the move before it', function () {
    $lead = Lead::factory()->create(['captured_at' => now()->subDays(20)]);
    stageMove($lead, LeadStage::New, LeadStage::Contacted, now()->subDays(18));
    stageMove($lead, LeadStage::Contacted, LeadStage::QuoteSent, now()->subDays(12));

    $dwell = collect(FunnelTimes::dwell(now()->subYear(), now()))->keyBy('key');

    expect($dwell['new']['days'])->toBe(2.0)
        ->and($dwell['contacted']['days'])->toBe(6.0);
});

// --- ShootReport --------------------------------------------------------

it('buckets the lead time on the primary shoot and counts the dateless apart', function () {
    $booked = ClientCase::factory()->create(['type' => ProjectType::Wedding]);
    markBooked($booked, ['booked_at' => now()]);
    Lead::factory()->create(['case_id' => $booked->id, 'captured_at' => now()->subMonths(2)]);
    Shoot::factory()->create(['case_id' => $booked->id, 'is_primary' => true, 'date' => now()->addMonths(5)]);

    $dateless = ClientCase::factory()->create(['type' => ProjectType::Wedding]);
    markBooked($dateless, ['booked_at' => now()]);
    Lead::factory()->create(['case_id' => $dateless->id, 'captured_at' => now()->subMonth()]);
    Shoot::factory()->create(['case_id' => $dateless->id, 'is_primary' => true, 'date' => null]);

    $times = ShootReport::leadTimes(now()->subYear(), now());

    expect(collect($times['buckets'])->firstWhere('key', 'q3')['projects'])->toBe(1)
        ->and($times['withoutDate'])->toBe(1);
});

it('reads the lead-time steps in the unit most of the lead times fall into', function () {
    // Drei Aufträge, alle wenige Wochen vorher gebucht: eine Monatsachse
    // legte alle drei in die erste Stufe.
    foreach ([18, 25, 40] as $days) {
        $project = ClientCase::factory()->create(['type' => ProjectType::Portrait]);
        markBooked($project, ['booked_at' => now()]);
        // Der Termin ist ein Datum ohne Uhrzeit: ohne startOfDay misst der
        // Abstand die Tageszeit mit und der Median kippt um einen Tag.
        $captured = now()->subMonths(2)->startOfDay();

        Lead::factory()->create(['case_id' => $project->id, 'captured_at' => $captured]);
        Shoot::factory()->create([
            'case_id' => $project->id,
            'is_primary' => true,
            'date' => $captured->copy()->addDays($days),
        ]);
    }

    $times = ShootReport::leadTimes(now()->subYear(), now());

    expect(collect($times['buckets'])->pluck('label')->all())->toBe([
        'unter 2 Wochen', '2–4 Wochen', '4–8 Wochen', '8–12 Wochen', 'über 12 Wochen',
    ])
        ->and(collect($times['buckets'])->firstWhere('key', 'q2')['projects'])->toBe(2)
        ->and(collect($times['buckets'])->firstWhere('key', 'q3')['projects'])->toBe(1)
        ->and($times['median'])->toBe('4 Wochen');
});

it('counts the shooting days by the date they fall on', function () {
    Shoot::factory()->create(['date' => now()->subMonth()]);
    Shoot::factory()->count(2)->create(['date' => now()]);
    Shoot::factory()->create(['date' => null]);

    $months = collect(ShootReport::byMonth(now()->subYear(), now()));

    expect($months->sum('shoots'))->toBe(3)
        ->and($months->firstWhere('month', now()->format('Y-m'))['shoots'])->toBe(2)
        // Ein Monat ohne Termin steht mit 0 in der Reihe statt zu fehlen.
        ->and($months->firstWhere('month', now()->subMonths(6)->format('Y-m'))['shoots'])->toBe(0);
});

// --- RevenueReport ------------------------------------------------------

it('averages each project type against its own bookings', function () {
    $wedding = ClientCase::factory()->create(['type' => ProjectType::Wedding]);
    $portrait = ClientCase::factory()->create(['type' => ProjectType::Portrait]);

    PaymentPlan::factory()->for(markBooked($wedding), 'case')
        ->create(['total' => 400_000]);
    PaymentPlan::factory()->for(markBooked($portrait), 'case')
        ->create(['total' => 60_000]);

    $rows = collect(RevenueReport::perProjectType(now()->subYear(), now()))->keyBy('type');

    expect($rows['wedding']['average'])->toBe(400_000)
        ->and($rows['portrait']['average'])->toBe(60_000)
        ->and($rows->keys()->first())->toBe('wedding');
});

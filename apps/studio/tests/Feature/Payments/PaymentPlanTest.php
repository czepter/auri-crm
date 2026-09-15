<?php

use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Numbering\Enums\DocumentType;
use App\Numbering\Models\NumberCounter;
use App\Payments\Actions\CreatePaymentPlan;
use App\Payments\Actions\UpdatePaymentPlan;
use App\Payments\Data\PaymentPlanData;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Payments\Support\PaymentInstallmentSplitter;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Visus\Cuid2\Cuid2;

uses(RefreshDatabase::class);

// contractProject(), renderedContract() and staffMember() live in tests/Pest.php.

/**
 * A booked project (contractProject() already gives it a Job) with a quote
 * the client has accepted — the agreement a plan is created against.
 */
function acceptedQuoteJob(): Job
{
    $project = contractProject();
    Quote::factory()->for($project)->create(['status' => QuoteStatus::Accepted, 'accepted_at' => now()]);

    return $project->job()->sole();
}

/** An installment with a number, a rendered PDF and a transfer already against it. */
function issuedInstallment(PaymentPlan $plan, int $amount): PaymentInstallment
{
    return PaymentInstallment::factory()->for($plan, 'plan')->create([
        'amount' => $amount,
        'due_on' => '2026-09-01',
        'sort_order' => 0,
        'status' => PaymentInstallmentStatus::Paid,
        'paid_on' => '2026-08-20',
        'invoice_number' => 'RE-2026-0001',
        'invoiced_at' => '2026-08-25 10:00:00',
        'invoice_path' => 'invoices/re-2026-0001.pdf',
    ]);
}

// --- PaymentInstallmentSplitter: the deterministic split -----------------

it('splits a total evenly across installments, with any remainder cent landing on the LAST one', function () {
    // 500 over 3 does not divide evenly: 166 + 166 + 168, not 167/167/166 —
    // the whole remainder (2 cents) goes on the last installment, named here
    // once so the test and the implementation cannot silently disagree.
    expect(PaymentInstallmentSplitter::evenly(500, 3))->toBe([166, 166, 168])
        ->and(PaymentInstallmentSplitter::evenly(101, 2))->toBe([50, 51])
        ->and(PaymentInstallmentSplitter::evenly(9000, 3))->toBe([3000, 3000, 3000]);
});

it('always produces exactly $count amounts summing to exactly $total, across many random combinations', function () {
    // The same seeded, many-iteration sweep QuoteTest.php uses for Q-03 —
    // no property-testing plugin ships with this repo's Pest.
    mt_srand(20260809);

    for ($run = 0; $run < 200; $run++) {
        $total = mt_rand(0, 10_000_000);
        $count = mt_rand(1, 12);

        $amounts = PaymentInstallmentSplitter::evenly($total, $count);

        expect($amounts)->toHaveCount($count)
            ->and(array_sum($amounts))->toBe($total);

        // Every installment but the last holds the same base share; the
        // last is that share plus whatever did not divide evenly — never
        // spread across more than one row.
        $base = intdiv($total, $count);
        foreach (array_slice($amounts, 0, -1) as $amount) {
            expect($amount)->toBe($base);
        }
        expect(end($amounts))->toBe($base + ($total - $base * $count));
    }
});

// --- CreatePaymentPlan -----------------------------------------------------

it('creates a plan whose installments sum exactly to the total, split evenly with the remainder on the last', function () {
    Storage::fake('local');
    $job = acceptedQuoteJob();

    $plan = app(CreatePaymentPlan::class)($job, 1000, ['2026-09-01', '2026-10-01', '2026-11-01']);

    expect($plan->fresh()->total)->toBe(1000)
        ->and($plan->installments()->sum('amount'))->toBe(1000)
        ->and($plan->installments()->orderBy('sort_order')->pluck('amount')->all())->toBe([333, 333, 334]);
});

it('burns no invoice number on creation — M-06 moved the Verwendungszweck onto the invoice itself', function () {
    // T-07 allocated an Invoice-type number here as the plan's reference.
    // Once real invoices existed, every one of those was a number no invoice
    // carried — a hole in the sequence a Betriebsprüfung asks about. The
    // client now transfers under the issued invoice's own number.
    Storage::fake('local');
    $job = acceptedQuoteJob();

    $plan = app(CreatePaymentPlan::class)($job, 500, ['2026-09-01']);

    expect($plan->fresh()->reference)->toBeNull()
        ->and(NumberCounter::where('document_type', DocumentType::Invoice->value)->count())->toBe(0);
});

it('logs the creation, naming the job and the total', function () {
    Storage::fake('local');
    $job = acceptedQuoteJob();

    $plan = app(CreatePaymentPlan::class)($job, 500, ['2026-09-01']);

    $row = AuditLog::where('event', 'payment_plan.created')->sole();
    expect($row->subject)->toBe($plan->public_id)
        ->and($row->context['job'])->toBe($job->public_id)
        ->and($row->context['total'])->toBe(500);
});

it('refuses to create a plan against a project with no quote', function () {
    $job = contractProject()->job()->sole(); // booked, but nothing offered

    app(CreatePaymentPlan::class)($job, 1000, ['2026-09-01']);
})->throws(HttpException::class);

it('refuses to create a plan against a quote that was sent but never accepted', function () {
    $project = contractProject();
    Quote::factory()->for($project)->create(['status' => QuoteStatus::Sent, 'sent_at' => now()]);

    app(CreatePaymentPlan::class)($project->job()->sole(), 1000, ['2026-09-01']);
})->throws(HttpException::class);

it('refuses a second plan for a job that already has one', function () {
    Storage::fake('local');
    $job = acceptedQuoteJob();
    app(CreatePaymentPlan::class)($job, 1000, ['2026-09-01']);

    app(CreatePaymentPlan::class)($job, 500, ['2026-10-01']);
})->throws(HttpException::class);

// --- UpdatePaymentPlan: wholesale replace, atomically ----------------------

it('drops an unissued row the payload does not name and creates the ones it adds', function () {
    $plan = PaymentPlan::factory()->create(['total' => 200]);
    $old = PaymentInstallment::factory()->for($plan, 'plan')->create(['amount' => 200, 'sort_order' => 0]);

    app(UpdatePaymentPlan::class)($plan, 900, [
        ['amount' => 300, 'due_on' => '2026-09-01', 'status' => 'scheduled'],
        ['amount' => 600, 'due_on' => '2026-10-01', 'status' => 'scheduled'],
    ]);

    expect($plan->fresh()->total)->toBe(900)
        ->and($plan->installments()->count())->toBe(2)
        ->and($plan->installments()->sum('amount'))->toBe(900)
        ->and(PaymentInstallment::find($old->id))->toBeNull();
});

it('is a real atomic transaction: the intermediate empty-installments state never becomes a committed fact', function () {
    // Proof, not assertion by inspection: force every deferred constraint
    // check pending in this session to run right now, immediately after the
    // action returns. If the delete-then-recreate inside
    // App\Payments\Actions\UpdatePaymentPlan were not one transaction, the
    // delete alone would already have failed before reaching here.
    $plan = PaymentPlan::factory()->create(['total' => 200]);
    PaymentInstallment::factory()->for($plan, 'plan')->create(['amount' => 200, 'sort_order' => 0]);

    app(UpdatePaymentPlan::class)($plan, 450, [
        ['amount' => 450, 'due_on' => '2026-09-01', 'status' => 'scheduled'],
    ]);

    expect(fn () => DB::statement('SET CONSTRAINTS ALL IMMEDIATE'))->not->toThrow(QueryException::class);
});

it('updates a named row in place, keeping the issued one whole', function () {
    // The #407 case: the plan reopens after the first installment is paid,
    // and the next edit must not take its number, its PDF or its payment
    // with it.
    $plan = PaymentPlan::factory()->create(['total' => 300]);
    $issued = issuedInstallment($plan, 100);
    $later = PaymentInstallment::factory()->for($plan, 'plan')
        ->create(['amount' => 200, 'due_on' => '2026-10-01', 'sort_order' => 1]);

    app(UpdatePaymentPlan::class)($plan, 400, [
        ['public_id' => $issued->public_id, 'amount' => 100, 'due_on' => '2026-09-15', 'status' => 'paid'],
        ['public_id' => $later->public_id, 'amount' => 300, 'due_on' => '2026-11-01', 'status' => 'scheduled'],
    ]);

    $issued->refresh();

    expect($issued->invoice_number)->toBe('RE-2026-0001')
        ->and($issued->invoice_path)->toBe('invoices/re-2026-0001.pdf')
        ->and($issued->amount)->toBe(100)
        ->and($issued->status)->toBe(PaymentInstallmentStatus::Paid)
        ->and($issued->paid_on->format('Y-m-d'))->toBe('2026-08-20')
        ->and($issued->due_on->format('Y-m-d'))->toBe('2026-09-15')
        ->and($later->fresh()->amount)->toBe(300)
        ->and($plan->fresh()->total)->toBe(400);
});

it('refuses to change an issued installment\'s amount, whoever writes it', function () {
    $plan = PaymentPlan::factory()->create(['total' => 100]);
    $issued = issuedInstallment($plan, 100);

    expect(fn () => $issued->update(['amount' => 200]))->toThrow(HttpException::class)
        ->and(fn () => $issued->update(['title' => 'Restbetrag']))->toThrow(HttpException::class)
        ->and($issued->fresh()->amount)->toBe(100);
});

it('audits a moved due date on an issued installment', function () {
    $plan = PaymentPlan::factory()->create(['total' => 100]);
    $issued = issuedInstallment($plan, 100);

    app(UpdatePaymentPlan::class)($plan, 100, [
        ['public_id' => $issued->public_id, 'amount' => 100, 'due_on' => '2026-12-24', 'status' => 'paid'],
    ]);

    $row = AuditLog::where('event', 'payments.installment.due_on_changed')->sole();

    expect($row->subject)->toBe($issued->public_id)
        ->and($row->context['from'])->toBe('2026-09-01')
        ->and($row->context['to'])->toBe('2026-12-24');
});

it('refuses to drop an issued installment out of the plan', function () {
    $plan = PaymentPlan::factory()->create(['total' => 300]);
    $issued = issuedInstallment($plan, 100);
    PaymentInstallment::factory()->for($plan, 'plan')->create(['amount' => 200, 'sort_order' => 1]);

    expect(fn () => app(UpdatePaymentPlan::class)($plan, 200, [
        ['amount' => 200, 'due_on' => '2026-10-01', 'status' => 'scheduled'],
    ]))->toThrow(HttpException::class);

    expect($issued->fresh())->not->toBeNull();
});

it('logs the update', function () {
    $plan = PaymentPlan::factory()->create(['total' => 200]);
    PaymentInstallment::factory()->for($plan, 'plan')->create(['amount' => 200, 'sort_order' => 0]);

    app(UpdatePaymentPlan::class)($plan, 300, [
        ['amount' => 300, 'due_on' => '2026-09-01', 'status' => 'scheduled'],
    ]);

    expect(AuditLog::where('event', 'payment_plan.updated')->where('subject', $plan->public_id)->exists())->toBeTrue();
});

// --- The database itself rejects a mismatched sum --------------------------

it('lets the database reject installments whose sum does not match the plan total, bypassing every app-level guard', function () {
    $plan = PaymentPlan::factory()->create(['total' => 10000]);

    expect(function () use ($plan) {
        DB::transaction(function () use ($plan) {
            DB::table('payment_installments')->insert([
                'public_id' => (string) new Cuid2,
                'payment_plan_id' => $plan->id,
                'amount' => 9999, // one cent short of the plan's total
                'due_on' => now()->toDateString(),
                'status' => 'scheduled',
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Forces the deferred constraint trigger to run now, inside this
            // still-open transaction, rather than waiting on a real COMMIT
            // that RefreshDatabase's own wrapping transaction never performs.
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        });
    })->toThrow(QueryException::class);

    expect($plan->installments()->count())->toBe(0);
});

it('lets the database reject a plan created with a nonzero total and no installments at all', function () {
    expect(function () {
        DB::transaction(function () {
            DB::table('payment_plans')->insert([
                'public_id' => (string) new Cuid2,
                'job_id' => Job::factory()->create()->id,
                'total' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        });
    })->toThrow(QueryException::class);
});

it('lets a plan whose installments genuinely sum to its total commit cleanly', function () {
    // The negative control: the same immediate-check technique proves the
    // trigger does not reject a plan that is actually correct.
    expect(function () {
        DB::transaction(function () {
            $planId = DB::table('payment_plans')->insertGetId([
                'public_id' => (string) new Cuid2,
                'job_id' => Job::factory()->create()->id,
                'total' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('payment_installments')->insert([
                'public_id' => (string) new Cuid2,
                'payment_plan_id' => $planId,
                'amount' => 100,
                'due_on' => now()->toDateString(),
                'status' => 'scheduled',
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        });
    })->not->toThrow(QueryException::class);
});

// --- The admin screen: creating and editing through the HTTP boundary -----

it('creates a payment plan through the admin screen, split evenly across the due dates given', function () {
    Storage::fake('local');
    $job = acceptedQuoteJob();
    $user = staffMember(StaffRole::Owner);

    $this->actingAs($user, 'staff')
        ->post("/auftraege/{$job->project->public_id}/zahlungsplan", [
            'total' => 100000,
            'installments' => [
                ['due_on' => '2026-09-01'],
                ['due_on' => '2026-10-01'],
            ],
        ])
        ->assertRedirect();

    $plan = $job->fresh()->paymentPlan;
    expect($plan)->not->toBeNull()
        ->and($plan->total)->toBe(100000)
        ->and($plan->installments()->sum('amount'))->toBe(100000);
});

it('refuses to create a plan for a project with no job at all', function () {
    $project = Project::factory()->create();
    $user = staffMember(StaffRole::Owner);

    $this->actingAs($user, 'staff')
        ->post("/auftraege/{$project->public_id}/zahlungsplan", [
            'total' => 1000,
            'installments' => [['due_on' => '2026-09-01']],
        ])
        ->assertNotFound();
});

it('updates a plan through the admin screen, rejecting a submission whose installments do not sum to the total', function () {
    $project = Project::factory()->create();
    $job = Job::factory()->for($project)->create();
    $plan = PaymentPlan::factory()->for($job)->create(['total' => 200]);
    PaymentInstallment::factory()->for($plan, 'plan')->create(['amount' => 200, 'sort_order' => 0]);
    $user = staffMember(StaffRole::Owner);

    $this->actingAs($user, 'staff')
        ->patch("/zahlungsplaene/{$plan->public_id}", [
            'total' => 200,
            'installments' => [
                ['due_on' => '2026-09-01', 'amount' => 100, 'status' => 'scheduled'],
            ],
        ])
        ->assertSessionHasErrors('installments');

    expect($plan->installments()->sum('amount'))->toBe(200);

    $this->actingAs($user, 'staff')
        ->patch("/zahlungsplaene/{$plan->public_id}", [
            'total' => 300,
            'installments' => [
                ['due_on' => '2026-09-01', 'amount' => 100, 'status' => 'paid'],
                ['due_on' => '2026-10-01', 'amount' => 200, 'status' => 'scheduled'],
            ],
        ])
        ->assertRedirect();

    expect($plan->fresh()->total)->toBe(300)
        ->and($plan->installments()->sum('amount'))->toBe(300);
});

it('sums an issued row at its stored amount, and takes no status for it', function () {
    // The form sends neither an editable amount nor a status for an issued
    // row, so the plan total is checked against what the invoice says.
    $project = Project::factory()->create();
    $job = Job::factory()->for($project)->create();
    $plan = PaymentPlan::factory()->for($job)->create(['total' => 300]);
    $issued = issuedInstallment($plan, 100);
    PaymentInstallment::factory()->for($plan, 'plan')->create(['amount' => 200, 'sort_order' => 1]);

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->patch("/zahlungsplaene/{$plan->public_id}", [
            'total' => 500,
            'installments' => [
                ['public_id' => $issued->public_id, 'due_on' => '2026-09-01', 'amount' => 0],
                ['due_on' => '2026-10-01', 'amount' => 400, 'status' => 'scheduled'],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($plan->fresh()->total)->toBe(500)
        ->and($issued->fresh()->status)->toBe(PaymentInstallmentStatus::Paid)
        ->and($plan->installments()->count())->toBe(2);
});

it('refuses a role without ManageContracts, and lets one with it through', function (StaffRole $role, int $status) {
    Storage::fake('local');
    $job = acceptedQuoteJob();

    $this->actingAs(staffMember($role), 'staff')
        ->post("/auftraege/{$job->project->public_id}/zahlungsplan", [
            'total' => 1000,
            'installments' => [['due_on' => '2026-09-01']],
        ])
        ->assertStatus($status);
})->with([
    [StaffRole::Owner, 302],
    [StaffRole::Photographer, 302],
    [StaffRole::Editor, 302],
    [StaffRole::SecondShooter, 403],
    [StaffRole::Assistant, 403],
]);

// --- No float, anywhere in the path ----------------------------------------

it('sends the payment plan to the overview screen as integer minor units, never a float', function () {
    Storage::fake('local');
    $job = acceptedQuoteJob();
    app(CreatePaymentPlan::class)($job, 100001, ['2026-09-01', '2026-10-01', '2026-11-01']);

    $props = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get("/auftraege/{$job->project->public_id}")
        ->assertOk()
        ->viewData('page')['props']['header'];

    expect($props['paymentPlan']['total'])->toBeInt()
        ->and($props['paymentPlan']['openTotal'])->toBeInt();

    foreach ($props['paymentPlan']['installments'] as $installment) {
        expect($installment['amount'])->toBeInt();
    }

    // 100001 over 3 does not divide evenly — the deterministic split proven
    // above, seen again end to end through the HTTP boundary.
    expect(collect($props['paymentPlan']['installments'])->pluck('amount')->all())->toBe([33333, 33333, 33335]);
});

it('labels installments Anzahlung, N. Rate and Restbetrag by position, mockup 04-auftrag\'s own vocabulary', function () {
    Storage::fake('local');
    $job = acceptedQuoteJob();
    $plan = app(CreatePaymentPlan::class)($job, 900, ['2026-09-01', '2026-10-01', '2026-11-01']);

    $data = PaymentPlanData::fromModel($plan->load('installments'));

    expect(collect($data->installments)->pluck('label')->all())->toBe(['Anzahlung', '2. Rate', 'Restbetrag']);
});

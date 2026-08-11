<?php

use App\Contracts\Actions\SignContract;
use App\Contracts\Events\ContractSigned;
use App\Contracts\Models\Contract;
use App\Contracts\Models\ContractSignature;
use App\Crm\Actions\BookProject;
use App\Crm\Events\ProjectBooked;
use App\Crm\Models\Contact;
use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Models\AuditLog;
use App\Payments\Actions\CreatePaymentPlan;
use App\Payments\Actions\MarkInstallmentPaid;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Enums\SettledVia;
use App\Payments\Events\InstallmentPaid;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// contractProject(), renderedContract() and staffMember() live in tests/Pest.php.

// --- Neither condition alone books the project -----------------------------

it('does nothing when a contract is signed and no payment plan exists at all', function () {
    Event::fake([ProjectBooked::class]);
    Storage::fake('local');

    $contract = renderedContract();
    $contact = Contact::factory()->create();

    app(SignContract::class)($contract, $contact, 'Lena Meier', '203.0.113.5', 'Mozilla/5.0');

    expect(Job::count())->toBe(0)
        ->and(AuditLog::where('event', 'project.booking_attempted')->count())->toBe(0);
    Event::assertNotDispatched(ProjectBooked::class);
});

it('does nothing when a contract is signed but the first installment is still scheduled, not paid', function () {
    Event::fake([ProjectBooked::class]);
    Storage::fake('local');

    $project = contractProject();
    $plan = PaymentPlan::factory()->for($project->job)->create(['total' => 100000]);
    PaymentInstallment::factory()->for($plan, 'plan')->create(['sort_order' => 0, 'amount' => 100000]);

    $contract = renderedContract($project);
    $contact = Contact::factory()->create();
    app(SignContract::class)($contract, $contact, 'Lena Meier', '203.0.113.5', 'Mozilla/5.0');

    expect(AuditLog::where('event', 'project.booking_attempted')->count())->toBe(0);
    Event::assertNotDispatched(ProjectBooked::class);
});

it('does nothing when the first installment is paid but no contract on the project is signed', function () {
    // Built directly on factories rather than through CreatePaymentPlan: that
    // action already refuses a plan without a signed contract (T-07), so this
    // exercises the listener's OWN check rather than relying on that guard.
    Event::fake([ProjectBooked::class]);

    $plan = PaymentPlan::factory()->create(['total' => 100000]);
    $installment = PaymentInstallment::factory()->for($plan, 'plan')->paid()->create(['sort_order' => 0, 'amount' => 100000]);

    InstallmentPaid::dispatch($installment->fresh());

    expect(AuditLog::where('event', 'project.booking_attempted')->count())->toBe(0);
    Event::assertNotDispatched(ProjectBooked::class);
});

it('does nothing when a non-first installment is paid, even with a signed contract', function () {
    Event::fake([ProjectBooked::class]);
    Storage::fake('local');

    $project = contractProject();
    $plan = PaymentPlan::factory()->for($project->job)->create(['total' => 200000]);
    PaymentInstallment::factory()->for($plan, 'plan')->create(['sort_order' => 0, 'amount' => 100000]);
    $second = PaymentInstallment::factory()->for($plan, 'plan')->create(['sort_order' => 1, 'amount' => 100000]);

    $contract = renderedContract($project);
    $contact = Contact::factory()->create();
    app(SignContract::class)($contract, $contact, 'Lena Meier', '203.0.113.5', 'Mozilla/5.0');

    app(MarkInstallmentPaid::class)($second, SettledVia::Transfer, '2026-09-01', null);

    expect(AuditLog::where('event', 'project.booking_attempted')->count())->toBe(0);
    Event::assertNotDispatched(ProjectBooked::class);
});

// --- Whichever condition arrives second notices the other already holds ----

it('attempts booking when a contract is signed after the deposit was already paid', function () {
    // Deposit-before-signature is the order CreatePaymentPlan's own guard
    // rules out for the FIRST contract on a project (a plan requires one
    // already signed) — but nothing stops a second contract (a reissue) on
    // an already-deposited project, which is what this constructs directly.
    Event::fake([ProjectBooked::class]);

    $job = Job::factory()->create();
    $plan = PaymentPlan::factory()->for($job)->create(['total' => 100000]);
    $installment = PaymentInstallment::factory()->for($plan, 'plan')->paid()->create(['sort_order' => 0, 'amount' => 100000]);

    $contract = Contract::factory()->for($job->project)->create();
    $signature = ContractSignature::factory()->for($contract)->create();

    ContractSigned::dispatch($contract, $signature);

    expect(Job::count())->toBe(1)
        ->and(Job::sole()->is($job))->toBeTrue();
    Event::assertNotDispatched(ProjectBooked::class);

    $row = AuditLog::where('event', 'project.booking_attempted')->where('subject', $job->project->public_id)->sole();
    expect($row->context['via'])->toBe('contract');
    expect($installment->fresh()->status)->toBe(PaymentInstallmentStatus::Paid);
});

// --- The race with the manual button ----------------------------------------

it('yields one job, one ProjectBooked and two distinguishable audit rows when the button wins a moment before the listener', function () {
    Event::fake([ProjectBooked::class]);
    Storage::fake('local');

    $project = Project::factory()->create();
    $contract = renderedContract($project);
    $contact = Contact::factory()->create();
    app(SignContract::class)($contract, $contact, 'Lena Meier', '203.0.113.5', 'Mozilla/5.0');

    // The button, pressed a moment before the listener's own condition-check
    // would have fired: a real actor, via=manual.
    $staff = staffMember();
    $job = app(BookProject::class)($project, $staff);

    // The deposit clears right around the same time — the listener's own
    // trigger, via=contract, actor null.
    $plan = app(CreatePaymentPlan::class)($job, 100000, ['2026-09-01']);
    app(MarkInstallmentPaid::class)($plan->installments->sole(), SettledVia::Transfer, '2026-09-01', null);

    expect(Job::count())->toBe(1)
        ->and(Job::sole()->is($job))->toBeTrue();
    Event::assertDispatchedTimes(ProjectBooked::class, 1);

    $rows = AuditLog::where('subject', $project->public_id)
        ->whereIn('event', ['project.booked', 'project.booking_attempted'])
        ->get();

    expect($rows)->toHaveCount(2);

    $booked = $rows->firstWhere('event', 'project.booked');
    $attempted = $rows->firstWhere('event', 'project.booking_attempted');

    expect($booked->context['via'])->toBe('manual')
        ->and($booked->actor_id)->toBe($staff->id)
        ->and($attempted->context['via'])->toBe('contract')
        ->and($attempted->actor_id)->toBeNull();
});

// --- The project already has a job, whatever created it --------------------

it('is a no-op — but still audits the attempt — when the project already has a job from something other than BookProject', function () {
    // Judgment call (see PR description): the listener cannot distinguish "a
    // fixture" from "a genuine race with the manual button" — both look
    // identical from here, a job that already exists. Auditing every
    // attempt uniformly, win or lose, is what keeps the race test's two rows
    // meaningful, and needs no extra state the listener has no way to hold.
    Event::fake([ProjectBooked::class]);
    Storage::fake('local');

    $project = Project::factory()->create();
    $existingJob = Job::factory()->for($project)->create();

    $contract = renderedContract($project);
    $contact = Contact::factory()->create();
    app(SignContract::class)($contract, $contact, 'Lena Meier', '203.0.113.5', 'Mozilla/5.0');

    $plan = app(CreatePaymentPlan::class)($existingJob, 100000, ['2026-09-01']);
    app(MarkInstallmentPaid::class)($plan->installments->sole(), SettledVia::Transfer, '2026-09-01', null);

    expect(Job::count())->toBe(1)
        ->and(Job::sole()->is($existingJob))->toBeTrue();
    Event::assertNotDispatched(ProjectBooked::class);

    expect(AuditLog::where('event', 'project.booking_attempted')->where('subject', $project->public_id)->count())->toBe(1);
});

<?php

use App\Payments\Actions\DetectOverdueInstallments;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Events\InstallmentOverdue;
use App\Payments\Models\PaymentInstallment;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('dispatches InstallmentOverdue for a scheduled installment whose due date has passed', function () {
    Event::fake([InstallmentOverdue::class]);

    $overdue = PaymentInstallment::factory()->create([
        'status' => PaymentInstallmentStatus::Scheduled,
        'due_on' => now()->subDays(3)->toDateString(),
    ]);

    $count = app(DetectOverdueInstallments::class)();

    expect($count)->toBe(1)
        ->and($overdue->fresh()->overdue_notified_at)->not->toBeNull();

    Event::assertDispatched(InstallmentOverdue::class, fn (InstallmentOverdue $event) => $event->installment->is($overdue));
});

it('never flags a paid installment, or one not yet due, regardless of status', function () {
    Event::fake([InstallmentOverdue::class]);

    PaymentInstallment::factory()->create([
        'status' => PaymentInstallmentStatus::Paid,
        'due_on' => now()->subDays(3)->toDateString(),
    ]);
    PaymentInstallment::factory()->create([
        'status' => PaymentInstallmentStatus::Scheduled,
        'due_on' => now()->addDays(3)->toDateString(),
    ]);

    expect(app(DetectOverdueInstallments::class)())->toBe(0);

    Event::assertNotDispatched(InstallmentOverdue::class);
});

it('is idempotent across repeated runs: a second scan never refires the same installment', function () {
    Event::fake([InstallmentOverdue::class]);

    PaymentInstallment::factory()->create([
        'status' => PaymentInstallmentStatus::Scheduled,
        'due_on' => now()->subDays(3)->toDateString(),
    ]);

    app(DetectOverdueInstallments::class)();
    app(DetectOverdueInstallments::class)();

    Event::assertDispatchedTimes(InstallmentOverdue::class, 1);
});

it('runs from the artisan command, reporting how many it flagged', function () {
    PaymentInstallment::factory()->create([
        'status' => PaymentInstallmentStatus::Scheduled,
        'due_on' => now()->subDays(1)->toDateString(),
    ]);

    $this->artisan('payments:detect-overdue-installments')
        ->expectsOutputToContain('Flagged 1 overdue installment(s).')
        ->assertSuccessful();
});

it('registers the overdue scan on the daily schedule', function () {
    $schedule = app(Schedule::class);

    $events = collect($schedule->events())
        ->filter(fn ($event) => str_contains($event->command ?? '', 'payments:detect-overdue-installments'));

    expect($events)->not->toBeEmpty()
        ->and($events->first()->expression)->toBe('0 0 * * *');
});

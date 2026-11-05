<?php

use App\Crm\Actions\AdvanceShootStatus;
use App\Crm\Data\ShootData;
use App\Crm\Enums\ShootStatus;
use App\Crm\Events\ShootCancelled;
use App\Crm\Events\ShootStatusChanged;
use App\Crm\Models\Shoot;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('orders the pipeline by declaration order', function () {
    expect(ShootStatus::Requested->position())->toBe(0)
        ->and(ShootStatus::Archived->position())->toBe(8)
        ->and(ShootStatus::Requested->next())->toBe(ShootStatus::Booked)
        ->and(ShootStatus::Archived->next())->toBeNull();
});

it('allows a forward skip and refuses a backward move', function () {
    // prep and culling are checklists a photographer may never tick, so
    // booked -> shot is a fact rather than a shortcut.
    expect(ShootStatus::Booked->canTransitionTo(ShootStatus::Shot))->toBeTrue()
        ->and(ShootStatus::Delivered->canTransitionTo(ShootStatus::Booked))->toBeFalse()
        ->and(ShootStatus::Booked->canTransitionTo(ShootStatus::Booked))->toBeFalse();
});

it('advances a shoot and restamps the status entry time', function () {
    $shoot = Shoot::factory()->atStatus(ShootStatus::Booked, daysAgo: 11)->on(now()->subDay()->toDateString())->create();

    $advanced = app(AdvanceShootStatus::class)($shoot, stage(ShootStatus::Shot));

    expect($advanced->status->key)->toBe(ShootStatus::Shot->value)
        ->and($advanced->status_entered_at->isToday())->toBeTrue();
});

it('emits an event carrying both statuses', function () {
    Event::fake([ShootStatusChanged::class]);
    $shoot = Shoot::factory()->create();

    app(AdvanceShootStatus::class)($shoot, stage(ShootStatus::Booked));

    Event::assertDispatched(ShootStatusChanged::class, fn ($e) => $e->from->key === ShootStatus::Requested->value
        && $e->to->key === ShootStatus::Booked->value
        && $e->shoot->is($shoot));
});

it('writes an audit row naming the transition', function () {
    $actor = User::factory()->create();
    $shoot = Shoot::factory()->create();

    app(AdvanceShootStatus::class)($shoot, stage(ShootStatus::Booked), $actor);

    $row = AuditLog::where('event', 'shoot.status_changed')->sole();

    expect($row->actor_id)->toBe($actor->id)
        ->and($row->subject)->toBe($shoot->public_id)
        ->and($row->context['from'])->toBe('requested')
        ->and($row->context['to'])->toBe('booked');
});

it('takes a backward move back, restoring the moment it first stood there', function () {
    $shoot = Shoot::factory()->atStatus(ShootStatus::Booked, daysAgo: 11)->on(now()->subDay()->toDateString())->create();
    $entered = $shoot->status_entered_at;

    app(AdvanceShootStatus::class)($shoot, stage(ShootStatus::Shot));
    app(AdvanceShootStatus::class)($shoot, stage(ShootStatus::Booked));

    expect($shoot->fresh()->status->key)->toBe(ShootStatus::Booked->value)
        ->and($shoot->fresh()->status_entered_at->diffInSeconds($entered))->toBeLessThan(2)
        ->and(AuditLog::where('event', 'shoot.status_changed')->latest('id')->first()->context['reverted'])->toBeTrue();
});

it('leaves nothing behind when a transition is refused', function () {
    $shoot = Shoot::factory()->atStatus(ShootStatus::Delivered)->create();

    try {
        // The no-op is the move both pipelines still refuse: it would write an
        // entry timestamp and an audit row that say nothing happened.
        app(AdvanceShootStatus::class)($shoot, stage(ShootStatus::Delivered));
    } catch (DomainException) {
        // expected
    }

    expect($shoot->fresh()->status->key)->toBe(ShootStatus::Delivered->value)
        ->and(AuditLog::where('event', 'shoot.status_changed')->count())->toBe(0);
});

it('backdates a status entered after the fact', function () {
    $shoot = Shoot::factory()->atStatus(ShootStatus::Booked)->on(now()->subDay()->toDateString())->create();

    app(AdvanceShootStatus::class)($shoot, stage(ShootStatus::Shot), null, CarbonImmutable::parse('2026-07-03 09:00'));

    expect($shoot->fresh()->status_entered_at->toDateString())->toBe('2026-07-03');
});

it('takes back an absage so the shoot can move again', function () {
    $shoot = Shoot::factory()->atStatus(ShootStatus::Booked)->on(now()->subDay()->toDateString())->create();

    app(AdvanceShootStatus::class)->cancel($shoot, 'Termin geplatzt');
    expect($shoot->fresh()->isCancelled())->toBeTrue();

    app(AdvanceShootStatus::class)->uncancel($shoot);

    expect($shoot->fresh()->isCancelled())->toBeFalse()
        ->and($shoot->fresh()->cancelled_reason)->toBeNull()
        ->and($shoot->fresh()->status->key)->toBe(ShootStatus::Booked->value);

    app(AdvanceShootStatus::class)($shoot, stage(ShootStatus::Shot));
    expect($shoot->fresh()->status->key)->toBe(ShootStatus::Shot->value);
});

it('cancels a shoot without changing the status it died in', function () {
    Event::fake([ShootCancelled::class]);
    $shoot = Shoot::factory()->atStatus(ShootStatus::Prep)->create();

    $cancelled = app(AdvanceShootStatus::class)->cancel($shoot, 'Termin abgesagt');

    expect($cancelled->isCancelled())->toBeTrue()
        ->and($cancelled->status->key)->toBe(ShootStatus::Prep->value)
        ->and($cancelled->cancelled_reason)->toBe('Termin abgesagt');

    Event::assertDispatched(ShootCancelled::class);
});

it('records the status a shoot was cancelled in on the audit row', function () {
    $shoot = Shoot::factory()->atStatus(ShootStatus::Prep)->create();

    app(AdvanceShootStatus::class)->cancel($shoot, 'Termin abgesagt');

    expect(AuditLog::where('event', 'shoot.cancelled')->sole()->context['status'])->toBe('prep');
});

it('refuses to advance a cancelled shoot', function () {
    $shoot = Shoot::factory()->cancelled('Termin abgesagt')->create();

    app(AdvanceShootStatus::class)($shoot, stage(ShootStatus::Shot));
})->throws(DomainException::class);

it('refuses to cancel a shoot twice', function () {
    $shoot = Shoot::factory()->cancelled('Termin abgesagt')->create();

    app(AdvanceShootStatus::class)->cancel($shoot, 'Nochmal');
})->throws(DomainException::class);

it('excludes cancelled shoots from the uncancelled scope', function () {
    Shoot::factory()->count(2)->create();
    Shoot::factory()->cancelled('Termin abgesagt')->create();

    expect(Shoot::query()->uncancelled()->count())->toBe(2);
});

it('stamps the status entry time on a shoot created outside the action', function () {
    // CaptureInquiry writes shoots directly. A null here would make the
    // staleness read on P-04's board silently skip the newest rows.
    expect(Shoot::factory()->create()->status_entered_at)->not->toBeNull();
});

it('offers Fotografiert only from the shoot date on', function () {
    $keys = fn (Shoot $shoot) => collect(ShootData::fromShoot($shoot)->forwardStatuses)->pluck('value')->all();

    $future = Shoot::factory()->atStatus(ShootStatus::Booked)->create(['date' => now()->addDays(5)]);
    $today = Shoot::factory()->atStatus(ShootStatus::Booked)->create(['date' => now()->startOfDay()]);

    expect($keys($future))->toContain('prep')->not->toContain('shot')
        ->and($keys($today))->toContain('prep', 'shot');
});

it('names Fotografiert as due only once the day has come', function () {
    $due = fn (Shoot $shoot) => ShootData::fromShoot($shoot)->dueStatus['value'] ?? null;

    $future = Shoot::factory()->atStatus(ShootStatus::Booked)->create(['date' => now()->addDays(5)]);
    $today = Shoot::factory()->atStatus(ShootStatus::Booked)->create(['date' => now()->startOfDay()]);
    $shot = Shoot::factory()->atStatus(ShootStatus::Shot)->create(['date' => now()->subDay()]);

    expect($due($future))->toBeNull()
        ->and($due($today))->toBe(ShootStatus::Shot->value)
        ->and($due($shot))->toBeNull();
});

it('refuses Fotografiert and later before the shoot date, but not archiving', function () {
    $advance = app(AdvanceShootStatus::class);
    $future = Shoot::factory()->atStatus(ShootStatus::Booked)->create(['date' => now()->addDays(5)]);

    expect(fn () => $advance($future, stage(ShootStatus::Delivered)))->toThrow(DomainException::class)
        ->and(fn () => $advance($future, stage(ShootStatus::Shot)))->toThrow(DomainException::class);

    $advance($future, stage(ShootStatus::Archived));

    expect($future->fresh()->status->key)->toBe(ShootStatus::Archived->value);
});

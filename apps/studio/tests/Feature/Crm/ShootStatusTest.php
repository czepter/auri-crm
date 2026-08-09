<?php

use App\Crm\Actions\AdvanceShootStatus;
use App\Crm\Enums\ShootStatus;
use App\Crm\Events\ShootCancelled;
use App\Crm\Events\ShootStatusChanged;
use App\Crm\Models\Shoot;
use App\Models\AuditLog;
use App\Models\User;
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
    $shoot = Shoot::factory()->atStatus(ShootStatus::Booked, daysAgo: 11)->create();

    $advanced = app(AdvanceShootStatus::class)($shoot, ShootStatus::Shot);

    expect($advanced->status)->toBe(ShootStatus::Shot)
        ->and($advanced->status_entered_at->isToday())->toBeTrue();
});

it('emits an event carrying both statuses', function () {
    Event::fake([ShootStatusChanged::class]);
    $shoot = Shoot::factory()->create();

    app(AdvanceShootStatus::class)($shoot, ShootStatus::Booked);

    Event::assertDispatched(ShootStatusChanged::class, fn ($e) => $e->from === ShootStatus::Requested
        && $e->to === ShootStatus::Booked
        && $e->shoot->is($shoot));
});

it('writes an audit row naming the transition', function () {
    $actor = User::factory()->create();
    $shoot = Shoot::factory()->create();

    app(AdvanceShootStatus::class)($shoot, ShootStatus::Booked, $actor);

    $row = AuditLog::where('event', 'shoot.status_changed')->sole();

    expect($row->actor_id)->toBe($actor->id)
        ->and($row->subject)->toBe($shoot->public_id)
        ->and($row->context['from'])->toBe('requested')
        ->and($row->context['to'])->toBe('booked');
});

it('refuses a backward transition', function () {
    $shoot = Shoot::factory()->atStatus(ShootStatus::Delivered)->create();

    app(AdvanceShootStatus::class)($shoot, ShootStatus::Booked);
})->throws(DomainException::class);

it('leaves nothing behind when a transition is refused', function () {
    $shoot = Shoot::factory()->atStatus(ShootStatus::Delivered)->create();

    try {
        app(AdvanceShootStatus::class)($shoot, ShootStatus::Booked);
    } catch (DomainException) {
        // expected
    }

    expect($shoot->fresh()->status)->toBe(ShootStatus::Delivered)
        ->and(AuditLog::where('event', 'shoot.status_changed')->count())->toBe(0);
});

it('cancels a shoot without changing the status it died in', function () {
    Event::fake([ShootCancelled::class]);
    $shoot = Shoot::factory()->atStatus(ShootStatus::Prep)->create();

    $cancelled = app(AdvanceShootStatus::class)->cancel($shoot, 'Termin abgesagt');

    expect($cancelled->isCancelled())->toBeTrue()
        ->and($cancelled->status)->toBe(ShootStatus::Prep)
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

    app(AdvanceShootStatus::class)($shoot, ShootStatus::Shot);
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

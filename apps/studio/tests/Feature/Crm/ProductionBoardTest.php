<?php

use App\Crm\Actions\AdvanceShootStatus;
use App\Crm\Actions\ReadProductionBoard;
use App\Crm\Enums\ShootStatus;
use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function booked(string $name = 'Projekt'): Project
{
    $project = Project::factory()->create(['name' => $name]);
    Job::factory()->for($project)->create();

    return $project;
}

it('carries one row per project, its least advanced uncancelled shoot', function () {
    // The same rule DeriveProjectPosition applies to a single project, in SQL
    // for many. Two screens deriving one fact by different rules is exactly
    // the disagreement deriving it was meant to prevent.
    $project = booked();
    Shoot::factory()->for($project)->atStatus(ShootStatus::Delivered)->create();
    $governing = Shoot::factory()->for($project)->atStatus(ShootStatus::Prep)->create();

    $rows = app(ReadProductionBoard::class)();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->shootId)->toBe($governing->public_id)
        ->and($rows->first()->status)->toBe('prep');
});

it('joins through jobs, so an unbooked project is not on the board', function () {
    // A production board holds what is in production. Before the Job exists
    // the project is a pursuit, and the funnel is where it shows.
    $unbooked = Project::factory()->create();
    Shoot::factory()->for($unbooked)->create();

    expect(app(ReadProductionBoard::class)())->toHaveCount(0);
});

it('excludes cancelled shoots when choosing the governing one', function () {
    $project = booked();
    Shoot::factory()->for($project)->cancelled('Abgesagt')->create();
    Shoot::factory()->for($project)->atStatus(ShootStatus::Editing)->create();

    expect(app(ReadProductionBoard::class)()->first()->status)->toBe('editing');
});

it('drops a project whose every shoot is cancelled', function () {
    $project = booked();
    Shoot::factory()->for($project)->cancelled('Abgesagt')->create();

    expect(app(ReadProductionBoard::class)())->toHaveCount(0);
});

it('reflects a status change on the next read, with no write of its own', function () {
    // The story's acceptance.
    $project = booked();
    $shoot = Shoot::factory()->for($project)->atStatus(ShootStatus::Booked)->create();

    expect(app(ReadProductionBoard::class)()->first()->status)->toBe('booked');

    app(AdvanceShootStatus::class)($shoot, ShootStatus::Shot);

    expect(app(ReadProductionBoard::class)()->first()->status)->toBe('shot');
});

it('agrees with the single-project derivation', function () {
    $project = booked();
    Shoot::factory()->for($project)->atStatus(ShootStatus::Ready)->create();
    Shoot::factory()->for($project)->atStatus(ShootStatus::Culling)->create();

    $board = app(ReadProductionBoard::class)()->first()->status;
    $single = app(App\Crm\Actions\DeriveProjectPosition::class)($project)->value;

    expect($board)->toBe($single);
});

it('orders by the shooting day, undated last', function () {
    $late = booked('Spät');
    Shoot::factory()->for($late)->on('2026-09-01')->atStatus(ShootStatus::Booked)->create();

    $undated = booked('Offen');
    Shoot::factory()->for($undated)->on(null)->atStatus(ShootStatus::Booked)->create();

    $soon = booked('Bald');
    Shoot::factory()->for($soon)->on('2026-06-14')->atStatus(ShootStatus::Booked)->create();

    expect(app(ReadProductionBoard::class)()->pluck('projectName')->all())
        ->toBe(['Bald', 'Spät', 'Offen']);
});

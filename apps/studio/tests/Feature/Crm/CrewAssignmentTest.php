<?php

use App\Crm\Enums\CrewRole;
use App\Crm\Models\CrewAssignment;
use App\Crm\Models\Shoot;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('assigns crew per shoot with a role', function () {
    $shoot = Shoot::factory()->create();
    $photographer = User::factory()->create();

    CrewAssignment::factory()->for($shoot)->for($photographer)
        ->create(['role' => CrewRole::SecondShooter]);

    $assignment = $shoot->crew()->sole();

    expect($assignment->role)->toBe(CrewRole::SecondShooter)
        ->and($assignment->user->is($photographer))->toBeTrue();
});

it('refuses the same person twice on one shoot', function () {
    $shoot = Shoot::factory()->create();
    $user = User::factory()->create();

    CrewAssignment::factory()->for($shoot)->for($user)->create();
    CrewAssignment::factory()->for($shoot)->for($user)
        ->create(['role' => CrewRole::Assistant]);
})->throws(QueryException::class);

it('allows one person across several shoots', function () {
    // The second shooter on the wedding day may also work the engagement
    // session — the constraint is per shoot, not per person.
    $user = User::factory()->create();

    CrewAssignment::factory()->count(3)->for($user)->create();

    expect(CrewAssignment::where('user_id', $user->id)->count())->toBe(3);
});

it('falls back to initials, since users carry no avatar', function () {
    expect(User::factory()->make(['name' => 'Christian Bergmann'])->initials())->toBe('CB')
        ->and(User::factory()->make(['name' => 'Nina'])->initials())->toBe('N');
});

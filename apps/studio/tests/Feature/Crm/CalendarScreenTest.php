<?php

use App\Crm\Enums\ShootStatus;
use App\Crm\Models\CrewAssignment;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
});

it('renders the calendar screen', function () {
    // Regression: the shoots prop was built with ShootData::fromShoot(...) as a
    // first-class callable inside Collection::map, which calls back with
    // ($item, $key) — the key bound to fromShoot's optional array $conflicts and
    // fataled on every request. Nothing covered this screen, so it stayed broken
    // until the Übersicht grew a footer that linked to it.
    $project = Project::factory()->create(['name' => 'Meier & Kowalski']);
    $shoot = Shoot::factory()->for($project)
        ->on(now()->addDays(3)->format('Y-m-d'))
        ->atStatus(ShootStatus::Prep)
        ->create(['label' => 'Hochzeitstag']);
    CrewAssignment::factory()->for($shoot)->create(['user_id' => $this->staff->id]);

    $props = props('/kalender');

    expect($props['shoots'])->toHaveCount(1)
        ->and($props['shoots'][0]['label'])->toBe('Hochzeitstag')
        ->and($props['projects'][$shoot->public_id]['name'])->toBe('Meier & Kowalski')
        ->and($props['mine'])->toBe([$shoot->public_id]);
});

it('renders the calendar screen when the studio has no shoots', function () {
    expect(props('/kalender')['shoots'])->toBe([]);
});

it('never puts an integer identifier into the calendar', function () {
    $project = Project::factory()->create();
    $shoot = Shoot::factory()->for($project)->on(now()->addDay()->format('Y-m-d'))->create();
    CrewAssignment::factory()->for($shoot)->create();

    expect(integerIdPaths(props('/kalender')))->toBe([]);
});

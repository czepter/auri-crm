<?php

use App\Crm\Enums\ShootStatus;
use App\Crm\Models\ClientCase;
use App\Crm\Models\CrewAssignment;
use App\Crm\Models\Shoot;
use App\Models\Absence;
use App\Models\StudioClosure;
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
    $project = ClientCase::factory()->create(['name' => 'Meier & Kowalski']);
    $shoot = Shoot::factory()->for($project, 'case')
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
    $project = ClientCase::factory()->create();
    $shoot = Shoot::factory()->for($project, 'case')->on(now()->addDay()->format('Y-m-d'))->create();
    CrewAssignment::factory()->for($shoot)->create();

    expect(integerIdPaths(props('/kalender')))->toBe([]);
});

// C-13 (#249): six week rows, always, starting on the Monday on or before the
// first — a grid that is five rows in February and six in March is a different
// shape every time it is opened.
it('lays the month out as six weeks from a monday', function () {
    $props = test()->actingAs(test()->staff, 'staff')
        ->get('/kalender?monat=2026-02')->assertOk()->viewData('page')['props'];

    expect($props['days'])->toHaveCount(42)
        ->and($props['days'][0]['date'])->toBe('2026-01-26')
        ->and($props['days'][0]['inMonth'])->toBeFalse()
        ->and($props['label'])->toContain('2026');
});

it('puts a requested shoot on the grid as a hold, and a booked one as a shoot', function () {
    $project = ClientCase::factory()->create(['name' => 'Meier & Kowalski']);
    Shoot::factory()->for($project, 'case')->on('2026-02-14')->atStatus(ShootStatus::Requested)->create();
    Shoot::factory()->for($project, 'case')->on('2026-02-15')->atStatus(ShootStatus::Booked)->create(['call_time' => '09:00']);

    $days = collect(test()->actingAs(test()->staff, 'staff')
        ->get('/kalender?monat=2026-02')->assertOk()->viewData('page')['props']['days'])
        ->keyBy('date');

    expect($days['2026-02-14']['entries'][0]['kind'])->toBe('hold')
        ->and($days['2026-02-15']['entries'][0]['kind'])->toBe('shoot')
        ->and($days['2026-02-15']['entries'][0]['time'])->toBe('09:00');
});

// A free Saturday is the most important thing in the grid, and it is not
// shown with text.
it('leaves an empty day empty', function () {
    $days = collect(test()->actingAs(test()->staff, 'staff')
        ->get('/kalender?monat=2026-02')->assertOk()->viewData('page')['props']['days'])
        ->keyBy('date');

    expect($days['2026-02-14']['entries'])->toBe([]);
});

it('carries an absence and a studio closure as all-day lines', function () {
    Absence::factory()->create([
        'user_id' => User::factory()->create(['name' => 'Nina Brandt'])->id,
        'starts_on' => '2026-02-10',
        'ends_on' => '2026-02-11',
    ]);
    StudioClosure::factory()->create([
        'label' => 'Betriebsferien',
        'starts_on' => '2026-02-20',
        'ends_on' => '2026-02-21',
    ]);

    $days = collect(test()->actingAs(test()->staff, 'staff')
        ->get('/kalender?monat=2026-02')->assertOk()->viewData('page')['props']['days'])
        ->keyBy('date');

    expect($days['2026-02-10']['entries'][0])->toMatchArray(['kind' => 'away', 'what' => 'Nina Brandt'])
        ->and($days['2026-02-21']['entries'][0])->toMatchArray(['kind' => 'away', 'what' => 'Betriebsferien']);
});

// #365: the season is every year a studio has, so it travels only when the
// season view asks for it — and a hold is an inquiry, not a booked day.
it('sends the season on request, without holds', function () {
    $project = ClientCase::factory()->create(['name' => 'Meier & Kowalski']);
    Shoot::factory()->for($project, 'case')->on('2019-06-15')->atStatus(ShootStatus::Delivered)->create();
    Shoot::factory()->for($project, 'case')->on('2027-05-15')->atStatus(ShootStatus::Requested)->create();
    Absence::factory()->create(['starts_on' => '2026-07-27', 'ends_on' => '2026-08-07']);

    expect(props('/kalender'))->toMatchArray(['view' => 'monat', 'season' => null])
        ->and(props('/kalender?ansicht=woche')['view'])->toBe('monat');

    $props = props('/kalender?ansicht=saison');
    $season = $props['season'];

    expect($props['view'])->toBe('saison')
        ->and($season['shoots'])->toHaveCount(1)
        ->and($season['shoots'][0])->toMatchArray(['date' => '2019-06-15', 'name' => 'Meier & Kowalski', 'phase' => 'delivered'])
        ->and($season['away'][0])->toMatchArray(['from' => '2026-07-27', 'to' => '2026-08-07']);
});

// The subscription secret was the widest block over the working area. It is a
// credential of one person, so it sits in that person's profile.
it('keeps the feed url off the calendar and puts it in the profile', function () {
    $props = test()->actingAs(test()->staff, 'staff')->get('/kalender')->assertOk()->viewData('page')['props'];

    expect($props)->not->toHaveKey('feedUrl');

    $profile = test()->actingAs(test()->staff, 'staff')
        ->get('/profil/kalender')->assertOk()->viewData('page')['props'];

    expect($profile['calendarFeedUrl'])->toContain('/shootings.ics');
});

it('mints no calendar token on the other profile panes', function () {
    $props = test()->actingAs(test()->staff, 'staff')->get('/profil')->assertOk()->viewData('page')['props'];

    expect($props['calendarFeedUrl'])->toBeNull()
        ->and(test()->staff->fresh()->calendar_token)->toBeNull();
});

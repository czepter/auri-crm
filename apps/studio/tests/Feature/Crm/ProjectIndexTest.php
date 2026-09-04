<?php

use App\Crm\Enums\ShootStatus;
use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
});

function bookedProject(?string $name = null): Project
{
    $project = Project::factory()->wedding()->create(array_filter(['name' => $name]));
    Job::factory()->for($project)->create();

    return $project;
}

function projectRows(?User $as = null): array
{
    return test()->actingAs($as ?? test()->staff, 'staff')
        ->get('/projekte')->assertOk()->viewData('page')['props']['projects'];
}

// C-02 (#236): a studio looks a project up by its day, so the nearest one is
// the first row and the ones without a date do not push it down.
it('orders by the primary shoot, undated last', function () {
    $far = bookedProject('Vehse');
    Shoot::factory()->for($far)->primary()->on('2026-09-12')->create();

    bookedProject('Sundermann');

    $near = bookedProject('Meier & Kowalski');
    Shoot::factory()->for($near)->primary()->on('2026-06-14')->create();

    expect(collect(projectRows())->pluck('name')->all())
        ->toBe(['Meier & Kowalski', 'Vehse', 'Sundermann']);
});

it('reads the position off the same rule the project screen does', function () {
    $project = bookedProject();
    Shoot::factory()->for($project)->primary()->on('2026-06-14')
        ->create(['status' => ShootStatus::Booked]);

    expect(projectRows()[0]['position'])->toBe(ShootStatus::Booked->label());
});

// An inquiry is the same record before its Job exists — it belongs on the
// board at /anfragen, not in this list.
it('leaves out projects that are not booked', function () {
    $inquiry = Project::factory()->wedding()->create(['name' => 'Sundermann']);
    Shoot::factory()->for($inquiry)->primary()->on('2026-06-14')->create();

    expect(projectRows())->toBe([]);
});

// The list is a read over many projects, so it obeys the same scope every
// other one does: a crew role sees the days they are on, not the whole book.
it('shows a second shooter only the projects they are crewed on', function () {
    $project = bookedProject('Vehse');
    Shoot::factory()->for($project)->primary()->on('2026-09-12')->create();

    $crew = User::factory()->withMfa()->create(['role' => StaffRole::SecondShooter]);

    expect(projectRows($crew))->toBe([]);
});

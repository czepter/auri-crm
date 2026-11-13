<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Enums\LeadOutcome;
use App\Crm\Enums\ShootStatus;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\Shoot;
use App\Crm\Models\Stage;
use App\Crm\Support\CrmVocabulary;
use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
});

function bookedProject(?string $name = null): ClientCase
{
    $project = ClientCase::factory()->wedding()->create(array_filter(['name' => $name]));
    markBooked($project);

    return $project;
}

function projectRows(?User $as = null): array
{
    return test()->actingAs($as ?? test()->staff, 'staff')
        ->get('/projekte')->assertOk()->viewData('page')['props']['projects'];
}

it('opens the view named in the url, the list otherwise', function () {
    test()->actingAs(test()->staff, 'staff');

    expect(props('/projekte?ansicht=board')['view'])->toBe('board')
        ->and(props('/projekte?ansicht=kanban')['view'])->toBe('liste');
});

// C-02 (#236): a studio looks a project up by its day, so the nearest one is
// the first row and the ones without a date do not push it down.
it('orders by the primary shoot, undated last', function () {
    $far = bookedProject('Vehse');
    Shoot::factory()->for($far, 'case')->primary()->on('2026-09-12')->create();

    bookedProject('Sundermann');

    $near = bookedProject('Meier & Kowalski');
    Shoot::factory()->for($near, 'case')->primary()->on('2026-06-14')->create();

    expect(collect(projectRows())->pluck('name')->all())
        ->toBe(['Meier & Kowalski', 'Vehse', 'Sundermann']);
});

it('reads the position off the same rule the project screen does', function () {
    $project = bookedProject();
    Shoot::factory()->for($project, 'case')->primary()->on('2026-06-14')
        ->create(['status' => ShootStatus::Booked]);

    expect(projectRows()[0]['position'])->toBe(ShootStatus::Booked->label());
});

// #364: the list groups by where the work stands.
it('files each stage under its phase', function (ShootStatus $status, string $phase) {
    $project = bookedProject();
    Shoot::factory()->for($project, 'case')->primary()->on('2026-06-14')->atStatus($status)->create();

    expect(projectRows()[0])->toMatchArray(['positionKey' => $status->value, 'phase' => $phase]);
})->with([
    [ShootStatus::Prep, 'upcoming'],
    [ShootStatus::Culling, 'editing'],
    [ShootStatus::Delivered, 'delivered'],
]);

// A studio renames its stages, so the phase comes off the anchor, never off
// the name.
it('keeps a renamed stage in its phase', function () {
    Stage::query()->where('pipeline', StagePipeline::Project->value)->where('key', 'culling')->update(['name' => 'Sichtung']);
    app(CrmVocabulary::class)->forget();

    $project = bookedProject();
    Shoot::factory()->for($project, 'case')->primary()->on('2026-06-14')->atStatus(ShootStatus::Culling)->create();

    expect(projectRows()[0])->toMatchArray(['position' => 'Sichtung', 'phase' => 'editing']);
});

// An inquiry is the same record before its booking — it belongs on the
// board at /anfragen, not in this list.
it('leaves out projects that are not booked', function () {
    $inquiry = ClientCase::factory()->wedding()->create(['name' => 'Sundermann']);
    Shoot::factory()->for($inquiry, 'case')->primary()->on('2026-06-14')->create();

    expect(projectRows())->toBe([]);
});

// The list is a read over many projects, so it obeys the same scope every
// other one does: a crew role sees the days they are on, not the whole book.
it('shows a second shooter only the projects they are crewed on', function () {
    $project = bookedProject('Vehse');
    Shoot::factory()->for($project, 'case')->primary()->on('2026-09-12')->create();

    $crew = User::factory()->withMfa()->create(['role' => StaffRole::Contributor]);

    expect(projectRows($crew))->toBe([]);
});

it('moves every open shoot to the stage a card is dropped on', function () {
    $project = bookedProject();
    $first = Shoot::factory()->for($project, 'case')->primary()->on('2026-06-14')->atStatus(ShootStatus::Booked)->create();
    $second = Shoot::factory()->for($project, 'case')->on('2026-06-15')->atStatus(ShootStatus::Booked)->create();

    test()->actingAs(test()->staff, 'staff')
        ->post("/auftraege/{$project->public_id}/stufe", ['stage' => ShootStatus::Culling->value])
        ->assertRedirect();

    expect($first->fresh()->status->key)->toBe(ShootStatus::Culling->value)
        ->and($second->fresh()->status->key)->toBe(ShootStatus::Culling->value);
});

it('files a closed project under closed, not upcoming', function () {
    $project = bookedProject();
    Shoot::factory()->for($project, 'case')->primary()->on('2022-06-25')->create();
    $project->forceFill(['closed_at' => now(), 'close_outcome' => LeadOutcome::Closed])->save();

    expect(projectRows()[0])->toMatchArray(['position' => 'Storniert', 'phase' => 'closed']);
});

it('costs the same queries for three projects as for nine', function () {
    $add = function (int $count) {
        foreach (range(1, $count) as $i) {
            $project = bookedProject();
            $project->attachContact(Contact::factory()->create(), CaseContactRole::Primary);
            Shoot::factory()->for($project, 'case')->primary()->on('2026-06-14')->create();
        }
    };
    $measure = function () {
        DB::enableQueryLog();
        projectRows();
        $count = count(DB::getRawQueryLog());
        DB::flushQueryLog();
        DB::disableQueryLog();

        return $count;
    };

    $add(3);
    projectRows();
    $three = $measure();

    $add(6);
    $nine = $measure();

    expect($nine)->toBe($three);
});

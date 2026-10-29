<?php

use App\Crm\Actions\ReadStageTrack;
use App\Crm\Data\CasePositionData;
use App\Crm\Enums\ShootStatus;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Shoot;
use App\Crm\Screens\OpenCases;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function bookedAt(ShootStatus $status, string $date): ClientCase
{
    $project = ClientCase::factory()->create();
    markBooked($project);
    Shoot::factory()->for($project, 'case')->primary()->atStatus($status)->on($date)->create();

    return $project;
}

it('groups booked projects by what the day asks of them', function () {
    $stalled = bookedAt(ShootStatus::Booked, now()->subDays(119)->toDateString());
    $soon = bookedAt(ShootStatus::Prep, now()->addDays(3)->toDateString());
    $editing = bookedAt(ShootStatus::Editing, now()->subDays(20)->toDateString());
    $later = bookedAt(ShootStatus::Booked, now()->addMonths(4)->toDateString());
    bookedAt(ShootStatus::Delivered, now()->subDays(60)->toDateString());

    $groups = collect(app(OpenCases::class)->queue(User::factory()->withMfa()->create()))
        ->mapWithKeys(fn (array $group) => [$group['key'] => array_map(fn ($row) => $row->id, $group['rows'])]);

    expect($groups->all())->toBe([
        'haengt' => [$stalled->public_id],
        'woche' => [$soon->public_id],
        'bearbeitung' => [$editing->public_id],
        'spaeter' => [$later->public_id],
    ]);
});

it('shows the inquiry done and the project at its booked step', function () {
    $steps = collect(app(ReadStageTrack::class)(new CasePositionData(source: 'lead', value: 'booked', label: 'Gebucht')));

    expect($steps->where('state', 'done')->pluck('key')->all())
        ->toBe(['new', 'contacted', 'quote_sent', 'quote_accepted'])
        ->and($steps->firstWhere('state', 'current'))->toMatchArray(['key' => 'booked', 'pipeline' => 'project'])
        ->and($steps->pluck('key')->last())->toBe('completed');
});

<?php

use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Models\RecentView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
});

function recent(): array
{
    return props('/')['recent'];
}

it('opens with what this user last looked at, newest first', function () {
    $contact = Contact::factory()->create(['name' => 'Familie Osterhoff']);
    $project = ClientCase::factory()->create(['name' => 'Meier & Kowalski']);

    $this->get("/kontakte/{$contact->public_id}")->assertOk();
    $this->get("/auftraege/{$project->public_id}")->assertOk();

    expect(collect(recent())->pluck('title')->all())->toBe(['Meier & Kowalski', 'Familie Osterhoff'])
        ->and(recent()[0]['href'])->toBe("/auftraege/{$project->public_id}");
});

// An inquiry and a project are the same record in two phases, and the palette
// names it by the phase it is in — the booking is the seam.
it('names an unbooked project an Anfrage and a booked one a Projekt', function () {
    $inquiry = ClientCase::factory()->create();
    $this->get("/auftraege/{$inquiry->public_id}")->assertOk();

    expect(recent()[0]['kind'])->toBe('anfrage');

    markBooked($inquiry);
    $this->get("/auftraege/{$inquiry->public_id}")->assertOk();

    expect(recent())->toHaveCount(1)
        ->and(recent()[0]['kind'])->toBe('projekt');
});

it('keeps twenty, and drops the oldest rather than growing', function () {
    // Named by hand: two factory-random names can collide, and the assertion
    // below is about which row fell out, not about how many share a name.
    $projects = collect(range(1, 22))->map(
        fn (int $number) => ClientCase::factory()->create(['name' => "Projekt {$number}"])
    );

    foreach ($projects as $project) {
        $this->get("/auftraege/{$project->public_id}")->assertOk();
    }

    expect(RecentView::query()->where('user_id', $this->staff->id)->count())->toBe(20)
        ->and(collect(recent())->pluck('title')->all())
        ->not->toContain($projects[0]->name);
});

// Per user, not per studio: on a shared account "per studio" fills the
// palette with someone else's afternoon.
it('never shows one user what another opened', function () {
    $project = ClientCase::factory()->create(['name' => 'Vehse & Rieck']);
    $this->get("/auftraege/{$project->public_id}")->assertOk();

    $this->actingAs(User::factory()->withMfa()->create(), 'staff');

    expect(recent())->toBe([]);
});

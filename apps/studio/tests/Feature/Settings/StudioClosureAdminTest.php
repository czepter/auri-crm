<?php

use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Settings\Models\StudioClosure;
use App\Settings\Models\StudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

// staffMember() and props() live in tests/Pest.php — see the note there.

beforeEach(fn () => Cache::store('redis')->flush());

it('lists every closure', function () {
    StudioClosure::factory()->spanning('2026-12-24', '2026-12-31')->create(['label' => 'Weihnachtspause']);

    $props = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/schliesszeiten')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['closures'])->toHaveCount(1)
        ->and($props['closures'][0]['label'])->toBe('Weihnachtspause')
        ->and($props['closures'][0]['startsOn'])->toBe('2026-12-24')
        ->and($props['closures'][0]['endsOn'])->toBe('2026-12-31');
});

it('hands the working days to the calendar overlay', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/kalender/einstellungen')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('settingsOpen', true)->where('workingDays', [1, 2, 3, 4, 5, 6, 7]));
});

it('saves the working days and audits the owner', function () {
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')
        ->from('/kalender/einstellungen')
        ->patch('/kalender/einstellungen/arbeitstage', ['working_days' => [1, 2, 3, 4, 5]])
        ->assertRedirect('/kalender/einstellungen');

    expect(StudioSettings::query()->sole()->working_days)->toBe([1, 2, 3, 4, 5])
        ->and(AuditLog::where('context->key', 'working_days')->sole()->actor_id)->toBe($owner->id);
});

it('rejects an empty or out-of-range working-days selection', function (array $days, string $key) {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->patch('/kalender/einstellungen/arbeitstage', ['working_days' => $days])
        ->assertInvalid([$key]);
})->with([
    [[], 'working_days'],
    [[0, 1, 2], 'working_days.0'],
]);

it('creates a closure and leaves an audit row naming the actor', function () {
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')
        ->from('/schliesszeiten')
        ->post('/schliesszeiten', [
            'label' => 'Weihnachtspause',
            'starts_on' => '2026-12-24',
            'ends_on' => '2026-12-31',
            'message' => 'Zurück am 2. Januar.',
        ])
        ->assertRedirect('/schliesszeiten');

    $closure = StudioClosure::query()->sole();

    expect($closure->label)->toBe('Weihnachtspause')
        ->and($closure->message)->toBe('Zurück am 2. Januar.')
        ->and(AuditLog::where('event', 'studio_closure.created')->sole()->actor_id)->toBe($owner->id);
});

it('rejects a closure whose end is before its start', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->from('/schliesszeiten')
        ->post('/schliesszeiten', [
            'label' => 'Ungültig',
            'starts_on' => '2026-12-31',
            'ends_on' => '2026-12-24',
        ])
        ->assertRedirect('/schliesszeiten')
        ->assertSessionHasErrors('ends_on');

    expect(StudioClosure::count())->toBe(0);
});

it('updates a closure and leaves an audit row', function () {
    $owner = staffMember(StaffRole::Owner);
    $closure = StudioClosure::factory()->spanning('2026-12-24', '2026-12-31')->create(['label' => 'Weihnachtspause']);

    $this->actingAs($owner, 'staff')
        ->from('/schliesszeiten')
        ->patch("/schliesszeiten/{$closure->public_id}", [
            'label' => 'Winterpause',
            'starts_on' => '2026-12-20',
            'ends_on' => '2027-01-02',
        ])
        ->assertRedirect('/schliesszeiten');

    $closure->refresh();

    expect($closure->label)->toBe('Winterpause')
        ->and($closure->starts_on->format('Y-m-d'))->toBe('2026-12-20')
        ->and($closure->ends_on->format('Y-m-d'))->toBe('2027-01-02')
        ->and(AuditLog::where('event', 'studio_closure.updated')->sole()->actor_id)->toBe($owner->id);
});

it('deletes a closure and leaves an audit row', function () {
    $owner = staffMember(StaffRole::Owner);
    $closure = StudioClosure::factory()->create();

    $this->actingAs($owner, 'staff')
        ->from('/schliesszeiten')
        ->delete("/schliesszeiten/{$closure->public_id}")
        ->assertRedirect('/schliesszeiten');

    expect(StudioClosure::count())->toBe(0)
        ->and(AuditLog::where('event', 'studio_closure.deleted')->sole()->actor_id)->toBe($owner->id);
});

it('binds the closure route on the public id, not the primary key', function () {
    $closure = StudioClosure::factory()->create();

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->delete("/schliesszeiten/{$closure->id}")
        ->assertNotFound();
});

it('gives every non-owner role the same 404 every other settings screen gives', function (StaffRole $role, int $status) {
    foreach (['/schliesszeiten', '/kalender/einstellungen'] as $path) {
        $this->actingAs(staffMember($role), 'staff')->get($path)->assertStatus($status);
    }
})->with([
    [StaffRole::Owner, 200],
    [StaffRole::Photographer, 404],
    [StaffRole::Editor, 404],
    [StaffRole::SecondShooter, 404],
    [StaffRole::Assistant, 404],
]);

it('puts no integer identifier on the closures page', function () {
    StudioClosure::factory()->create();

    $props = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/schliesszeiten')
        ->assertOk()
        ->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
});

it('gives the plain calendar no overlay data to a role without ManageSettings', function () {
    $this->actingAs(staffMember(StaffRole::Photographer), 'staff')
        ->get('/kalender')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('workingDays', null)->where('settingsOpen', false));
});

it('sends the old availability address to the closures page', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/einstellungen/verfuegbarkeit')
        ->assertRedirect('/schliesszeiten');
});

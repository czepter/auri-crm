<?php

use App\Enums\AbsenceCategory;
use App\Enums\Capability;
use App\Enums\StaffRole;
use App\Models\Absence;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets a second shooter record their own absence without ManageStaff', function () {
    $nina = staffMember(StaffRole::Contributor);

    expect($nina->role->allows(Capability::ManageStaff))->toBeFalse();

    $this->actingAs($nina, 'staff')
        ->post('/abwesenheiten', [
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-07',
        ])
        ->assertRedirect();

    $absence = Absence::sole();

    expect($absence->user_id)->toBe($nina->id)
        ->and($absence->category)->toBe(AbsenceCategory::Unavailable);
});

it('refuses a second shooter recording an absence for somebody else', function () {
    $nina = staffMember(StaffRole::Contributor);
    $other = staffMember(StaffRole::Manager);

    $this->actingAs($nina, 'staff')
        ->post('/abwesenheiten', [
            'user' => $other->public_id,
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-07',
        ])
        ->assertForbidden();

    expect(Absence::count())->toBe(0);
});

it('lets ManageCrew record an absence on behalf of somebody else', function () {
    $owner = staffMember(StaffRole::Admin);
    $nina = staffMember(StaffRole::Contributor);

    expect($owner->role->allows(Capability::ManageCrew))->toBeTrue();

    $this->actingAs($owner, 'staff')
        ->post('/abwesenheiten', [
            'user' => $nina->public_id,
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-03',
        ])
        ->assertRedirect();

    expect(Absence::sole()->user_id)->toBe($nina->id);
});

it('shows only your own absences to a role without ManageCrew', function () {
    $nina = staffMember(StaffRole::Contributor);
    $someoneElse = staffMember(StaffRole::Manager);

    Absence::factory()->for($nina)->create();
    Absence::factory()->for($someoneElse)->create();

    $this->actingAs($nina, 'staff');
    $rows = props('/abwesenheiten')['absences'];

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['userId'])->toBe($nina->public_id);
});

it('shows every absence to a role with ManageCrew', function () {
    $owner = staffMember(StaffRole::Admin);
    $nina = staffMember(StaffRole::Contributor);
    $mia = staffMember(StaffRole::Manager);

    Absence::factory()->for($nina)->create();
    Absence::factory()->for($mia)->create();

    $this->actingAs($owner, 'staff');
    $rows = props('/abwesenheiten')['absences'];

    expect($rows)->toHaveCount(2);
});

it('holds that rule on the calendar and the week beside it', function () {
    $nina = staffMember(StaffRole::Contributor);
    $mia = staffMember(StaffRole::Manager);

    Absence::factory()->for($mia)->create([
        'starts_on' => now()->addDay()->toDateString(),
        'ends_on' => now()->addDays(2)->toDateString(),
    ]);

    $this->actingAs($nina, 'staff');

    $entries = collect(props('/kalender')['days'])->pluck('entries')->flatten(1);

    expect($entries->where('kind', 'away'))->toBeEmpty()
        ->and(collect(props('/')['entries'])->where('away', true))->toBeEmpty();
});

it('refuses a second shooter editing somebody else\'s absence', function () {
    $nina = staffMember(StaffRole::Contributor);
    $mia = staffMember(StaffRole::Manager);
    $absence = Absence::factory()->for($mia)->create();

    $this->actingAs($nina, 'staff')
        ->patch("/abwesenheiten/{$absence->public_id}", [
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-10-02',
        ])
        ->assertForbidden();
});

it('lets a second shooter edit their own absence, leaving the category alone', function () {
    $nina = staffMember(StaffRole::Contributor);
    $absence = Absence::factory()->for($nina)->create(['category' => 'other']);

    $this->actingAs($nina, 'staff')
        ->patch("/abwesenheiten/{$absence->public_id}", [
            'category' => 'holiday',
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-10-02',
        ])
        ->assertRedirect();

    expect($absence->fresh()->category)->toBe(AbsenceCategory::Other)
        ->and($absence->fresh()->starts_on->format('Y-m-d'))->toBe('2026-10-01');
});

it('lets ManageCrew edit and delete anybody\'s absence', function () {
    $owner = staffMember(StaffRole::Admin);
    $nina = staffMember(StaffRole::Contributor);
    $absence = Absence::factory()->for($nina)->create();

    $this->actingAs($owner, 'staff')
        ->delete("/abwesenheiten/{$absence->public_id}")
        ->assertRedirect();

    expect(Absence::count())->toBe(0);
});

it('rejects an unauthenticated request', function () {
    $this->get('/abwesenheiten')->assertRedirect('/login');
});

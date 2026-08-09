<?php

use App\Crm\Models\Shoot;
use App\Crm\Models\ShotListItem;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// staffMember() lives in tests/Pest.php — see the note there.

it('belongs to a shoot and starts unchecked', function () {
    $shoot = Shoot::factory()->create();

    $item = ShotListItem::factory()->for($shoot)->create(['label' => 'Ringe auf dem Buch']);

    expect($item->shoot->is($shoot))->toBeTrue()
        ->and($item->isCheckedOff())->toBeFalse()
        ->and($shoot->shotListItems()->sole()->is($item))->toBeTrue();
});

it('adds a shot list item through the admin screen', function () {
    $shoot = Shoot::factory()->create();
    $user = staffMember(StaffRole::Owner);

    $this->actingAs($user, 'staff')
        ->post("/shootings/{$shoot->public_id}/checkliste", ['label' => 'Erster Kuss'])
        ->assertRedirect();

    $item = $shoot->shotListItems()->sole();
    expect($item->label)->toBe('Erster Kuss')
        ->and($item->isCheckedOff())->toBeFalse();
});

it('checks a shot list item off, and off again reopens it', function () {
    $item = ShotListItem::factory()->create();
    $user = staffMember(StaffRole::Owner);

    $this->actingAs($user, 'staff')
        ->post("/checkliste/{$item->public_id}/erledigt")
        ->assertRedirect();
    expect($item->fresh()->isCheckedOff())->toBeTrue();

    $this->actingAs($user, 'staff')
        ->post("/checkliste/{$item->public_id}/erledigt")
        ->assertRedirect();
    expect($item->fresh()->isCheckedOff())->toBeFalse();
});

it('leaves an audit row when a shot list item is checked off', function () {
    $item = ShotListItem::factory()->create(['label' => 'Erster Tanz']);
    $user = staffMember(StaffRole::Owner);

    $this->actingAs($user, 'staff')->post("/checkliste/{$item->public_id}/erledigt")->assertRedirect();

    $row = AuditLog::where('event', 'shot_list_item.checked')->where('subject', $item->public_id)->sole();
    expect($row->actor_id)->toBe($user->id)
        ->and($row->context['label'])->toBe('Erster Tanz');
});

it('removes a shot list item through the admin screen', function () {
    $item = ShotListItem::factory()->create();
    $user = staffMember(StaffRole::Owner);

    $this->actingAs($user, 'staff')
        ->delete("/checkliste/{$item->public_id}")
        ->assertRedirect();

    expect(ShotListItem::count())->toBe(0);
});

it('refuses a role without ManageShoots, and lets one with it through', function (StaffRole $role, int $status) {
    $shoot = Shoot::factory()->create();

    $this->actingAs(staffMember($role), 'staff')
        ->post("/shootings/{$shoot->public_id}/checkliste", ['label' => 'Familienfoto'])
        ->assertStatus($status);
})->with([
    [StaffRole::Owner, 302],
    [StaffRole::Photographer, 302],
    [StaffRole::Editor, 302],
    [StaffRole::SecondShooter, 403],
    [StaffRole::Assistant, 403],
]);

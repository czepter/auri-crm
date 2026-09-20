<?php

use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\StaffOwnershipTransferred;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function owner(): User
{
    return User::factory()->withMfa()->create(['role' => StaffRole::Owner, 'password' => Hash::make('correct-horse')]);
}

it('hands the role over and leaves exactly one owner', function () {
    Notification::fake();

    $owner = owner();
    $nina = staffMember(StaffRole::SecondShooter);

    $this->actingAs($owner, 'staff')
        ->post("/team/{$nina->public_id}/inhaber", ['current_password' => 'correct-horse'])
        ->assertRedirect();

    expect(User::where('role', StaffRole::Owner)->pluck('id')->all())->toBe([$nina->id])
        ->and($owner->fresh()->role)->toBe(StaffRole::Photographer)
        ->and(AuditLog::where('event', 'staff.ownership_transferred')->count())->toBe(1);

    Notification::assertSentTo([$owner, $nina], StaffOwnershipTransferred::class);
});

it('refuses a wrong password', function () {
    $owner = owner();
    $nina = staffMember(StaffRole::SecondShooter);

    $this->actingAs($owner, 'staff')
        ->post("/team/{$nina->public_id}/inhaber", ['current_password' => 'wrong'])
        ->assertSessionHasErrors('current_password');

    expect($owner->fresh()->role)->toBe(StaffRole::Owner);
});

it('hides the transfer from everybody but the owner, and from the owner to themselves', function () {
    $owner = owner();
    $nina = staffMember(StaffRole::SecondShooter);

    $this->actingAs($nina, 'staff')
        ->post("/team/{$owner->public_id}/inhaber", ['current_password' => 'x'])
        ->assertNotFound();

    $this->actingAs($owner, 'staff')
        ->post("/team/{$owner->public_id}/inhaber", ['current_password' => 'correct-horse'])
        ->assertNotFound();

    expect($owner->fresh()->role)->toBe(StaffRole::Owner);
});

it('offers the transfer for everybody but the viewer', function () {
    $owner = owner();
    $nina = staffMember(StaffRole::SecondShooter);

    $staff = $this->actingAs($owner, 'staff')->get('/team')->assertOk()->viewData('page')['props']['staff'];

    expect(collect($staff)->pluck('transferable', 'id')->all())
        ->toEqual([$owner->public_id => false, $nina->public_id => true]);
});

it('tells each side of the transfer something different', function () {
    $owner = owner();
    $nina = staffMember(StaffRole::SecondShooter);
    $owner->role = StaffRole::Photographer;
    $mail = new StaffOwnershipTransferred($owner, $nina, app(StudioSettingsResolver::class));

    expect((string) $mail->toMail($nina)->render())->toContain('hat dir die Inhaberschaft')
        ->and((string) $mail->toMail($owner)->render())->toContain('bist jetzt Fotograf');
});

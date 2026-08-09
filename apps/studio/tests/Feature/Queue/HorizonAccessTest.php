<?php

use App\Enums\StaffRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Owner is the only StaffRole whose capabilities() include ManageStaff (see
// StaffRole::capabilities()) — the same fact InvitationController's own
// "/team" tests lean on. Passed explicitly below anyway, so which role is
// being asserted is visible at the call site.
it('lets a ManageStaff session reach horizon', function () {
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')->get('/horizon')->assertOk();
});

it('refuses horizon to a staff session without ManageStaff', function () {
    $photographer = staffMember(StaffRole::Photographer);

    $this->actingAs($photographer, 'staff')->get('/horizon')->assertForbidden();
});

it('refuses horizon to a guest', function () {
    $this->get('/horizon')->assertForbidden();
});

it('still denies a session without ManageStaff in the local environment', function () {
    // The published Horizon provider's authorization() waves every request
    // through with `|| app()->environment('local')` — exactly the shortcut
    // M-00 rules out. HorizonServiceProvider overrides authorization() to
    // drop it; this fails on the stock behaviour and passes on the override.
    app()->instance('env', 'local');

    $photographer = staffMember(StaffRole::Photographer);

    $this->actingAs($photographer, 'staff')->get('/horizon')->assertForbidden();
});

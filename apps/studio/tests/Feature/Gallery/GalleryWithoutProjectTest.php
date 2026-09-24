<?php

use App\Enums\StaffRole;
use App\Gallery\Models\Gallery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
});

it('stands without a project', function () {
    $gallery = Gallery::factory()->create(['case_id' => null]);

    expect($gallery->fresh()->case_id)->toBeNull();
});

// Ohne Projekt gibt es nichts, woran ein Zugriff hängen könnte — die
// Capability allein entscheidet, und das heißt: jede Rolle mit ManageShoots
// sieht jede projektlose Galerie des Studios.
it('lets anyone with ManageShoots manage a gallery that has no project', function () {
    $gallery = Gallery::factory()->create(['case_id' => null]);

    expect($this->staff->can('manageMedia', $gallery))->toBeTrue();
});

it('refuses a role without ManageShoots', function () {
    $gallery = Gallery::factory()->create(['case_id' => null]);
    $viewer = User::factory()->withMfa()->create(['role' => StaffRole::Reader]);

    expect($viewer->can('manageMedia', $gallery))->toBeFalse();
});

it('serves the record for a gallery with no project', function () {
    $gallery = Gallery::factory()->create(['case_id' => null]);

    $props = props("/galerien/{$gallery->public_id}/einstellungen");

    expect($props['siblings'])->toBe([]);
});

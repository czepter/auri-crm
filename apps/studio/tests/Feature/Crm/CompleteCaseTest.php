<?php

use App\Crm\Enums\ShootStatus;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Shoot;
use App\Crm\Screens\OpenCases;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
    $this->case = ClientCase::factory()->create();
    markBooked($this->case);
});

it('completes a project whatever its shoots say, and reopens it', function () {
    Shoot::factory()->for($this->case, 'case')->primary()->atStatus(ShootStatus::Booked)->on(now()->subYear()->toDateString())->create();

    $this->post("/auftraege/{$this->case->public_id}/abschluss")->assertRedirect();

    $queued = collect(app(OpenCases::class)->queue($this->staff))->pluck('rows')->flatten()->pluck('id');
    $header = $this->get("/auftraege/{$this->case->public_id}")->viewData('page')['props']['header'];

    expect($this->case->fresh()->isCompleted())->toBeTrue()
        ->and($queued)->not->toContain($this->case->public_id)
        ->and($header['position']['source'])->toBe('completed')
        ->and(collect($header['track'])->firstWhere('state', 'current')['key'])->toBe('completed');

    $this->delete("/auftraege/{$this->case->public_id}/abschluss")->assertRedirect();

    expect($this->case->fresh()->isCompleted())->toBeFalse();
});

it('moves every open shoot when the stand of a booked project is set', function () {
    $wedding = Shoot::factory()->for($this->case, 'case')->primary()->atStatus(ShootStatus::Booked)->on(now()->subDay()->toDateString())->create();
    $engagement = Shoot::factory()->for($this->case, 'case')->atStatus(ShootStatus::Shot)->on(now()->subDay()->toDateString())->create();

    $this->patch("/auftraege/{$this->case->public_id}", ['stage' => ShootStatus::Delivered->value])
        ->assertSessionHasNoErrors();

    expect($wedding->fresh()->status->key)->toBe(ShootStatus::Delivered->value)
        ->and($engagement->fresh()->status->key)->toBe(ShootStatus::Delivered->value);
});

<?php

use App\Crm\Models\ClientCase;
use App\Crm\Models\Shoot;
use App\Gallery\Models\Gallery;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('carries its own date only while no shoot answers the question', function () {
    $gallery = Gallery::factory()->create(['shoot_id' => null, 'event_date' => '2026-08-22']);

    expect($gallery->fresh()->event_date->toDateString())->toBe('2026-08-22');
});

it('refuses a date beside a shoot', function () {
    $project = ClientCase::factory()->create();
    $shoot = Shoot::factory()->for($project, 'case')->create();

    expect(fn () => Gallery::factory()->for($project, 'case')->create([
        'shoot_id' => $shoot->id,
        'event_date' => '2026-08-22',
    ]))->toThrow(QueryException::class);
});

<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\TenantProbe;

beforeEach(function () {
    Schema::create('seam_probes', function (Blueprint $table) {
        $table->id();
        $table->tenantId();
        $table->publicId();
        $table->string('label');
    });
});

afterEach(fn () => Schema::dropIfExists('seam_probes'));

it('generates a 24 character cuid2 on create', function () {
    $probe = TenantProbe::create(['label' => 'first']);

    expect($probe->public_id)->toBeString()->toHaveLength(24)
        ->and($probe->public_id)->toMatch('/^[a-z][a-z0-9]{23}$/');
});

it('generates distinct identifiers that are not sequential', function () {
    $ids = collect(range(1, 20))
        ->map(fn (int $n) => TenantProbe::create(['label' => "probe-{$n}"])->public_id);

    expect($ids->unique())->toHaveCount(20)
        ->and($ids->sort()->values()->all())->not->toBe($ids->values()->all());
});

it('hides the internal primary key when serialising', function () {
    $probe = TenantProbe::create(['label' => 'first']);

    expect($probe->toArray())->not->toHaveKey('id')
        ->and($probe->toArray())->toHaveKey('public_id');
});

it('resolves route binding on the public id', function () {
    $probe = TenantProbe::create(['label' => 'first']);

    expect($probe->getRouteKeyName())->toBe('public_id')
        ->and($probe->getRouteKey())->toBe($probe->public_id);
});

it('finds a record by its public id', function () {
    $probe = TenantProbe::create(['label' => 'first']);

    expect(TenantProbe::findByPublicId($probe->public_id)?->id)->toBe($probe->id)
        ->and(TenantProbe::findByPublicId('nonexistentnonexistent01'))->toBeNull();
});

it('keeps the public id stable across updates', function () {
    $probe = TenantProbe::create(['label' => 'first']);
    $original = $probe->public_id;

    $probe->update(['label' => 'second']);

    expect($probe->fresh()->public_id)->toBe($original);
});

<?php

use App\Enums\StaffRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::forever('release-log.latest', '0.9.2');
    Cache::forever('release-log.feed', [
        ['version' => '0.9.2', 'date' => '2026-09-08', 'headline' => null,
            'sections' => [['key' => 'new', 'label' => 'Neu', 'count' => 1]]],
        ['version' => '0.9.1', 'date' => '2026-09-06', 'headline' => 'Das Angebot wird ein Regler',
            'sections' => [['key' => 'new', 'label' => 'Neu', 'count' => 22]]],
    ]);
    Cache::forever('release-log.release.0.9.1', [
        'version' => '0.9.1',
        'date' => '2026-09-06',
        'headline' => 'Das Angebot wird ein Regler',
        'body' => [],
        'image' => null,
        'sections' => [[
            'key' => 'new',
            'label' => 'Neu',
            'items' => [['type' => 'feat', 'title' => 'Regler im Portal', 'description' => null, 'commit' => '619c73c8']],
        ]],
    ]);
});

it('lists every release release-log knows', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/neuigkeiten')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Releases/Index')
            ->has('releases', 2)
            ->where('releases.1.headline', 'Das Angebot wird ein Regler'));
});

it('shows one release with its entries', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/neuigkeiten/0.9.1')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Releases/Show')
            ->where('release.sections.0.items.0.title', 'Regler im Portal'));
});

it('has nothing to show for a version release-log does not carry', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/neuigkeiten/0.8.0')
        ->assertNotFound();
});

// Die Zeile in der Seitenleiste steht auf jedem Admin-Screen, nicht nur auf
// diesem — deshalb wird sie am Dashboard geprüft.
it('carries the running version into every admin screen', function () {
    config(['app.version' => '0.9.1']);

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('release.installed', '0.9.1')
            ->where('release.updateAvailable', true));
});

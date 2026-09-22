<?php

use App\Crm\Models\ProjectType;
use App\Enums\StaffRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// staffMember() lives in tests/Pest.php.
beforeEach(function () {
    $this->actingAs(staffMember(StaffRole::Admin), 'staff');
});

it('opens the overlay over the board on the pipeline or type its address names', function (string $path, string $component, array $selection) {
    $this->get($path)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component($component)
            ->where('settingsSelection', $selection)
            ->has('stageSettings.pipelines', 2));
})->with([
    ['/anfragen/einstellungen/stufen', 'Leads/Board', ['kind' => 'pipeline', 'key' => 'lead']],
    ['/auftraege/einstellungen/stufen', 'Projects/Index', ['kind' => 'pipeline', 'key' => 'project']],
]);

it('opens the overlay on a project type by its key', function () {
    $key = ProjectType::query()->firstOrFail()->key;

    $this->get("/auftraege/einstellungen/typen/{$key}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Projects/Index')->where('settingsSelection', ['kind' => 'type', 'key' => $key]));

    $this->get('/auftraege/einstellungen/typen/gibt-es-nicht')->assertNotFound();
});

it('leaves the plain lists without an overlay', function () {
    foreach (['/anfragen', '/projekte'] as $path) {
        $this->get($path)->assertInertia(fn ($page) => $page->where('settingsSelection', null)->where('returnTo', null));
    }
});

it('refuses the addresses to a role that may not configure the funnel', function (string $path) {
    $this->actingAs(staffMember(StaffRole::Contributor), 'staff')->get($path)->assertNotFound();
})->with([
    '/anfragen/einstellungen/stufen',
    '/auftraege/einstellungen/stufen',
    '/auftraege/einstellungen/typen/wedding',
]);

it('hands on a local return path and ignores anything that leaves the domain', function (string $return, ?string $expected) {
    $this->get('/anfragen/einstellungen/stufen?'.http_build_query(['return' => $return]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('returnTo', $expected));
})->with([
    'a settings page' => ['/einstellungen/allgemein', '/einstellungen/allgemein'],
    'with a query' => ['/vorlagen?typ=aufgaben', '/vorlagen?typ=aufgaben'],
    'protocol-relative' => ['//evil.example', null],
    'backslash' => ['/\\evil.example', null],
    'absolute' => ['https://evil.example', null],
    'tab inside' => ["/\t/evil.example", null],
]);

it('shows the settings menu links only to who may open their targets', function () {
    $this->get('/anfragen')->assertInertia(fn ($page) => $page->where('settingsLinks', ['stages' => true, 'templates' => true]));

    $this->actingAs(staffMember(StaffRole::Contributor), 'staff')
        ->get('/anfragen')
        ->assertInertia(fn ($page) => $page->where('settingsLinks.stages', false));
});

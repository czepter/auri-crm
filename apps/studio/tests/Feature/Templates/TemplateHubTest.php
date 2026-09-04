<?php

use App\Crm\Models\FormTemplate;
use App\Crm\Models\TaskTemplate;
use App\Documents\Models\DocumentTemplate;
use App\Enums\StaffRole;
use App\Gallery\Models\GalleryPreset;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists every kind of template in one place', function () {
    TaskTemplate::factory()->create(['title' => 'Shotliste abstimmen']);
    FormTemplate::factory()->create(['name' => 'Ablauf Hochzeitstag']);
    DocumentTemplate::factory()->create(['name' => 'Fotovertrag']);
    GalleryPreset::factory()->create(['name' => 'Hochzeit komplett']);

    $props = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/vorlagen')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['kind'])->toBeNull()
        ->and($props['counts'])->toBe([
            'aufgaben' => 1,
            'fragebogen' => 1,
            'dokumente' => 1,
            'galerien' => 1,
        ])
        ->and(collect($props['taskTemplates'])->pluck('title')->all())->toBe(['Shotliste abstimmen'])
        ->and(collect($props['formTemplates'])->pluck('name')->all())->toBe(['Ablauf Hochzeitstag'])
        ->and(collect($props['documentTemplates'])->pluck('name')->all())->toBe(['Fotovertrag'])
        ->and(collect($props['galleryPresets'])->pluck('name')->all())->toBe(['Hochzeit komplett']);
});

it('offers a role only the kinds it may keep', function () {
    // Assistant hält Aufgaben und sonst nichts — kein ManageFormTemplates,
    // kein ManageContracts, kein ManageSettings.
    $props = $this->actingAs(staffMember(StaffRole::Assistant), 'staff')
        ->get('/vorlagen')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['kinds'])->toBe(['aufgaben'])
        ->and($props['formTemplates'])->toBe([])
        ->and($props['documentTemplates'])->toBe([])
        ->and($props['galleryPresets'])->toBe([]);
});

it('404s a kind the role may not keep', function () {
    $this->actingAs(staffMember(StaffRole::Assistant), 'staff')
        ->get('/vorlagen?typ=dokumente')
        ->assertNotFound();
});

it('rejects a kind that is not one of the four', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/vorlagen?typ=pakete')
        ->assertSessionHasErrors('typ');
});

it('sends the old settings entries to the hub', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff');

    $this->get('/einstellungen/aufgabenvorlagen')->assertRedirect('/vorlagen?typ=aufgaben');
    $this->get('/einstellungen/fragebogen-vorlagen')->assertRedirect('/vorlagen?typ=fragebogen');
    $this->get('/einstellungen/galerie-vorlagen')->assertRedirect('/vorlagen?typ=galerien');
    $this->get('/dokumentvorlagen')->assertRedirect('/vorlagen?typ=dokumente');
});

it('carries no integer identifier in the props', function () {
    TaskTemplate::factory()->create();
    FormTemplate::factory()->create();
    DocumentTemplate::factory()->create();
    GalleryPreset::factory()->create();

    $props = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/vorlagen')
        ->assertOk()
        ->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
});

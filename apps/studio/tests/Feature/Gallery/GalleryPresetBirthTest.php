<?php

use App\Catalog\Models\Package;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Lead;
use App\Crm\Models\Shoot;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Enums\StaffRole;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryPreset;
use App\Gallery\Models\GallerySet;
use App\Models\StudioSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withMfa()->create(), 'staff');
});

function fullPreset(array $overrides = []): GalleryPreset
{
    return GalleryPreset::factory()->create([
        'is_default' => true,
        'sets' => [
            ['name' => 'Highlights', 'access' => 'internal'],
            ['name' => 'Getting Ready', 'access' => 'internal'],
            ['name' => 'Fotobox', 'access' => 'guests'],
        ],
        'design' => ['cover' => 'geteilt', 'type' => 'serif', 'colour' => 'dunkel', 'size' => 'klein', 'spacing' => 'weit', 'flow' => 'spalten'],
        'view_mode' => 'text',
        'download_rule' => 'after_payment',
        'download_sizes' => ['web'],
        'permissions' => ['client' => ['comments' => false], 'guest' => ['favorites' => false]],
        'intro_enabled' => true,
        'expiry_reminder' => true,
        'expiry_mode' => 'days',
        'expiry_days' => 30,
        ...$overrides,
    ]);
}

it('applies sets, release defaults, design and expiry from the preset', function () {
    $preset = fullPreset();

    $this->post('/galerien', ['title' => 'Hochzeit', 'is_external' => false])->assertRedirect();

    $gallery = Gallery::sole();

    expect($gallery->sets()->orderBy('position')->get(['name', 'access'])->map->only(['name', 'access'])->all())->toBe([
        ['name' => 'Highlights', 'access' => 'internal'],
        ['name' => 'Getting Ready', 'access' => 'internal'],
        ['name' => 'Fotobox', 'access' => 'guests'],
    ])
        ->and($gallery->view_mode)->toBe('text')
        ->and($gallery->download_rule)->toBe('after_payment')
        ->and($gallery->download_sizes)->toBe(['web'])
        ->and($gallery->intro_enabled)->toBeTrue()
        ->and($gallery->expiry_reminder)->toBeTrue()
        ->and($gallery->allows('comments', true))->toBeFalse()
        ->and($gallery->allows('favorites', false))->toBeFalse()
        ->and($gallery->allows('favorites', true))->toBeTrue()
        ->and($gallery->design['cover'])->toBe('geteilt')
        ->and($gallery->expires_at->toDateString())->toBe(now()->addDays(30)->toDateString())
        ->and($gallery->gallery_preset_id)->toBe($preset->id);
});

it('keeps the single default set for a preset without sets', function () {
    GalleryPreset::factory()->create(['is_default' => true]);

    $this->post('/galerien', ['title' => 'Ohne', 'is_external' => false])->assertRedirect();

    expect(Gallery::sole()->sets()->pluck('name')->all())->toBe(['Highlights']);
});

it('keeps legacy switches in step with the preset matrix', function () {
    $preset = GalleryPreset::factory()->create([
        'permissions' => ['client' => ['favorites' => false], 'guest' => ['favorites' => false]],
        'download_rule' => 'off',
    ]);

    expect($preset->lists_enabled)->toBeFalse()
        ->and($preset->downloads_enabled)->toBeFalse()
        ->and($preset->comments_enabled)->toBeTrue();
});

it('saves the new preset fields from the settings room', function () {
    $family = DocumentTemplate::factory()->create(['type' => DocumentTemplateType::Mail])->family_id;

    $this->post('/einstellungen/galerie-vorlagen', [
        'name' => 'Hochzeit',
        'expiry_mode' => 'inherit',
        'sets' => [['name' => 'Fotobox', 'access' => 'guests']],
        'view_mode' => 'logo',
        'download_rule' => 'open',
        'download_sizes' => ['original'],
        'permissions' => ['guest' => ['share' => false]],
        'intro_enabled' => true,
        'expiry_reminder' => false,
        'mail_template_family_id' => $family,
    ])->assertRedirect();

    $preset = GalleryPreset::sole();

    expect($preset->sets)->toBe([['name' => 'Fotobox', 'access' => 'guests']])
        ->and($preset->view_mode)->toBe('logo')
        ->and($preset->permissions['guest']['share'])->toBeFalse()
        ->and($preset->mail_template_family_id)->toBe($family);
});

it('refuses a set access and a mail template that do not exist', function () {
    $this->post('/einstellungen/galerie-vorlagen', [
        'name' => 'Kaputt',
        'expiry_mode' => 'inherit',
        'sets' => [['name' => 'A', 'access' => 'alle']],
        'mail_template_family_id' => 'gibtesnicht',
    ])->assertSessionHasErrors(['sets.0.access', 'mail_template_family_id']);
});

it('captures sets and release defaults when saving a gallery as a preset', function () {
    $gallery = Gallery::factory()->create(['view_mode' => 'locked', 'intro_enabled' => true]);
    $gallery->sets()->delete();
    GallerySet::factory()->create(['gallery_id' => $gallery->id, 'name' => 'Fotobox', 'access' => 'guests']);

    $this->post("/galerien/{$gallery->public_id}/vorlage", ['name' => 'Aus Galerie'])->assertRedirect();

    $preset = GalleryPreset::sole();

    expect($preset->sets)->toBe([['name' => 'Fotobox', 'access' => 'guests']])
        ->and($preset->view_mode)->toBe('locked')
        ->and($preset->intro_enabled)->toBeTrue();
});

it('prefills the send dialog from the preset mail template', function () {
    $template = DocumentTemplate::factory()->create([
        'type' => DocumentTemplateType::Mail,
        'body' => '<p>Die Bilder zu {{ project.name }} sind da.</p>',
    ]);
    $preset = GalleryPreset::factory()->create(['mail_template_family_id' => $template->family_id]);
    $project = ClientCase::factory()->create(['name' => 'Meier']);
    $gallery = Gallery::factory()->for($project, 'case')->create(['gallery_preset_id' => $preset->id]);

    expect(props("/galerien/{$gallery->public_id}")['gallery']['mailMessage'])->toBe('Die Bilder zu Meier sind da.');

    $gallery->update(['gallery_preset_id' => null]);

    expect(props("/galerien/{$gallery->public_id}")['gallery']['mailMessage'])->toBeNull();
});

it('creates the project gallery with one click from the package preset', function () {
    $preset = fullPreset();
    $package = Package::factory()->create(['gallery_preset_id' => $preset->id]);
    $project = ClientCase::factory()->create(['name' => 'Meier Hochzeit']);
    Lead::factory()->create(['case_id' => $project->id, 'package_id' => $package->id]);
    Shoot::factory()->primary()->on('2027-06-12')->create(['case_id' => $project->id]);

    $this->post("/auftraege/{$project->public_id}/galerien")->assertRedirect();

    $gallery = Gallery::sole();

    expect($gallery->title)->toBe('Meier Hochzeit')
        ->and($gallery->case_id)->toBe($project->id)
        ->and($gallery->occursOn()->toDateString())->toBe('2027-06-12')
        ->and($gallery->gallery_preset_id)->toBe($preset->id)
        ->and($gallery->sets()->count())->toBe(3)
        ->and($gallery->isWithdrawn())->toBeFalse()
        ->and($gallery->currentVersion)->toBeNull();
});

it('names the gallery after the chosen shoot and takes its date', function () {
    $project = ClientCase::factory()->create(['name' => 'Meier']);
    $shoot = Shoot::factory()->on('2027-05-01')->create(['case_id' => $project->id, 'kind' => 'Trauung', 'label' => null]);

    $this->post("/auftraege/{$project->public_id}/galerien", ['shoot_id' => $shoot->public_id])->assertRedirect();

    $gallery = Gallery::sole();

    expect($gallery->title)->toBe('Trauung')
        ->and($gallery->shoot_id)->toBe($shoot->id)
        ->and($gallery->occursOn()->toDateString())->toBe('2027-05-01');
});

it('falls back to the default preset, then to the studio defaults', function () {
    $project = ClientCase::factory()->create();
    $default = GalleryPreset::factory()->create(['is_default' => true, 'view_mode' => 'logo']);

    $this->post("/auftraege/{$project->public_id}/galerien")->assertRedirect();
    expect(Gallery::latest('id')->first()->view_mode)->toBe('logo')
        ->and(Gallery::latest('id')->first()->gallery_preset_id)->toBe($default->id);

    $default->delete();
    $this->post("/auftraege/{$project->public_id}/galerien")->assertRedirect();

    expect(Gallery::latest('id')->first()->view_mode)->toBe('none')
        ->and(Gallery::latest('id')->first()->design)->toHaveKey('cover');
});

it('refuses the one-click gallery to a role that may not manage shoots', function () {
    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Reader]), 'staff');
    $project = ClientCase::factory()->create();

    $this->post("/auftraege/{$project->public_id}/galerien")->assertForbidden();

    expect(Gallery::count())->toBe(0);
});

it('stores the preset on a package and clears it when the preset goes', function () {
    $preset = GalleryPreset::factory()->create();
    $package = Package::factory()->create();

    $this->patch("/pakete/{$package->public_id}", ['name' => $package->name, 'gallery_preset_id' => $preset->public_id])->assertRedirect();
    expect($package->fresh()->gallery_preset_id)->toBe($preset->id);

    $this->patch("/pakete/{$package->public_id}", ['name' => $package->name])->assertRedirect();
    expect($package->fresh()->gallery_preset_id)->toBe($preset->id);

    $preset->delete();
    expect($package->fresh()->gallery_preset_id)->toBeNull();
});

it('refuses an unknown preset on a package', function () {
    $package = Package::factory()->create();

    $this->patch("/pakete/{$package->public_id}", ['name' => $package->name, 'gallery_preset_id' => 'nein'])
        ->assertSessionHasErrors('gallery_preset_id');
});

it('is born with the studio download rule, and a preset rule wins over it', function () {
    StudioSettings::current()->forceFill(['gallery_default_download_rule' => 'after_payment'])->save();

    $this->post('/galerien', ['title' => 'Studio', 'is_external' => false])->assertRedirect();
    GalleryPreset::factory()->create(['is_default' => true, 'download_rule' => 'off']);
    $this->post('/galerien', ['title' => 'Vorlage', 'is_external' => false])->assertRedirect();

    expect(Gallery::query()->where('title', 'Studio')->value('download_rule'))->toBe('after_payment')
        ->and(Gallery::query()->where('title', 'Vorlage')->first())->download_rule->toBe('off')->downloads_enabled->toBeFalse();
});

it('reads a preset from before the rule as open or off', function () {
    GalleryPreset::factory()->create(['is_default' => true, 'downloads_enabled' => false, 'download_rule' => null]);
    $this->post('/galerien', ['title' => 'Alt', 'is_external' => false])->assertRedirect();

    expect(Gallery::sole()->download_rule)->toBe('off');
});

it('resolves the booked package from the lead only', function () {
    $package = Package::factory()->create();
    $booked = ClientCase::factory()->create();
    Lead::factory()->create(['case_id' => $booked->id, 'package_id' => $package->id]);
    $withoutPackage = ClientCase::factory()->create();
    Lead::factory()->create(['case_id' => $withoutPackage->id, 'package_id' => null]);

    expect($booked->bookedPackage()?->is($package))->toBeTrue()
        ->and($withoutPackage->bookedPackage())->toBeNull()
        ->and(ClientCase::factory()->create()->bookedPackage())->toBeNull();
});

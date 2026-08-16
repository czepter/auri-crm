<?php

use App\Documents\Actions\SeedStarterTemplates;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Documents\Support\StarterTemplateCatalog;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('finishes onboarding with a current template for each offered type', function () {
    app(SeedStarterTemplates::class)(StarterTemplateCatalog::onboardingDefaults(), staffMember(StaffRole::Owner));

    foreach ([DocumentTemplateType::Quote, DocumentTemplateType::Contract, DocumentTemplateType::Invoice, DocumentTemplateType::Mail] as $type) {
        expect(DocumentTemplate::query()->current()->ofType($type)->exists())->toBeTrue("no {$type->value} template");
    }
});

it('writes a first version through the ordinary path, body and blocks alike', function () {
    app(SeedStarterTemplates::class)(['contract-werkvertrag'], staffMember(StaffRole::Owner));

    $template = DocumentTemplate::sole();

    expect($template->version)->toBe(1)
        ->and($template->is_current)->toBeTrue()
        ->and($template->family_id)->not->toBeEmpty()
        // The body is rendered server-side, because no browser is in the loop.
        ->and($template->body)->toContain('<h2>Fotovertrag</h2>')
        ->and($template->body)->toContain('{{ settings.widerrufText }}')
        ->and($template->blocks['blocks'])->not->toBeEmpty();
});

it('leaves the template count unchanged when the step runs twice', function () {
    $seed = app(SeedStarterTemplates::class);
    $actor = staffMember(StaffRole::Owner);

    $first = $seed(StarterTemplateCatalog::onboardingDefaults(), $actor);
    $second = $seed(StarterTemplateCatalog::onboardingDefaults(), $actor);

    expect($first)->toBeGreaterThan(0)
        ->and($second)->toBe(0)
        ->and(DocumentTemplate::count())->toBe($first);
});

it('never imposes a second copy on a studio that renamed its own', function () {
    $seed = app(SeedStarterTemplates::class);
    $seed(['contract-werkvertrag'], staffMember(StaffRole::Owner));

    // The studio edits the wording and keeps the name. Re-running the step
    // must not put the original back beside it.
    DocumentTemplate::sole()->update(['body' => '<p>Unsere eigene Fassung.</p>']);
    $seed(['contract-werkvertrag'], staffMember(StaffRole::Owner));

    expect(DocumentTemplate::count())->toBe(1)
        ->and(DocumentTemplate::sole()->body)->toBe('<p>Unsere eigene Fassung.</p>');
});

it('records who accepted each seeded template', function () {
    app(SeedStarterTemplates::class)(['contract-werkvertrag', 'invoice-standard'], staffMember(StaffRole::Owner));

    $rows = AuditLog::where('event', 'document_template.seeded')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->first()->actor_id)->not->toBeNull()
        ->and($rows->pluck('context.starter')->all())->toContain('contract-werkvertrag', 'invoice-standard');
});

it('ignores a key the catalog does not know rather than failing the step', function () {
    $written = app(SeedStarterTemplates::class)(['nope', 'contract-werkvertrag'], staffMember(StaffRole::Owner));

    expect($written)->toBe(1);
});

// --- the wizard step ----------------------------------------------------

it('offers the catalog on the onboarding wizard, without shipping every document body', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/einrichtung/studio')
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];

            expect($props['starterDefaults'])->toBe(StarterTemplateCatalog::onboardingDefaults())
                ->and($props['starters']['contract'][0])->toHaveKeys(['key', 'name', 'description'])
                // Choosing does not need the blocks, and shipping them all
                // would be page weight nobody asked for.
                ->and($props['starters']['contract'][0])->not->toHaveKey('blocks');
        });
});

it('seeds the chosen set from the wizard', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post('/dokumentvorlagen/starter', ['keys' => ['contract-werkvertrag']])
        ->assertRedirect();

    expect(DocumentTemplate::sole()->name)->toBe('Fotovertrag (Werkvertrag)');
});

it('treats skipping as a real answer, leaving the tenant with none', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post('/dokumentvorlagen/starter', ['keys' => []])
        ->assertRedirect();

    expect(DocumentTemplate::count())->toBe(0);
});

it('refuses a role that may not create a template', function () {
    $this->actingAs(staffMember(StaffRole::Assistant), 'staff')
        ->post('/dokumentvorlagen/starter', ['keys' => ['contract-werkvertrag']])
        ->assertForbidden();

    expect(DocumentTemplate::count())->toBe(0);
});

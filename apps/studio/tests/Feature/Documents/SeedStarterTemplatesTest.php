<?php

use App\Documents\Actions\SeedStarterTemplates;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Documents\Support\StarterTemplateCatalog;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('finishes onboarding with a current template for the quote and the mails', function () {
    app(SeedStarterTemplates::class)(StarterTemplateCatalog::onboardingDefaults(), staffMember(StaffRole::Owner));

    foreach ([DocumentTemplateType::Quote, DocumentTemplateType::Mail] as $type) {
        expect(DocumentTemplate::query()->current()->ofType($type)->exists())->toBeTrue("no {$type->value} template");
    }

    // Die Rechnungsvorlage entsteht erst mit der ersten Rechnung — welche
    // der beiden Fassungen richtig ist, entscheidet der USt.-Modus.
    expect(DocumentTemplate::query()->current()->ofType(DocumentTemplateType::Invoice)->exists())->toBeFalse();
});

it('writes a first version through the ordinary path, body and blocks alike', function () {
    app(SeedStarterTemplates::class)(['quote-generic'], staffMember(StaffRole::Owner));

    $template = DocumentTemplate::sole();

    expect($template->version)->toBe(1)
        ->and($template->is_current)->toBeTrue()
        ->and($template->family_id)->not->toBeEmpty()
        // The body is rendered server-side, because no browser is in the loop.
        ->and($template->body)->toContain('<h2>Angebot {{ project.name }}</h2>')
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
    $seed(['quote-generic'], staffMember(StaffRole::Owner));

    // The studio edits the wording and keeps the name. Re-running the step
    // must not put the original back beside it.
    DocumentTemplate::sole()->update(['body' => '<p>Unsere eigene Fassung.</p>']);
    $seed(['quote-generic'], staffMember(StaffRole::Owner));

    expect(DocumentTemplate::count())->toBe(1)
        ->and(DocumentTemplate::sole()->body)->toBe('<p>Unsere eigene Fassung.</p>');
});

it('records who accepted each seeded template', function () {
    app(SeedStarterTemplates::class)(['quote-generic', 'invoice-standard'], staffMember(StaffRole::Owner));

    $rows = AuditLog::where('event', 'document_template.seeded')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->first()->actor_id)->not->toBeNull()
        ->and($rows->pluck('context.starter')->all())->toContain('quote-generic', 'invoice-standard');
});

it('ignores a key the catalog does not know rather than failing the step', function () {
    $written = app(SeedStarterTemplates::class)(['nope', 'quote-generic'], staffMember(StaffRole::Owner));

    expect($written)->toBe(1);
});

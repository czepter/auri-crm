<?php

use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('starts a fresh template family at version 1, current', function () {
    $template = DocumentTemplate::createFirstVersion(['name' => 'Hochzeit', 'body' => '<p>v1</p>', 'type' => DocumentTemplateType::Contract]);

    expect($template->version)->toBe(1)
        ->and($template->is_current)->toBeTrue()
        ->and($template->type)->toBe(DocumentTemplateType::Contract)
        ->and($template->shoot_kind)->toBeNull();
});

it('creates a new version rather than mutating the current row', function () {
    $v1 = DocumentTemplate::createFirstVersion(['name' => 'Hochzeit', 'body' => '<p>v1</p>', 'type' => DocumentTemplateType::Contract]);

    $v2 = $v1->newVersion(['name' => 'Hochzeit', 'body' => '<p>v2</p>']);

    expect($v2->id)->not->toBe($v1->id)
        ->and($v2->version)->toBe(2)
        ->and($v2->is_current)->toBeTrue()
        ->and($v2->family_id)->toBe($v1->family_id);

    // The old row is untouched — not deleted, not flipped to the new body.
    $v1->refresh();
    expect($v1->body)->toBe('<p>v1</p>')
        ->and($v1->is_current)->toBeFalse();
});

it('keeps every old version queryable by its own public id', function () {
    $v1 = DocumentTemplate::createFirstVersion(['name' => 'Hochzeit', 'body' => '<p>v1</p>', 'type' => DocumentTemplateType::Contract]);
    $v1->newVersion(['name' => 'Hochzeit', 'body' => '<p>v2</p>']);

    $found = DocumentTemplate::findByPublicId($v1->public_id);

    expect($found)->not->toBeNull()
        ->and($found->body)->toBe('<p>v1</p>');
});

it('numbers a third version off the family maximum, not off whichever row called it', function () {
    $v1 = DocumentTemplate::createFirstVersion(['name' => 'Hochzeit', 'body' => '<p>v1</p>', 'type' => DocumentTemplateType::Contract]);
    $v2 = $v1->newVersion(['name' => 'Hochzeit', 'body' => '<p>v2</p>']);
    $v3 = $v2->newVersion(['name' => 'Hochzeit', 'body' => '<p>v3</p>']);

    expect($v3->version)->toBe(3)
        ->and(DocumentTemplate::current()->where('family_id', $v1->family_id)->sole()->id)->toBe($v3->id);
});

it('scopes current() to exactly the one row per family with the pointer', function () {
    $v1 = DocumentTemplate::createFirstVersion(['name' => 'A', 'body' => '<p>a</p>', 'type' => DocumentTemplateType::Contract]);
    $v1->newVersion(['name' => 'A', 'body' => '<p>b</p>']);

    expect(DocumentTemplate::current()->where('family_id', $v1->family_id)->count())->toBe(1);
});

/** T-12: type and shoot_kind name the family and carry forward across every version, never accepted as edit input. */
it('carries type and shoot_kind forward across a new version rather than accepting them as edit input', function () {
    $v1 = DocumentTemplate::createFirstVersion([
        'name' => 'Hochzeitsangebot', 'body' => '<p>v1</p>',
        'type' => DocumentTemplateType::Quote, 'shoot_kind' => 'wedding',
    ]);

    $v2 = $v1->newVersion(['name' => 'Hochzeitsangebot', 'body' => '<p>v2</p>']);

    expect($v2->type)->toBe(DocumentTemplateType::Quote)
        ->and($v2->shoot_kind)->toBe('wedding');
});

it('scopes ofType to exactly the templates of one document type', function () {
    DocumentTemplate::factory()->create(['type' => DocumentTemplateType::Contract]);
    DocumentTemplate::factory()->create(['type' => DocumentTemplateType::Quote]);
    DocumentTemplate::factory()->create(['type' => DocumentTemplateType::Mail]);

    expect(DocumentTemplate::current()->ofType(DocumentTemplateType::Quote)->count())->toBe(1);
});

/** T-12's own acceptance criterion: variants per shoot kind rather than one template full of conditionals. */
it('scopes forShootKind to the generic templates plus the matching variant', function () {
    $generic = DocumentTemplate::factory()->create(['type' => DocumentTemplateType::Contract, 'shoot_kind' => null]);
    $wedding = DocumentTemplate::factory()->create(['type' => DocumentTemplateType::Contract, 'shoot_kind' => 'wedding']);
    DocumentTemplate::factory()->create(['type' => DocumentTemplateType::Contract, 'shoot_kind' => 'portrait']);

    $offered = DocumentTemplate::current()->ofType(DocumentTemplateType::Contract)->forShootKind('wedding')->pluck('id');

    expect($offered->sort()->values()->all())->toBe(collect([$generic->id, $wedding->id])->sort()->values()->all());
});

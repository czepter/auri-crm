<?php

use App\Contracts\Models\ContractTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('starts a fresh template family at version 1, current', function () {
    $template = ContractTemplate::createFirstVersion(['name' => 'Hochzeit', 'body' => '<p>v1</p>']);

    expect($template->version)->toBe(1)
        ->and($template->is_current)->toBeTrue();
});

it('creates a new version rather than mutating the current row', function () {
    $v1 = ContractTemplate::createFirstVersion(['name' => 'Hochzeit', 'body' => '<p>v1</p>']);

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
    $v1 = ContractTemplate::createFirstVersion(['name' => 'Hochzeit', 'body' => '<p>v1</p>']);
    $v1->newVersion(['name' => 'Hochzeit', 'body' => '<p>v2</p>']);

    $found = ContractTemplate::findByPublicId($v1->public_id);

    expect($found)->not->toBeNull()
        ->and($found->body)->toBe('<p>v1</p>');
});

it('numbers a third version off the family maximum, not off whichever row called it', function () {
    $v1 = ContractTemplate::createFirstVersion(['name' => 'Hochzeit', 'body' => '<p>v1</p>']);
    $v2 = $v1->newVersion(['name' => 'Hochzeit', 'body' => '<p>v2</p>']);
    $v3 = $v2->newVersion(['name' => 'Hochzeit', 'body' => '<p>v3</p>']);

    expect($v3->version)->toBe(3)
        ->and(ContractTemplate::current()->where('family_id', $v1->family_id)->sole()->id)->toBe($v3->id);
});

it('scopes current() to exactly the one row per family with the pointer', function () {
    $v1 = ContractTemplate::createFirstVersion(['name' => 'A', 'body' => '<p>a</p>']);
    $v1->newVersion(['name' => 'A', 'body' => '<p>b</p>']);

    expect(ContractTemplate::current()->where('family_id', $v1->family_id)->count())->toBe(1);
});

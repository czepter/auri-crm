<?php

use App\Crm\Enums\FormFieldType;
use App\Crm\Models\FormAnswer;
use App\Crm\Models\FormInstance;
use App\Crm\Models\FormInstanceField;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function formInstanceField(): FormInstanceField
{
    return FormInstanceField::factory()->for(FormInstance::factory(), 'formInstance')->create();
}

it('has no current answer before the field has ever been answered', function () {
    $field = formInstanceField();

    expect($field->currentAnswer)->toBeNull()
        ->and($field->answers()->count())->toBe(0);
});

it('answering produces an immutable version 1', function () {
    $field = formInstanceField();

    $v1 = $field->answer('Ab 9 Uhr im Louis C. Jacob, Suite 12.');

    expect($v1)->toBeInstanceOf(FormAnswer::class)
        ->and($v1->version)->toBe(1)
        ->and($v1->value)->toBe('Ab 9 Uhr im Louis C. Jacob, Suite 12.')
        ->and($v1->created_at)->not->toBeNull();

    expect($field->currentAnswer()->first()->id)->toBe($v1->id);
});

// The acceptance criterion, proven directly: editing an answer after
// submission preserves the previous value and its original timestamp, and
// the new value is what "current" now reads.
it('editing an answer preserves the previous value and its timestamp, and the new value becomes current', function () {
    $field = formInstanceField();

    $v1 = $field->answer('Strand');
    $v1CreatedAt = $v1->created_at;

    $this->travel(2)->days();

    $v2 = $field->answer('Stadt');

    expect($v2->id)->not->toBe($v1->id)
        ->and($v2->version)->toBe(2)
        ->and($v2->value)->toBe('Stadt');

    // v1's exact original value and timestamp — untouched by the edit.
    $v1->refresh();
    expect($v1->version)->toBe(1)
        ->and($v1->value)->toBe('Strand')
        ->and($v1->created_at->equalTo($v1CreatedAt))->toBeTrue();

    expect($field->currentAnswer()->first()->id)->toBe($v2->id);
    expect(FormAnswer::findByPublicId($v1->public_id)->value)->toBe('Strand');
});

it('numbers versions off the field maximum, not off a counter that could be raced', function () {
    $field = formInstanceField();
    $field->answer('a');
    $field->answer('b');
    $v3 = $field->answer('c');

    expect($v3->version)->toBe(3)
        ->and($field->answers()->count())->toBe(3);
});

it('scopes answers to their own field, not to every field on the instance', function () {
    $a = formInstanceField();
    $b = formInstanceField();

    $a->answer('1');
    $a->answer('2');
    $b->answer('1');

    expect($a->answers()->count())->toBe(2)
        ->and($b->answers()->count())->toBe(1)
        ->and($b->currentAnswer->version)->toBe(1);
});

it('accepts a null value, leaving the question explicitly unanswered rather than absent', function () {
    $field = formInstanceField();

    $v1 = $field->answer(null);

    expect($v1->value)->toBeNull()
        ->and($field->currentAnswer->value)->toBeNull();
});

// Aufnahme durch das Studio: dieselbe Antwort, am Telefon mitgeschrieben.
// Sie läuft durch dieselbe Normalisierung wie die aus dem Portal.
it('normalises a group photo plan the studio records itself', function () {
    $field = FormInstanceField::factory()
        ->for(FormInstance::factory(), 'formInstance')
        ->create(['type' => FormFieldType::GroupPhotos, 'options' => ['suggestions' => [], 'minutesPerGroup' => 2]]);

    $this->actingAs(staffMember(), 'staff')
        ->patch("/frageboegen-felder/{$field->public_id}/antwort", [
            'value' => json_encode([
                'people' => ['Anna', 'Jonas'],
                'groups' => [['name' => 'Trauzeugen', 'members' => ['Anna', 'Niemand']]],
            ]),
        ])
        ->assertRedirect();

    expect(json_decode($field->fresh()->currentAnswer->value, true))->toBe([
        'people' => ['Anna', 'Jonas'],
        'groups' => [['name' => 'Trauzeugen', 'members' => ['Anna']]],
    ]);
});

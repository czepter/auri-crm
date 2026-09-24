<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Enums\FormFieldType;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\FormInstance;
use App\Crm\Models\FormInstanceField;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::PORTAL));

/** @return array{0: FormInstanceField, 1: Contact, 2: string} */
function groupPhotoQuestion(bool $required = false): array
{
    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    $instance = FormInstance::factory()->for($project, 'case')->create(['name' => 'Gruppenfotos']);
    $instance->forceFill(['shared_at' => now()])->save();

    $field = FormInstanceField::factory()->for($instance)->create([
        'label' => 'Welche Gruppenfotos wünscht ihr euch?',
        'type' => FormFieldType::GroupPhotos,
        'required' => $required,
        'options' => ['help' => null, 'suggestions' => ['Großeltern'], 'minutesPerGroup' => 2],
    ]);

    return [$field, $contact, "/projekte/{$project->public_id}/fragebogen/{$instance->public_id}"];
}

it('keeps a group photo plan as json and drops a member who is not in the people list', function () {
    [$field, $contact, $url] = groupPhotoQuestion();

    test()->actingAs($contact, 'portal')->patch($url, [
        'answers' => [$field->public_id => json_encode([
            'people' => ['Anna', 'Jonas', ' Ben '],
            'groups' => [
                ['name' => 'Trauzeugen', 'members' => ['Anna', 'Ben', 'Wer auch immer'], 'minutes' => 99],
            ],
            'shoot' => 'nichts hier verloren',
        ])],
    ])->assertRedirect();

    expect(json_decode($field->fresh()->currentAnswer->value, true))->toBe([
        'people' => ['Anna', 'Jonas', 'Ben'],
        'groups' => [['name' => 'Trauzeugen', 'members' => ['Anna', 'Ben']]],
    ]);
});

it('counts an empty plan as unanswered on a required question', function () {
    [$field, $contact, $url] = groupPhotoQuestion(required: true);

    test()->actingAs($contact, 'portal')
        ->patch($url, ['answers' => [$field->public_id => '{"people":[],"groups":[]}']])
        ->assertSessionHasErrors("answers.{$field->public_id}");

    expect($field->fresh()->currentAnswer)->toBeNull();
});

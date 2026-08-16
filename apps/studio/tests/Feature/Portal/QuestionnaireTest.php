<?php

use App\Crm\Enums\FormFieldType;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\FormAnswer;
use App\Crm\Models\FormInstance;
use App\Crm\Models\FormInstanceField;
use App\Crm\Models\Project;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::PORTAL));

/** @return array{0: FormInstance, 1: Contact, 2: Project} */
function sharedQuestionnaire(bool $shared = true): array
{
    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    $instance = FormInstance::factory()->for($project)->create(['name' => 'Vor der Hochzeit']);
    FormInstanceField::factory()->for($instance)->create([
        'label' => 'Erster Tanz', 'type' => FormFieldType::ShortText, 'required' => true, 'sort_order' => 0,
    ]);
    FormInstanceField::factory()->for($instance)->create([
        'label' => 'Wünsche', 'type' => FormFieldType::Text, 'required' => false, 'sort_order' => 1,
    ]);

    if ($shared) {
        $instance->forceFill(['shared_at' => now()])->save();
    }

    return [$instance->fresh(), $contact, $project];
}

// P-03's own gap: the inquiry screen linked to a form nobody could fill in.
it('opens a shared questionnaire for the client', function () {
    [$instance, $contact, $project] = sharedQuestionnaire();

    $props = test()->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/fragebogen/{$instance->public_id}")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['questionnaire']['name'])->toBe('Vor der Hochzeit')
        ->and($props['questionnaire']['fields'])->toHaveCount(2);
});

// A studio's internal checklist lives on the same table.
it('404s a questionnaire the studio has not shared', function () {
    [$instance, $contact, $project] = sharedQuestionnaire(shared: false);

    test()->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/fragebogen/{$instance->public_id}")
        ->assertNotFound();
});

it('404s a questionnaire on somebody else\'s booking', function () {
    [$instance, , $project] = sharedQuestionnaire();

    test()->actingAs(Contact::factory()->create(), 'portal')
        ->get("/projekte/{$project->public_id}/fragebogen/{$instance->public_id}")
        ->assertNotFound();
});

it('records the client\'s answers as a new version each, naming them', function () {
    [$instance, $contact, $project] = sharedQuestionnaire();
    [$first, $second] = $instance->fields;

    test()->actingAs($contact, 'portal')
        ->patch("/projekte/{$project->public_id}/fragebogen/{$instance->public_id}", [
            'answers' => [$first->public_id => 'Perfect von Ed Sheeran', $second->public_id => 'Viel Schwarzweiß.'],
        ])
        ->assertRedirect();

    expect($first->fresh()->currentAnswer->value)->toBe('Perfect von Ed Sheeran')
        ->and($first->fresh()->currentAnswer->contact_id)->toBe($contact->id)
        ->and($first->fresh()->currentAnswer->author_id)->toBeNull();
});

// form_answers is append-only; revising is another version, never an update.
it('appends a version when the client revises an answer', function () {
    [$instance, $contact, $project] = sharedQuestionnaire();
    $field = $instance->fields->first();
    $url = "/projekte/{$project->public_id}/fragebogen/{$instance->public_id}";

    test()->actingAs($contact, 'portal')->patch($url, ['answers' => [$field->public_id => 'Erste Wahl']]);
    test()->patch($url, ['answers' => [$field->public_id => 'Doch etwas anderes']]);

    expect($field->fresh()->answers()->count())->toBe(2)
        ->and($field->fresh()->currentAnswer->value)->toBe('Doch etwas anderes')
        ->and($field->fresh()->answers()->orderBy('version')->first()->value)->toBe('Erste Wahl');
});

it('writes nothing when the questionnaire comes back unchanged', function () {
    [$instance, $contact, $project] = sharedQuestionnaire();
    $field = $instance->fields->first();
    $url = "/projekte/{$project->public_id}/fragebogen/{$instance->public_id}";

    test()->actingAs($contact, 'portal')->patch($url, ['answers' => [$field->public_id => 'Erste Wahl']]);
    test()->patch($url, ['answers' => [$field->public_id => 'Erste Wahl']]);

    expect(FormAnswer::count())->toBe(1);
});

it('holds the client to a required question', function () {
    [$instance, $contact, $project] = sharedQuestionnaire();
    [$first, $second] = $instance->fields;

    test()->actingAs($contact, 'portal')
        ->patch("/projekte/{$project->public_id}/fragebogen/{$instance->public_id}", [
            'answers' => [$first->public_id => '  ', $second->public_id => 'Nur das Optionale.'],
        ])
        ->assertSessionHasErrors("answers.{$first->public_id}");

    expect(FormAnswer::count())->toBe(0);
});

// Both surfaces append through the same method; only the name on the row
// tells a phone call apart from the client's own typing.
it('names staff on an answer the studio records', function () {
    [$instance] = sharedQuestionnaire();
    $field = $instance->fields->first();
    $staff = App\Models\User::factory()->create();

    $field->answer('Am Telefon gesagt', $staff);

    expect($field->fresh()->currentAnswer->author_id)->toBe($staff->id)
        ->and($field->fresh()->currentAnswer->contact_id)->toBeNull();
});

it('lists a shared questionnaire on the booking screen and hides an unshared one', function () {
    [$instance, $contact, $project] = sharedQuestionnaire();

    $props = test()->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['project']['questionnaires'])->toHaveCount(1)
        ->and($props['project']['questionnaires'][0]['openCount'])->toBe(2);

    $instance->forceFill(['shared_at' => null])->save();

    $props = test()->get("/projekte/{$project->public_id}")->assertOk()->viewData('page')['props'];

    expect($props['project']['questionnaires'])->toBe([]);
});

// Taking a questionnaire back hides the screen; it never deletes what the
// client already said.
it('keeps the answers when the studio un-shares', function () {
    useSurface(Surface::ADMIN);

    [$instance] = sharedQuestionnaire();
    $instance->fields->first()->answer('Perfect von Ed Sheeran');

    test()->actingAs(staffMember(), 'staff')
        ->post("/frageboegen/{$instance->public_id}/freigeben", ['shared' => false])
        ->assertRedirect();

    expect($instance->fresh()->isShared())->toBeFalse()
        ->and(FormAnswer::count())->toBe(1);
});

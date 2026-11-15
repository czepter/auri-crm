<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Enums\FormFieldType;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\FormAnswer;
use App\Crm\Models\FormInstance;
use App\Crm\Models\FormInstanceField;
use App\Models\User;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::PORTAL));

/** @return array{0: FormInstance, 1: Contact, 2: ClientCase} */
function sharedQuestionnaire(bool $shared = true): array
{
    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    $instance = FormInstance::factory()->for($project, 'case')->create(['name' => 'Vor der Hochzeit']);
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

it('hands the portal each question\'s help text', function () {
    [$instance, $contact, $project] = sharedQuestionnaire();
    $instance->fields()->where('label', 'Wünsche')->update(['help' => "Zeile eins\nZeile zwei"]);

    $fields = test()->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/fragebogen/{$instance->public_id}")
        ->viewData('page')['props']['questionnaire']['fields'];

    expect(collect($fields)->pluck('help', 'label')->all())->toBe(['Erster Tanz' => null, 'Wünsche' => "Zeile eins\nZeile zwei"]);
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
    $staff = User::factory()->create();

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

it('shows a closed questionnaire but takes no more answers', function () {
    [$instance, $contact, $project] = sharedQuestionnaire();
    $field = $instance->fields->first();
    $field->answer('Perfect von Ed Sheeran');
    $instance->forceFill(['closed_at' => now()])->save();
    $url = "/projekte/{$project->public_id}/fragebogen/{$instance->public_id}";

    $props = test()->actingAs($contact, 'portal')->get($url)->assertOk()->viewData('page')['props'];
    expect($props['questionnaire']['closed'])->toBeTrue();

    test()->patch($url, ['answers' => [$field->public_id => 'Doch etwas anderes']])->assertForbidden();

    expect($field->fresh()->currentAnswer->value)->toBe('Perfect von Ed Sheeran');
});

// --- "Datei" fields: the client attaches, and may take back ------------

/** @return array{0: FormInstance, 1: Contact, 2: FormInstanceField} */
function questionnaireWithFileField(array $options = ['formats' => ['pdf'], 'maxSize' => 5, 'multiple' => false], bool $required = false): array
{
    [$instance, $contact] = sharedQuestionnaire();
    $field = FormInstanceField::factory()->for($instance)->create([
        'label' => 'Ablaufplan eurer Planerin',
        'type' => FormFieldType::File,
        'required' => $required,
        'options' => $options,
        'sort_order' => 2,
    ]);

    return [$instance->fresh(), $contact, $field];
}

it('takes a file the client uploads for a file field', function () {
    Storage::fake('local');
    [$instance, $contact, $field] = questionnaireWithFileField();

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$instance->case->public_id}/fragebogen/{$instance->public_id}/datei", [
            'field' => $field->public_id,
            'file' => UploadedFile::fake()->create('ablauf.pdf', 20, 'application/pdf'),
        ])
        ->assertRedirect();

    $stored = $field->files()->sole();
    expect($stored->name)->toBe('ablauf.pdf')
        ->and($stored->contact_id)->toBe($contact->id)
        ->and(Storage::disk('local')->exists($stored->path))->toBeTrue();
});

it('rejects a file whose format the field never allowed', function () {
    Storage::fake('local');
    [$instance, $contact, $field] = questionnaireWithFileField();

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$instance->case->public_id}/fragebogen/{$instance->public_id}/datei", [
            'field' => $field->public_id,
            'file' => UploadedFile::fake()->create('urlaub.png', 20, 'image/png'),
        ])
        ->assertSessionHasErrors('file');

    expect($field->files()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
});

it('404s on a file field belonging to somebody else\'s questionnaire', function () {
    Storage::fake('local');
    [$instance, $contact] = questionnaireWithFileField();
    [, , $foreignField] = questionnaireWithFileField();

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$instance->case->public_id}/fragebogen/{$instance->public_id}/datei", [
            'field' => $foreignField->public_id,
            'file' => UploadedFile::fake()->create('ablauf.pdf', 20, 'application/pdf'),
        ])
        ->assertNotFound();

    expect($foreignField->files()->count())->toBe(0);
});

it('lets the client remove their own file, bytes included', function () {
    Storage::fake('local');
    [$instance, $contact, $field] = questionnaireWithFileField();
    $file = $field->attachFile(UploadedFile::fake()->create('ablauf.pdf', 20, 'application/pdf'), $contact);

    $this->actingAs($contact, 'portal')
        ->delete("/projekte/{$instance->case->public_id}/fragebogen/{$instance->public_id}/datei/{$file->public_id}")
        ->assertRedirect();

    expect($field->files()->count())->toBe(0)
        ->and(Storage::disk('local')->exists($file->path))->toBeFalse();
});

it('404s on deleting a file that hangs off another questionnaire', function () {
    Storage::fake('local');
    [$instance, $contact] = questionnaireWithFileField();
    [, , $foreignField] = questionnaireWithFileField();
    $foreign = $foreignField->attachFile(UploadedFile::fake()->create('fremd.pdf', 20, 'application/pdf'));

    $this->actingAs($contact, 'portal')
        ->delete("/projekte/{$instance->case->public_id}/fragebogen/{$instance->public_id}/datei/{$foreign->public_id}")
        ->assertNotFound();

    expect($foreignField->files()->count())->toBe(1);
});

it('holds the submit until a required file field actually has a file', function () {
    Storage::fake('local');
    [$instance, $contact, $field] = questionnaireWithFileField(required: true);

    $answers = ['answers' => $instance->fields
        ->reject(fn ($f) => $f->type === FormFieldType::File)
        ->mapWithKeys(fn ($f) => [$f->public_id => 'egal'])
        ->all()];

    $this->actingAs($contact, 'portal')
        ->patch("/projekte/{$instance->case->public_id}/fragebogen/{$instance->public_id}", $answers)
        ->assertSessionHasErrors("answers.{$field->public_id}");

    $field->attachFile(UploadedFile::fake()->create('ablauf.pdf', 20, 'application/pdf'), $contact);

    $this->actingAs($contact, 'portal')
        ->patch("/projekte/{$instance->case->public_id}/fragebogen/{$instance->public_id}", $answers)
        ->assertSessionHasNoErrors();

    // The file never became a FormAnswer version — that ledger stays text.
    expect($field->answers()->count())->toBe(0);
});

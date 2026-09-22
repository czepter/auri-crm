<?php

use App\Crm\Actions\SeedFormInstanceFromTemplate;
use App\Crm\Enums\FormFieldType;
use App\Crm\Models\FormField;
use App\Crm\Models\FormInstance;
use App\Crm\Models\FormInstanceField;
use App\Crm\Models\FormTemplate;
use App\Crm\Models\Project;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// --- SeedFormInstanceFromTemplate: the deep, disconnected copy ---------

it('seeds an instance\'s fields from a template\'s current fields, matching them exactly', function () {
    $template = FormTemplate::factory()->create(['name' => 'Ablaufplan']);
    FormField::factory()->for($template, 'formTemplate')->required()->create([
        'label' => 'Wann beginnt das Getting Ready?',
        'sort_order' => 0,
    ]);
    FormField::factory()->for($template, 'formTemplate')->select(['Ja', 'Nein'])->create([
        'label' => 'Darf während der Zeremonie fotografiert werden?',
        'sort_order' => 1,
    ]);
    $instance = FormInstance::factory()->create();

    app(SeedFormInstanceFromTemplate::class)($instance, $template);

    expect($instance->fields)->toHaveCount(2)
        ->and($instance->fields->first()->label)->toBe('Wann beginnt das Getting Ready?')
        ->and($instance->fields->first()->required)->toBeTrue()
        ->and($instance->fields->last()->type)->toBe(FormFieldType::Select)
        ->and($instance->fields->last()->options)->toBe(['Ja', 'Nein']);
});

it('leaves an instance already seeded from a template untouched when the template is edited afterwards', function () {
    // The acceptance criterion, proven directly: FormTemplate::copyFields()
    // hands back a disconnected copy with no id and no form_template_id, so
    // nothing written into the instance can be reached by a later write to
    // the template — the whole reason FormInstance exists rather than a
    // project holding a live reference to the template it started from.
    $template = FormTemplate::factory()->create(['name' => 'Familienkonstellation']);
    $originalField = FormField::factory()->for($template, 'formTemplate')->create([
        'label' => 'Wer gehört zur engsten Familie?',
        'required' => false,
    ]);
    $instance = FormInstance::factory()->create();

    app(SeedFormInstanceFromTemplate::class)($instance, $template);

    $seededLabel = $instance->fields->sole()->label;
    $seededRequired = $instance->fields->sole()->required;

    // Edit the template: rename it, relabel and re-require its field, then
    // add a second question and delete the template outright.
    $template->update(['name' => 'Umbenannt']);
    $originalField->update(['label' => 'Retitled', 'required' => true]);
    FormField::factory()->for($template, 'formTemplate')->create(['label' => 'Neue Frage']);

    $instance->refresh();

    expect($instance->fields)->toHaveCount(1)
        ->and($instance->fields->sole()->label)->toBe($seededLabel)
        ->and($instance->fields->sole()->required)->toBe($seededRequired)
        ->and($instance->fields->sole()->label)->not->toBe('Retitled');

    $template->delete();
    $instance->refresh();

    expect($instance->fields)->toHaveCount(1)
        ->and($instance->fields->sole()->label)->toBe($seededLabel);
});

it('seeds nothing from a template with no fields', function () {
    $template = FormTemplate::factory()->create();
    $instance = FormInstance::factory()->create();

    app(SeedFormInstanceFromTemplate::class)($instance, $template);

    expect($instance->fields)->toHaveCount(0);
});

// --- FormInstanceController: creating and personalising -----------------

it('creates a blank form instance for a project', function () {
    $user = staffMember();
    $project = Project::factory()->create();

    $this->actingAs($user, 'staff')
        ->post("/auftraege/{$project->public_id}/frageboegen", [])
        ->assertRedirect();

    $instance = $project->formInstances()->sole();

    expect($instance->name)->toBe('Neuer Fragebogen')
        ->and($instance->source_template_name)->toBeNull()
        ->and($instance->fields)->toHaveCount(0);

    $row = AuditLog::where('event', 'form_instance.created')->sole();
    expect($row->actor_id)->toBe($user->id)
        ->and($row->subject)->toBe($instance->public_id);
});

it('creates a form instance seeded from a template via the store action', function () {
    $project = Project::factory()->create();
    $template = FormTemplate::factory()->create(['name' => 'Ablaufplan']);
    FormField::factory()->for($template, 'formTemplate')->create(['label' => 'Frage 1']);
    FormField::factory()->for($template, 'formTemplate')->create(['label' => 'Frage 2']);

    $this->actingAs(staffMember(), 'staff')
        ->post("/auftraege/{$project->public_id}/frageboegen", ['template' => $template->public_id])
        ->assertRedirect();

    $instance = $project->formInstances()->sole();

    expect($instance->name)->toBe('Ablaufplan')
        ->and($instance->source_template_name)->toBe('Ablaufplan')
        ->and($instance->fields)->toHaveCount(2);
});

it('rejects an inactive template as a seed source', function () {
    $project = Project::factory()->create();
    $template = FormTemplate::factory()->inactive()->create();

    $this->actingAs(staffMember(), 'staff')
        ->post("/auftraege/{$project->public_id}/frageboegen", ['template' => $template->public_id])
        ->assertInvalid(['template']);

    expect($project->formInstances()->count())->toBe(0);
});

it('personalises an instance\'s fields and name, wholesale replacing what was there, without touching its source template', function () {
    $user = staffMember();
    $project = Project::factory()->create();
    $template = FormTemplate::factory()->create(['name' => 'Ablaufplan']);
    $templateField = FormField::factory()->for($template, 'formTemplate')->create(['label' => 'Unveränderte Vorlagenfrage']);

    $instance = FormInstance::factory()->for($project)->create(['name' => 'Ablaufplan', 'source_template_name' => 'Ablaufplan']);
    app(SeedFormInstanceFromTemplate::class)($instance, $template);
    $seededFieldId = $instance->fields->sole()->id;

    $this->actingAs($user, 'staff')->patch("/frageboegen/{$instance->public_id}", [
        'name' => 'After-Wedding · Ablauf & Wünsche',
        'fields' => [
            ['label' => 'Wohin soll es gehen?', 'type' => 'select', 'required' => true, 'options' => ['Strand', 'Stadt']],
            ['label' => 'Freitextfrage nur für dieses Projekt', 'type' => 'text', 'required' => false, 'options' => null],
        ],
    ])->assertRedirect();

    $instance->refresh();

    expect($instance->name)->toBe('After-Wedding · Ablauf & Wünsche')
        ->and($instance->fields)->toHaveCount(2)
        ->and($instance->fields->pluck('sort_order')->all())->toBe([0, 1])
        ->and($instance->fields->first()->options)->toBe(['Strand', 'Stadt'])
        ->and(FormInstanceField::find($seededFieldId))->toBeNull();

    // The source template — name and its own field — is untouched.
    expect($template->fresh()->name)->toBe('Ablaufplan')
        ->and($templateField->fresh()->label)->toBe('Unveränderte Vorlagenfrage');

    $row = AuditLog::where('event', 'form_instance.updated')->sole();
    expect($row->subject)->toBe($instance->public_id);
});

it('opens the project forms screen to whoever can view the project, regardless of ManageFormTemplates', function (StaffRole $role, int $status) {
    // The screen itself only gates on Project::view — the same rule every
    // other project tab uses (ProjectController::quotes(), ::shoots()) — so
    // Contributor, who holds no ViewAllProjects capability and is not
    // crewed on this uncrewed project, gets 403 here just as it would on
    // /shootings or /angebote. canManageForms is what the screen itself
    // uses to decide whether to render the editing controls.
    $project = Project::factory()->create();

    $this->actingAs(staffMember($role), 'staff')
        ->get("/auftraege/{$project->public_id}/frageboegen")
        ->assertStatus($status);
})->with([
    [StaffRole::Admin, 200],
    [StaffRole::Manager, 200],
    [StaffRole::Contributor, 403],
    [StaffRole::Reader, 200],
]);

it('lists a project\'s form instances with their fields on the project screen', function () {
    $project = Project::factory()->create();
    $template = FormTemplate::factory()->create(['name' => 'Wunschliste Gruppenfotos']);
    $instance = FormInstance::factory()->for($project)->create(['name' => 'Wunschliste Gruppenfotos', 'source_template_name' => 'Wunschliste Gruppenfotos']);
    app(SeedFormInstanceFromTemplate::class)($instance, $template);
    FormField::factory()->for($template, 'formTemplate')->create();

    $props = $this->actingAs(staffMember(), 'staff')
        ->get("/auftraege/{$project->public_id}/frageboegen")
        ->assertOk()
        ->viewData('page')['props'];

    expect(collect($props['formInstances'])->pluck('name'))->toContain('Wunschliste Gruppenfotos')
        ->and(collect($props['formTemplates'])->pluck('label'))->toContain('Wunschliste Gruppenfotos');
});

it('carries no integer identifier in the props', function () {
    $project = Project::factory()->create();
    $instance = FormInstance::factory()->for($project)->create();
    $field = FormInstanceField::factory()->for($instance, 'formInstance')->create();

    $props = $this->actingAs(staffMember(), 'staff')
        ->get("/auftraege/{$project->public_id}/frageboegen")
        ->assertOk()
        ->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);

    // Not a dead assertion — proves the fixture actually reached the props.
    expect(collect($props['formInstances'])->pluck('id'))->toContain($instance->public_id);
    expect(collect($props['formInstances'])->flatMap(fn ($i) => collect($i['fields'])->pluck('id')))->toContain($field->public_id);
});

// --- FormInstanceController::answer — E-03's FormAnswer, over HTTP --------

it('records an answer via the controller and audit-logs it', function () {
    $user = staffMember();
    $project = Project::factory()->create();
    $instance = FormInstance::factory()->for($project)->create();
    $field = FormInstanceField::factory()->for($instance, 'formInstance')->create();

    $this->actingAs($user, 'staff')
        ->patch("/frageboegen-felder/{$field->public_id}/antwort", ['value' => 'Strand'])
        ->assertRedirect();

    expect($field->currentAnswer->value)->toBe('Strand');

    $row = AuditLog::where('event', 'form_instance_field.answered')->sole();
    expect($row->actor_id)->toBe($user->id)
        ->and($row->subject)->toBe($field->public_id);
});

it('editing an already-answered field over HTTP keeps the previous answer retrievable, not overwritten', function () {
    $project = Project::factory()->create();
    $instance = FormInstance::factory()->for($project)->create();
    $field = FormInstanceField::factory()->for($instance, 'formInstance')->create();
    $this->actingAs(staffMember(), 'staff')
        ->patch("/frageboegen-felder/{$field->public_id}/antwort", ['value' => 'Strand']);
    $v1 = $field->currentAnswer;

    $this->actingAs(staffMember(), 'staff')
        ->patch("/frageboegen-felder/{$field->public_id}/antwort", ['value' => 'Stadt'])
        ->assertRedirect();

    expect($field->currentAnswer()->first()->value)->toBe('Stadt')
        ->and($field->answers()->count())->toBe(2)
        ->and($v1->fresh()->value)->toBe('Strand');
});

it('exposes the current answer to the props once the field has been answered', function () {
    $project = Project::factory()->create();
    $instance = FormInstance::factory()->for($project)->create();
    $field = FormInstanceField::factory()->for($instance, 'formInstance')->create();
    $field->answer('Strand');

    $props = $this->actingAs(staffMember(), 'staff')
        ->get("/auftraege/{$project->public_id}/frageboegen")
        ->assertOk()
        ->viewData('page')['props'];

    $fieldProp = collect($props['formInstances'])->sole()['fields'][0];
    expect($fieldProp['currentAnswer'])->toBe('Strand')
        ->and($fieldProp['answeredAt'])->not->toBeNull();
});

it('rejects answering a field to a staff member without ManageFormTemplates', function () {
    $project = Project::factory()->create();
    $instance = FormInstance::factory()->for($project)->create();
    $field = FormInstanceField::factory()->for($instance, 'formInstance')->create();

    $this->actingAs(staffMember(StaffRole::Contributor), 'staff')
        ->patch("/frageboegen-felder/{$field->public_id}/antwort", ['value' => 'Strand'])
        ->assertForbidden();

    expect($field->answers()->count())->toBe(0);
});

// --- E-03: "Datei" fields --------------------------------------------

function fileField(array $options = ['formats' => ['pdf'], 'maxSize' => 5, 'multiple' => false]): FormInstanceField
{
    $instance = FormInstance::factory()->for(Project::factory()->create())->create();

    return FormInstanceField::factory()->for($instance, 'formInstance')->create([
        'label' => 'Ablaufplan eurer Planerin',
        'type' => FormFieldType::File,
        'options' => $options,
    ]);
}

it('stores an allowed file against a file field', function () {
    Storage::fake('local');
    $field = fileField();

    $this->actingAs(staffMember(), 'staff')
        ->post("/frageboegen-felder/{$field->public_id}/datei", [
            'file' => UploadedFile::fake()->create('ablauf.pdf', 40, 'application/pdf'),
        ])
        ->assertRedirect();

    $stored = $field->files()->sole();
    expect($stored->name)->toBe('ablauf.pdf')
        ->and(Storage::disk('local')->exists($stored->path))->toBeTrue();
});

it('rejects a file whose format the field never allowed', function () {
    Storage::fake('local');
    $field = fileField();

    $this->actingAs(staffMember(), 'staff')
        ->post("/frageboegen-felder/{$field->public_id}/datei", [
            'file' => UploadedFile::fake()->create('makro.docx', 40, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])
        ->assertSessionHasErrors('file');

    expect($field->files()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
});

it('rejects a file over the size the field allows', function () {
    Storage::fake('local');
    $field = fileField(['formats' => ['pdf'], 'maxSize' => 1, 'multiple' => false]);

    $this->actingAs(staffMember(), 'staff')
        ->post("/frageboegen-felder/{$field->public_id}/datei", [
            'file' => UploadedFile::fake()->create('gross.pdf', 2048, 'application/pdf'),
        ])
        ->assertSessionHasErrors('file');

    expect($field->files()->count())->toBe(0);
});

it('replaces the file on a single-file field and keeps both on a multiple one', function () {
    Storage::fake('local');
    $single = fileField();
    $many = fileField(['formats' => ['pdf'], 'maxSize' => 5, 'multiple' => true]);

    foreach ([$single, $many] as $field) {
        foreach (['eins.pdf', 'zwei.pdf'] as $name) {
            $this->actingAs(staffMember(), 'staff')
                ->post("/frageboegen-felder/{$field->public_id}/datei", [
                    'file' => UploadedFile::fake()->create($name, 10, 'application/pdf'),
                ]);
        }
    }

    expect($single->files()->pluck('name')->all())->toBe(['zwei.pdf'])
        ->and($many->files()->pluck('name')->all())->toBe(['eins.pdf', 'zwei.pdf'])
        // The replaced upload took its bytes with it: one file per surviving row.
        ->and(Storage::disk('local')->allFiles())->toHaveCount(3);
});

it('deletes a file and its bytes', function () {
    Storage::fake('local');
    $field = fileField();
    $file = $field->attachFile(UploadedFile::fake()->create('ablauf.pdf', 10, 'application/pdf'));

    $this->actingAs(staffMember(), 'staff')
        ->delete("/fragebogen-dateien/{$file->public_id}")
        ->assertRedirect();

    expect($field->files()->count())->toBe(0)
        ->and(Storage::disk('local')->exists($file->path))->toBeFalse();
});

it('rejects uploading to a file field for a staff member without ManageFormTemplates', function () {
    Storage::fake('local');
    $field = fileField();

    $this->actingAs(staffMember(StaffRole::Contributor), 'staff')
        ->post("/frageboegen-felder/{$field->public_id}/datei", [
            'file' => UploadedFile::fake()->create('ablauf.pdf', 10, 'application/pdf'),
        ])
        ->assertForbidden();

    expect($field->files()->count())->toBe(0);
});

it('keeps a file field\'s upload config in the same options column select uses', function () {
    $project = Project::factory()->create();
    $instance = FormInstance::factory()->for($project)->create();

    $this->actingAs(staffMember(), 'staff')
        ->patch("/frageboegen/{$instance->public_id}", [
            'name' => 'Ablauf',
            'fields' => [
                ['label' => 'Ablaufplan', 'type' => 'file', 'required' => true, 'options' => ['formats' => ['pdf', 'jpg'], 'maxSize' => 10, 'multiple' => true]],
                ['label' => 'Trauzeugen', 'type' => 'select', 'required' => false, 'options' => ['Beide', 'Keine']],
            ],
        ])
        ->assertRedirect();

    expect($instance->fields()->get()->pluck('options')->all())
        ->toBe([['maxSize' => 10, 'multiple' => true, 'formats' => ['pdf', 'jpg']], ['Beide', 'Keine']]);
});

it('rejects a file field configured with a format the app does not accept at all', function () {
    $instance = FormInstance::factory()->for(Project::factory()->create())->create();

    $this->actingAs(staffMember(), 'staff')
        ->patch("/frageboegen/{$instance->public_id}", [
            'name' => 'Ablauf',
            'fields' => [
                ['label' => 'Ablaufplan', 'type' => 'file', 'required' => true, 'options' => ['formats' => ['exe'], 'maxSize' => 10, 'multiple' => false]],
            ],
        ])
        ->assertSessionHasErrors('fields.0.options.formats.0');
});

it('hands the studio back a file it stored', function () {
    $project = Project::factory()->create();
    $instance = FormInstance::factory()->for($project)->create();
    $field = FormInstanceField::factory()->for($instance)->create(['type' => FormFieldType::File]);

    Storage::fake('local');
    $file = $field->attachFile(UploadedFile::fake()->create('ablauf.pdf', 12, 'application/pdf'), null);

    $this->actingAs(User::factory()->create(), 'staff')
        ->get("/fragebogen-dateien/{$file->public_id}")
        ->assertOk()
        ->assertDownload('ablauf.pdf');
});

// Der Schirm zeigt den Fehler jetzt an; vorher sah das Speichern aus, als
// täte es nichts, wenn eine frisch angelegte Frage noch keinen Titel trug.
it('refuses a question without a title and names the field', function () {
    $project = Project::factory()->create();
    $instance = FormInstance::factory()->for($project)->create();

    $this->actingAs(User::factory()->create(), 'staff')
        ->from("/auftraege/{$project->public_id}/frageboegen")
        ->patch("/frageboegen/{$instance->public_id}", [
            'name' => 'Ablauf',
            'fields' => [['label' => '', 'type' => 'text', 'required' => false, 'options' => null]],
        ])
        ->assertSessionHasErrors('fields.0.label');
});

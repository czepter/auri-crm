<?php

use App\Crm\Enums\ProjectFieldType;
use App\Crm\Models\Project;
use App\Crm\Models\ProjectField;
use App\Crm\Models\ProjectType;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff');
    $this->type = ProjectType::query()->where('key', 'wedding')->sole();
});

function field(array $attributes = []): ProjectField
{
    return ProjectField::factory()->create([
        'project_type_id' => ProjectType::query()->where('key', 'wedding')->value('id'),
        ...$attributes,
    ]);
}

it('adds a field and derives its key from the label', function () {
    $this->post("/projekttypen/{$this->type->public_id}/felder", [
        'label' => 'Location-Art',
        'type' => ProjectFieldType::Select->value,
        'options' => ['Scheune', 'Schloss'],
        'allow_new_options' => true,
    ])->assertRedirect();

    $field = $this->type->fields()->sole();

    expect($field->key)->toBe('location_art')
        ->and($field->options)->toBe(['Scheune', 'Schloss'])
        ->and($field->allow_new_options)->toBeTrue();
});

it('drops a unit and options the chosen type has no use for', function () {
    $this->post("/projekttypen/{$this->type->public_id}/felder", [
        'label' => 'Trauung',
        'type' => ProjectFieldType::Date->value,
        'options' => ['Frei'],
        'unit' => 'Personen',
        'allow_new_options' => true,
    ])->assertRedirect();

    $field = $this->type->fields()->sole();

    expect($field->options)->toBeNull()
        ->and($field->unit)->toBeNull()
        ->and($field->allow_new_options)->toBeFalse();
});

it('renames a field without moving the values standing on it', function () {
    $field = field();
    $project = Project::factory()->create(['type' => 'wedding']);
    $project->forceFill(['field_values' => ['gaeste' => 120]])->save();

    $this->patch("/projekttypen/{$this->type->public_id}/felder/{$field->public_id}", [
        'label' => 'Gästezahl',
        'type' => ProjectFieldType::Number->value,
        'unit' => 'Personen',
        'is_active' => true,
    ])->assertRedirect();

    expect($field->fresh()->key)->toBe('gaeste')
        ->and($field->fresh()->label)->toBe('Gästezahl')
        ->and($project->fresh()->field_values)->toBe(['gaeste' => 120]);
});

it('refuses to retype a field that already carries values', function () {
    $field = field();
    Project::factory()->create(['type' => 'wedding'])
        ->forceFill(['field_values' => ['gaeste' => 120]])->save();

    $this->patch("/projekttypen/{$this->type->public_id}/felder/{$field->public_id}", [
        'label' => 'Gäste',
        'type' => ProjectFieldType::Text->value,
        'is_active' => true,
    ])->assertSessionHasErrors('type');

    expect($field->fresh()->type)->toBe(ProjectFieldType::Number);
});

it('reorders the fields of a type', function () {
    $first = field(['key' => 'a', 'label' => 'A', 'sort_order' => 0]);
    $second = field(['key' => 'b', 'label' => 'B', 'sort_order' => 1]);

    $this->patch("/projekttypen/{$this->type->public_id}/felder/reihenfolge", [
        'order' => [$second->public_id, $first->public_id],
    ])->assertRedirect();

    expect($this->type->fields()->pluck('key')->all())->toBe(['b', 'a']);
});

it('deletes a field nobody answered', function () {
    $field = field();

    $this->delete("/projekttypen/{$this->type->public_id}/felder/{$field->public_id}")->assertRedirect();

    expect(ProjectField::query()->find($field->id))->toBeNull();
});

it('refuses to delete a field with values and points at deactivating', function () {
    $field = field();
    Project::factory()->create(['type' => 'wedding'])
        ->forceFill(['field_values' => ['gaeste' => 120]])->save();

    $this->delete("/projekttypen/{$this->type->public_id}/felder/{$field->public_id}")
        ->assertSessionHasErrors('field');

    expect(ProjectField::query()->find($field->id))->not->toBeNull();

    $this->patch("/projekttypen/{$this->type->public_id}/felder/{$field->public_id}", [
        'label' => 'Gäste',
        'type' => ProjectFieldType::Number->value,
        'is_active' => false,
    ])->assertRedirect();

    expect($field->fresh()->is_active)->toBeFalse();
});

it('hands the panel the fields of every type', function () {
    field();

    $this->get('/projekte')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('stageSettings.types.0.fields', 1)
            ->where('stageSettings.types.0.fields.0.label', 'Gäste')
            ->has('stageSettings.fieldTypes', count(ProjectFieldType::cases())));
});

it('withholds the fields from a role that may not configure them', function () {
    field();

    $this->actingAs(staffMember(StaffRole::SecondShooter), 'staff')
        ->post("/projekttypen/{$this->type->public_id}/felder", [
            'label' => 'Gäste',
            'type' => ProjectFieldType::Number->value,
        ])
        ->assertForbidden();
});

it('shows the definitions and the values on the project screen', function () {
    field();
    $project = Project::factory()->create(['type' => 'wedding']);
    $project->forceFill(['field_values' => ['gaeste' => 120]])->save();

    $this->get("/auftraege/{$project->public_id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('fields', 1)
            ->where('fields.0.unit', 'Personen')
            ->where('fieldValues.gaeste', 120));
});

it('saves values with the rest of the project and writes one history line', function () {
    field();
    field(['key' => 'stil', 'label' => 'Stil', 'type' => ProjectFieldType::Tags, 'unit' => null, 'options' => ['Boho']]);
    $project = Project::factory()->create(['type' => 'wedding', 'name' => 'Lena & Jonas']);

    $this->patch("/auftraege/{$project->public_id}", [
        'name' => 'Lena & Jonas',
        'fields' => ['gaeste' => '120', 'stil' => ['Boho']],
    ])->assertRedirect();

    // jsonb hands its keys back in its own order, so the comparison names them.
    expect($project->fresh()->field_values)->toEqualCanonicalizing(['gaeste' => 120, 'stil' => ['Boho']])
        ->and(AuditLog::query()->where('event', 'project.fields_changed')->sole()->context['fields'])
        ->toBe(['gaeste', 'stil']);
});

it('refuses a value outside a closed list', function () {
    field(['key' => 'trauung', 'label' => 'Trauung', 'type' => ProjectFieldType::Select, 'unit' => null, 'options' => ['Frei', 'Kirchlich']]);
    $project = Project::factory()->create(['type' => 'wedding']);

    $this->patch("/auftraege/{$project->public_id}", [
        'name' => $project->name,
        'fields' => ['trauung' => 'Standesamt'],
    ])->assertSessionHasErrors('fields.trauung');

    expect($project->fresh()->field_values)->toBeNull();
});

it('adds the option to the definition when the field allows it', function () {
    $stil = field([
        'key' => 'stil', 'label' => 'Stil', 'type' => ProjectFieldType::Tags,
        'unit' => null, 'options' => ['Boho'], 'allow_new_options' => true,
    ]);
    $project = Project::factory()->create(['type' => 'wedding']);

    $this->patch("/auftraege/{$project->public_id}", [
        'name' => $project->name,
        'fields' => ['stil' => ['Boho', 'Vintage']],
    ])->assertRedirect();

    expect($stil->fresh()->options)->toBe(['Boho', 'Vintage']);
});

it('grows no option when a later field in the same save is refused', function () {
    $stil = field([
        'key' => 'stil', 'label' => 'Stil', 'type' => ProjectFieldType::Tags,
        'unit' => null, 'options' => ['Boho'], 'allow_new_options' => true,
    ]);
    field(['key' => 'trauung', 'label' => 'Trauung', 'type' => ProjectFieldType::Select, 'unit' => null, 'options' => ['Frei']]);
    $project = Project::factory()->create(['type' => 'wedding']);

    $this->patch("/auftraege/{$project->public_id}", [
        'name' => $project->name,
        'fields' => ['stil' => ['Boho', 'Vintage'], 'trauung' => 'Standesamt'],
    ])->assertSessionHasErrors('fields.trauung');

    expect($stil->fresh()->options)->toBe(['Boho'])
        ->and($project->fresh()->field_values)->toBeNull();
});

it('refuses to save a project whose required field is blank', function () {
    field(['required' => true]);
    $project = Project::factory()->create(['type' => 'wedding']);

    $this->patch("/auftraege/{$project->public_id}", [
        'name' => $project->name,
        'fields' => ['gaeste' => ''],
    ])->assertSessionHasErrors('fields.gaeste');
});

it('keeps the values of the type a project left', function () {
    field();
    $project = Project::factory()->create(['type' => 'wedding']);
    $project->forceFill(['field_values' => ['gaeste' => 120]])->save();

    $project->update(['type' => 'portrait']);

    expect($project->fresh()->field_values)->toBe(['gaeste' => 120]);

    $this->get("/auftraege/{$project->public_id}")
        ->assertInertia(fn ($page) => $page->has('fields', 0)->where('fieldValues.gaeste', 120));
});

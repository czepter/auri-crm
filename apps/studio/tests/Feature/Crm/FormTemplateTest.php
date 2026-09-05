<?php

use App\Crm\Enums\FormFieldType;
use App\Crm\Enums\ProjectType;
use App\Crm\Models\FormField;
use App\Crm\Models\FormTemplate;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists templates with their field count', function () {
    $template = FormTemplate::factory()->create(['name' => 'Familienkonstellation']);
    FormField::factory()->for($template, 'formTemplate')->count(2)->create();
    FormTemplate::factory()->inactive()->create(['name' => 'Alt']);

    $props = $this->actingAs(staffMember(), 'staff')
        ->get('/vorlagen?typ=fragebogen')
        ->assertOk()
        ->viewData('page')['props'];

    expect(collect($props['formTemplates'])->pluck('name'))->toContain('Familienkonstellation', 'Alt')
        ->and(collect($props['formTemplates'])->firstWhere('id', $template->public_id)['fieldCount'])->toBe(2);
});

it('creates a template shell and lands on its builder', function () {
    $user = staffMember();

    $this->actingAs($user, 'staff')->post('/einstellungen/fragebogen-vorlagen', [
        'name' => 'Familienkonstellation',
        'project_type' => 'wedding',
    ])->assertRedirect();

    $template = FormTemplate::sole();

    expect($template->name)->toBe('Familienkonstellation')
        ->and($template->project_type->key)->toBe(ProjectType::Wedding->value)
        ->and($template->is_active)->toBeTrue()
        ->and($template->fields)->toHaveCount(0);

    $row = AuditLog::where('event', 'form_template.created')->sole();
    expect($row->actor_id)->toBe($user->id)
        ->and($row->subject)->toBe($template->public_id);
});

it('requires a name to create a template', function () {
    $this->actingAs(staffMember(), 'staff')
        ->post('/einstellungen/fragebogen-vorlagen', ['name' => '', 'project_type' => null])
        ->assertInvalid(['name']);

    expect(FormTemplate::count())->toBe(0);
});

it('shows a template with its fields on the builder screen', function () {
    $template = FormTemplate::factory()->create(['name' => 'Familienkonstellation']);
    FormField::factory()->for($template, 'formTemplate')->required()->create(['label' => 'Namen der engsten Familie', 'sort_order' => 0]);
    FormField::factory()->for($template, 'formTemplate')->select(['Ja', 'Nein', 'Teilweise'])->create(['label' => 'Dürfen Fotos veröffentlicht werden?', 'sort_order' => 1]);

    $props = $this->actingAs(staffMember(), 'staff')
        ->get("/einstellungen/fragebogen-vorlagen/{$template->public_id}")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['template']['id'])->toBe($template->public_id)
        ->and($props['template']['fields'])->toHaveCount(2)
        ->and($props['template']['fields'][0]['label'])->toBe('Namen der engsten Familie')
        ->and($props['template']['fields'][0]['required'])->toBeTrue()
        ->and($props['template']['fields'][1]['type'])->toBe('select')
        ->and($props['template']['fields'][1]['options'])->toBe(['Ja', 'Nein', 'Teilweise']);
});

it('builds several field types on a template and saves them, wholesale replacing what was there', function () {
    $user = staffMember();
    $template = FormTemplate::factory()->create(['name' => 'Alt']);
    $original = FormField::factory()->for($template, 'formTemplate')->create(['label' => 'Alte Frage']);

    $this->actingAs($user, 'staff')->patch("/einstellungen/fragebogen-vorlagen/{$template->public_id}", [
        'name' => 'Familienkonstellation',
        'project_type' => 'wedding',
        'is_active' => true,
        'fields' => [
            ['label' => 'Wer gehört zur engsten Familie?', 'type' => 'name_list', 'required' => true, 'options' => null],
            ['label' => 'Sonstige Hinweise', 'type' => 'text', 'required' => false, 'options' => null],
            ['label' => 'Anlass', 'type' => 'select', 'required' => true, 'options' => ['Verlobung', 'Hochzeit']],
        ],
    ])->assertRedirect();

    $template->refresh();

    expect($template->name)->toBe('Familienkonstellation')
        ->and($template->fields)->toHaveCount(3)
        ->and($template->fields->pluck('sort_order')->all())->toBe([0, 1, 2])
        ->and($template->fields->first()->type)->toBe(FormFieldType::NameList)
        ->and($template->fields->last()->options)->toBe(['Verlobung', 'Hochzeit'])
        ->and(FormField::find($original->id))->toBeNull();

    expect(AuditLog::where('event', 'form_template.updated')->where('subject', $template->public_id)->exists())->toBeTrue();
});

it('deactivates a template through the ordinary update, not a separate toggle', function () {
    $user = staffMember();
    $template = FormTemplate::factory()->create();

    $this->actingAs($user, 'staff')->patch("/einstellungen/fragebogen-vorlagen/{$template->public_id}", [
        'name' => $template->name,
        'project_type' => $template->project_type->value,
        'is_active' => false,
        'fields' => [],
    ])->assertRedirect();

    expect($template->fresh()->is_active)->toBeFalse();
});

it('deletes a template and logs it — nothing references it yet, so nothing to orphan', function () {
    $user = staffMember();
    $template = FormTemplate::factory()->create();
    FormField::factory()->for($template, 'formTemplate')->create();

    $this->actingAs($user, 'staff')
        ->delete("/einstellungen/fragebogen-vorlagen/{$template->public_id}")
        ->assertRedirect('/einstellungen/fragebogen-vorlagen');

    expect(FormTemplate::count())->toBe(0)
        ->and(FormField::count())->toBe(0)
        ->and(AuditLog::where('event', 'form_template.deleted')->where('subject', $template->public_id)->exists())->toBeTrue();
});

it('opens the builder to every role ManageFormTemplates reaches — Owner, Photographer, Editor', function (StaffRole $role, int $status) {
    $this->actingAs(staffMember($role), 'staff')
        ->get('/vorlagen?typ=fragebogen')
        ->assertStatus($status);
})->with([
    [StaffRole::Owner, 200],
    [StaffRole::Photographer, 200],
    [StaffRole::Editor, 200],
    // Der Hub zeigt nur die Arten, die die Rolle führen darf — eine, die
    // Fragebögen nicht führt, findet die Ansicht gar nicht erst.
    [StaffRole::SecondShooter, 404],
    [StaffRole::Assistant, 404],
]);

it('declares an audience on every form-template route, same as every other admin route', function () {
    $routes = routesForSurface('admin');

    $formTemplateRoutes = collect($routes)->filter(
        fn ($route) => str_starts_with($route->uri(), 'einstellungen/fragebogen-vorlagen')
    );

    expect($formTemplateRoutes)->toHaveCount(6)
        ->and($formTemplateRoutes->every(
            fn ($route) => collect($route->gatherMiddleware())->contains(fn ($m) => str_starts_with((string) $m, 'audience:'))
        ))->toBeTrue();
});

it('carries no integer identifier in the props', function () {
    $template = FormTemplate::factory()->create();
    FormField::factory()->for($template, 'formTemplate')->create();

    $props = $this->actingAs(staffMember(), 'staff')
        ->get("/einstellungen/fragebogen-vorlagen/{$template->public_id}")
        ->assertOk()
        ->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
});

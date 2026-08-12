<?php

use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a first template version', function () {
    $staff = staffMember(StaffRole::Owner);

    $this->actingAs($staff, 'staff')
        ->post('/dokumentvorlagen', ['name' => 'Hochzeit', 'body' => '<p>{{ project.name }}</p>', 'type' => 'contract'])
        ->assertRedirect('/dokumentvorlagen');

    $template = DocumentTemplate::sole();
    expect($template->version)->toBe(1)
        ->and($template->is_current)->toBeTrue()
        ->and($template->type)->toBe(DocumentTemplateType::Contract)
        ->and(AuditLog::where('event', 'document_template.created')->sole()->actor_id)->toBe($staff->id);
});

it('creates a quote or mail template exactly the same way as a contract one', function (string $type) {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post('/dokumentvorlagen', ['name' => 'Standard', 'body' => '<p>{{ project.name }}</p>', 'type' => $type])
        ->assertRedirect('/dokumentvorlagen');

    expect(DocumentTemplate::sole()->type)->toBe(DocumentTemplateType::from($type));
})->with(['quote', 'mail']);

it('rejects an unknown document type', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post('/dokumentvorlagen', ['name' => 'X', 'body' => '<p>x</p>', 'type' => 'invoice'])
        ->assertInvalid(['type']);
});

it('accepts an optional shoot kind variant', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post('/dokumentvorlagen', ['name' => 'Hochzeit', 'body' => '<p>x</p>', 'type' => 'contract', 'shoot_kind' => 'wedding'])
        ->assertRedirect('/dokumentvorlagen');

    expect(DocumentTemplate::sole()->shoot_kind)->toBe('wedding');
});

it('creates a new version rather than mutating on update', function () {
    $staff = staffMember(StaffRole::Owner);
    $template = DocumentTemplate::factory()->create(['body' => '<p>v1</p>']);

    $this->actingAs($staff, 'staff')
        ->patch("/dokumentvorlagen/{$template->public_id}", ['name' => $template->name, 'body' => '<p>v2</p>'])
        ->assertRedirect('/dokumentvorlagen');

    expect(DocumentTemplate::query()->where('family_id', $template->family_id)->count())->toBe(2);

    $template->refresh();
    expect($template->body)->toBe('<p>v1</p>')
        ->and($template->is_current)->toBeFalse();

    $current = DocumentTemplate::current()->where('family_id', $template->family_id)->sole();
    expect($current->body)->toBe('<p>v2</p>')
        ->and(AuditLog::where('event', 'document_template.updated')->sole()->context['version'])->toBe(2);
});

it('keeps block content through a new version, not just the block type', function () {
    $template = DocumentTemplate::factory()->create();

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->patch("/dokumentvorlagen/{$template->public_id}", [
            'name' => $template->name,
            'body' => '<p>Neu</p>',
            'blocks' => ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Neu']]]],
        ])
        ->assertRedirect('/dokumentvorlagen');

    $version = DocumentTemplate::query()->where('version', 2)->firstOrFail();

    expect($version->blocks['blocks'][0]['data']['text'])->toBe('Neu');
});

it('404s editing a superseded version', function () {
    $staff = staffMember(StaffRole::Owner);
    $template = DocumentTemplate::factory()->create();
    $template->newVersion(['name' => $template->name, 'body' => '<p>v2</p>']);

    $this->actingAs($staff, 'staff')->get("/dokumentvorlagen/{$template->public_id}")->assertNotFound();
});

it('opens the template list to every role that manages contracts, and refuses the ones that cannot', function (StaffRole $role, int $status) {
    $this->actingAs(staffMember($role), 'staff')->get('/dokumentvorlagen')->assertStatus($status);
})->with([
    [StaffRole::Owner, 200],
    [StaffRole::Photographer, 200],
    [StaffRole::Editor, 200],
    [StaffRole::SecondShooter, 403],
    [StaffRole::Assistant, 403],
]);

it('filters the index to one document type via the type query parameter', function () {
    DocumentTemplate::factory()->create(['name' => 'Vertrag A', 'type' => DocumentTemplateType::Contract]);
    DocumentTemplate::factory()->create(['name' => 'Angebot A', 'type' => DocumentTemplateType::Quote]);

    $props = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/dokumentvorlagen?type=quote')
        ->assertOk()
        ->viewData('page')['props'];

    expect(collect($props['templates'])->pluck('name')->all())->toBe(['Angebot A']);
});

it('stores the editor blocks next to the serialized body', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post('/dokumentvorlagen', [
            'name' => 'Fotovertrag',
            'type' => 'contract',
            'shoot_kind' => null,
            'body' => '<p>Hallo</p>',
            'blocks' => ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Hallo']]]],
        ])
        ->assertRedirect('/dokumentvorlagen');

    $template = DocumentTemplate::query()->firstOrFail();

    // Not just the type: a validation rule covering only `.type` lets Laravel's
    // validated() silently reconstruct the block without its `data`, which is
    // exactly the bug this test caught (see InlineHtmlSanitizer's write-path fix).
    expect($template->body)->toBe('<p>Hallo</p>')
        ->and($template->blocks['blocks'][0]['type'])->toBe('paragraph')
        ->and($template->blocks['blocks'][0]['data']['text'])->toBe('Hallo');
});

it('refuses a block the document type may not use', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post('/dokumentvorlagen', [
            'name' => 'Angebotsmail',
            'type' => 'mail',
            'shoot_kind' => null,
            'body' => '<p>x</p>',
            'blocks' => ['blocks' => [['type' => 'columns', 'data' => ['left' => [], 'right' => []]]]],
        ])
        ->assertInvalid(['blocks']);

    expect(DocumentTemplate::query()->count())->toBe(0);
});

it('accepts a template saved without blocks, the way an untouched legacy row is', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post('/dokumentvorlagen', [
            'name' => 'Alt',
            'type' => 'contract',
            'shoot_kind' => null,
            'body' => '<p>alt</p>',
        ])
        ->assertRedirect('/dokumentvorlagen');

    expect(DocumentTemplate::query()->firstOrFail()->blocks)->toBeNull();
});

it('gives the create screen a palette for its type and the merge-field samples', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/dokumentvorlagen/neu')
        ->assertInertia(fn ($page) => $page
            ->component('DocumentTemplates/Create')
            ->has('palettes.contract')
            ->has('palettes.mail')
            ->has('mergeFields.0.path')
            ->has('mergeFields.0.sample'));
});

it('gives the edit screen the palette for the template it is editing', function () {
    $template = DocumentTemplate::factory()->create(['type' => 'mail']);

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get("/dokumentvorlagen/{$template->public_id}")
        ->assertInertia(fn ($page) => $page
            ->component('DocumentTemplates/Edit')
            ->where('palette', fn ($palette) => collect($palette)->pluck('type')->doesntContain('columns')));
});

it('refuses a body carrying an href with an unsafe scheme', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post('/dokumentvorlagen', [
            'name' => 'Angebotsmail',
            'type' => 'mail',
            'shoot_kind' => null,
            'body' => '<p><a href=\'javascript:alert(1)\'>klick</a></p>',
        ])
        ->assertInvalid(['body']);

    expect(DocumentTemplate::query()->count())->toBe(0);
});

it('sanitises block html on the way into storage, not merely on the way out', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post('/dokumentvorlagen', [
            'name' => 'Fotovertrag',
            'type' => 'contract',
            'shoot_kind' => null,
            'body' => '<p>Hallo</p>',
            'blocks' => ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Hallo <img src=x onerror=alert(1)>']]]],
        ])
        ->assertRedirect('/dokumentvorlagen');

    expect(DocumentTemplate::query()->firstOrFail()->blocks['blocks'][0]['data']['text'])->toBe('Hallo ');
});

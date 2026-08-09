<?php

use App\Contracts\Models\ContractTemplate;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a first template version', function () {
    $staff = staffMember(StaffRole::Owner);

    $this->actingAs($staff, 'staff')
        ->post('/vertragsvorlagen', ['name' => 'Hochzeit', 'body' => '<p>{{ project.name }}</p>'])
        ->assertRedirect('/vertragsvorlagen');

    $template = ContractTemplate::sole();
    expect($template->version)->toBe(1)
        ->and($template->is_current)->toBeTrue()
        ->and(AuditLog::where('event', 'contract_template.created')->sole()->actor_id)->toBe($staff->id);
});

it('creates a new version rather than mutating on update', function () {
    $staff = staffMember(StaffRole::Owner);
    $template = ContractTemplate::factory()->create(['body' => '<p>v1</p>']);

    $this->actingAs($staff, 'staff')
        ->patch("/vertragsvorlagen/{$template->public_id}", ['name' => $template->name, 'body' => '<p>v2</p>'])
        ->assertRedirect('/vertragsvorlagen');

    expect(ContractTemplate::query()->where('family_id', $template->family_id)->count())->toBe(2);

    $template->refresh();
    expect($template->body)->toBe('<p>v1</p>')
        ->and($template->is_current)->toBeFalse();

    $current = ContractTemplate::current()->where('family_id', $template->family_id)->sole();
    expect($current->body)->toBe('<p>v2</p>')
        ->and(AuditLog::where('event', 'contract_template.updated')->sole()->context['version'])->toBe(2);
});

it('404s editing a superseded version', function () {
    $staff = staffMember(StaffRole::Owner);
    $template = ContractTemplate::factory()->create();
    $template->newVersion(['name' => $template->name, 'body' => '<p>v2</p>']);

    $this->actingAs($staff, 'staff')->get("/vertragsvorlagen/{$template->public_id}")->assertNotFound();
});

it('opens the template list to every role that manages contracts, and refuses the ones that cannot', function (StaffRole $role, int $status) {
    $this->actingAs(staffMember($role), 'staff')->get('/vertragsvorlagen')->assertStatus($status);
})->with([
    [StaffRole::Owner, 200],
    [StaffRole::Photographer, 200],
    [StaffRole::Editor, 200],
    [StaffRole::SecondShooter, 403],
    [StaffRole::Assistant, 403],
]);

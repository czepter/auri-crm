<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Enums\StaffRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

// C-16 (#252) gave this file its first rows: the preview now reckons against
// a real project, so what it writes has to be rolled back like everywhere
// else. Without this the two records leak into every test that runs after.
uses(RefreshDatabase::class);

it('previews a contract body against sample data with no unresolved placeholder', function () {
    $response = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post('/dokumentvorlagen/vorschau', [
            'type' => 'contract',
            'body' => '<p>Vertrag mit {{ contact.name }} über {{ project.name }}.</p>',
        ])
        ->assertOk();

    expect($response->getContent())
        ->toContain('Lena Meier')
        ->toContain('Hochzeit Meier & Kowalski')
        ->not->toContain('{{');
});

it('previews a mail body unwrapped, while a contract preview carries the letterhead shell', function () {
    $mail = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post('/dokumentvorlagen/vorschau', ['type' => 'mail', 'body' => '<p>Hallo {{ contact.name }}</p>'])
        ->assertOk();

    expect($mail->getContent())->not->toContain('<html');
});

it('refuses a preview for a role without ManageContracts', function () {
    $this->actingAs(staffMember(StaffRole::SecondShooter), 'staff')
        ->post('/dokumentvorlagen/vorschau', ['type' => 'contract', 'body' => '<p>x</p>'])
        ->assertForbidden();
});

it('rejects a preview with an unknown document type', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post('/dokumentvorlagen/vorschau', ['type' => 'receipt', 'body' => '<p>x</p>'])
        ->assertInvalid(['type']);
});

// C-16 (#252): the preview reckons with a chosen project, not with sample
// values — only then does an empty placeholder show up here rather than in
// front of the client.
it('renders the preview against a chosen project', function () {
    // No ampersand in the name: the renderer escapes it, and this test is
    // about which values arrive, not about escaping.
    $project = Project::factory()->create(['name' => 'Hochzeit Kowalski']);
    $project->attachContact(Contact::factory()->create(['name' => 'Lena Meier']), ProjectContactRole::Primary);

    $html = test()->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post('/dokumentvorlagen/vorschau', [
            'type' => 'mail',
            'body' => '<p>Hallo {{ contact.name }}, {{ project.name }}</p>',
            'project' => $project->public_id,
        ])
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Lena Meier')
        ->and($html)->toContain('Hochzeit Kowalski');
});

it('falls back to the sample values when no project is chosen', function () {
    $html = test()->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post('/dokumentvorlagen/vorschau', [
            'type' => 'mail',
            'body' => '<p>{{ contact.name }}</p>',
        ])
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Lena Meier');
});

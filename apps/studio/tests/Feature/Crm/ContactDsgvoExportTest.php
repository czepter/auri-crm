<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Engagement\Models\Message;
use App\Engagement\Models\Thread;
use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->withMfa()->create(['role' => StaffRole::Admin]);
});

it('exports everything the studio holds about a contact as downloadable json', function () {
    $contact = Contact::factory()->create(['name' => 'Lena Meier', 'email' => 'lena@example.test']);
    $project = ClientCase::factory()->create(['name' => 'Meier — Hochzeit']);
    $project->attachContact($contact, CaseContactRole::Primary);

    $thread = Thread::factory()->for($project, 'case')->create(['subject' => 'Frage zum Termin']);
    Message::factory()->for($thread)->create(['contact_id' => $contact->id, 'author_id' => null, 'body' => 'Können wir früher starten?']);
    Message::factory()->for($thread)->fromStaff($this->owner)->create(['body' => 'Klar, 9 Uhr passt.']);

    $response = $this->actingAs($this->owner, 'staff')
        ->get("/kontakte/{$contact->public_id}/dsgvo/export")
        ->assertOk();

    expect($response->headers->get('content-disposition'))
        ->toContain("dsgvo-export-{$contact->public_id}.json");

    $data = json_decode($response->getContent(), true);

    expect($data['contact']['id'])->toBe($contact->public_id)
        ->and($data['contact']['name'])->toBe('Lena Meier')
        ->and($data['contact']['email'])->toBe('lena@example.test')
        ->and($data['projects'])->toHaveCount(1);

    $exportedProject = $data['projects'][0];
    expect($exportedProject['id'])->toBe($project->public_id)
        ->and($exportedProject['role'])->toBe('primary')
        ->and($exportedProject['threads'][0]['subject'])->toBe('Frage zum Termin')
        ->and(collect($exportedProject['threads'][0]['messages'])->pluck('body'))
        ->toContain('Können wir früher starten?', 'Klar, 9 Uhr passt.');

    // The whole document names every id it carries via public_id — never an
    // internal integer primary key, the same rule every other JSON/Inertia
    // response in this app already follows.
    expect(integerIdPaths($data))->toBe([]);
});

it("never leaks another contact's own words from a shared project", function () {
    $subject = Contact::factory()->create(['name' => 'Lena Meier']);
    $partner = Contact::factory()->create(['name' => 'Tom Kowalski']);
    $project = ClientCase::factory()->create();
    $project->attachContact($subject, CaseContactRole::Primary);
    $project->attachContact($partner, CaseContactRole::Partner);

    $thread = Thread::factory()->for($project, 'case')->create();
    Message::factory()->for($thread)->create(['contact_id' => $subject->id, 'author_id' => null, 'body' => 'Meine Nachricht']);
    Message::factory()->for($thread)->create(['contact_id' => $partner->id, 'author_id' => null, 'body' => 'Toms Geheimnis']);

    $data = json_decode(
        $this->actingAs($this->owner, 'staff')
            ->get("/kontakte/{$subject->public_id}/dsgvo/export")
            ->getContent(),
        true
    );

    $bodies = collect($data['projects'][0]['threads'][0]['messages'])->pluck('body');

    expect($bodies)->toContain('Meine Nachricht')->not->toContain('Toms Geheimnis');
});

it('refuses every role but the owner', function (StaffRole $role) {
    $contact = Contact::factory()->create();

    $this->actingAs(User::factory()->withMfa()->create(['role' => $role]), 'staff')
        ->get("/kontakte/{$contact->public_id}/dsgvo/export")
        ->assertForbidden();
})->with([StaffRole::Manager, StaffRole::Contributor, StaffRole::Contributor, StaffRole::Contributor]);

it('rejects unauthenticated access', function () {
    $contact = Contact::factory()->create();

    $this->get("/kontakte/{$contact->public_id}/dsgvo/export")->assertRedirect('/login');
});

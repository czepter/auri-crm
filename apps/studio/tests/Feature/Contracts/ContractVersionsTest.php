<?php

use App\Contracts\Actions\SignContract;
use App\Contracts\Enums\ContractState;
use App\Contracts\Enums\ContractStatus;
use App\Contracts\Models\Contract;
use App\Contracts\Models\ContractSigner;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Documents\Models\DocumentTemplate;
use App\Enums\StaffRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function draftContract(?Project $project = null): Contract
{
    return Contract::factory()
        ->for($project ?? bookedContactProject())
        ->for(DocumentTemplate::factory()->create(['body' => '<p>Vorlage</p>']), 'template')
        ->create(['state' => ContractState::Draft, 'version' => 1]);
}

it('edits a draft Fassung in place and leaves the version number alone', function () {
    Queue::fake();

    $contract = draftContract();

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->patch("/vertraege/{$contract->public_id}", ['body' => '<p>§ 1 Gegenstand</p>'])
        ->assertRedirect();

    expect(Contract::count())->toBe(1)
        ->and($contract->fresh()->body)->toBe('<p>§ 1 Gegenstand</p>')
        ->and($contract->fresh()->version)->toBe(1);
});

it('starts the next Fassung instead of touching a sent one, and carries its signers over unsigned', function () {
    Queue::fake();

    $project = bookedContactProject();
    $contract = draftContract($project);
    $contact = $project->contacts()->sole();
    $contract->signers()->create(['contact_id' => $contact->id, 'signed_at' => now()]);
    $contract->markSent();

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->patch("/vertraege/{$contract->public_id}", ['body' => '<p>§ 4 Zweitfotografin</p>'])
        ->assertRedirect();

    $next = Contract::where('version', 2)->sole();

    expect($contract->fresh()->body)->toBeNull()
        ->and($contract->fresh()->state)->toBe(ContractState::Valid)
        ->and($next->state)->toBe(ContractState::Draft)
        ->and($next->body)->toBe('<p>§ 4 Zweitfotografin</p>')
        ->and($next->signers()->sole()->contact_id)->toBe($contact->id)
        ->and($next->signers()->sole()->signed_at)->toBeNull();
});

it('keeps the signed Fassung valid until the new one is sent', function () {
    Queue::fake();

    $project = bookedContactProject();
    $signed = draftContract($project);
    $signed->markSent();

    $draft = Contract::factory()->for($project)->for($signed->template, 'template')
        ->create(['state' => ContractState::Draft, 'version' => 2]);

    expect($signed->fresh()->state)->toBe(ContractState::Valid);

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post("/vertraege/{$draft->public_id}/versenden")
        ->assertRedirect();

    expect($signed->fresh()->state)->toBe(ContractState::Superseded)
        ->and($draft->fresh()->state)->toBe(ContractState::Valid)
        ->and($draft->fresh()->sent_at)->not->toBeNull();
});

it('refuses to send anything but a draft', function () {
    $contract = draftContract();
    $contract->markSent();

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post("/vertraege/{$contract->public_id}/versenden")
        ->assertStatus(422);
});

it('renders the Fassung’s own wording rather than the template it started from', function () {
    $contract = draftContract();
    $contract->update(['body' => '<p>Eigener Text</p>']);

    expect($contract->fresh()->resolvedBody())->toBe('<p>Eigener Text</p>')
        ->and(draftContract(Project::factory()->create())->resolvedBody())->toBe('<p>Vorlage</p>');
});

it('refuses editing for a role without ManageContracts', function () {
    $contract = draftContract();

    $this->actingAs(staffMember(StaffRole::SecondShooter), 'staff')
        ->patch("/vertraege/{$contract->public_id}", ['body' => '<p>x</p>'])
        ->assertForbidden();
});

it('adds only project contacts as signers, and never deletes one that has signed', function () {
    $project = bookedContactProject();
    $contract = draftContract($project);
    $contact = $project->contacts()->sole();
    $stranger = Contact::factory()->create();
    $staff = staffMember(StaffRole::Owner);

    $this->actingAs($staff, 'staff')
        ->post("/vertraege/{$contract->public_id}/unterzeichner", ['contact' => $stranger->public_id])
        ->assertNotFound();

    $this->actingAs($staff, 'staff')
        ->post("/vertraege/{$contract->public_id}/unterzeichner", ['contact' => $contact->public_id])
        ->assertRedirect();

    $signer = ContractSigner::sole();
    expect($signer->contact_id)->toBe($contact->id);

    $this->actingAs($staff, 'staff')->delete("/unterzeichner/{$signer->public_id}")->assertRedirect();
    expect(ContractSigner::count())->toBe(0);

    $signed = $contract->signers()->create(['contact_id' => $contact->id, 'signed_at' => now()]);

    $this->actingAs($staff, 'staff')->delete("/unterzeichner/{$signed->public_id}")->assertStatus(422);
});

it('records a reminder without sending mail — there is no client-facing contract notification', function () {
    Notification::fake();

    $project = bookedContactProject();
    $contract = draftContract($project);
    $signer = $contract->signers()->create(['contact_id' => $project->contacts()->sole()->id]);

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post("/unterzeichner/{$signer->public_id}/erinnerung")
        ->assertRedirect();

    expect($signer->fresh()->reminded_at)->not->toBeNull();
    Notification::assertNothingSent();
});

it('stamps the signer row when the contract is signed', function () {
    Storage::fake('local');

    $project = bookedContactProject();
    $contract = renderedContract($project);
    $contact = $project->contacts()->sole();
    $signer = $contract->signers()->create(['contact_id' => $contact->id]);

    app(SignContract::class)($contract, $contact, 'Lena Meier', '127.0.0.1', null);

    expect($signer->fresh()->signed_at)->not->toBeNull();
});

it('numbers a second contract for the same project as the next Fassung', function () {
    Queue::fake();

    $project = bookedContactProject();
    $template = DocumentTemplate::factory()->create();
    draftContract($project);

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post("/auftraege/{$project->public_id}/vertraege", ['template' => $template->public_id])
        ->assertRedirect();

    expect(Contract::orderByDesc('version')->first()->version)->toBe(2)
        ->and(Contract::orderByDesc('version')->first()->state)->toBe(ContractState::Draft)
        ->and(Contract::orderByDesc('version')->first()->status)->toBe(ContractStatus::Pending);
});

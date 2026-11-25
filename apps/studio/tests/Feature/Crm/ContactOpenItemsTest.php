<?php

use App\Crm\Models\Contact;
use App\Crm\Models\Shoot;
use App\Crm\Models\Task;
use App\Enums\Capability;
use App\Enums\StaffRole;
use App\Payments\Actions\IssueInvoice;
use App\Settings\Actions\UpdateStudioSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function contactWithOpenWork(): Contact
{
    Storage::fake('local');
    app(UpdateStudioSetting::class)('tax_number', '22/123/45678', null);

    $installment = invoiceablePlan();
    $project = $installment->plan->case;
    $contact = $project->primaryContact();

    app(IssueInvoice::class)($installment);
    Task::factory()->create(['case_id' => $project->id, 'title' => 'Album planen']);
    Shoot::factory()->for($project, 'case')->on(now()->addDays(10)->toDateString())->create();

    return $contact;
}

it('lists what is open on the contact: invoices, tasks and the next shoot', function () {
    $contact = contactWithOpenWork();

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get("/kontakte/{$contact->public_id}")->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('openInvoices', 1)
            ->where('openInvoices.0.openInstallments.0.remaining', 150_000 / 2)
            ->where('openTasks.0.title', 'Album planen')
            ->where('nextShoot.date', now()->addDays(10)->toDateString())
            ->where('nextShoot.projectName', 'Hochzeit Meier & Kowalski'));
});

it('keeps invoices from staff who may not see payments', function () {
    $contact = contactWithOpenWork();

    $crew = staffMember(StaffRole::Contributor);
    $crew->givePermissionTo(Capability::ManageContacts->value, Capability::ViewAllCases->value);

    $this->actingAs($crew, 'staff')
        ->get("/kontakte/{$contact->public_id}")->assertOk()
        ->assertInertia(fn ($page) => $page->where('openInvoices', [])->has('openTasks', 1));
});

it('is empty when nothing is open', function () {
    $contact = Contact::factory()->create();

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get("/kontakte/{$contact->public_id}")->assertOk()
        ->assertInertia(fn ($page) => $page->where('openInvoices', [])->where('openTasks', [])->where('nextShoot', null));
});

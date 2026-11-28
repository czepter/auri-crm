<?php

use App\Automation\Models\AutomationRun;
use App\Automation\Notifications\AutomationReminder;
use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\CrewAssignment;
use App\Crm\Models\Shoot;
use App\Enums\StaffRole;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\Invoice;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function draftedReminder(array $roles = [CaseContactRole::Primary], array $installment = []): array
{
    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $contacts = collect($roles)->map(function (CaseContactRole $role) use ($project) {
        $contact = Contact::factory()->create();
        $project->attachContact($contact, $role);

        return $contact;
    });

    $installment = PaymentInstallment::factory()->create([
        'payment_plan_id' => PaymentPlan::factory()->create(['case_id' => markBooked($project)]),
        'due_on' => now()->subDay()->toDateString(),
        ...$installment,
    ]);

    test()->artisan('payments:detect-overdue-installments');
    test()->travel(4)->days();
    test()->artisan('payments:detect-overdue-installments');

    return [$project, $installment, $contacts];
}

it('keeps the payment reminder draft from a contributor who may not manage the plan', function () {
    [$project] = draftedReminder();
    $contributor = staffMember(StaffRole::Contributor);
    CrewAssignment::factory()->for(Shoot::factory()->for($project, 'case')->create())->for($contributor)->create();
    $draft = AutomationRun::sole();

    expect(AutomationRun::awaitingFor($contributor))->toHaveCount(0)
        ->and(AutomationRun::awaitingFor(staffMember(StaffRole::Manager)))->toHaveCount(1);

    $this->actingAs($contributor, 'staff')
        ->post("/posteingang/entwuerfe/{$draft->public_id}/versenden", ['subject' => 'Erinnerung', 'message' => 'Text'])
        ->assertForbidden();
});

it('retires the reminder draft once the rate is paid', function () {
    [, $installment] = draftedReminder();
    $manager = staffMember(StaffRole::Manager);
    $draft = AutomationRun::sole();

    $installment->update(['status' => PaymentInstallmentStatus::Paid]);

    expect(AutomationRun::awaitingFor($manager))->toHaveCount(0);

    $this->actingAs($manager, 'staff')
        ->post("/posteingang/entwuerfe/{$draft->public_id}/versenden", ['subject' => 'Erinnerung', 'message' => 'Text'])
        ->assertForbidden();
});

it('refreshes the waiting draft instead of adding one per rung', function () {
    [, $installment] = draftedReminder();

    $installment->update(['paid_amount' => 1000, 'status' => PaymentInstallmentStatus::Partial]);
    $this->travel(5)->days();
    Artisan::call('payments:detect-overdue-installments');

    expect(AutomationRun::count())->toBe(1)
        ->and(AutomationRun::sole()->body)->toContain(number_format(($installment->amount - 1000) / 100, 2, ',', '.'));
});

it('drafts one reminder per recipient', function () {
    [, , $contacts] = draftedReminder([CaseContactRole::Partner, CaseContactRole::Payer]);

    expect(AutomationRun::all()->map(fn ($run) => $run->contact_ids)->all())
        ->toEqualCanonicalizing([[$contacts[0]->id], [$contacts[1]->id]]);
});

it('attaches the invoice PDF when the reminder draft is sent', function () {
    Notification::fake();
    Storage::fake('local');
    [$project, $installment, $contacts] = draftedReminder();

    $invoice = Invoice::create([
        'number' => 'RE-2026-0001',
        'issued_at' => now(),
        'contact_id' => $contacts[0]->id,
        'case_id' => $project->id,
        'pdf_path' => 'invoices/re-2026-0001.pdf',
    ]);
    Storage::disk('local')->put($invoice->pdf_path, 'pdf');
    $installment->update(['invoice_id' => $invoice->id]);

    $this->actingAs(staffMember(StaffRole::Manager), 'staff')
        ->post('/posteingang/entwuerfe/'.AutomationRun::sole()->public_id.'/versenden', ['subject' => 'Erinnerung', 'message' => 'Text']);

    Notification::assertSentTo($contacts[0], AutomationReminder::class, fn (AutomationReminder $mail) => count($mail->toMail($contacts[0])->rawAttachments) === 1);
});

it('rolls the rule column back with drafts that belong to no rule present', function () {
    draftedReminder();
    $migration = '2026_11_25_100000_make_automation_run_rule_nullable';

    Artisan::call('migrate:rollback', ['--path' => "database/migrations/{$migration}.php", '--force' => true]);

    expect(AutomationRun::count())->toBe(0);

    Artisan::call('migrate', ['--path' => "database/migrations/{$migration}.php", '--force' => true]);
});

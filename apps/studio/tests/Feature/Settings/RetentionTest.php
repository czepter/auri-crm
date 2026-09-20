<?php

use App\Crm\Enums\LeadOutcome;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Job;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Payments\Models\Invoice;
use App\Settings\Models\StudioSettings;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function retentionMonths(?int $months): void
{
    StudioSettings::query()->update(['retention_months' => $months]);
    app(StudioSettingsResolver::class)->forget();
}

function inquiry(?LeadOutcome $outcome = LeadOutcome::Lost, int $idleMonths = 13): Contact
{
    $contact = Contact::factory()->create();
    $project = Project::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);
    $lead = Lead::factory()->for($project)->create();

    $outcome === null || $lead->update(['outcome' => $outcome, 'lost_at' => now()]);
    DB::table('leads')->where('id', $lead->id)->update(['updated_at' => now()->subMonths($idleMonths)]);

    return $contact;
}

function erased(Contact $contact): bool
{
    return $contact->fresh()->isDsgvoErased();
}

it('erases nothing while the setting is off', function () {
    $contact = inquiry();

    $this->artisan('retention:erase')->assertSuccessful();

    expect(erased($contact))->toBeFalse();
});

it('erases lost and declined inquiries past the deadline, once, with a count in the log', function () {
    retentionMonths(12);
    $lost = inquiry(LeadOutcome::Lost);
    $declined = inquiry(LeadOutcome::Declined);

    $this->artisan('retention:erase')->assertSuccessful();
    $this->artisan('retention:erase')->assertSuccessful();

    expect(erased($lost))->toBeTrue()
        ->and(erased($declined))->toBeTrue()
        ->and(Lead::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('event', 'retention.erased')->sole()->context)->toBe(['count' => 2, 'months' => 12]);
});

it('keeps open and closed inquiries and those within the deadline', function () {
    retentionMonths(12);
    $kept = [inquiry(null), inquiry(LeadOutcome::Closed), inquiry(LeadOutcome::Lost, 11)];

    $this->artisan('retention:erase')->assertSuccessful();

    expect(array_map(erased(...), $kept))->toBe([false, false, false]);
});

it('keeps a contact with a booked project', function () {
    retentionMonths(12);
    $contact = inquiry();
    $booked = Project::factory()->create();
    $booked->attachContact($contact, ProjectContactRole::Primary);
    Job::factory()->for($booked)->create();

    $this->artisan('retention:erase')->assertSuccessful();

    expect(erased($contact))->toBeFalse();
});

it('keeps a contact who has an invoice, directly or through a project', function () {
    retentionMonths(12);
    $direct = inquiry();
    Invoice::create(['number' => 'RE-1', 'issued_at' => now(), 'contact_id' => $direct->id]);
    $viaProject = inquiry();
    Invoice::create(['number' => 'RE-2', 'issued_at' => now(), 'project_id' => $viaProject->projects()->sole()->id]);

    $this->artisan('retention:erase')->assertSuccessful();

    expect(erased($direct))->toBeFalse()->and(erased($viaProject))->toBeFalse();
});

it('shows the page and saves the deadline for the owner only', function () {
    $this->actingAs(staffMember(StaffRole::Photographer), 'staff')->get('/einstellungen/aufbewahrung')->assertNotFound();
    $this->actingAs(staffMember(StaffRole::Photographer), 'staff')->patch('/einstellungen/aufbewahrung', ['retention_months' => 6])->assertNotFound();

    $this->actingAs(staffMember(StaffRole::Owner), 'staff');
    $this->get('/einstellungen/aufbewahrung')->assertOk()->assertInertia(fn ($page) => $page->where('months', null));
    $this->patch('/einstellungen/aufbewahrung', ['retention_months' => 6])->assertRedirect();
    expect(app(StudioSettingsResolver::class)->retentionMonths())->toBe(6);

    $this->patch('/einstellungen/aufbewahrung', ['retention_months' => 121])->assertSessionHasErrors('retention_months');
    $this->patch('/einstellungen/aufbewahrung', ['retention_months' => null])->assertRedirect();
    expect(app(StudioSettingsResolver::class)->retentionMonths())->toBeNull();
});

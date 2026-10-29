<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Enums\LeadOutcome;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\StudioSettings;
use App\Payments\Models\Invoice;
use App\Settings\Data\RetentionPolicyData;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function retentionMonths(?int $months, ?RetentionPolicyData $policy = null): void
{
    StudioSettings::query()->update(['retention_months' => $months, 'retention_policy' => $policy?->removed()]);
    app(StudioSettingsResolver::class)->forget();
}

function inquiry(?LeadOutcome $outcome = LeadOutcome::Lost, int $idleMonths = 13): Contact
{
    $contact = Contact::factory()->create();
    $project = ClientCase::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);
    $lead = Lead::factory()->for($project, 'case')->create(['inquiry_text' => 'Hochzeit im Mai']);

    $outcome === null || $project->forceFill(['close_outcome' => $outcome, 'closed_at' => now()->subMonths($idleMonths)])->save();
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

it('anonymizes lost and declined inquiries past the deadline, once, keeping the lead for the reports', function () {
    retentionMonths(12);
    $lost = inquiry(LeadOutcome::Lost);
    $declined = inquiry(LeadOutcome::Declined);

    $this->artisan('retention:erase')->assertSuccessful();
    $this->artisan('retention:erase')->assertSuccessful();

    expect(erased($lost))->toBeTrue()
        ->and(erased($declined))->toBeTrue()
        ->and(Lead::query()->count())->toBe(2)
        ->and(Lead::query()->whereNotNull('inquiry_text')->count())->toBe(0)
        ->and(ClientCase::query()->where('name', '[gelöscht]')->count())->toBe(2)
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
    $booked = ClientCase::factory()->create();
    $booked->attachContact($contact, CaseContactRole::Primary);
    markBooked($booked);

    $this->artisan('retention:erase')->assertSuccessful();

    expect(erased($contact))->toBeFalse();
});

it('keeps a contact who has an invoice, directly or through a project', function () {
    retentionMonths(12);
    $direct = inquiry();
    Invoice::create(['number' => 'RE-1', 'issued_at' => now(), 'contact_id' => $direct->id]);
    $viaProject = inquiry();
    Invoice::create(['number' => 'RE-2', 'issued_at' => now(), 'case_id' => $viaProject->cases()->sole()->id]);

    $this->artisan('retention:erase')->assertSuccessful();

    expect(erased($direct))->toBeFalse()->and(erased($viaProject))->toBeFalse();
});

it('shows the page and saves the deadline for the owner only', function () {
    $this->actingAs(staffMember(StaffRole::Manager), 'staff')->get('/einstellungen/aufbewahrung')->assertNotFound();
    $this->actingAs(staffMember(StaffRole::Manager), 'staff')->patch('/einstellungen/aufbewahrung', ['retention_months' => 6])->assertNotFound();

    $this->actingAs(staffMember(StaffRole::Admin), 'staff');
    $this->get('/einstellungen/aufbewahrung')->assertOk()->assertInertia(fn ($page) => $page->where('months', null));
    $this->patch('/einstellungen/aufbewahrung', ['retention_months' => 6, 'policy' => ['name' => true, 'text' => true, 'messages' => true, 'offers' => true, 'galleries' => true, 'order' => false]])->assertRedirect();
    expect(app(StudioSettingsResolver::class)->retentionMonths())->toBe(6);

    $this->patch('/einstellungen/aufbewahrung', ['retention_months' => 121])->assertSessionHasErrors('retention_months');
    $this->patch('/einstellungen/aufbewahrung', ['retention_months' => null])->assertRedirect();
    expect(app(StudioSettingsResolver::class)->retentionMonths())->toBeNull();
});

it('removes lead and project when the policy takes the order', function () {
    retentionMonths(12, RetentionPolicyData::everything());
    $contact = inquiry();

    $this->artisan('retention:erase')->assertSuccessful();

    expect(erased($contact))->toBeTrue()->and(Lead::query()->count())->toBe(0)->and(ClientCase::query()->count())->toBe(0);
});

it('drops only the texts and leaves the name when that is the policy, and does not come back for the contact', function () {
    retentionMonths(12, new RetentionPolicyData(name: false, text: true, messages: false, offers: false, galleries: false, order: false));
    $contact = inquiry();
    $name = $contact->name;

    $this->artisan('retention:erase')->expectsOutput('1 bearbeitet.')->assertSuccessful();
    $this->artisan('retention:erase')->expectsOutput('0 bearbeitet.')->assertSuccessful();

    expect(erased($contact))->toBeFalse()
        ->and($contact->fresh()->name)->toBe($name)
        ->and($contact->fresh()->retention_applied_at)->not->toBeNull()
        ->and(Lead::query()->whereNotNull('inquiry_text')->count())->toBe(0);
});

it('runs now only with the owner\'s password, and saves what was chosen', function () {
    $owner = staffMember(StaffRole::Admin);
    $this->actingAs($owner, 'staff');
    $contact = inquiry();
    $body = ['retention_months' => 12, 'policy' => ['name' => false, 'text' => true, 'messages' => false, 'offers' => false, 'galleries' => false, 'order' => false]];

    $this->post('/einstellungen/aufbewahrung/durchfuehren', $body + ['current_password' => 'falsch'])->assertSessionHasErrors('current_password');
    expect(erased($contact))->toBeFalse();

    $this->post('/einstellungen/aufbewahrung/durchfuehren', $body + ['current_password' => 'password'])->assertRedirect()->assertSessionHasNoErrors();
    expect(erased($contact))->toBeFalse()
        ->and(Lead::query()->whereNotNull('inquiry_text')->count())->toBe(0)
        ->and(app(StudioSettingsResolver::class)->retentionMonths())->toBe(12)
        ->and(app(StudioSettingsResolver::class)->retentionPolicy()->name)->toBeFalse();
});

it('offers every candidate to the page, with its age and whether an invoice keeps it', function () {
    $this->actingAs(staffMember(StaffRole::Admin), 'staff');
    inquiry(LeadOutcome::Lost, 14);
    $kept = inquiry(LeadOutcome::Declined, 3);
    Invoice::create(['number' => 'RE-3', 'issued_at' => now(), 'contact_id' => $kept->id]);

    $this->get('/einstellungen/aufbewahrung')->assertInertia(fn ($page) => $page
        ->has('candidates', 2)
        ->where('candidates.0.idleMonths', 14)
        ->where('candidates.0.invoiced', false)
        ->where('candidates.1.invoiced', true)
        ->where('funnel.total', 2)
        ->where('policy.order', false));
});

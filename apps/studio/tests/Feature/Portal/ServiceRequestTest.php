<?php

use App\Catalog\Models\Package;
use App\Crm\Enums\CaptureChannel;
use App\Crm\Enums\LeadSource;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Support\CrmVocabulary;
use App\Settings\Models\StudioSettings;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::PORTAL);
    StudioSettings::query()->update(['portal_offers_enabled' => true]);
});

function offered(array $attributes = []): Package
{
    return Package::factory()->create([
        'portal_offered' => true,
        'project_type_key' => app(CrmVocabulary::class)->type('wedding')?->key,
        ...$attributes,
    ]);
}

it('hides a package the studio does not offer in the portal', function () {
    $package = Package::factory()->create(['portal_offered' => false]);

    test()->actingAs(Contact::factory()->create(), 'portal')
        ->get("/leistungen/{$package->public_id}")
        ->assertNotFound();
});

// T-08: a capability the studio switched off looks like a feature that does
// not exist, never like one refused.
it('hides the whole shelf when the studio has not switched it on', function () {
    StudioSettings::query()->update(['portal_offers_enabled' => false]);

    test()->actingAs(Contact::factory()->create(), 'portal')
        ->get('/leistungen/'.offered()->public_id)
        ->assertNotFound();
});

// P-09: die Übersteuerung je Kontakt, hier bis ins Portal durchgezogen. Ein
// Kunde mitten in einer Reklamation soll nichts angeboten bekommen, auch wenn
// das Studio das Regal offen hat.
it('hides the shelf from a contact whose own override says no', function () {
    $contact = Contact::factory()->create(['portal_overrides' => ['offers' => false]]);

    test()->actingAs($contact, 'portal')
        ->get('/leistungen/'.offered()->public_id)
        ->assertNotFound();
});

it('shows the shelf to a contact who overrides a studio default that is off', function () {
    StudioSettings::query()->update(['portal_offers_enabled' => false]);
    $contact = Contact::factory()->create(['portal_overrides' => ['offers' => true]]);

    test()->actingAs($contact, 'portal')
        ->get('/leistungen/'.offered()->public_id)
        ->assertOk();
});

it('opens a booking with one primary shoot when the package asks for a date', function () {
    $contact = Contact::factory()->create();
    $package = offered(['name' => 'Paarshooting zum Jahrestag']);

    test()->actingAs($contact, 'portal')
        ->post("/leistungen/{$package->public_id}/anfragen", [
            'date' => now()->addMonths(3)->format('Y-m-d'),
            'venue' => 'Jenischpark',
            'text' => 'Am liebsten nachmittags.',
        ])
        ->assertRedirect();

    $project = Project::query()->where('name', 'Paarshooting zum Jahrestag')->sole();
    $shoot = Shoot::query()->where('project_id', $project->id)->sole();

    expect($project->contacts->pluck('id'))->toContain($contact->id)
        ->and($project->portal_visible)->toBeTrue()
        ->and($shoot->is_primary)->toBeTrue()
        ->and($shoot->venue_name)->toBe('Jenischpark');
});

// Ein Album ist kein Shooting. CaptureInquiry legt immer genau einen Shoot an;
// genau deshalb geht diese Anfrage nicht durch sie hindurch.
it('opens a booking without a shoot when the package needs no date', function () {
    $package = offered(['portal_requires_date' => false, 'name' => 'Fotoalbum']);

    test()->actingAs(Contact::factory()->create(), 'portal')
        ->post("/leistungen/{$package->public_id}/anfragen", ['text' => 'Leinen, bitte.'])
        ->assertRedirect();

    $project = Project::query()->where('name', 'Fotoalbum')->sole();

    expect(Shoot::query()->where('project_id', $project->id)->count())->toBe(0);
});

it('refuses a request without the date the package asks for', function () {
    $package = offered();

    test()->actingAs(Contact::factory()->create(), 'portal')
        ->post("/leistungen/{$package->public_id}/anfragen", ['text' => 'Ohne Datum.'])
        ->assertSessionHasErrors('date');
});

// LeadSource beantwortet "woher kennt uns der Kunde". Bei einem
// Bestandskunden steht das an seiner ersten Anfrage und wird von dort
// übernommen — dass die zweite aus dem Portal kam, steht in captured_via.
it('keeps the source of the contact first inquiry and records the portal as the channel', function () {
    $contact = Contact::factory()->create();

    $first = Project::factory()->create();
    $first->attachContact($contact, ProjectContactRole::Primary);
    Lead::factory()->create([
        'project_id' => $first->id,
        'source' => LeadSource::WeddingFair,
        'captured_at' => now()->subYear(),
    ]);

    test()->actingAs($contact, 'portal')
        ->post('/leistungen/'.offered(['name' => 'Fotoalbum'])->public_id.'/anfragen', [
            'date' => now()->addMonth()->format('Y-m-d'),
        ])
        ->assertRedirect();

    $lead = Lead::query()->whereNot('project_id', $first->id)->sole();

    expect($lead->source)->toBe(LeadSource::WeddingFair)
        ->and($lead->captured_via)->toBe(CaptureChannel::Portal);
});

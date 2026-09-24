<?php

use App\Catalog\Models\Package;
use App\Catalog\Models\PackageItem;
use App\Catalog\Models\ServiceSuggestion;
use App\Catalog\Support\PortalOffers;
use App\Crm\Enums\CaptureChannel;
use App\Crm\Enums\CaseContactRole;
use App\Crm\Enums\LeadSource;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
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

// Zweimal dieselbe Leistung auf einem Screen ist keine zweite Gelegenheit.
it('takes the suggested service out of the shelf', function () {
    $contact = Contact::factory()->create();
    $suggested = offered(['name' => 'Fotoalbum']);
    offered(['name' => 'Wandbild']);

    ServiceSuggestion::create([
        'contact_id' => $contact->id,
        'package_id' => $suggested->id,
        'note' => 'Ein Satz über diesen Kunden.',
    ]);

    $offers = app(PortalOffers::class)->for($contact);

    expect($offers['suggestion']['service']['name'])->toBe('Fotoalbum')
        ->and($offers['suggestion']['note'])->toContain('Satz')
        ->and(collect($offers['shelf'])->pluck('name')->all())->toBe(['Wandbild']);
});

// Das Studio hat die Leistung zurueckgezogen, nicht den Vorschlag widerrufen.
it('drops a suggestion whose package left the portal', function () {
    $contact = Contact::factory()->create();
    $package = offered();
    ServiceSuggestion::create(['contact_id' => $contact->id, 'package_id' => $package->id]);

    $package->update(['portal_offered' => false]);

    expect(app(PortalOffers::class)->for($contact)['suggestion'])->toBeNull();
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

    $project = ClientCase::query()->where('name', 'Paarshooting zum Jahrestag')->sole();
    $shoot = Shoot::query()->where('case_id', $project->id)->sole();

    expect($project->contacts->pluck('id'))->toContain($contact->id)
        ->and($project->portal_visible)->toBeTrue()
        ->and($project->lead->package_id)->toBe($package->id)
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

    $project = ClientCase::query()->where('name', 'Fotoalbum')->sole();

    expect(Shoot::query()->where('case_id', $project->id)->count())->toBe(0);
});

// Paketpreise sind netto. Was oben draufkommt, muss dastehen, bevor jemand
// anfragt — und für einen Kleinunternehmer kommt nichts drauf.
it('names the tax on top of the price', function () {
    test()->actingAs(Contact::factory()->create(), 'portal')
        ->get('/leistungen/'.offered()->public_id)
        ->assertInertia(fn ($page) => $page->where('vatRate', 1900));
});

it('names no tax for a Kleinunternehmer', function () {
    StudioSettings::query()->update(['vat_mode' => 'small_business']);

    test()->actingAs(Contact::factory()->create(), 'portal')
        ->get('/leistungen/'.offered()->public_id)
        ->assertInertia(fn ($page) => $page->where('vatRate', null));
});

it('counts an included and a preselected item in the price, but not an unchosen add-on', function () {
    $package = offered();
    PackageItem::factory()->for($package)->create(['title' => 'Fotograf', 'unit_price' => 100000, 'quantity' => 1, 'selectable' => false, 'selected' => false]);
    PackageItem::factory()->for($package)->create(['title' => 'Album', 'unit_price' => 20000, 'quantity' => 1, 'selectable' => true, 'selected' => true]);
    PackageItem::factory()->for($package)->create(['title' => 'Zweitfotograf', 'unit_price' => 60000, 'quantity' => 1, 'selectable' => true, 'selected' => false]);

    test()->actingAs(Contact::factory()->create(), 'portal')
        ->get("/leistungen/{$package->public_id}")
        ->assertInertia(fn ($page) => $page
            ->where('service.price', 120000)
            ->where('service.items.0.addOnPrice', null)
            ->where('service.items.1.addOnPrice', null)
            ->where('service.items.2.addOnPrice', 60000)
        );
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

    $first = ClientCase::factory()->create();
    $first->attachContact($contact, CaseContactRole::Primary);
    Lead::factory()->create([
        'case_id' => $first->id,
        'source' => LeadSource::WeddingFair,
        'captured_at' => now()->subYear(),
    ]);

    test()->actingAs($contact, 'portal')
        ->post('/leistungen/'.offered(['name' => 'Fotoalbum'])->public_id.'/anfragen', [
            'date' => now()->addMonth()->format('Y-m-d'),
        ])
        ->assertRedirect();

    $lead = Lead::query()->whereNot('case_id', $first->id)->sole();

    expect($lead->source)->toBe(LeadSource::WeddingFair)
        ->and($lead->captured_via)->toBe(CaptureChannel::Portal);
});

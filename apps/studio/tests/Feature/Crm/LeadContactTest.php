<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Crm\Models\Shoot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
});

function leadWithPrimary(): Lead
{
    $project = ClientCase::factory()->wedding()->create(['name' => 'Rahn']);
    Shoot::factory()->for($project, 'case')->primary()->on('2027-06-12')->create();
    $project->attachContact(Contact::factory()->create(['name' => 'Jana Rahn']), CaseContactRole::Primary);

    return Lead::factory()->for($project, 'case')->create(['inquiry_text' => 'Wir heiraten auf Gut Basthorst.']);
}

it('keeps a freely written role as it was typed', function () {
    $lead = leadWithPrimary();

    $this->post("/anfragen/{$lead->public_id}/kontakte", [
        'name' => 'Kerstin Voß',
        'email' => 'kv@feierwerk.de',
        'role' => 'Trauredner',
    ])->assertRedirect();

    $roles = $lead->case->contacts()->pluck('case_contacts.role');

    expect($roles)->toContain('Trauredner');
});

it('refuses to add a second primary contact', function () {
    $lead = leadWithPrimary();

    $this->post("/anfragen/{$lead->public_id}/kontakte", [
        'name' => 'Michael Rahn',
        'role' => CaseContactRole::Primary->value,
    ])->assertStatus(422);
});

it('refuses to remove the primary contact', function () {
    $lead = leadWithPrimary();
    $primary = $lead->case->contacts()->first();

    $this->delete("/anfragen/{$lead->public_id}/kontakte/{$primary->public_id}")->assertStatus(422);

    expect($lead->case->contacts()->count())->toBe(1);
});

it('removes anybody else', function () {
    $lead = leadWithPrimary();
    $planner = Contact::factory()->create(['name' => 'Kerstin Voß']);
    $lead->case->attachContact($planner, 'Planung');

    $this->delete("/anfragen/{$lead->public_id}/kontakte/{$planner->public_id}")->assertRedirect();

    expect($lead->case->contacts()->count())->toBe(1);
});

// Eine bekannte Adresse ist dieselbe Person — dieselbe Regel, nach der die
// Erfassung ihren Kontakt auflöst.
it('links the contact behind a known address instead of writing a second one', function () {
    $lead = leadWithPrimary();
    $known = Contact::factory()->create(['name' => 'Katrin Brandes', 'email' => 'katrin@brandes-floral.de']);

    $this->post("/anfragen/{$lead->public_id}/kontakte", [
        'name' => 'K. Brandes',
        'email' => 'katrin@brandes-floral.de',
        'role' => 'Floristik',
    ])->assertRedirect();

    expect($lead->case->contacts()->pluck('contacts.id'))->toContain($known->id)
        ->and(Contact::where('email', 'katrin@brandes-floral.de')->count())->toBe(1);
});

// Jede Karte schickt ihre eigene Teilmenge, seit es keinen Bearbeiten-Modus
// über den ganzen Bildschirm mehr gibt.
it('leaves the inquiry text alone when only the people were sent', function () {
    $lead = leadWithPrimary();
    $primary = $lead->case->contacts()->first();

    $this->patch("/anfragen/{$lead->public_id}", [
        'people' => [
            ['id' => $primary->public_id, 'name' => 'Jana Rahn-Kleist', 'role' => 'Hauptkontakt'],
        ],
    ])->assertRedirect();

    expect($lead->fresh()->inquiry_text)->toBe('Wir heiraten auf Gut Basthorst.')
        ->and($primary->fresh()->name)->toBe('Jana Rahn-Kleist');
});

// Eine Verschiebung ist der häufigste Vorgang überhaupt und war bis hierher
// ein 422, sobald gebucht war.
it('moves a date after the booking', function () {
    $lead = leadWithPrimary();
    markBooked($lead->case);
    $shoot = $lead->case->shoots()->first();

    $this->patch("/anfragen/{$lead->public_id}", [
        'shoots' => [
            ['id' => $shoot->public_id, 'date' => '2027-06-19', 'venue_name' => 'Gut Basthorst'],
        ],
    ])->assertRedirect();

    expect($shoot->fresh()->date->toDateString())->toBe('2027-06-19');
});

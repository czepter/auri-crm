<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
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
    $project = Project::factory()->wedding()->create(['name' => 'Rahn']);
    Shoot::factory()->for($project)->primary()->on('2027-06-12')->create();
    $project->attachContact(Contact::factory()->create(['name' => 'Jana Rahn']), ProjectContactRole::Primary);

    return Lead::factory()->for($project)->create(['inquiry_text' => 'Wir heiraten auf Gut Basthorst.']);
}

it('keeps a freely written role as it was typed', function () {
    $lead = leadWithPrimary();

    $this->post("/anfragen/{$lead->public_id}/kontakte", [
        'name' => 'Kerstin Voß',
        'email' => 'kv@feierwerk.de',
        'role' => 'Trauredner',
    ])->assertRedirect();

    $roles = $lead->project->contacts()->pluck('project_contacts.role');

    expect($roles)->toContain('Trauredner');
});

it('refuses to add a second primary contact', function () {
    $lead = leadWithPrimary();

    $this->post("/anfragen/{$lead->public_id}/kontakte", [
        'name' => 'Michael Rahn',
        'role' => ProjectContactRole::Primary->value,
    ])->assertStatus(422);
});

it('refuses to remove the primary contact', function () {
    $lead = leadWithPrimary();
    $primary = $lead->project->contacts()->first();

    $this->delete("/anfragen/{$lead->public_id}/kontakte/{$primary->public_id}")->assertStatus(422);

    expect($lead->project->contacts()->count())->toBe(1);
});

it('removes anybody else', function () {
    $lead = leadWithPrimary();
    $planner = Contact::factory()->create(['name' => 'Kerstin Voß']);
    $lead->project->attachContact($planner, 'Planung');

    $this->delete("/anfragen/{$lead->public_id}/kontakte/{$planner->public_id}")->assertRedirect();

    expect($lead->project->contacts()->count())->toBe(1);
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

    expect($lead->project->contacts()->pluck('contacts.id'))->toContain($known->id)
        ->and(Contact::where('email', 'katrin@brandes-floral.de')->count())->toBe(1);
});

// Jede Karte schickt ihre eigene Teilmenge, seit es keinen Bearbeiten-Modus
// über den ganzen Bildschirm mehr gibt.
it('leaves the inquiry text alone when only the people were sent', function () {
    $lead = leadWithPrimary();
    $primary = $lead->project->contacts()->first();

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
    markBooked($lead->project);
    $shoot = $lead->project->shoots()->first();

    $this->patch("/anfragen/{$lead->public_id}", [
        'shoots' => [
            ['id' => $shoot->public_id, 'date' => '2027-06-19', 'venue_name' => 'Gut Basthorst'],
        ],
    ])->assertRedirect();

    expect($shoot->fresh()->date->toDateString())->toBe('2027-06-19');
});

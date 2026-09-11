<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Models\User;
use App\Quotes\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function studioWithProject(): array
{
    $user = User::factory()->withMfa()->create();
    $project = Project::factory()->create();

    return [$user, $project];
}

it('belegt den Empfaenger mit dem Zahler vor', function () {
    [$user, $project] = studioWithProject();

    $payer = Contact::factory()->create(['street' => 'Elbchaussee 88', 'postcode' => '22763', 'city' => 'Hamburg']);
    $project->attachContact($payer, ProjectContactRole::Payer);

    $this->actingAs($user, 'staff')->post("/auftraege/{$project->public_id}/angebote", [])->assertRedirect();

    $quote = Quote::query()->latest('id')->first();

    expect($quote->recipient_contact_id)->toBe($payer->id)
        ->and($quote->recipient_street)->toBe('Elbchaussee 88')
        ->and($quote->recipient_city)->toBe('Hamburg');
});

it('traegt die Anschrift in einen Kontakt ohne Anschrift nach', function () {
    [$user, $project] = studioWithProject();

    $contact = Contact::factory()->create(['street' => null, 'postcode' => null, 'city' => null]);
    $project->attachContact($contact, ProjectContactRole::Primary);

    $quote = Quote::factory()->for($project)->create(['recipient_contact_id' => $contact->id]);

    $this->actingAs($user, 'staff')->patch("/angebote/{$quote->public_id}", [
        'pricing_mode' => 'classic',
        'recipient_contact' => $contact->public_id,
        'recipient_street' => 'Hafenstraße 12',
        'recipient_postcode' => '20459',
        'recipient_city' => 'Hamburg',
        'items' => [],
        'stages' => [],
    ])->assertRedirect();

    expect($contact->fresh()->street)->toBe('Hafenstraße 12');
});

it('laesst eine abweichende Anschrift am Kontakt unberuehrt', function () {
    [$user, $project] = studioWithProject();

    $contact = Contact::factory()->create(['street' => 'Elbchaussee 88', 'postcode' => '22763', 'city' => 'Hamburg']);
    $project->attachContact($contact, ProjectContactRole::Primary);

    $quote = Quote::factory()->for($project)->create(['recipient_contact_id' => $contact->id]);

    $this->actingAs($user, 'staff')->patch("/angebote/{$quote->public_id}", [
        'pricing_mode' => 'classic',
        'recipient_contact' => $contact->public_id,
        'recipient_street' => 'Hafenstraße 12',
        'recipient_postcode' => '20459',
        'recipient_city' => 'Hamburg',
        'items' => [],
        'stages' => [],
    ])->assertRedirect();

    expect($contact->fresh()->street)->toBe('Elbchaussee 88')
        ->and($quote->fresh()->recipient_street)->toBe('Hafenstraße 12');
});

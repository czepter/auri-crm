<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Quotes\Actions\RenderQuotePdf;
use App\Quotes\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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

it('uebernimmt die Anschrift auf Knopfdruck in den Kontakt', function () {
    [$user, $project] = studioWithProject();

    $contact = Contact::factory()->create(['street' => 'Elbchaussee 88', 'postcode' => '22763', 'city' => 'Hamburg']);
    $project->attachContact($contact, ProjectContactRole::Primary);

    $quote = Quote::factory()->for($project)->create([
        'recipient_contact_id' => $contact->id,
        'recipient_street' => 'Hafenstraße 12',
        'recipient_postcode' => '20459',
        'recipient_city' => 'Hamburg',
    ]);

    $this->actingAs($user, 'staff')->post("/angebote/{$quote->public_id}/empfaenger-uebernehmen")->assertRedirect();

    expect($contact->fresh()->street)->toBe('Hafenstraße 12');
});

it('lehnt die Anschriftsuebernahme ohne Empfaengerkontakt ab', function () {
    [$user, $project] = studioWithProject();

    $quote = Quote::factory()->for($project)->create(['recipient_contact_id' => null]);

    $this->actingAs($user, 'staff')->post("/angebote/{$quote->public_id}/empfaenger-uebernehmen")->assertNotFound();
});

it('druckt die Anschrift des Angebots, nicht die des Kontakts', function () {
    [, $project] = studioWithProject();

    $contact = Contact::factory()->create(['name' => 'Ursula Meier', 'street' => 'Elbchaussee 88', 'postcode' => '22763', 'city' => 'Hamburg']);
    $project->attachContact($contact, ProjectContactRole::Payer);

    $quote = Quote::factory()->for($project)->create([
        'recipient_contact_id' => $contact->id,
        'recipient_street' => 'Hafenstraße 12',
        'recipient_postcode' => '20459',
        'recipient_city' => 'Hamburg',
    ]);

    $html = app(RenderQuotePdf::class)->html($quote);

    expect($html)->toContain('Ursula Meier')
        ->and($html)->toContain('Hafenstraße 12')
        ->and($html)->toContain('20459 Hamburg')
        ->and($html)->not->toContain('Elbchaussee 88');
});

it('behaelt den Empfaenger, wenn das Formular ihn nicht mitschickt', function () {
    [$user, $project] = studioWithProject();

    $contact = Contact::factory()->create(['street' => 'Elbchaussee 88', 'postcode' => '22763', 'city' => 'Hamburg']);
    $project->attachContact($contact, ProjectContactRole::Payer);

    $quote = Quote::factory()->for($project)->create([
        'recipient_contact_id' => $contact->id,
        'recipient_street' => 'Elbchaussee 88',
        'recipient_postcode' => '22763',
        'recipient_city' => 'Hamburg',
    ]);

    $this->actingAs($user, 'staff')
        ->patch("/angebote/{$quote->public_id}", ['pricing_mode' => 'classic', 'items' => [], 'stages' => []])
        ->assertRedirect();

    $quote->refresh();

    expect($quote->recipient_contact_id)->toBe($contact->id)
        ->and($quote->recipient_street)->toBe('Elbchaussee 88');
});

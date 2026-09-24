<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\Contact;
use App\Quotes\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rendert die Vorschau als HTML', function () {
    [$user, $project] = studioWithProject();
    $quote = Quote::factory()->for($project, 'case')->create(['time_title' => 'Hochzeitsreportage']);

    $this->actingAs($user, 'staff')
        ->post("/angebote/{$quote->public_id}/vorschau", ['intro' => 'Liebe Lena,'])
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=utf-8')
        ->assertSee('Liebe Lena,', escape: false);
});

it('haelt Fremde von der Vorschau fern', function () {
    [, $project] = studioWithProject();
    $quote = Quote::factory()->for($project, 'case')->create();

    $this->post("/angebote/{$quote->public_id}/vorschau")->assertRedirect('/login');
});

it('speichert beim Vorschau-Rendern nichts', function () {
    [$user, $project] = studioWithProject();
    $quote = Quote::factory()->for($project, 'case')->create(['intro' => 'Original']);
    $updatedAt = $quote->updated_at;

    $this->actingAs($user, 'staff')
        ->post("/angebote/{$quote->public_id}/vorschau", [
            'intro' => 'Geaendert',
            'items' => [['title' => 'Shooting', 'quantity' => 1, 'unit_price' => 10000]],
            'stages' => [['percent' => 100, 'label' => 'Gesamt']],
        ])
        ->assertOk();

    $quote->refresh();
    expect($quote->intro)->toBe('Original');
    expect($quote->items()->count())->toBe(0);
    expect($quote->paymentStages()->count())->toBe(0);
    expect($quote->updated_at)->toEqual($updatedAt);
});

it('zeigt Empfaenger und Anschrift im Blatt', function () {
    [$user, $project] = studioWithProject();
    $contact = Contact::factory()->create(['name' => 'Lena Meier']);
    $project->attachContact($contact, CaseContactRole::Payer);

    $quote = Quote::factory()->for($project, 'case')->create(['recipient_contact_id' => $contact->id]);

    $this->actingAs($user, 'staff')
        ->post("/angebote/{$quote->public_id}/vorschau", [
            'recipient_street' => 'Elbchaussee 88',
            'recipient_postcode' => '22763',
            'recipient_city' => 'Hamburg',
        ])
        ->assertOk()
        ->assertSee('Lena Meier')
        ->assertSee('Elbchaussee 88')
        ->assertSee('22763 Hamburg');
});

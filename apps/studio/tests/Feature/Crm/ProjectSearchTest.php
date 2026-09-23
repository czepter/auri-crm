<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withMfa()->create(), 'staff');
});

it('finds a project case-insensitively', function () {
    ClientCase::factory()->create(['name' => 'Basthorst 2026']);

    $response = $this->getJson('/projekte/suche?q=basth');

    $response->assertOk();
    expect($response->json('projects.0.name'))->toBe('Basthorst 2026');
});

it('suggests open leads and projects newest first below two characters', function () {
    $older = Lead::factory()->create();
    $older->case->forceFill(['updated_at' => now()->subDay()])->save();
    $newer = Lead::factory()->create();
    Lead::factory()->lost()->create();
    $completed = Lead::factory()->create();
    $completed->case->forceFill(['completed_at' => now()])->save();

    $ids = collect($this->getJson('/projekte/suche?q=b')->json('projects'))->pluck('id')->all();

    expect($ids)->toBe([$newer->case->public_id, $older->case->public_id]);
});

// Die drei Werte reisen mit dem Vorschlag, damit die Geburtskette im Formular
// vollständig ablaufen kann, ohne dass die Projektwahl einen zweiten Weg zum
// Server braucht.
it('carries the primary contact gallery overrides', function () {
    $contact = Contact::factory()->create([
        'portal_overrides' => ['gallery_downloads' => false],
    ]);
    $project = ClientCase::factory()->create(['name' => 'Basthorst 2026']);
    $project->attachContact($contact, CaseContactRole::Primary);

    $suggestion = $this->getJson('/projekte/suche?q=basth')->json('projects.0');

    expect($suggestion['contactName'])->toBe($contact->name)
        ->and($suggestion['galleryDefaults']['downloads'])->toBeFalse()
        ->and($suggestion['galleryDefaults']['lists'])->toBeNull();
});

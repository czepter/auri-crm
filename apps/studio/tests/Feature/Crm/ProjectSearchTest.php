<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withMfa()->create(), 'staff');
});

it('finds a project case-insensitively', function () {
    Project::factory()->create(['name' => 'Basthorst 2026']);

    $response = $this->getJson('/projekte/suche?q=basth');

    $response->assertOk();
    expect($response->json('projects.0.name'))->toBe('Basthorst 2026');
});

it('answers an empty list below two characters', function () {
    Project::factory()->create(['name' => 'Basthorst 2026']);

    expect($this->getJson('/projekte/suche?q=b')->json('projects'))->toBe([]);
});

// Die drei Werte reisen mit dem Vorschlag, damit die Geburtskette im Formular
// vollständig ablaufen kann, ohne dass die Projektwahl einen zweiten Weg zum
// Server braucht.
it('carries the primary contact gallery overrides', function () {
    $contact = Contact::factory()->create([
        'portal_overrides' => ['gallery_downloads' => false],
    ]);
    $project = Project::factory()->create(['name' => 'Basthorst 2026']);
    $project->attachContact($contact, ProjectContactRole::Primary);

    $suggestion = $this->getJson('/projekte/suche?q=basth')->json('projects.0');

    expect($suggestion['contactName'])->toBe($contact->name)
        ->and($suggestion['galleryDefaults']['downloads'])->toBeFalse()
        ->and($suggestion['galleryDefaults']['lists'])->toBeNull();
});

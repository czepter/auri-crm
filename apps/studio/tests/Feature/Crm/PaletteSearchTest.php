<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Gallery\Models\Gallery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withMfa()->create(), 'staff');
});

it('finds an inquiry and its contact by email fragment', function () {
    $contact = Contact::factory()->create(['name' => 'Annika Steffens', 'email' => 'a.steffens@posteo.de']);
    $project = Project::factory()->create(['name' => 'Hochzeit Juni']);
    $project->attachContact($contact, ProjectContactRole::Primary);

    $results = $this->getJson('/suche?q=POSTEO')->assertOk()->json('results');

    expect($results)->toBe([
        ['kind' => 'kontakt', 'title' => 'Annika Steffens', 'href' => "/kontakte/{$contact->public_id}"],
        ['kind' => 'anfrage', 'title' => 'Hochzeit Juni', 'href' => "/auftraege/{$project->public_id}"],
    ]);
});

it('finds a gallery by its project name', function () {
    $project = Project::factory()->create(['name' => 'Basthorst 2026']);
    $gallery = Gallery::factory()->for($project)->create(['title' => 'Highlights']);

    expect($this->getJson('/suche?q=basth')->json('results'))->toContain([
        'kind' => 'galerie',
        'title' => 'Highlights',
        'href' => "/galerien/{$gallery->public_id}",
    ]);
});

it('answers an empty list below two characters', function () {
    Contact::factory()->create(['name' => 'Annika Steffens']);

    expect($this->getJson('/suche?q=a')->json('results'))->toBe([]);
});

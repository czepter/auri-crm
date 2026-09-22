<?php

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

// Ein Artikel, den nur der Inhaber sehen darf. Als Datei statt als Fixture,
// weil HelpIndex das Verzeichnis liest und nichts anderes.
function gesperrterArtikel(): void
{
    file_put_contents(resource_path('help/gesperrte-probe.md'), <<<'MD'
        ---
        titel: Probe
        beschreibung: Probe
        art: aufgabe
        faehigkeit: manage_settings
        ---

        Rumpf.
        MD);
}

afterEach(fn () => @unlink(resource_path('help/gesperrte-probe.md')));

it('teilt den Artikel zum aktuellen Screen mit', function () {
    $user = User::factory()->withMfa()->create();

    $this->actingAs($user, 'staff')
        ->get('/anfragen')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('help.0.slug', 'lead-anlegen')
            ->where('help.0.art', 'aufgabe'));
});

it('liefert den Artikel als JSON', function () {
    $user = User::factory()->withMfa()->create();

    $this->actingAs($user, 'staff')
        ->getJson('/hilfe/artikel/lead-anlegen')
        ->assertOk()
        ->assertJsonPath('slug', 'lead-anlegen')
        ->assertJsonStructure(['slug', 'titel', 'art', 'html']);
});

it('antwortet 404 statt 403, wenn die Fähigkeit fehlt', function () {
    // Dieselbe Regel, die AdminLayout auf seine Navigation anwendet: ein
    // Eintrag, hinter dem für diesen Nutzer nichts steht, existiert für ihn
    // nicht.
    //
    // Gegen manage_settings geprüft, nicht gegen manage_leads: das hat ein
    // Fotograf laut StaffRole::capabilities() ebenfalls, der Test wäre grün,
    // ohne etwas zu prüfen.
    gesperrterArtikel();

    $user = User::factory()->withMfa()->create(['role' => StaffRole::Manager]);

    $this->actingAs($user, 'staff')
        ->get('/hilfe/gesperrte-probe')
        ->assertNotFound();
});

it('lässt einen Artikel ohne Fähigkeit aus der Suche weg', function () {
    gesperrterArtikel();

    $user = User::factory()->withMfa()->create(['role' => StaffRole::Manager]);

    $treffer = $this->actingAs($user, 'staff')->getJson('/hilfe/suche')->json();

    expect(collect($treffer)->pluck('slug'))->not->toContain('gesperrte-probe');
});

it('weist Gäste ab', function () {
    $this->get('/hilfe')->assertRedirect('/login');
});

it('rendert die Vollansicht mit beiden Ebenen', function () {
    $user = User::factory()->withMfa()->create();

    $this->actingAs($user, 'staff')
        ->get('/hilfe')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Help/Index')
            ->has('aufgaben')
            ->has('referenz'));
});

it('zeigt auf der Hilfeseite selbst keine Hilfespalte', function () {
    // Ein Artikel, der erklärt, was der Leser gerade offensichtlich bedient.
    $user = User::factory()->withMfa()->create();

    $this->actingAs($user, 'staff')
        ->get('/hilfe')
        ->assertInertia(fn (Assert $page) => $page->where('help', []));
});

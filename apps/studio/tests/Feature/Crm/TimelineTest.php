<?php

use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Models\TimelineEntry;
use App\Enums\StaffRole;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// staffMember() lives in tests/Pest.php — see the note there.

it('orders the timeline by time regardless of creation order, breaking ties by sort_order', function () {
    $shoot = Shoot::factory()->create();
    TimelineEntry::factory()->for($shoot)->at('16:00')->create(['title' => 'Empfang']);
    TimelineEntry::factory()->for($shoot)->at('09:00')->create(['title' => 'Antritt']);
    TimelineEntry::factory()->for($shoot)->at('09:00')->create(['title' => 'Zweiter um 9', 'sort_order' => 1]);

    expect($shoot->timeline->pluck('title')->all())->toBe(['Antritt', 'Zweiter um 9', 'Empfang']);
});

it('adds a timeline entry to a shoot', function () {
    $user = staffMember(StaffRole::Owner);
    $shoot = Shoot::factory()->create();

    $this->actingAs($user, 'staff')->post("/shootings/{$shoot->public_id}/ablauf", [
        'time' => '09:00',
        'title' => 'Getting Ready — Braut',
        'location' => 'Hotel Louis C. Jacob · Suite 12',
        'note' => 'Ringe & Kleid vorab',
    ])->assertRedirect();

    $entry = TimelineEntry::sole();
    expect($entry->shoot_id)->toBe($shoot->id)
        ->and($entry->title)->toBe('Getting Ready — Braut')
        ->and($entry->location)->toBe('Hotel Louis C. Jacob · Suite 12')
        ->and($entry->is_transit)->toBeFalse();
});

it('adds a transit entry with no location', function () {
    $user = staffMember(StaffRole::Owner);
    $shoot = Shoot::factory()->create();

    $this->actingAs($user, 'staff')->post("/shootings/{$shoot->public_id}/ablauf", [
        'time' => '11:45',
        'title' => 'Fahrt · 25 Min.',
        'is_transit' => true,
    ])->assertRedirect();

    expect(TimelineEntry::sole()->is_transit)->toBeTrue();
});

it('refuses a role with no manageShoots capability', function () {
    $user = staffMember(StaffRole::SecondShooter);
    $shoot = Shoot::factory()->create();

    $this->actingAs($user, 'staff')->post("/shootings/{$shoot->public_id}/ablauf", [
        'time' => '09:00',
        'title' => 'Antritt',
    ])->assertForbidden();

    expect(TimelineEntry::count())->toBe(0);
});

it('deletes a timeline entry', function () {
    $user = staffMember(StaffRole::Owner);
    $entry = TimelineEntry::factory()->create();

    $this->actingAs($user, 'staff')->delete("/ablauf/{$entry->public_id}")->assertRedirect();

    expect(TimelineEntry::count())->toBe(0);
});

it('exports the timeline as a PDF through Gotenberg', function () {
    Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-fake-bytes', 200)]);

    $user = staffMember(StaffRole::Owner);
    $project = Project::factory()->create(['name' => 'Meier & Kowalski']);
    $shoot = Shoot::factory()->for($project)->on('2026-06-14')->create(['label' => 'Hochzeitstag']);
    TimelineEntry::factory()->for($shoot)->at('09:00')->create(['title' => 'Getting Ready']);

    $response = $this->actingAs($user, 'staff')->get("/shootings/{$shoot->public_id}/ablauf.pdf");

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', "attachment; filename=\"ablauf-{$shoot->public_id}.pdf\"");

    expect($response->getContent())->toBe('%PDF-fake-bytes');

    Http::assertSent(function (HttpRequest $request) {
        $body = (string) $request->body();

        return str_contains($body, 'Getting Ready') && str_contains($body, 'Meier &amp; Kowalski');
    });
});

it('refuses to export a PDF for a shoot with no date', function () {
    $user = staffMember(StaffRole::Owner);
    $shoot = Shoot::factory()->create(['date' => null]);

    $this->actingAs($user, 'staff')->get("/shootings/{$shoot->public_id}/ablauf.pdf")->assertNotFound();
});

it('exports the timeline as an ICS feed with one VEVENT per entry', function () {
    $user = staffMember(StaffRole::Owner);
    $project = Project::factory()->create(['name' => 'Meier & Kowalski']);
    $shoot = Shoot::factory()->for($project)->on('2026-06-14')->create(['label' => 'Hochzeitstag']);
    TimelineEntry::factory()->for($shoot)->at('09:00')->create([
        'title' => 'Getting Ready — Braut',
        'location' => 'Hotel Louis C. Jacob',
    ]);
    TimelineEntry::factory()->for($shoot)->at('12:30')->create(['title' => 'First Look']);

    $response = $this->actingAs($user, 'staff')->get("/shootings/{$shoot->public_id}/ablauf.ics");

    $response->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8');

    $body = $response->getContent();

    expect($body)->toStartWith("BEGIN:VCALENDAR\r\n")
        ->and($body)->toContain('END:VCALENDAR')
        ->and(substr_count($body, 'BEGIN:VEVENT'))->toBe(2)
        // 09:00 Berlin in June is 07:00Z, and the first entry runs until the
        // second one starts rather than a fixed length of its own.
        ->and($body)->toContain('DTSTART:20260614T070000Z')
        ->and($body)->toContain('DTEND:20260614T103000Z')
        ->and($body)->toContain('SUMMARY:Getting Ready — Braut')
        ->and($body)->toContain('LOCATION:Hotel Louis C. Jacob');
});

it('gives the last entry the configured default duration', function () {
    $user = staffMember(StaffRole::Owner);
    $shoot = Shoot::factory()->on('2026-06-14')->create();
    TimelineEntry::factory()->for($shoot)->at('20:00')->create(['title' => 'Eröffnungstanz']);

    (new StudioSettingsResolver)->set('default_shoot_duration_minutes', 90);

    $body = $this->actingAs($user, 'staff')->get("/shootings/{$shoot->public_id}/ablauf.ics")->getContent();

    // 20:00 + 90 minutes = 21:30 Berlin time, 19:30Z in June.
    expect($body)->toContain('DTSTART:20260614T180000Z')
        ->and($body)->toContain('DTEND:20260614T193000Z');
});

it('refuses to export an ICS feed for a shoot with no date', function () {
    $user = staffMember(StaffRole::Owner);
    $shoot = Shoot::factory()->create(['date' => null]);

    $this->actingAs($user, 'staff')->get("/shootings/{$shoot->public_id}/ablauf.ics")->assertNotFound();
});

it('escapes a value that would otherwise break the ICS format', function () {
    $user = staffMember(StaffRole::Owner);
    $shoot = Shoot::factory()->on('2026-06-14')->create();
    TimelineEntry::factory()->for($shoot)->at('09:00')->create(['title' => 'Halt; Gruppe, hier']);

    $body = $this->actingAs($user, 'staff')->get("/shootings/{$shoot->public_id}/ablauf.ics")->getContent();

    expect($body)->toContain('Halt\; Gruppe\, hier');
});

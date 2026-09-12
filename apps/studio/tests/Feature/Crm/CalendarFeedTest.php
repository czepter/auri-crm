<?php

use App\Crm\Models\CrewAssignment;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Models\Absence;
use App\Models\User;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function feedFor(User $user): string
{
    return test()->get("/kalender/{$user->calendarToken()}/shootings.ics")
        ->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
        ->streamedContent() ?: '';
}

it('serves a subscribable calendar without a session', function () {
    // The whole point: a calendar app cannot log in.
    $user = User::factory()->create();

    $body = $this->get("/kalender/{$user->calendarToken()}/shootings.ics")->assertOk()->getContent();

    expect($body)->toStartWith("BEGIN:VCALENDAR\r\n")
        ->and($body)->toContain('END:VCALENDAR');
});

it('carries the crew member assigned shoots with their call times', function () {
    // The story's acceptance, minus the phone.
    $user = User::factory()->create(['name' => 'Nina Sturm']);
    $project = Project::factory()->create(['name' => 'Meier & Kowalski']);
    $shoot = Shoot::factory()->for($project)->on('2026-06-14')->create([
        'label' => 'Hochzeitstag',
        'call_time' => '09:00',
        'end_time' => '23:00',
        'venue_name' => 'Gut Basthorst',
    ]);
    CrewAssignment::factory()->for($shoot)->for($user)->create();

    $body = $this->get("/kalender/{$user->calendarToken()}/shootings.ics")->getContent();

    // 09:00 in Berlin is 07:00Z in June — a phone in another country still
    // shows the correct local call time.
    expect($body)->toContain('DTSTART:20260614T070000Z')
        ->and($body)->toContain('DTEND:20260614T210000Z')
        ->and($body)->toContain('SUMMARY:Hochzeitstag · Meier & Kowalski')
        ->and($body)->toContain('LOCATION:Gut Basthorst')
        ->and($body)->toContain("UID:{$shoot->public_id}@".parse_url(config('app.url'), PHP_URL_HOST));
});

it('shows a shoot with no call time as an all-day event', function () {
    // Honest for "that day, time not settled yet", and it avoids inventing a
    // start of midnight.
    $user = User::factory()->create();
    $shoot = Shoot::factory()->on('2026-07-05')->create(['call_time' => null]);
    CrewAssignment::factory()->for($shoot)->for($user)->create();

    $body = $this->get("/kalender/{$user->calendarToken()}/shootings.ics")->getContent();

    expect($body)->toContain('DTSTART;VALUE=DATE:20260705')
        ->and($body)->toContain('DTEND;VALUE=DATE:20260706');
});

it('carries only the shoots this person is assigned to', function () {
    $mine = User::factory()->create();
    $theirs = User::factory()->create();

    $ours = Shoot::factory()->on('2026-06-14')->create(['label' => 'Meins']);
    CrewAssignment::factory()->for($ours)->for($mine)->create();

    $other = Shoot::factory()->on('2026-06-20')->create(['label' => 'Fremd']);
    CrewAssignment::factory()->for($other)->for($theirs)->create();

    $body = $this->get("/kalender/{$mine->calendarToken()}/shootings.ics")->getContent();

    expect($body)->toContain('Meins')
        ->and($body)->not->toContain('Fremd');
});

it('refuses an unknown token', function () {
    $this->get('/kalender/'.str_repeat('a', 64).'/shootings.ics')->assertNotFound();
});

it('stops serving a revoked token', function () {
    $user = User::factory()->create();
    $old = $user->calendarToken();

    $user->regenerateCalendarToken();

    $this->get("/kalender/{$old}/shootings.ics")->assertNotFound();
    $this->get("/kalender/{$user->fresh()->calendarToken()}/shootings.ics")->assertOk();
});

it('mints no token until one is asked for', function () {
    expect(User::factory()->create()->calendar_token)->toBeNull();
});

it('never lets the token ride along in a serialised user', function () {
    $user = User::factory()->create();
    $user->calendarToken();

    expect($user->fresh()->toArray())->not->toHaveKey('calendar_token');
});

it('escapes a value that would otherwise break the format', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['name' => 'Meier; Kowalski, und mehr']);
    $shoot = Shoot::factory()->for($project)->on('2026-06-14')->create(['label' => 'Tag']);
    CrewAssignment::factory()->for($shoot)->for($user)->create();

    $body = $this->get("/kalender/{$user->calendarToken()}/shootings.ics")->getContent();

    expect($body)->toContain('Meier\; Kowalski\, und mehr');
});

it('folds a long line without splitting a character in half', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['name' => str_repeat('Grüße für Späthochzeiten ', 6)]);
    $shoot = Shoot::factory()->for($project)->on('2026-06-14')->create();
    CrewAssignment::factory()->for($shoot)->for($user)->create();

    $body = $this->get("/kalender/{$user->calendarToken()}/shootings.ics")->getContent();

    foreach (explode("\r\n", $body) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(75);
    }

    // Still valid UTF-8 once the continuation markers are removed.
    expect(mb_check_encoding(str_replace("\r\n ", '', $body), 'UTF-8'))->toBeTrue();
});

it('ends a shoot with no end_time at the configured default duration', function () {
    // T-09: retires the hardcoded addHours(2), which this default (120)
    // reproduces exactly — every studio keeps today's behaviour until it
    // changes the setting.
    $user = User::factory()->create();
    $shoot = Shoot::factory()->on('2026-06-14')->create(['call_time' => '09:00', 'end_time' => null]);
    CrewAssignment::factory()->for($shoot)->for($user)->create();

    $body = $this->get("/kalender/{$user->calendarToken()}/shootings.ics")->getContent();

    expect($body)->toContain('DTSTART:20260614T070000Z')
        ->and($body)->toContain('DTEND:20260614T090000Z');
});

it('follows a changed default shoot duration setting', function () {
    (new StudioSettingsResolver)->set('default_shoot_duration_minutes', 90);

    $user = User::factory()->create();
    $shoot = Shoot::factory()->on('2026-06-14')->create(['call_time' => '09:00', 'end_time' => null]);
    CrewAssignment::factory()->for($shoot)->for($user)->create();

    $body = $this->get("/kalender/{$user->calendarToken()}/shootings.ics")->getContent();

    // 09:00 + 90 minutes = 10:30 Berlin time, 08:30Z in June.
    expect($body)->toContain('DTSTART:20260614T070000Z')
        ->and($body)->toContain('DTEND:20260614T083000Z');
});

/**
 * T-10's portal-isolation requirement extended to this feed: it is not the
 * client portal, but its URL is a bare token in the path, so it gets the
 * same proof — seed an absence for the very person whose feed this is, on a
 * shoot inside it, and show the category and date range never turn up.
 */
it('never lets an absence category or date reach this feed, even for the person it belongs to', function () {
    $user = User::factory()->create(['name' => 'Nina Sturm']);
    $shoot = Shoot::factory()->on('2026-09-03')->create();
    CrewAssignment::factory()->for($shoot)->for($user)->create();
    Absence::factory()->for($user)->spanning('2026-09-01', '2026-09-07')->create(['category' => 'holiday']);

    $body = $this->get("/kalender/{$user->calendarToken()}/shootings.ics")->getContent();

    expect($body)->not->toContain('holiday')
        ->not->toContain('Urlaub')
        ->not->toContain('2026-09-01')
        ->not->toContain('2026-09-07');
});

it('ends a shoot that runs past midnight on the next day', function () {
    $user = User::factory()->create();
    $shoot = Shoot::factory()->on('2026-06-14')->create(['call_time' => '21:00', 'end_time' => '02:00']);
    CrewAssignment::factory()->for($shoot)->for($user)->create();

    $body = $this->get("/kalender/{$user->calendarToken()}/shootings.ics")->getContent();

    expect($body)->toContain('DTSTART:20260614T190000Z')
        ->and($body)->toContain('DTEND:20260615T000000Z');
});

it('carries every dated shoot when the studio has only one person', function () {
    $owner = User::factory()->create();
    Shoot::factory()->on('2026-06-14')->create(['label' => 'Unbesetzt']);

    $body = $this->get("/kalender/{$owner->calendarToken()}/shootings.ics")->getContent();

    expect($body)->toContain('Unbesetzt');
});

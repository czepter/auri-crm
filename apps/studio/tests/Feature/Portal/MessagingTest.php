<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Engagement\Models\Message;
use App\Engagement\Models\Thread;
use App\Settings\Models\StudioSettings;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

// T-08's "messaging" switch defaults to off (see
// tests/Feature/Portal/CapabilitySwitchesTest.php for that default and the
// 404 it produces while off) — every test below exercises the thread itself,
// so the switch is on for all of them. Cache::flush() first: the resolver
// caches the row (T-01), and a row already cached by an earlier test in the
// same suite run would otherwise still read as off.
beforeEach(function () {
    useSurface(Surface::PORTAL);
    Cache::store('redis')->flush();
    StudioSettings::query()->update(['portal_messaging_enabled' => true]);
});

it('shows no thread yet when the client has not started one', function () {
    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/nachrichten")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['thread'])->toBeNull();
});

// E-06's own acceptance criterion.
it('lets a client start a thread from the portal', function () {
    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create(['name' => 'Lena Meier']);
    $project->attachContact($contact, ProjectContactRole::Primary);

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/nachrichten", [
            'subject' => 'Frage zum Ablauf',
            'body' => 'Dürfen wir während des Ringtauschs nah an den Altar?',
        ])
        ->assertRedirect();

    $thread = Thread::where('project_id', $project->id)->sole();

    expect($thread->subject)->toBe('Frage zum Ablauf')
        ->and($thread->messages)->toHaveCount(1)
        ->and($thread->messages->first()->contact->is($contact))->toBeTrue();
});

it('lets the client reply on the thread they already started, without a subject', function () {
    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/nachrichten", ['subject' => 'Thema', 'body' => 'Erste Nachricht.'])
        ->assertRedirect();

    $this->post("/projekte/{$project->public_id}/nachrichten", ['body' => 'Zweite Nachricht.'])
        ->assertRedirect();

    expect(Thread::where('project_id', $project->id)->count())->toBe(1)
        ->and(Message::count())->toBe(2);
});

it('requires a subject to start a thread but never to reply on one', function () {
    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/nachrichten", ['body' => 'Ohne Thema.'])
        ->assertSessionHasErrors('subject');

    expect(Thread::count())->toBe(0);
});

it('refuses a project the contact is not on, as a 404 rather than a 403', function () {
    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();

    $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/nachrichten")
        ->assertNotFound();
});

it('refuses a project the studio has not made portal-visible', function () {
    $project = Project::factory()->create(['portal_visible' => false]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/nachrichten")
        ->assertNotFound();
});

it('shows the studio\'s reply beside the client\'s own message', function () {
    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);
    $staff = App\Models\User::factory()->create(['name' => 'Christian']);

    $thread = app(App\Engagement\Actions\StartThread::class)($project, $contact, 'Thema', 'Erste Nachricht.');
    app(App\Engagement\Actions\PostMessage::class)($thread, $staff, 'Antwort vom Studio.');

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/nachrichten")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['thread']['messages'])->toHaveCount(2)
        ->and($props['thread']['messages'][1]['author'])->toBe('Christian')
        ->and($props['thread']['messages'][1]['fromStaff'])->toBeTrue();
});

<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Enums\ShootStatus;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Crm\Models\Shoot;
use App\Engagement\Models\Thread;
use App\Models\AuditLog;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::PORTAL));

/** An inquiry: one requested shoot, no job, nothing signed. */
function inquiryProject(Contact $contact): ClientCase
{
    $project = ClientCase::factory()->create(['portal_visible' => true, 'name' => 'Viola & Chris']);
    $project->attachContact($contact, CaseContactRole::Primary);
    Shoot::factory()->for($project, 'case')->on('2026-06-14')->create([
        'is_primary' => true,
        'status' => ShootStatus::Requested,
        'venue_name' => 'Gut Basthorst',
        'venue_city' => 'Basthorst',
    ]);
    Lead::factory()->for($project, 'case')->create(['inquiry_text' => 'Freie Trauung im Park.']);

    return $project;
}

// P-03: before anything is reserved, the details belong to the client.
it('lets the client correct the date, the place and their own words', function () {
    $contact = Contact::factory()->create();
    $project = inquiryProject($contact);

    test()->actingAs($contact, 'portal')
        ->patch("/projekte/{$project->public_id}/angaben", [
            'date' => '2026-06-21',
            'venue_name' => 'Gut Basthorst',
            'venue_city' => 'Basthorst',
            'inquiry_text' => 'Freie Trauung am See.',
        ])
        ->assertRedirect();

    $shoot = $project->primaryShoot()->first();

    expect($shoot->date->format('Y-m-d'))->toBe('2026-06-21')
        ->and($project->lead->fresh()->inquiry_text)->toBe('Freie Trauung am See.');
});

// A silent change to a wedding date is how a photographer turns up on the
// wrong Saturday.
it('writes the change into the conversation and into the audit log', function () {
    $contact = Contact::factory()->create();
    $project = inquiryProject($contact);

    test()->actingAs($contact, 'portal')
        ->patch("/projekte/{$project->public_id}/angaben", [
            'date' => '2026-06-21',
            'venue_name' => 'Gut Basthorst',
            'venue_city' => 'Basthorst',
            'inquiry_text' => 'Freie Trauung im Park.',
        ]);

    $thread = Thread::where('case_id', $project->id)->sole();

    expect($thread->messages)->toHaveCount(1)
        ->and($thread->messages->first()->body)->toContain('21.06.2026')
        ->and(AuditLog::where('event', 'portal.project.details_updated')->count())->toBe(1);
});

it('says nothing at all when nothing actually changed', function () {
    $contact = Contact::factory()->create();
    $project = inquiryProject($contact);

    test()->actingAs($contact, 'portal')
        ->patch("/projekte/{$project->public_id}/angaben", [
            'date' => '2026-06-14',
            'venue_name' => 'Gut Basthorst',
            'venue_city' => 'Basthorst',
            'inquiry_text' => 'Freie Trauung im Park.',
        ]);

    expect(Thread::count())->toBe(0)
        ->and(AuditLog::where('event', 'portal.project.details_updated')->count())->toBe(0);
});

// From `gebucht` the date and the place are contract content.
it('refuses the write once the booking exists, as a 404', function () {
    $contact = Contact::factory()->create();
    $project = inquiryProject($contact);
    markBooked($project);

    test()->actingAs($contact, 'portal')
        ->patch("/projekte/{$project->public_id}/angaben", ['date' => '2026-06-21'])
        ->assertNotFound();

    expect($project->primaryShoot()->first()->date->format('Y-m-d'))->toBe('2026-06-14');
});

it('refuses the write on a booking the contact is not on', function () {
    $project = inquiryProject(Contact::factory()->create());

    test()->actingAs(Contact::factory()->create(), 'portal')
        ->patch("/projekte/{$project->public_id}/angaben", ['date' => '2026-06-21'])
        ->assertNotFound();
});

it('renders the letter while it is an inquiry and the booked screen once it is not', function () {
    $contact = Contact::factory()->create();
    $project = inquiryProject($contact);

    $page = test()->actingAs($contact, 'portal')->get("/projekte/{$project->public_id}")
        ->assertOk()->viewData('page');

    expect($page['component'])->toBe('Projects/Inquiry');

    markBooked($project);

    $page = test()->actingAs($contact, 'portal')->get("/projekte/{$project->public_id}")
        ->assertOk()->viewData('page');

    expect($page['component'])->toBe('Projects/Show');
});

it('closes the letter as soon as a shoot moves past requested, without a job', function () {
    $contact = Contact::factory()->create();
    $project = inquiryProject($contact);
    $project->primaryShoot()->first()->update(['status' => ShootStatus::Booked]);

    expect($project->fresh()->clientMayEditDetails())->toBeFalse();
});

it('names the studio on both shapes of the booking screen', function () {
    App\Settings\Models\StudioSettings::query()->update(['display_name' => 'Nordlicht Fotografie']);
    Illuminate\Support\Facades\Cache::store('redis')->flush();

    $contact = Contact::factory()->create();
    $project = inquiryProject($contact);

    $props = test()->actingAs($contact, 'portal')->get("/projekte/{$project->public_id}")
        ->assertOk()->viewData('page')['props'];

    expect($props['studio']['name'])->toBe('Nordlicht Fotografie');

    markBooked($project);

    $props = test()->actingAs($contact, 'portal')->get("/projekte/{$project->public_id}")
        ->assertOk()->viewData('page')['props'];

    expect($props['studio']['name'])->toBe('Nordlicht Fotografie');
});

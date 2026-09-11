<?php

use App\Crm\Models\Project;
use App\Crm\Support\ProjectActivity;
use App\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns a project\'s events newest first', function () {
    $project = Project::factory()->create();

    AuditLogger::record('quote.sent', null, [], null, $project->id);
    $this->travel(1)->hours();
    AuditLogger::record('contract.signed', null, [], null, $project->id);

    $activity = ProjectActivity::for($project);

    expect($activity)->toHaveCount(2)
        ->and($activity[0]['event'])->toBe('contract.signed');
});

it('dates a backdated event when it happened, not when it was typed', function () {
    $this->freezeTime();
    $project = Project::factory()->create();

    AuditLogger::record('lead.lost', null, [], null, $project->id, occurredAt: now()->subWeek());

    expect(ProjectActivity::for($project)[0]['at'])->toBe(now()->subWeek()->toIso8601String());
});

it('names the status a shoot moved to', function () {
    $project = Project::factory()->create();

    AuditLogger::record('shoot.status_changed', null, ['from' => 'booked', 'to' => 'editing'], null, $project->id);

    expect(ProjectActivity::for($project)[0]['label'])->toBe('Shooting-Status: Bearbeitung');
});

it('names the phase a lead moved to', function () {
    $project = Project::factory()->create();

    AuditLogger::record('lead.stage_changed', null, ['from' => 'new', 'to' => 'quote_sent'], null, $project->id);

    expect(ProjectActivity::for($project)[0]['label'])->toBe('Phase: Angebot');
});

it('drops an event with no label rather than showing its key', function () {
    $project = Project::factory()->create();

    AuditLogger::record('quote.sent', null, [], null, $project->id);
    AuditLogger::record('some.internal.plumbing', null, [], null, $project->id);

    expect(ProjectActivity::for($project))->toHaveCount(1);
});

it('excludes events belonging to another project', function () {
    $project = Project::factory()->create();
    $other = Project::factory()->create();

    AuditLogger::record('quote.sent', null, [], null, $other->id);

    expect(ProjectActivity::for($project))->toBeEmpty();
});

it('never exposes the request metadata an audit row carries', function () {
    $project = Project::factory()->create();

    AuditLogger::record('quote.sent', staffMember(), ['contact' => 'c_secret'], null, $project->id);

    expect(array_keys(ProjectActivity::for($project)[0]))
        ->toBe(['event', 'label', 'at', 'actorName']);
});

/**
 * The unit test above covers the presenter's own output. This one covers
 * the same whitelist at the HTTP layer, where a later change to the
 * controller — a ->with(), a resource wrapper, a debug prop — could
 * reintroduce ip_address/user_agent/context without ever touching
 * ProjectActivity itself.
 */
it('never ships request metadata in the admin project payload', function () {
    $project = Project::factory()->create();
    AuditLogger::record('quote.sent', staffMember(), ['contact' => 'c_secret'], null, $project->id);

    $this->actingAs(staffMember(), 'staff');
    $thread = collect(props("/auftraege/{$project->public_id}")['thread'])
        ->filter(fn ($entry) => $entry['system'])
        ->values();

    // Proves the payload actually carries the event before asserting what's
    // absent from it — an empty trace would satisfy the assertions below for
    // the wrong reason.
    expect($thread)->toHaveCount(1)
        ->and($thread[0]['body'])->toBe(__('activity.quote.sent'));

    $payload = json_encode($thread);

    expect($payload)
        ->not->toContain('ip_address')
        ->not->toContain('user_agent')
        ->not->toContain('c_secret');
});

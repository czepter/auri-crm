<?php

use App\Crm\Models\Project;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('records an event with actor and context', function () {
    $user = User::factory()->create();

    $log = AuditLogger::record('staff.login.succeeded', $user, ['method' => 'password']);

    expect($log->event)->toBe('staff.login.succeeded')
        ->and($log->actor_id)->toBe($user->id)
        ->and($log->context)->toBe(['method' => 'password']);
});

it('records an event without an actor', function () {
    $log = AuditLogger::record('staff.login.failed', null, ['email_hash' => 'abc']);

    expect($log->actor_id)->toBeNull()
        ->and($log->event)->toBe('staff.login.failed');
});

it('refuses to update an existing entry', function () {
    $log = AuditLogger::record('staff.logout', null);

    expect(fn () => $log->update(['event' => 'tampered']))
        ->toThrow(RuntimeException::class, 'Audit log entries are append-only.');
});

it('refuses to delete an existing entry', function () {
    $log = AuditLogger::record('staff.logout', null);

    expect(fn () => $log->delete())
        ->toThrow(RuntimeException::class, 'Audit log entries are append-only.');
});

it('never stores raw credentials in context', function () {
    $log = AuditLogger::record('staff.login.failed', null, [
        'password' => 'hunter2',
        'code' => '123456',
        'email' => 'someone@example.com',
    ]);

    expect($log->context)->not->toHaveKey('password')
        ->and($log->context)->not->toHaveKey('code')
        ->and($log->context['email'])->toBe('[redacted]');
});

it('records the project an event belongs to', function () {
    $project = Project::factory()->create();

    $log = AuditLogger::record('quote.sent', null, [], 'q_123', $project->id);

    expect($log->project_id)->toBe($project->id);
});

it('leaves project_id null for events that belong to no project', function () {
    $log = AuditLogger::record('staff.login.succeeded', staffMember());

    expect($log->project_id)->toBeNull();
});

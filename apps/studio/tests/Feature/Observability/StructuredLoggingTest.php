<?php

use App\Support\RequestId;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

it('writes request id, surface, tenant id and the actor\'s public id onto every observability log line', function () {
    $path = storage_path('logs/observability-test-'.Str::random(8).'.log');
    config(['logging.channels.observability.with.stream' => $path]);

    $staff = staffMember();
    $this->actingAs($staff, 'staff');

    RequestId::set('req-structured-log-test');

    Log::channel('observability')->info('F-11 structured logging smoke test');

    $lines = array_values(array_filter(explode("\n", file_get_contents($path))));
    $record = json_decode(end($lines), true);

    expect($record['extra']['request_id'])->toBe('req-structured-log-test')
        ->and($record['extra']['surface'])->toBe('admin')
        ->and($record['extra']['tenant_id'])->toBe(config('tenancy.tenant_id'))
        ->and($record['extra']['actor_public_id'])->toBe($staff->public_id);

    // Never the bigint primary key — the same rule every JSON response in
    // this app already follows (App\Concerns\HasPublicId).
    expect($record['extra']['actor_public_id'])->not->toBe($staff->id);

    @unlink($path);
});

it('carries no actor when nobody is authenticated', function () {
    $path = storage_path('logs/observability-test-'.Str::random(8).'.log');
    config(['logging.channels.observability.with.stream' => $path]);

    RequestId::set('req-anonymous');

    Log::channel('observability')->info('F-11 anonymous line');

    $lines = array_values(array_filter(explode("\n", file_get_contents($path))));
    $record = json_decode(end($lines), true);

    expect($record['extra'])->not->toHaveKey('actor_public_id')
        ->and($record['extra']['request_id'])->toBe('req-anonymous');

    @unlink($path);
});

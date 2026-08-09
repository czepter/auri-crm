<?php

use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Settings\Jobs\ReadStudioSettingsJob;
use App\Settings\Models\StudioSettings;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// staffMember() lives in tests/Pest.php — see the note there.

beforeEach(function () {
    // The redis store is shared infrastructure, not per-test state — clear
    // whatever a previous test (or a previous run against the same Compose
    // stack) left behind, or a stale cached row could make a test pass for
    // the wrong reason.
    Cache::store('redis')->flush();
});

it('seeds exactly one settings row per tenant from the migration itself', function () {
    expect(StudioSettings::query()->count())->toBe(1)
        ->and(StudioSettings::query()->sole()->display_name)->toBeNull();
});

it('resolves the seeded row through a plain, container-constructed instance', function () {
    // No binding, no facade — the resolver is autowired the same way
    // GotenbergClient and every other plain support class in this codebase
    // is, so a controller only has to type-hint it.
    $resolver = app(StudioSettingsResolver::class);

    expect($resolver->displayName())->toBeNull()
        ->and($resolver->publicId())->toBe(StudioSettings::query()->sole()->public_id);
});

it('makes a write visible to a completely fresh resolver instance, with no cache-clear command', function () {
    $owner = staffMember(StaffRole::Owner);

    // Warm the cache the way a long-running worker process would — reading
    // once before anything changes.
    $warm = new StudioSettingsResolver;
    expect($warm->displayName())->toBeNull();

    // Written through a second, unrelated instance — the web process.
    (new StudioSettingsResolver)->set('display_name', 'Atelier Nordlicht', $owner);

    // Read through a third instance holding nothing the write above touched
    // directly, the same shape a Horizon worker's own container resolution
    // hands the very next job it pops. A naive array or file cache would
    // still pass this line inside one PHP process; the redis store is what
    // makes it mean something.
    expect((new StudioSettingsResolver)->displayName())->toBe('Atelier Nordlicht');
});

it('lets a job popped by a real queue worker read a setting the web process changed', function () {
    $owner = staffMember(StaffRole::Owner);

    (new StudioSettingsResolver)->set('display_name', 'Atelier Nordlicht', $owner);

    // A real Illuminate\Queue\Worker popping a real queued job — the same
    // "boots real infra" tier QueueRuntimeTest's FailingJob test uses,
    // rather than a sync dispatch that never leaves the current call stack.
    ReadStudioSettingsJob::dispatch()->onConnection('database')->onQueue('settings-runtime-test');

    Artisan::call('queue:work', [
        'connection' => 'database',
        '--queue' => 'settings-runtime-test',
        '--once' => true,
        '--tries' => 1,
    ]);

    expect(DB::table('failed_jobs')->count())->toBe(0)
        ->and(Cache::store('redis')->get(ReadStudioSettingsJob::CACHE_KEY))->toBe('Atelier Nordlicht');
});

it('writes an audit row naming the actor, the key and both values', function () {
    $owner = staffMember(StaffRole::Owner);

    (new StudioSettingsResolver)->set('display_name', 'Atelier Nordlicht', $owner);

    $log = AuditLog::where('event', 'studio_settings.updated')->sole();

    expect($log->actor_id)->toBe($owner->id)
        ->and($log->context['key'])->toBe('display_name')
        ->and($log->context['old'])->toBeNull()
        ->and($log->context['new'])->toBe('Atelier Nordlicht');
});

it('leaves no audit row for a write that changes nothing', function () {
    $owner = staffMember(StaffRole::Owner);
    $resolver = new StudioSettingsResolver;

    $resolver->set('display_name', null, $owner);

    expect(AuditLog::where('event', 'studio_settings.updated')->count())->toBe(0);
});

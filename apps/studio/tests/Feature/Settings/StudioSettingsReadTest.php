<?php

use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\StudioSettings;
use App\Settings\Actions\StoreStudioLogo;
use App\Settings\Actions\UpdateStudioSetting;
use App\Settings\Jobs\ReadStudioSettingsJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

    expect(StudioSettings::current()->display_name)->toBeNull()
        ->and(StudioSettings::current()->public_id)->toBe(StudioSettings::query()->sole()->public_id);
});

it('makes a write visible to a completely fresh resolver instance, with no cache-clear command', function () {
    $owner = staffMember(StaffRole::Admin);

    // Warm the cache the way a long-running worker process would — reading
    // once before anything changes.
    expect(StudioSettings::current()->display_name)->toBeNull();

    // Written through a second, unrelated instance — the web process.
    app(UpdateStudioSetting::class)('display_name', 'Atelier Nordlicht', $owner);

    // Read through a third instance holding nothing the write above touched
    // directly, the same shape a Horizon worker's own container resolution
    // hands the very next job it pops. A naive array or file cache would
    // still pass this line inside one PHP process; the redis store is what
    // makes it mean something.
    expect(StudioSettings::current()->display_name)->toBe('Atelier Nordlicht');
});

it('lets a job popped by a real queue worker read a setting the web process changed', function () {
    $owner = staffMember(StaffRole::Admin);

    app(UpdateStudioSetting::class)('display_name', 'Atelier Nordlicht', $owner);

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
    $owner = staffMember(StaffRole::Admin);

    app(UpdateStudioSetting::class)('display_name', 'Atelier Nordlicht', $owner);

    $log = AuditLog::where('event', 'studio_settings.updated')->sole();

    expect($log->actor_id)->toBe($owner->id)
        ->and($log->context['key'])->toBe('display_name')
        ->and($log->context['old'])->toBeNull()
        ->and($log->context['new'])->toBe('Atelier Nordlicht');
});

it('leaves no audit row for a write that changes nothing', function () {
    $owner = staffMember(StaffRole::Admin);

    app(UpdateStudioSetting::class)('display_name', null, $owner);

    expect(AuditLog::where('event', 'studio_settings.updated')->count())->toBe(0);
});

it('returns T-02\'s legal-entity, brand and bank columns as one plain array', function () {
    $owner = staffMember(StaffRole::Admin);
    app(UpdateStudioSetting::class)('legal_name', 'Atelier Nordlicht Fotografie GmbH', $owner);

    $attributes = StudioSettings::current()->profile();

    expect($attributes['legal_name'])->toBe('Atelier Nordlicht Fotografie GmbH')
        ->and($attributes)->toHaveKeys(['display_name', 'tax_number', 'vat_id', 'bank_iban', 'logo_path']);
});

it('stores the uploaded logo once and resolves the same path from an independent instance — what a PDF render and a portal read would both consume', function () {
    Storage::fake('local');
    $owner = staffMember(StaffRole::Admin);

    app(StoreStudioLogo::class)(UploadedFile::fake()->image('logo.png'), $owner);

    $fromOneInstance = StudioSettings::current()->logo_path;
    $fromAnotherInstance = StudioSettings::current()->logo_path;

    expect($fromOneInstance)->not->toBeNull()
        ->and($fromAnotherInstance)->toBe($fromOneInstance);

    Storage::disk('local')->assertExists($fromOneInstance);
});

it('deletes the previous logo file when a new one replaces it', function () {
    Storage::fake('local');
    $owner = staffMember(StaffRole::Admin);

    app(StoreStudioLogo::class)(UploadedFile::fake()->image('first.png'), $owner);
    $firstPath = StudioSettings::current()->logo_path;

    app(StoreStudioLogo::class)(UploadedFile::fake()->image('second.svg'), $owner);
    $secondPath = StudioSettings::current()->logo_path;

    expect($secondPath)->not->toBe($firstPath);
    Storage::disk('local')->assertMissing($firstPath);
    Storage::disk('local')->assertExists($secondPath);
});

it('writes an audit row for a logo upload naming the actor', function () {
    Storage::fake('local');
    $owner = staffMember(StaffRole::Admin);

    app(StoreStudioLogo::class)(UploadedFile::fake()->image('logo.png'), $owner);

    $log = AuditLog::where('event', 'studio_settings.updated')->where('context->key', 'logo_path')->sole();
    expect($log->actor_id)->toBe($owner->id)
        ->and($log->context['old'])->toBeNull();
});

it('replaces a cached __PHP_Incomplete_Class left behind by a moved model namespace', function () {
    $key = 'studio_settings:'.config('tenancy.tenant_id');
    Cache::store('redis')->put(
        $key,
        unserialize('O:34:"App\\Settings\\Models\\StudioSettings":0:{}', ['allowed_classes' => false]),
        3600,
    );

    expect(Cache::store('redis')->get($key))->toBeInstanceOf(__PHP_Incomplete_Class::class);

    $settings = StudioSettings::current();

    expect($settings)->toBeInstanceOf(StudioSettings::class)
        ->and(Cache::store('redis')->get($key))->toBeInstanceOf(StudioSettings::class);
});

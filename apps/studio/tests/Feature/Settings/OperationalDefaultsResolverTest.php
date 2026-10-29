<?php

use App\Crm\Models\ClientCase;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\StudioSettings;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// staffMember() lives in tests/Pest.php — see the note there.

beforeEach(fn () => Cache::store('redis')->flush());

it('seeds a null timezone and every day as a working day', function () {
    $row = StudioSettings::query()->sole();

    expect($row->timezone)->toBeNull()
        ->and($row->working_days)->toBe([1, 2, 3, 4, 5, 6, 7]);
});

it('falls back to config(app.timezone) while the tenant timezone is unset', function () {
    $resolver = app(StudioSettingsResolver::class);

    expect($resolver->timezone())->toBe(config('app.timezone'));
});

it('returns the tenant timezone once set, not the environment fallback', function () {
    $owner = staffMember(StaffRole::Admin);
    $resolver = new StudioSettingsResolver;

    $resolver->set('timezone', 'Europe/Vienna', $owner);

    expect($resolver->timezone())->toBe('Europe/Vienna')
        ->and($resolver->timezone())->not->toBe(config('app.timezone'));
});

it('treats an empty working_days set as every day, guarding the roll in dueFor() against looping forever', function () {
    // Bypasses the controller's own min:1 validation on purpose, to prove
    // the resolver's own guard holds even if a row were ever corrupted.
    DB::table('studio_settings')->update(['working_days' => '[]']);

    $resolver = app(StudioSettingsResolver::class);

    expect($resolver->workingDays())->toBe([1, 2, 3, 4, 5, 6, 7]);
});

it('writes one audit row per changed operational-defaults field', function () {
    $owner = staffMember(StaffRole::Admin);
    $resolver = new StudioSettingsResolver;

    $resolver->set('timezone', 'Europe/Zurich', $owner);
    $resolver->set('working_days', [1, 2, 3, 4, 5], $owner);

    expect(AuditLog::where('event', 'studio_settings.updated')->count())->toBe(2)
        ->and(AuditLog::where('context->key', 'timezone')->sole()->context['new'])->toBe('Europe/Zurich')
        ->and(AuditLog::where('context->key', 'working_days')->sole()->context['new'])->toBe([1, 2, 3, 4, 5]);
});

it('leaves an existing stored timestamp exactly as it was, unaffected by an operational-defaults change', function () {
    // Acceptance criterion #2: presentation and due-date arithmetic only —
    // never storage. A row's own created_at, read straight off the
    // database rather than through Eloquent's timezone-aware accessor, is
    // the one place a silent rewrite would actually show up.
    $owner = staffMember(StaffRole::Admin);
    $project = ClientCase::factory()->create();
    $before = DB::table('cases')->where('id', $project->id)->value('created_at');

    (new StudioSettingsResolver)->set('timezone', 'Europe/Vienna', $owner);
    (new StudioSettingsResolver)->set('working_days', [1, 2, 3, 4, 5], $owner);

    $after = DB::table('cases')->where('id', $project->id)->value('created_at');

    expect($after)->toEqual($before);
});

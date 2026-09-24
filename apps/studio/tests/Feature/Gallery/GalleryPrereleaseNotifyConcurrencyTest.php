<?php

use App\Crm\Models\ClientCase;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryPrereleaseSignup;
use App\Gallery\Models\GallerySet;
use App\Media\Models\Asset;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/*
 * Deliberately does NOT `uses(RefreshDatabase::class)` — same reasoning as
 * SetupConcurrencyTest: that trait wraps a test in a savepoint on the app's
 * one default connection, and this needs two real, independent Postgres
 * sessions to prove PublishGallery::notifyPrereleaseSignups()'s
 * lockForUpdate() actually serializes two publishes racing the same
 * gallery, rather than both reading the same unnotified rows before either
 * writes (R45/I1). Cleans up by hand in afterEach instead.
 */

beforeEach(function () {
    Config::set('database.connections.pgsql_second', Config::get('database.connections.pgsql'));
});

afterEach(function () {
    GalleryPrereleaseSignup::query()->delete();
    Asset::query()->delete();
    GallerySet::query()->delete();
    Gallery::query()->delete();
    // Gallery::factory() defaults case_id to ClientCase::factory(), which
    // creates a real, committed project row this test's own connection
    // never rolls back — without this, it outlives the process and pollutes
    // every RefreshDatabase test that assumes an empty projects table.
    ClientCase::query()->delete();
    DB::purge('pgsql_second');
});

it('lets only one of two racing publishes claim a pre-release signup for mailing', function () {
    $gallery = Gallery::factory()->create();
    $signup = GalleryPrereleaseSignup::create(['gallery_id' => $gallery->id, 'email' => 'race@example.test']);

    // Publish A: the same claim notifyPrereleaseSignups() makes, run by
    // hand so its commit can be held back while B probes the lock.
    DB::connection('pgsql')->beginTransaction();
    DB::connection('pgsql')->table('gallery_prerelease_signups')
        ->where('id', $signup->id)
        ->lockForUpdate()
        ->get();

    // Publish B, on its own connection, tries the same row while A's
    // transaction still holds it. NOWAIT turns what would otherwise be a
    // blocking wait into an immediate failure this test can assert on —
    // Postgres error 55P03, lock_not_available.
    $blocked = false;

    try {
        DB::connection('pgsql_second')->table('gallery_prerelease_signups')
            ->where('id', $signup->id)
            ->lock('FOR UPDATE NOWAIT')
            ->get();
    } catch (Throwable $e) {
        $blocked = str_contains($e->getMessage(), 'could not obtain lock') || str_contains($e->getMessage(), '55P03');
    }

    expect($blocked)->toBeTrue();

    // A finishes: deletes the claimed row (notifyPrereleaseSignups()'s own
    // claim-by-delete, see PublishGallery) and commits.
    DB::connection('pgsql')->table('gallery_prerelease_signups')->where('id', $signup->id)->delete();
    DB::connection('pgsql')->commit();

    // B, now that A has committed and the row is gone, runs its own claim —
    // finds nothing, exactly as a second, slightly later publish must.
    $claimedByB = DB::connection('pgsql_second')->table('gallery_prerelease_signups')
        ->where('id', $signup->id)
        ->lockForUpdate()
        ->get();

    expect($claimedByB)->toHaveCount(0)
        ->and(GalleryPrereleaseSignup::count())->toBe(0);
});

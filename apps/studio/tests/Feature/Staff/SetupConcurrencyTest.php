<?php

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/*
 * Deliberately does NOT `uses(RefreshDatabase::class)`. That wraps a test in
 * a savepoint on the app's one default connection — this test needs two
 * real, independent Postgres sessions to prove SetupController::store()'s
 * advisory lock actually serializes them, the same reasoning
 * CutoverRehearsalTest gives for skipping it. Runs `migrate` itself up
 * front for the same reason that file does: this can run before any
 * RefreshDatabase-using file has applied the schema.
 *
 * SetupTest's "404s once a staff account already exists" only proves the
 * *sequential* case: create a user, then a second submission finds it and
 * is refused. lockForUpdate() passed that test too — right up until a
 * second, concurrent submission against an EMPTY table exposed that
 * Postgres's row lock never engages when there is no row yet to lock (see
 * SetupController::store()'s own comment). This proves the concurrent case
 * lockForUpdate() missed: two submissions racing an empty table, with
 * different emails, so the (tenant_id, email) unique index can't save it
 * either.
 */

beforeEach(function () {
    $this->artisan('migrate')->run();

    // A second connection *name* pointed at the same database as the
    // default 'pgsql' one. Laravel resolves and caches one PDO handle per
    // connection name, so a second name is what forces a genuinely separate
    // Postgres session rather than reusing the first one — standing in for
    // "the other tab"'s own request.
    Config::set('database.connections.pgsql_second', Config::get('database.connections.pgsql'));

    User::query()->delete();
});

afterEach(function () {
    User::query()->delete();
    DB::purge('pgsql_second');
});

it('serializes two concurrent setup submissions on an empty table, so only one becomes Owner', function () {
    $lockKey = crc32('staff_setup');
    $owner = fn (string $email) => [
        'name' => 'Christian Zepter',
        'email' => $email,
        'password' => 'ein-sehr-langes-passwort',
        'role' => StaffRole::Admin,
    ];

    // Request A: the same sequence as SetupController::store()'s
    // transaction, run by hand so its commit can be held back while B
    // probes the lock mid-flight.
    DB::connection('pgsql')->beginTransaction();
    DB::connection('pgsql')->select('SELECT pg_advisory_xact_lock(?)', [$lockKey]);

    // Request B, on its own connection, tries the non-blocking variant
    // while A's transaction is still open. If the row lock the brief
    // originally specified were still in place, B would sail through here —
    // there is nothing to lock on an empty table. The advisory lock has no
    // row to find, so it blocks B regardless.
    $acquiredWhileAHoldsIt = DB::connection('pgsql_second')
        ->selectOne('SELECT pg_try_advisory_xact_lock(?) AS acquired', [$lockKey])
        ->acquired;

    expect($acquiredWhileAHoldsIt)->toBeFalse();

    // A finishes: creates its Owner row and commits, releasing the lock —
    // same order as the real controller.
    User::on('pgsql')->create($owner('christian@nordlicht.test'));
    DB::connection('pgsql')->commit();

    // B, submitted with a *different* email — the case a unique index on
    // (tenant_id, email) cannot catch — now runs the real sequence on its
    // own connection: lock (free now that A committed), see the row A just
    // committed, and refuse rather than create a second Owner.
    DB::connection('pgsql_second')->transaction(function () use ($lockKey, $owner) {
        DB::connection('pgsql_second')->select('SELECT pg_advisory_xact_lock(?)', [$lockKey]);

        if (User::on('pgsql_second')->exists()) {
            return;
        }

        User::on('pgsql_second')->create($owner('zweite@nordlicht.test'));
    });

    expect(User::query()->count())->toBe(1);
    expect(User::where('role', StaffRole::Admin)->count())->toBe(1);
    expect(User::where('email', 'zweite@nordlicht.test')->exists())->toBeFalse();
});

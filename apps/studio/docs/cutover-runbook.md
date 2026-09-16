# Cutover runbook (X-03)

The runbook, not the migration script, is the deliverable: staff on the
admin surface have to be able to keep working on the day the switch
happens, and there has to be a way back if it doesn't.

Nothing in this codebase names the legacy system X-02 imports from — it
treats it as external and unspecified, an intermediate JSON format any export
gets converted into first (see `App\Gallery\Actions\ImportLegacyGallery`'s
doc comment). This runbook follows that: the rollback point below is a
snapshot of this app's own database, not anything involving the legacy
system.

All commands run through Docker, per this repo's rule that PHP always does
(`docker compose run --rm php ...`) — drop the prefix if you're running
against a host PHP set up the same way tests are.

## 0. Pre-cutover checklist

- [ ] Contacts and projects already entered on the admin surface, and the
      legacy galleries exported into a JSON fixture
      (`App\Gallery\Console\Commands\ImportLegacyGalleriesCommand`'s doc
      comment for the two record shapes)
- [ ] `docker compose up -d` — Postgres, Redis, the worker and Gotenberg are
      healthy (`docker compose ps`)
- [ ] Enough disk under `storage/app/private/cutover/` for a full database
      dump (a few seconds' `pg_dump` of everything the app has)
- [ ] Staff know the parallel-run window (§4) is starting and what it means
      for where they enter new work
- [ ] `php artisan list db` shows `db:cutover-snapshot` and
      `db:cutover-restore`

## 1. Dry run the import

```sh
docker compose run --rm php php artisan gallery:import-legacy storage/app/import/legacy-galleries.json --mode=backfill --dry-run
docker compose run --rm php php artisan gallery:import-legacy storage/app/import/legacy-galleries-linked.json --mode=link --dry-run
```

Read every `error` line X-02 prints.
Fix the source export and re-run until the only rows left unmapped are ones
someone has explicitly decided to drop — not ones nobody has looked at yet.
This step writes nothing; run it as many times as the source data needs.

## 2. Snapshot — the rollback point

```sh
docker compose run --rm php php artisan db:cutover-snapshot
```

Runs `pg_dump -Fc` against the app's own database and prints the path it
wrote to (`storage/app/private/cutover/cutover-<timestamp>.dump` by
default, `--path=` to choose another). Note that path — §6 needs it.
`db:cutover-snapshot` refuses to run against a non-Postgres connection
rather than doing something a `pg_dump` command doesn't understand.

## 3. Commit the import

```sh
docker compose run --rm php php artisan gallery:import-legacy storage/app/import/legacy-galleries.json --mode=backfill
docker compose run --rm php php artisan gallery:import-legacy storage/app/import/legacy-galleries-linked.json --mode=link
```

X-02 is per-record: a bad row is reported and skipped without blocking the
rest of the file. Compare the commit summary counts against the dry-run
report from §1 — they should match exactly.

## 4. Parallel-run window

The admin surface is unaffected by any of this — nothing above touches
staff sign-in, and the go/no-go decision (§5) happens with staff already
working in the new app, not blocked from it. That's what "keep working on
the day the switch happens" means operationally: cutover is a data event,
not a downtime event, for staff.

For the length of the window (a business day is usually enough to spot-check
against the legacy source; longer if that source is hard to reach):

- **This app is authoritative for everything created here from the moment
  §3 finishes** — new contacts, new bookings, payments (M-05
  lives only here, so there is no "legacy" side of it to reconcile against).
- **The legacy system is authoritative for anything predating the
  snapshot**, consulted read-only to verify what §3 imported, never written
  to going forward.
- Consider enabling the portal's maintenance closure (T-08 — a studio
  setting, `StudioSettingsResolver::maintenanceEnabled()`) for the short
  span between §2 and the go/no-go call in §5, if clients might accept
  quotes or otherwise write through the portal during it. A rollback
  (§6) discards portal writes made after the snapshot exactly as it
  discards the imports — closing the portal for that narrow window avoids
  silently losing one under the other. Not needed once §5 has said "go":
  from that point rollback is off the table (§6) and the portal resumes
  normally.

## 5. Go/no-go checklist

- [ ] §1's final dry run had zero unresolved unmapped/error rows
- [ ] §3's commit summary counts match §1's dry-run counts
- [ ] Spot-checked a sample of imported galleries against the legacy source
- [ ] Staff can sign in, see their projects and the data they expect, on the
      admin surface
- [ ] The snapshot from §2 exists and its path is recorded somewhere this
      checklist survives
- [ ] `tests/Feature/Migration/CutoverRehearsalTest.php` is green on the
      build being cut over (the automated stand-in for "exercised against a
      copy of production" — §7)

Any box unchecked is a **no** — roll back (§6), fix the source data, and
start again from §1. Don't commit partway through a no-go; the transaction
boundaries in §3 exist so "no" always means "nothing new-and-broken to
clean up beyond a restore."

## 6. Rollback

```sh
docker compose run --rm php php artisan db:cutover-restore storage/app/private/cutover/cutover-<timestamp>.dump
```

Confirms before running (`--force` to skip, e.g. from a script). Runs
`pg_restore --clean --if-exists` against the snapshot from §2 — every table
the dump covers is dropped and recreated from it, undoing the commit-mode
imports from §3 and anything else written since the snapshot was taken.
That last part is why §6 only belongs before §5 has said "go": once staff
and (if it was left open) clients have started writing real work into the
post-cutover state, a restore erases that work too, not just the imports.

`db:cutover-restore`, like `db:cutover-snapshot`, refuses a non-Postgres
connection outright.

## 7. The automated rehearsal

There's no production copy of this app anywhere in this sandboxed
environment to exercise the above against, so
`tests/Feature/Migration/CutoverRehearsalTest.php` is the stand-in the
acceptance criterion asks for: it runs §2 (snapshot) → §3 (the import,
commit mode, for real — not a dry run) → verifies the data landed → §6
(restore) → verifies the database is back to exactly the row counts it
started at, against the real test database through real `pg_dump`/
`pg_restore` processes, not mocks. It's part of the normal `./vendor/bin/pest`
run and CI job — a change to the import command or to either cutover command
that breaks this sequence fails the build before it fails a real cutover.

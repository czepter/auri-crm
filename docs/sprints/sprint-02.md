# Sprint 2 — Intake and Lead Board

**Dates:** 2026-08-09 → sprint ends when the Definition of Done is met
**Phase:** P1b (Sprint 1 of 3)
**Spec:** [`../superpowers/specs/2026-08-09-p1b-crm-core-design.md`](../superpowers/specs/2026-08-09-p1b-crm-core-design.md)
**Plan:** [`../superpowers/plans/2026-08-09-p1b-sprint-02-intake-and-lead-board.md`](../superpowers/plans/2026-08-09-p1b-sprint-02-intake-and-lead-board.md)
**Branch:** `feat/p1b-crm-core`, based on `feat/p1a-foundation` at `0bde280`
**Execution:** subagent-driven — one implementer per task, a task review after each, a whole-branch review at the end. Ledger at `.superpowers/sdd/2026-08-09-p1b-sprint-02-intake-and-lead-board/progress.md`.

---

## Sprint Goal

> **An inquiry taken by phone is typed into one German form, creates a contact, a project, a lead and its primary shoot in a single transaction, warns if that date is already taken without blocking the save, and appears on the six-column board in `Neu` — with no integer identifier anywhere in the response.**

Mockups `02-anfragen.html` and `03-anfrage-neu.html` are the acceptance surface.

### Why this goal and not the project screen

Mockup 04 is where the studio's work actually happens, and it is the wrong thing to build first. It depicts a project that has already been captured, quoted, booked and staffed — every one of those states has to exist before the screen summarising them can be built, and building the summary first means inventing them.

That failure is already on record in this repository. Clickdummy §6 documents screen 1 being built in the pattern-setter slot, before the eight screens it summarises existed, and being thrown away: *"A dashboard is a summary of other screens… its content had to be invented, after which the rest of the set would have had to live up to the invention."*

Mockup 03 is the only screen with nothing above it. `03-anfrage-neu.html:13`: *"every Project, Lead, Contact and Shoot in the system is born here."*

---

## Sprint Backlog

Tasks run in plan order. The plan's own step lists are the acceptance criteria.

| # | Task | Delivers | Depends on |
|---|---|---|---|
| 1 | German locale and the i18n catalog | `APP_LOCALE=de`, German validation messages, vue-i18n, the five P1a pages retrofitted | — |
| 2 | The CRM namespace and `Contact` | `app/Crm/`, partial unique email index | — |
| 3 | `Project` and `ProjectContact` | Both tables, role enum, partial unique primary-role index | 2 |
| 4 | `Lead` | `LeadStage`/`LeadSource`/`CaptureChannel`, `stage_entered_at`, `lost_at` | 3 |
| 5 | `Shoot` | date/time columns, partial unique `is_primary`, date index | 3 |
| 6 | Stage transitions | `AdvanceLeadStage`, legal-transition map, events, audit rows | 4 |
| 7 | Search and availability endpoints | `GET /kontakte/suche`, `GET /verfuegbarkeit`, typed props | 2, 5 |
| 8 | The capture form | Mockup 03, one transaction, inline conflict that warns and never blocks | 1, 6, 7 |
| 9 | The lead board | Mockup 02, grouped conflict query, stale colouring, demo seeder | 8 |
| 10 | Contacts list and edit | Closes C-01; grows the audience matrix and the hygiene test | 2 |

Tasks 1 and 2 are the only pair that can run in parallel — 1 touches `resources/js` and `lang/`, 2 touches `app/Crm` and `database/migrations`, and they share no file. Everything after is sequential, and unlike sprint 1 that is a real dependency chain rather than a caution about file conflicts.

---

## Definition of Done

Copied from the spec so the sprint can be closed without re-reading it.

- [x] `docker compose run --rm php ./vendor/bin/pest` passes with zero skipped tests — 141 passed, 412 assertions
- [x] `pnpm lint && pnpm test && pnpm build` passes at the workspace root
- [x] `pnpm --filter @studio/app test:e2e` passes: capture an inquiry, land on the board, find it in `Neu` — 6 passed
- [x] CI green on all jobs
- [x] Every new table carries `tenant_id` and `public_id` from its first migration
- [x] `NoInternalIdsTest` covers the board, the capture response, contact search and the availability response
- [x] `AudienceMatrixTest` covers every new route group
- [x] `generated.d.ts` is current and the CI staleness gate is green
- [x] Each partial unique index has a test that expects a violation: duplicate email, second `is_primary` shoot, second `primary` contact
- [x] Capture with a live date conflict **succeeds**, and the conflict is visible on the resulting board card
- [x] Moving the colliding project's date clears the flag on both screens with **no write** to the lead
- [x] The board renders its conflict flags in a bounded number of queries, asserted by a query-count test
- [x] A validation failure renders a German message, and every user-visible string in the admin pages resolves through the catalog
- [x] The "create new contact" path is unreachable until a search has run

### Found while building, fixed outside the plan

The plan assumed the suite ran on a clean checkout. It did not — CI had never executed, because the repository had no remote until this sprint. Four defects surfaced on the first real run and were fixed on `ci/p1a-clean-checkout` (PR #2), which merged into P1a before it went to `dev`:

1. `dorny/paths-filter` had no `pull-requests: read`, so the `changes` job died and every test job **skipped** on its outputs — a pull request that looked processed and was never tested.
2. `tests/Unit` is empty, so git never tracked it, and Pest exited 2 before running anything.
3. The Inertia root view calls `@vite`, whose manifest is gitignored build output the `studio` job never builds — ten page tests 500'd.
4. `pnpm/action-setup` got a version from both the workflow and `packageManager` and refused to choose.

Two more were local-only: the php image had neither `composer` nor `unzip`, so the `docker compose run --rm php composer install` every sprint document prescribes had never worked; and both host ports were hardcoded, so a second worktree could not start its stack.

---

## Out of scope

Named explicitly so it does not creep in: project detail (C-06) · internal notes (C-07) · additional shoots beyond the primary · `Shoot.status` transitions (P-03) · `Job` and conversion (P-05) · the production board (P-04) · crew assignment (S-02) · tasks and templates (S-03) · calendar and ICS (S-04) · staff invitations and password reset (S-05) · role-based policies (S-06) · the board's *Liste* and *Kalender* tabs and its filter control · quotes, contracts and anything with a price on it.

Every one has a home in [Roadmap-Board](https://github.com/users/czepter/projects/2).

---

## Risks

| Risk | Mitigation |
|---|---|
| Partial unique indexes need raw DDL and would silently vanish on another driver | Postgres-only, already pinned; each index has a violation test, not just a happy-path test |
| The conflict flag is the natural candidate for a stored boolean | Spec §5.2 states the reasoning; the DoD has the self-healing test; the reviewer is pointed at it by name |
| Board conflict detection is an obvious N+1 | Query-count assertion in the DoD, and the reviewer is told to check it fails when `forDate()` moves inside the card loop |
| The typeahead's anti-duplicate ordering is easy to implement as an ordinary autocomplete | Named as a review target; the DoD asserts "create new" is unreachable before a search |
| German copy volume jumps — this is the first sprint with real forms | i18n is task 1, before any new screen exists to retrofit |
| `feat/p1a-foundation` has never run in CI: no remote, no PR | Push it and get CI green before this branch merges, so a CI-only failure surfaces against 17 commits rather than 40 |
| Playwright's `reuseExistingServer` attaches to a stale container | Recorded in the P1a ledger; `docker ps --filter publish=8000` before every local e2e run |

---

## Sprint review question

At the end, one question decides whether the goal was met: **can a photographer take a phone call, type what they heard into one screen, be told the date is already taken without being stopped, and see that inquiry on the board before the call ends?**

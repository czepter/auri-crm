# P1b — CRM Core

**Date:** 2026-08-09
**Status:** Approved, implementation not started.
**Parent:** [Product Vision](2026-08-07-photography-client-management-vision-design.md) · [Clickdummy](2026-08-07-clickdummy-design.md) · [Roadmap-Board](https://github.com/users/czepter/projects/2)
**Predecessor:** [P1a — Foundation & Staff Auth](../plans/2026-08-07-p1a-foundation-and-staff-auth.md), complete 2026-08-08
**Branch:** `feat/p1b-crm-core`, based on `feat/p1a-foundation` at `0bde280`

The phase that replaces the spreadsheet. It runs over three sprints. This document specifies **sprint 2** at task level and sketches sprints 3 and 4 only far enough that sprint 2's schema decisions account for them.

---

## 1. Why sprint 2 stops where it stops

P1a shipped no business value on purpose: a staff member can sign in and see an empty dashboard. Sprint 2 is the first sprint a photographer would notice, and the temptation is to build the whole of mockup 04 — the project detail screen, the densest in the system — because it is where the work actually happens.

That would be the wrong order. Mockup 04 depicts a project that has already been captured, quoted, booked and staffed. Every one of those states has to exist before the screen that summarises them can be built, and building the summary first means inventing the states — the exact failure recorded in clickdummy §6, where screen 1 was built before the eight screens it summarises and had to be thrown away.

Sprint 2 therefore builds the one path that has no prerequisite: **an inquiry arriving, and the board it lands on.** Every object in the system is born on mockup 03 (`03-anfrage-neu.html:13` — "every Project, Lead, Contact and Shoot in the system is born here"), so it is the only screen with nothing above it.

### Sprint goal

> **An inquiry taken by phone is typed into one form, creates a contact, a project, a lead and its primary shoot in a single transaction, warns if that date is already taken, and appears on the six-column board in `Neu` — in German, with no integer identifier anywhere in the response.**

Mockups `02-anfragen.html` and `03-anfrage-neu.html` are the acceptance surface. Nothing from mockup 04 is in this sprint.

---

## 2. Corrections to the backlog and the vision

Three places where the sources disagree. Each is resolved here so the implementation does not have to re-litigate it.

### 2.1 No multi-shoot repeater at capture

`docs/backlog.md:52` specifies C-04 as "manual lead capture form with contact typeahead and **multi-shoot repeater**". Mockup 03 has no repeater. It collects one main date, and `03-anfrage-neu.html:116` states the intent directly: *"Ort, Gästezahl, weitere Shootings und Paket später im Projekt."*

The mockup is the later document and carries reasoning; the backlog line is superseded. Capture creates **exactly one `Shoot`**, flagged `is_primary`. Additional shoots are added on the project detail screen in sprint 3.

This also settles `is_primary`. Vision §3.1 requires it to be set at intake rather than derived, and warns that a derivation rule would be wrong for exactly the motivating case — the engagement shoot is the earliest date and is never the headline. With one shoot at capture, the primary one is unambiguous, and staff change it in sprint 3 when a second shoot exists to change it to.

### 2.2 `type` belongs to `Project`, not `Job`

Vision §3.1 lists `type` (wedding, portrait, other) on `Job`. Mockup 03 collects *Typ* at capture, months before a `Job` exists — a `Job` is created only at conversion, and most leads never convert.

`type` moves to `Project`. A project's nature does not change when it converts, so the entity that spans inquiry to archive is the one that should own it. `Job` gains nothing by carrying a copy, and a copy is a second value to keep in sync.

Recorded as a deviation from the vision. `Shoot.kind` stays as specified and remains the finer-grained field: a wedding project has shoots of kind `engagement`, `wedding`, `reception`.

### 2.3 The board card renders without money

`02-anfragen.html:46` shows a value on every card (`4.800 €`) and a sum in every column header. Sprint 2 has no source for either. `Quote` is lead-scoped and arrives with `Q-02` in P2. `venue` and the shoot count above one are not collected at capture. The mockup depicts a mature system, as clickdummy §4 says every screen does — "screens depict one moment, in the most informative state rather than empty".

Sprint 2's card is therefore: kicker (project type), contact names, date and shoot count, source, age, conflict flag. **No value row and no column sums until quotes exist.**

Two alternatives were rejected. Adding `Lead.estimated_value` puts a second money number in the system that has to be reconciled against the real quote in P2, and adds a field to a capture form whose six-field shape mockup 03 argues for deliberately. Rendering `0 €` placeholders reproduces the failure clickdummy §6 records for the first dashboard — a row nobody can act on.

The card still triages. Age carries the stale colouring (vision §7 nudges at 3 and 7 days), which is what `02-anfragen.html:84` uses to mark "8 Tage still", and that is the signal the board exists to surface.

---

## 3. Schema

Six tables. Every one carries `tenant_id` and `public_id` from its first migration, per the backlog's cross-cutting rule, using the `tenantId()` and `publicId()` blueprint macros P1a established. All timestamps are `timestamptz`; `shoots.date` is a `date` and `call_time`/`end_time` are `time`, per vision §8.

### `contacts`

| Column | Type | Notes |
|---|---|---|
| `name` | string | One field, not first/last. Mockup 02 carries `Familie Osterhoff` as a card name — a household is a contact. |
| `email` | string, nullable | |
| `phone` | string, nullable | |

**`unique (tenant_id, email) where email is not null`.** Vision §3.1 gives the reasoning: a couple sharing an inbox is one contact holding that address, a partner with their own is a second contact, and a venue coordinator with no address simply has no portal access. Letting the address repeat would make a P2 magic link resolve to more than one person and leave "who signed this contract" without an answer.

### `projects`

| Column | Type | Notes |
|---|---|---|
| `name` | string | Proposed from contact and type at capture, overridable |
| `type` | string (`ProjectType`) | `wedding`, `portrait`, `other` — see §2.2 |
| `portal_visible` | boolean, **default false** | |

`portal_visible` has no consumer in sprint 2 and is created anyway. Vision §3.1 makes it a safety property — a raw inquiry must not surface in a portal the moment it arrives — and the column costs nothing now while adding it in P2 means a migration plus an audit of every project row created in the interim. It is the one speculative column in this schema and it is deliberate.

No pipeline status on `projects`. Vision §3.1 and clickdummy §"Vocabulary" both rule it out: a project whose engagement shoot is delivered while the wedding day is unshot has no single state. Board position is derived (§5).

### `leads`

| Column | Type | Notes |
|---|---|---|
| `project_id` | fk, **unique** | One lead per project |
| `source` | string (`LeadSource`) | `referral`, `website`, `instagram`, `wedding_fair`, `other` |
| `stage` | string (`LeadStage`) | `new`, `contacted`, `quote_sent`, `quote_accepted`, `contract_sent`, `booked` |
| `stage_entered_at` | timestamptz | |
| `lost_at` | timestamptz, nullable | |
| `lost_reason` | string, nullable | |
| `inquiry_text` | text, nullable | |
| `captured_via` | string (`CaptureChannel`) | `phone`, `email`, `in_person`, `other` |
| `captured_by_id` | fk → `users` | |

`source` is a fixed list rather than free text, per `03-anfrage-neu.html:28` — it is the one fact that cannot be read back out of the inquiry text later, and its entire value is being countable. `captured_via` is a different question (how it arrived, not who referred them) and is equally unrecoverable, which is why both exist.

**`stage_entered_at` is stored, not derived.** The card's age reads *"8 Tage still"* in `Kontaktiert` but *"vor 2 Std."* in `Neu` (`02-anfragen.html:84`, `:46`) — it is time in the *current* stage, not since creation. Without a transitions table that cannot be computed, and a transitions table is a table to maintain when one timestamp answers the only question asked of it. Transitions still emit domain events and write `audit_logs` rows; the append-only ledger P1a built is where the history lives.

`lost` is not a stage. It is reachable from any stage (vision §7), and modelling it as a seventh enum value would lose the stage the lead was in when it died — which is the number the funnel is actually measured on.

### `project_contacts`

| Column | Type | Notes |
|---|---|---|
| `project_id` | fk | |
| `contact_id` | fk | |
| `role` | string (`ProjectContactRole`) | `primary`, `partner`, `payer`, `planner`, `vendor` |

`unique (project_id, contact_id, role)` and **`unique (project_id) where role = 'primary'`**. The second is not in the vision and is added here: every screen in the system reads "the client" off this pivot, and two primaries make that read ambiguous with no error anywhere to notice it.

### `shoots`

| Column | Type | Notes |
|---|---|---|
| `project_id` | fk | |
| `kind` | string (`ShootKind`) | `engagement`, `wedding`, `reception`, `portrait`, `other` |
| `label` | string, nullable | |
| `date` | **date**, nullable | |
| `call_time`, `end_time` | **time**, nullable | |
| `venue_name`, `venue_city` | string, nullable | Not collected at capture; card renders `Hof Eggers, Winsen` from these in sprint 3 |
| `status` | string (`ShootStatus`) | Nine states, default `requested` |
| `is_primary` | boolean | |
| `sort_order` | integer | |

**`unique (project_id) where is_primary`**, per vision §3.1. Index on `(tenant_id, date)` for the availability scan.

**`date` is nullable.** `02-anfragen.html:91` renders `Termin offen` for a portrait lead. A required date forces staff to invent one, which corrupts the exact column the availability check reads — the same failure mode §5 gives for a blocking conflict warning.

`status` is created with its full nine-state enum but only ever holds `requested` in sprint 2. The forward-only transition machinery is P-03, in sprint 3.

### Postgres-only DDL

Both partial unique indexes need raw statements — Laravel's `$table->unique()` takes no `where` clause. This is why the stack is Postgres 18 in CI and locally and never SQLite: on SQLite these constraints would be silently absent and their tests would pass against a database that does not have them. Each partial index gets a test that expects a violation, not just a test that the happy path works.

---

## 4. Module layout

Vision §3 specifies bounded contexts as modules with separate namespaces. Sprint 2 is the first context with more than two models, so the namespace starts here:

```
app/Crm/
  Models/     Contact  Project  Lead  Shoot  ProjectContact
  Enums/      ProjectType  LeadSource  LeadStage  CaptureChannel
              ProjectContactRole  ShootKind  ShootStatus
  Actions/    CaptureInquiry  AdvanceLeadStage  CheckDateAvailability
  Data/       LeadCardData  ContactSuggestionData  DateConflictData
  Events/     LeadCaptured  LeadStageChanged  LeadLost
  Http/Controllers/
```

`App\` already maps PSR-4 to `app/`, so `App\Crm\Models\Contact` resolves with no composer change.

`User`, `AuditLog`, `app/Concerns/*`, `app/Support/*` and `app/Http/Controllers/Staff/*` stay where P1a put them — they are Identity and Audit, not CRM. The layout is mixed for this phase and stops being mixed when P2 adds `app/Sales/`.

Migrations stay in `database/migrations`, which is Laravel's convention and where the P1a migrations already live.

**Routes take German paths and English names.** `/anfragen`, `/anfragen/neu`, `/kontakte`, `/kontakte/{contact}`, `/verfuegbarkeit` — matching a German UI and the mockups' own filenames. Route *names* stay English (`leads.index`, `contacts.search`) so PHP and Vue read in one language. Route-model binding resolves on `public_id` via `HasPublicId::getRouteKeyName()`, which P1a already enforces.

---

## 5. The two derived reads

Neither is a column. Each is one query used in two places.

### 5.1 Date availability

```sql
select s.*, p.name, p.public_id
from shoots s
join projects p on p.id = s.project_id
join leads    l on l.project_id = p.id
where s.tenant_id = :tenant
  and s.date      = :date
  and l.lost_at is null
  and p.id <> :self          -- omitted at capture; set when re-checking an existing project
```

Vision §7: availability checks scan `shoots.date` for projects whose lead is not lost. Joining through `leads` is what expresses "not lost" — a booked project still has its lead, at stage `booked`.

**It warns. It never blocks.** `03-anfrage-neu.html:31-37` gives the reasoning and it is kept verbatim in the acceptance criteria: the check answers *"is somebody already on this day"*, which is not the question *"may this be saved"*. The studio breaks the rule deliberately — a second shooter leads the smaller job, two half-days fit one Saturday, the colliding date may still move or be lost. And a blocking form teaches staff to type a wrong date to get past it, corrupting the `shoots.date` column the check reads.

A feature test asserts capture **succeeds** with a live conflict.

### 5.2 Why the conflict flag is derived, not stored

`02-anfragen.html:68` puts the flag on the board card; `03-anfrage-neu.html:102` puts it under the date field. `03-anfrage-neu.html:36` says it "travels with the lead onto the board", and the tempting implementation is a `has_conflict` boolean written at capture.

That would be wrong. The colliding project can move its date, or go lost, and a stored flag would then be a lie with nothing to clear it — there is no event on the *other* project that anything would think to subscribe to. Deriving it makes the flag self-healing, for the same reason vision §7 gives for the production board: *"no rollup column to drift, no event needed to maintain it, and no reconciliation job."*

The Definition of Done includes the test that proves it: move the colliding project's date, assert the flag disappears from both screens, assert nothing was written to the lead.

On the board this runs **once** over the distinct dates on screen, not once per card. A query-count assertion guards that — the naive per-card implementation is the obvious way to write this and produces an N+1 nobody notices at eleven cards.

### 5.3 Board bucketing

```sql
select ... from projects p
join leads l on l.project_id = p.id
where p.tenant_id = :tenant and l.lost_at is null
order by l.stage_entered_at desc
```

with the primary shoot and a shoots count eager-loaded. One query; the six columns are grouped in PHP in `LeadStage` declaration order, because the funnel order is not the alphabetical order of the stored strings and encoding it in SQL would mean a `case` expression that has to be kept in sync with the enum. Lost leads are absent from the board entirely and get a list view in a later sprint.

---

## 6. Capture as one transaction

`CaptureInquiry` writes, inside a single transaction:

1. `Contact` — resolved from the typeahead, or created via the *"„stef" als neuen Kontakt anlegen"* path
2. a second `Contact` if the optional partner field is filled
3. `Project` — name proposed from contact and type, overridable; `portal_visible = false`
4. `ProjectContact` rows — `primary`, plus `partner` when the second contact exists
5. `Lead` — `stage = new`, `stage_entered_at = now()`, `captured_by_id = auth()->id()`
6. `Shoot` — `is_primary = true`, `status = requested`, `sort_order = 0`

`03-anfrage-neu.html:22` explains why the partner is one field in the UI and a second `ProjectContact` underneath: one step for staff, correct model in the database, and in P2 the partner gets their own portal login rather than sharing one.

**The typeahead ordering is the anti-duplicate mechanism, not a convenience.** `03-anfrage-neu.html:16-20`: `ClientAccess` hangs off `Contact`, so a repeat client must resolve to the same row, and searching before typing is the ordering that prevents a second *Familie Osterhoff*. The contact fields only unfold once a search has run, which makes "create new" unreachable until the existing matches have been seen. A plain text field cannot do this — it just lets you type a fresh name. The task reviewer is pointed at this specifically.

---

## 7. German

The mockups are German throughout, and clickdummy §"Language" gives the reason: German compound nouns run roughly 30% longer than their English equivalents and break column and button widths that looked fine in English. Building the UI in English and translating later defers that discovery to the worst possible moment.

- `APP_LOCALE=de`, `APP_FALLBACK_LOCALE=de`; `lang/de/validation.php` and `lang/de/auth.php`
- `vue-i18n` with `resources/js/locales/de.json`, registered in `resources/js/admin/main.ts`
- The five pages P1a shipped are retrofitted — `resources/js/admin/pages/Auth/Login.vue:20` currently reads `Sign in`
- Dates use native `<input type="date">`, which renders `dd.mm.yyyy` under `de-DE` with no picker library and no parsing code
- Counts and money use vue-i18n `numberFormats` on `de-DE`, matching the mockups' `tabular-nums`

Only German ships. The catalog exists so a second locale is a file rather than a rewrite, not because one is planned.

---

## 8. Sprint 2 backlog

Ten tasks, one implementer each, following the P1a execution model: a task review after each, a whole-branch review at the end, ledger under `.superpowers/sdd/`.

| # | Task | Delivers | Depends on |
|---|---|---|---|
| 1 | i18n foundation | `APP_LOCALE=de`, German validation messages, vue-i18n, the five existing pages retrofitted | — |
| 2 | `app/Crm/` layout and `Contact` | Namespace, model, migration with the partial unique email index, factory | — |
| 3 | `Project` and `ProjectContact` | Both models, `ProjectType` and `ProjectContactRole`, partial unique primary-role index | 2 |
| 4 | `Lead` | Model, `LeadStage`/`LeadSource`/`CaptureChannel`, `stage_entered_at`, `lost_at`/`lost_reason`, unique `project_id` | 3 |
| 5 | `Shoot` | Model, kind and status enums, `date`/`time` columns, partial unique `is_primary`, date index | 3 |
| 6 | Stage transitions | `AdvanceLeadStage`, legal-transition map, `lost(reason)` from any stage, events, audit rows | 4 |
| 7 | Search and availability endpoints | `GET /kontakte/suche`, `GET /verfuegbarkeit`, typed props, no integer ids | 2, 5 |
| 8 | Capture form | Mockup 03, one transaction, inline conflict that warns and never blocks | 1, 6, 7 |
| 9 | Lead board | Mockup 02, six columns, grouped conflict query, stale colouring, demo seeder | 8 |
| 10 | Contacts list and edit | Closes C-01; grows the audience matrix and the hygiene test | 2 |

Tasks 1 and 2 are the only pair that can run in parallel — 1 touches `resources/js` and `lang/`, 2 touches `app/Crm` and `database/migrations`, and they share no file. Everything after is sequential.

---

## 9. Definition of Done

- [ ] `docker compose run --rm php ./vendor/bin/pest` passes with zero skipped tests
- [ ] `pnpm lint && pnpm test && pnpm build` passes at the workspace root
- [ ] `pnpm --filter @studio/app test:e2e` passes: capture an inquiry, land on the board, find it in `Neu`
- [ ] CI green on all three jobs
- [ ] Every new table carries `tenant_id` and `public_id` from its first migration
- [ ] `NoInternalIdsTest` covers the board, the capture response, contact search, and the availability response
- [ ] `AudienceMatrixTest` covers every new route group
- [ ] `generated.d.ts` is current and the CI staleness gate is green
- [ ] Each partial unique index has a test that expects a violation: duplicate email, second `is_primary` shoot, second `primary` contact
- [ ] Capture with a live date conflict **succeeds**, and the conflict is visible on the resulting board card
- [ ] Moving the colliding project's date clears the flag on both screens with **no write** to the lead
- [ ] The board renders its conflict flags in a bounded number of queries, asserted by a query-count test
- [ ] A validation failure renders a German message, and every user-visible string in the admin pages resolves through the catalog rather than being hardcoded — route names, identifiers and code comments stay English
- [ ] The "create new contact" path is unreachable until a search has run

---

## 10. Out of scope

Named so it does not creep in: project detail (C-06) · internal notes (C-07) · additional shoots beyond the primary · `Shoot.status` transitions (P-03) · `Job` and conversion (P-05) · the production board (P-04) · crew assignment (S-02) · tasks and templates (S-03) · calendar and ICS (S-04) · staff invitations and password reset (S-05) · role-based policies (S-06) · the board's *Liste* and *Kalender* tabs and its filter control · everything in P2.

Each has a home in [Roadmap-Board](https://github.com/users/czepter/projects/2).

---

## 11. Sprints 3 and 4, sketched

Detailed when sprint 2 closes. Sketched here only so sprint 2's schema accounts for them.

**Sprint 3 — the project.** Project detail chrome with the derived pipeline position (C-06, mockup 04), internal notes as their own model and never a flag on `Message` (C-07), shoot management beyond the primary including changing which one is primary (C-03 completion), the `Shoot.status` forward-only pipeline with an event per transition (P-03), crew assignment per shoot with stacked avatars (S-02).
*Goal: a project's four shoots are managed from one screen.*

**Sprint 4 — commitment and the working day.** Conversion on contract-signed plus deposit-paid, creating the `Job` and moving that project's shoots `requested → booked`, emitting `ProjectBooked` (P-05); the derived production board and its `distinct on` query joined through `jobs` (P-04); the dashboard KPI row over four CRM domains (S-01); `Task` and `TaskTemplate` applied on transition into `booked` with offsets relative to that shoot's date (S-03); calendar view and ICS export (S-04).
*Goal: conversion creates the Job, and the studio's day is visible.*

**S-05 and S-06 should slip to sprint 5.** Sprint 4 is already the heaviest in the phase, and role-based policies want resources to authorize — most of which only exist once it lands. Deciding this now is premature; it is recorded so the sprint 4 planning session starts from it rather than discovering it.

---

## 12. Risks

| Risk | Mitigation |
|---|---|
| Partial unique indexes need raw DDL and would silently vanish on another driver | Postgres-only, already pinned; each index has a violation test, not just a happy-path test |
| The conflict flag is the natural candidate for a stored boolean | §5.2 states the reasoning; the DoD has the self-healing test; the reviewer is pointed at it |
| Board conflict detection is an obvious N+1 | Query-count assertion in the DoD |
| The typeahead's anti-duplicate ordering is easy to implement as an ordinary autocomplete | Named as a review target; the DoD asserts "create new" is unreachable before a search |
| Sprint 2 is the first sprint with real forms, so German copy volume jumps | i18n is task 1, before any new screen exists to retrofit |
| `feat/p1a-foundation` has never run in CI — no remote, no PR | Push and get CI green before sprint 2 merges, so a CI-only failure surfaces against 17 commits rather than 40 |

---

## 13. Sprint review question

One question decides whether the goal was met: **can a photographer take a phone call, type what they heard into one screen, be told the date is already taken without being stopped, and see that inquiry on the board before the call ends?**

# Sprint 1 — Foundation & Staff Authentication

**Dates:** 2026-08-08 → sprint ends when the Definition of Done is met
**Phase:** P1a
**Plan:** [`../superpowers/plans/2026-08-07-p1a-foundation-and-staff-auth.md`](../superpowers/plans/2026-08-07-p1a-foundation-and-staff-auth.md)
**Branch:** `feat/p1a-foundation`
**Execution:** subagent-driven — one implementer per task, a task review after each, a whole-branch review at the end. Ledger at `.superpowers/sdd/2026-08-07-p1a-foundation-and-staff-auth/progress.md`.

---

## Sprint Goal

> **A staff member signs in to the admin surface with a password and a mandatory TOTP code, every authentication event lands in an append-only audit log, and the surface gate is proven by a test that boots the application per `APP_SURFACE` and asserts no other surface's routes are registered.**

One sentence, and everything in the sprint backlog either serves it or is cut. The goal is deliberately infrastructural: P1a ships no business value, and pretending otherwise would let CRM stories leak into a sprint whose real risk is the surface mechanism and the identifier discipline.

### Why this goal and not "the lead board"

The board is the first thing the studio would notice and the wrong thing to build first. Three decisions in the vision are expensive to retrofit and free to establish now:

1. **The surface gate** — a claim about the route table, tested against the route table. Retrofitting it after the portal exists means auditing every route that already shipped.
2. **`public_id` discipline** — `HasPublicId` plus `NoInternalIdsTest`. Once a single integer identifier has leaked into a URL that a client has bookmarked, the fix stops being mechanical.
3. **The tenant seam** — one column on every table from the first migration. Adding it later touches every query in the system.

None of the three is visible to a user. All three are cheap exactly once.

---

## Sprint Backlog

Tasks run in plan order. Each maps to one task in the P1a plan; the plan's own step lists are the acceptance criteria.

| # | Task | Delivers | Depends on |
|---|---|---|---|
| 1 | Workspace skeleton, Docker, CI | `pnpm lint/test/build` at root; postgres on 5433; `docker compose run --rm php` | — |
| 2 | Laravel scaffold with surface gating | `APP_SURFACE` loads one route file; `/api/health`; unknown surface throws at boot | 1 |
| 3 | Inertia, Vue, Tailwind, shadcn-vue | Admin entrypoint renders an Inertia page with the design tokens | 2 |
| 4 | Schema conventions | `BelongsToTenant`, `HasPublicId`, route binding on `public_id` | 2 |
| 5 | Staff users and roles | `users` table, `StaffRole` enum, seeder | 4 |
| 6 | Append-only audit log | `AuditLog` model, `AuditLogger`, documented privilege revocation | 4 |
| 7 | Password login end to end | Login page, session guard `staff`, uniform failures, throttling, audit rows | 5, 6, 3 |
| 8 | Mandatory TOTP | Enrollment, challenge, recovery codes shown exactly once | 7 |
| 9 | Audience middleware and matrix test | `EnsureAudience` on every non-public route group; matrix test | 8 |
| 10 | Typed Inertia props | `generated.d.ts` current and consumed; `NoInternalIdsTest` | 8 |

Tasks 3 and 4 are the only pair that could run in parallel. They are run sequentially anyway — both touch `apps/studio` scaffolding, and two implementers writing to one Laravel skeleton conflict on files that neither can see the other holding.

---

## Definition of Done

Copied from the plan so the sprint can be closed without re-reading it:

- [ ] `docker compose run --rm php ./vendor/bin/pest` passes with zero skipped tests
- [ ] `pnpm lint && pnpm test && pnpm build` passes at the workspace root
- [ ] `pnpm --filter @studio/app test:e2e` passes against a locally served admin surface
- [ ] CI green on all three jobs (`studio`, `web`, `e2e`)
- [ ] A staff user signs in at `http://localhost:8000`, is forced through TOTP enrollment on first sign-in, sees recovery codes exactly once, lands on the dashboard
- [ ] `APP_SURFACE=api` serves `/api/health` and registers no admin route; `APP_SURFACE=admin` serves `/login` and registers no api route — both asserted by `SurfaceIsolationTest`
- [ ] An unrecognised `APP_SURFACE` throws rather than starting
- [ ] `audit_logs` holds rows for login success, failure, throttling, MFA enrollment, MFA failure, recovery-code use, logout
- [ ] Every non-public route on every surface carries an `audience:` declaration, enforced by the matrix test
- [ ] `users` and `audit_logs` carry a unique `public_id`; no Inertia prop emits an integer identifier
- [ ] `resources/js/types/generated.d.ts` is current, consumed by at least one page, and CI fails if it goes stale
- [ ] `apps/studio/docs/database-grants.md` records the `audit_logs` privilege revocation as a deployment step

---

## Out of scope

Named explicitly so it does not creep in: the `portal` surface and client guard · the public inquiry endpoint · `packages/contracts` and OpenAPI generation · staff invitation and password reset · real dashboard metrics (zeroes are correct here) · role-based policies (the enum exists; nothing branches on it until there are resources to authorize) · network restriction of the admin surface · Inertia SSR.

Every one of these has a home in [`../backlog.md`](../backlog.md).

---

## Risks

| Risk | Mitigation |
|---|---|
| No host PHP — every PHP command must run in Docker | Global constraint in the plan; each implementer is told in its dispatch |
| Postgres 18 in CI and locally, never SQLite | Compose pins it; tests run against the real service |
| TOTP task (8) is the largest and touches an unusual authentication state | It gets a standard-tier implementer, not a cheap one, and its own review round budget |
| Surface isolation is a test about absence, easy to write so it passes vacuously | The reviewer is pointed at it specifically: the test must fail if a route file is loaded twice |

---

## Sprint review question

At the end, one question decides whether the goal was met: **can a new staff member be handed a laptop, run `docker compose up`, sign in with TOTP, and can we then prove from the audit log that they did?** Everything else is a step toward that.

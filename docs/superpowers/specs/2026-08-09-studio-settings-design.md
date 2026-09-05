# Studio Settings — design

**Date:** 2026-08-09
**Parent:** [Product Vision](2026-08-07-photography-client-management-vision-design.md) §8 · [Clickdummy](2026-08-07-clickdummy-design.md)
**Issues:** [T-01](https://github.com/czepter/auri-crm/issues/112) … [T-14](https://github.com/czepter/auri-crm/issues/122), plus [T-10](https://github.com/czepter/auri-crm/issues/123)–[T-12](https://github.com/czepter/auri-crm/issues/125) unscheduled
**Status:** the architecture behind those issues. The issues carry the scope and the acceptance lines; this carries the reasoning they would otherwise repeat fifteen times.

---

## 1. Why this exists now

Vision §8 wrote one sentence, under "Multi-tenancy seams", and then nobody built it:

> Settings and branding resolve through a tenant-scoped config service rather than environment variables.

Every phase since has quietly borrowed against that. `Q-04` opens its scope with "`vat_mode` on the tenant" and there is no tenant row to put it on. `M-01` shipped a contract renderer whose merge data is `project`, `contact` and `job` — no `studio` namespace at all, because the studio's own name still lives in `APP_NAME`. `Q-05` hardcoded `AN-{year}-{counter}`, which `X-01` cannot import against without issuing a number a client already holds. `CheckDateAvailability` scans one table. `ShootCalendar` decides a shoot is two hours long, in code.

None of these is a missing feature. Each is a studio decision currently held as a constant, and the cost of that shows up as a wrong document rather than as an error.

This design covers the settings section: what it stores, how it is read, and where the boundaries sit against the subsystems that consume it.

## 2. Scope

**In:** the settings store and its resolver, the admin section, studio identity, VAT, number ranges, document defaults, mail identity, payments configuration, portal configuration, availability and closures, operational defaults, task-template editing.

**Out, and named so the omissions are decisions rather than oversights:** notification preferences (they need `E-07` to read them), staff absences (they need a crew), the general template designer (it needs `M-01` to extract from), retention policy (`OQ-5` owns that question), security TTLs and rate limits (§9.3), custom roles (§9.2), editable lead-source and lost-reason vocabularies (no evidence they are wanted), an audit-log viewer.

## 3. The store

### D1 — Values are mutable. Documents snapshot.

There is no effective-dated settings history, and this is the decision the rest of the design hangs off.

The obligation to preserve what a document said at the moment it was issued already sits on the documents, and each of them already discharges it. `Q-04` copies the VAT mode and rate onto the quote at send. `M-02` records which template version a signature was made against, which `ContractTemplate::newVersion()` makes possible by never mutating a row. And `docs/contracts.md` states the general case outright: `RenderContractJob` resolves merge fields at render time, bakes them into the HTML, and nothing re-renders a contract to serve a later read. The PDF *is* the record.

A settings row that changes therefore cannot reach backwards into anything already produced. Versioning it as well would be a second answer to a question that is already answered correctly, one sprint after the first answer shipped.

The residual risk is the unsent document — a draft quote showing today's VAT and tomorrow's. `Q-06` already closes that: send freezes the snapshot.

### D2 — Typed columns, not a key–value bag

One `studio_settings` row per tenant with real columns and real casts, rather than a `key`/`value` table.

A bag is smaller to write and worse at everything afterwards: no type at the database boundary, no constraint, no migration when a shape changes, and a `->get('vat_rate')` that returns a string on Tuesday. The settings here are a known, small, slowly-growing set — not user-defined fields — so the argument for a bag never applies.

### D3 — The cache crosses processes or it lies

`M-00` put a Horizon worker beside the web container, and the worker is what renders PDFs. A value cached in the web process and changed in the worker's copy — or the reverse — produces a document with the old address and no error anywhere in the system.

So the resolver caches, and its invalidation reaches every process. Not "the cache is cleared on save" — cleared *where*.

The resolver is injected rather than reached through a global, so a test can substitute it without touching a cache.

### D4 — One capability

`Capability::ManageSettings`, singular.

The enum's house style is narrow — `ManagePortalAccess` and `ManageQuotes` were both added rather than folded into something broader — and by that logic "may change the IBAN and the VAT mode" and "may edit the portal welcome text" deserve separate capabilities. They do not get them, because the crew is the owner plus occasional second shooters, and second shooters reach no settings at all. A split has no second user to serve.

Revisit at the first non-owner who needs partial access. That is the trigger; until it fires, a split is configuration nobody configures.

One exception, because it is not really an exception: task templates (`T-14`) sit in the settings section but authorise on `Capability::ManageTasks`. Editing what a booked shoot owes is the same work as managing tasks, done in a different room.

### D5 — Every write is audited

`AuditLog` exists and is append-only. A settings change records actor, timestamp, key, old value and new. This is the control that makes D4 acceptable: one broad capability is tolerable when every use of it leaves a row.

## 4. Identity

### D6 — The legal entity is not the brand

Three fieldsets, one form, three different purposes:

| | Holds | Read by |
|---|---|---|
| Legal entity | registered name, legal form, address, Steuernummer / USt-IdNr, Handelsregister and court, representative | documents (§14 UStG), portal Impressum (§5 TMG) |
| Brand | display name, logo, website, accent colour | portal chrome, PDF letterhead |
| Bank | account holder, IBAN, BIC | transfer instructions, invoices |

A studio trading under a name that is not its registered one needs both of the first two. Conflating them puts a trading name where a court expects a registered one, which is the sort of mistake that is invisible until it is expensive.

Two validations earn their place at the boundary. **At least one of Steuernummer or USt-IdNr** must be present — a document carrying neither is not invoiceable. And the **IBAN is checked by its mod-97 checksum**, not by length: a transposed digit pair is the actual failure mode, and it fails silently at the bank rather than loudly in the form.

The logo is stored once and referenced by both the renderer and the portal. Two upload fields for one image is how the portal and the PDF end up showing different logos.

### D7 — `studio.*` joins the merge vocabulary by the documented route

`docs/contracts.md` already states the protocol: *"Add a path by extending `RenderContractJob::mergeData()` and this table in the same commit."* `T-02` follows it and adds a `studio` namespace. No refactor of `MergeFieldResolver`, which resolves against a plain array and neither knows nor cares where the array came from.

## 5. Numbers

### D8 — The starting counter is a correctness requirement

`Q-05` shipped one hardcoded format. Three things break it: `X-01` imports documents the studio has already numbered, `M-01` needs a contract range, `M-04` needs an invoice range.

A German Nummernkreis must be gapless and must not collide. So the configured range is prefix + reset rule (`yearly` or `continuous`) + counter, and the counter is **settable while the range has issued nothing for the current period, and refuses to move backwards once it has**. Not a preference with a warning — a rule the form enforces.

`Q-05`'s allocator gains its values from configuration and keeps its concurrency guarantee unchanged. Two documents allocated at once still never share a number; that part was right.

## 6. Payments

### D9 — Stripe is optional; the client chooses at pay time

The tenant switches Stripe *availability*. The client picks per payment, in the portal: bank transfer, or pay now by card. There is no `payment_method` decided in advance, because deciding in advance is what creates the mess in D10.

`M-03`'s `PaymentPlan` and `PaymentInstallment` are already provider-agnostic — job-scoped, minor units, nothing Stripe-shaped — so the seam is clean. `settled_via` (`stripe` | `transfer` | `manual`) is written when an installment is paid, recording what happened rather than predicting it.

### D10 — Nothing is created in Stripe until the client clicks pay

`M-04` says "Stripe Invoicing per installment". An invoice opened up front sits open, and when the client transfers instead, Stripe keeps dunning for money already in the account. Voiding on manual-payment is the correct handling and is exactly the line nobody remembers to write.

Creating the Stripe object on demand removes the race instead of handling it. The price is Stripe-hosted dunning — which a transfer-only studio never had anyway, so reminders must be ours regardless. That makes the loss zero and the invariant free.

### D11 — Both paths emit `InstallmentPaid`; overdue is a scan

`G-05` gates galleries on a settled balance, `E-07` fans out from the event, `I-04` registers webhooks on it. If only Stripe's webhook emits it, a transfer-taking studio has galleries that never unlock — discovered by a client who cannot download, which is the worst place in the system to find out.

So the staff "mark as paid" action emits the same event, with the same idempotency `M-05` gives webhook delivery. One double-click must not unlock a gallery twice and mail the client twice.

`InstallmentOverdue` cannot come from a webhook at all: with Stripe off there is no webhook, and overdue is still overdue. It becomes a scheduled scan over due dates, provider-independent, with any Stripe signal as an additional input rather than the only one.

### D12 — Keys are secrets, handled as secrets

`encrypted` cast, write-only in the form, masked on read, never present in an Inertia prop or an API response. Explicit test/live mode with its own key pair. This is not a place to be brief.

## 7. Availability

### D13 — One check, several sources, still advisory

`CheckDateAvailability` scans `shoots.date` joined through non-lost leads, and that is all it knows. Studio closures become a second source; staff absences (`T-10`) a third. All three return one conflict shape, so `C-05`'s intake warning, the lead board and `S-04`'s calendar keep asking one question instead of three half-questions.

Its existing docblock argues the advisory rule at length and it holds here without amendment:

> a blocking form would teach staff to type a wrong date to get past it, corrupting the very column this reads

A studio takes a wedding during Betriebsferien if it is the right wedding. Conflicts warn. The conflict data gains its source, so the warning can say *why* rather than only *that*.

The single-query shape of `forDates()` survives. Its comment warns that per-card calls are "an N+1 nobody notices at eleven cards"; adding sources must not quietly reintroduce it.

### D14 — No public-holiday calendar

Considered and cut. A wedding photographer *works* Feiertage — a derived holiday list would warn on precisely the days that are booked. It would also drag in a Bundesland setting and a dependency to produce noise.

Closures are typed by hand. The studio knows when it is closed.

### D15 — The person owns their absence row, and there is no free-text reason

Deferred to `T-10`, decided now so the table lands in the right shape when it does.

A second shooter enters their own absence without holding `ManageStaff`, or the owner becomes calendar secretary for everybody. `ManageCrew` sees all; nobody else sees anything but their own.

"Sick" is health data under Art. 9 DSGVO. A free-text reason field is how a CRM quietly acquires a special-category obligation inside a system whose vision §8 promises documented retention per entity. Categories stay non-health, and no absence detail crosses to the portal surface — including through `S-04`'s ICS export, which is the leak that is easy to miss.

## 8. Portal

### D16 — The maintenance switch is not a third revocation form

`A-04` shipped two revocation forms deliberately and its issue says *never one button*. A global switch that quietly did the same thing to everyone would be the third, and the one with the largest blast radius.

So it is scoped and worded as **maintenance**: a temporary closure with a message. It must not read as "your access was withdrawn", because for the client those are entirely different events.

### D17 — A switched-off client capability refuses the route

Accept quotes, sign contracts, see payment status, start a thread — each defaults off until the feature behind it exists. Switching one off hides its affordance *and* refuses its route. Hiding a button is not an authorisation control, and the audience matrix test is where that gets proven rather than asserted.

The portal also renders an Impressum from D6's particulars. A portal without one is abmahnfähig, and the portal is a page a client can see.

## 9. Deliberate omissions

### 9.1 Notification preferences wait for `E-07`

`E-07` is specified as the single fan-out choke point. Preferences written before it exists have no reader; preferences consulted by individual transports would make its central claim false on the day it merged. `T-11` stays unscheduled until then, and its scope says so.

### 9.2 No custom roles

The `Capability` enum is the vocabulary for staff policies *and* for `I-01`'s API token scopes — a token cannot do something no staff role can. A settings screen that minted roles would fork that contract, and the fork would be discovered from the API side, months later.

### 9.3 Security TTLs stay constants

`ClientAccess::VALID_FOR_MINUTES`, the magic-link rate limits, `StaffInvitation::VALID_FOR_DAYS`, the MFA attempt ceiling and decay. Exposing these converts a security control into a support request, and every value a studio would plausibly want to change is one it should not.

### 9.4 The template designer waits for something to extract from

`M-01` shipped the first template type, with versioning already solved — `newVersion()` inserts into the same `family_id` and moves `is_current`, which is exactly the shape `M-02` needs. `T-12` generalises that across contract, quote, mail and the shared letterhead, and unifies the merge vocabulary so `{{ studio.name }}` means one thing everywhere.

Sequenced after `M-01` on purpose. Extracting a shared vocabulary from one working implementation is smaller and more honest than designing it for three documents that do not exist.

## 10. Testing

The Definition of Done applies unchanged. Four things are specific to this design and are the ones worth writing first:

1. **The cross-process cache.** A value changed on the admin surface, read by a job in the worker, without a deploy or a cache command (D3). The test that fails when someone adds a static.
2. **Capability isolation.** `/einstellungen` returns the same response for a role without `ManageSettings` as for a route that does not exist, and the audience matrix grows to cover the new group (D4).
3. **Snapshot independence.** Changing VAT mode, legal particulars or the logo alters no already-sent quote and no rendered contract PDF in any value (D1). This is the test that would have caught effective-dating being necessary, if it had been.
4. **Both payment paths.** Marking an installment paid manually emits exactly one `InstallmentPaid`, and emits the same one Stripe would have (D11). Double-submission is part of the test, not a follow-up.

## 11. Sequencing

Placed by dependency, not by capacity — the constraint here is what unblocks what, and no existing issue moved.

| Sprint | Issues | Unblocks |
|---|---|---|
| 7 — The quote | ~~T-00 mockups~~ (done, #126) · **T-01** store, resolver, shell · **T-03** VAT | `Q-04`, which cannot ship without `vat_mode` |
| 8 — Contracts | **T-02** studio profile · **T-04** number ranges | `M-01`'s `studio.*` merge fields, `X-01`'s import |
| 9 — Money | **T-07** payments | `M-03`, `M-04`, `M-05`, all three amended |
| 10 — The studio's own settings | **T-05** document defaults · **T-06** mail identity · **T-08** portal · **T-09** availability · **T-13** operational defaults · **T-14** task templates | — |
| unscheduled | **T-10** absences (needs a crew) · **T-11** notifications (needs `E-07`) · **T-12** template designer (needs `M-01`, now merged) | — |

Ordering inside sprint 7 is load-bearing: `T-01` and `T-03` before `Q-04`. Sprint 8 needs `T-02` before the contract merge fields are extended. Everything else within a sprint is free.

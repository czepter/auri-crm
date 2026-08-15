# The Übersicht Completes — Design

**Status:** approved for planning
**Mockup:** [`mockups/admin/01-uebersicht.html`](../../../mockups/admin/01-uebersicht.html) — open it beside the built screen at the same viewport; it is the visual target.
**Predecessor:** issue #26 (`S-01`), which built the frame and the one column that had data behind it.

## Goal

Finish the screen `S-01` deliberately left unfinished. Its body says:

> So the screen fills progressively. Each later phase adds its own column once
> the data behind it is real: *Anfragen & Angebote* with Q-\*, *Zahlungen* with
> M-04, *Nach der Lieferung* with G-\*.

Every one of those phases has since closed — Sprint 7 (the quote), Sprint 9
(money), Sprints 12–14 (gallery, publishing, delivery), Sprint 15 (the
notification spine), plus `E-08`/`E-09`'s automation engine. Nobody came back
for the columns. The Übersicht still shows one of four, and two KPI tiles of
four.

This is not blocked work. It is the progressive fill, now due.

## Decisions Taken

Settled before this spec was written. Inputs, not open questions.

1. **Four issues, one per block.** The cut follows `S-01`'s own progressive-fill
   wording: each column ships on its own, is held next to the mockup on its own,
   and belongs to one domain. A single "the screen completes" issue would be one
   review touching four domains with no partial delivery.
2. **Automation-exhausted, not domain-condition.** A row appears when the domain
   condition still holds *and* the machine has already tried and failed. This is
   mockup 01's own rule, stated in its header comment: the screen shows only what
   the machine cannot do itself, which is why the payment row reads *"Stripe mahnt
   seit 9 Tagen erfolglos → Anrufen"* rather than *"Mahnen"*. Dunning is Stripe's
   job; the phone call is not.
3. **Real actions where they exist, honest ones where they don't.** `Anrufen` is a
   `tel:` link off `Contact.phone` — a native platform affordance, not a button
   that logs an intention. `Fällig stellen` and `Vertrag senden` POST to the
   existing domain actions. `Bedanken` and `Album anbieten` open a prefilled
   thread through `StartThread`. Nothing on this screen invents an action.
4. **`S-10` carries its own enablers.** *Nach der Lieferung* has zero derivable
   rows today. Rather than filing it blocked behind two enabler issues, the two
   schema additions it needs are part of it.

## Non-Goals

- **No new dashboard framework.** Four reader actions in four domain namespaces,
  composed by the existing controller. No registry, no rule-declaration DSL, no
  generic column abstraction over four call sites that do not yet agree on
  anything.
- **No Ø-Lieferzeit tile.** Banned by the mockup's own header comment: lagging
  averages are monthly-review numbers, not daily tiles. The ban stands.
- **No Betrieb column.** Rejected uploads and ingest failures live on screen 05
  and stay there; the mockup is explicit that their absence here is a decision.
- **No read-state schema.** The header link counts *unbeantwortet*, not
  *ungelesen* — see §5.
- **No visual system changes.** `tokens.css` and `admin-extra.css` already carry
  `.doms`, `.dom`, `.dh`, `.dr`, `.who`, `.what`, `.abtn`, `.blkfoot`, `.kpis`
  and `.kpi`, byte-identical to `mockups/_shared/`. Every column is assembled
  from that existing vocabulary. If a column seems to need a new class, the
  column is wrong, not the design system.

## Part 0 — Shared Foundation

Three units the four columns share. They land with `S-08`, the first column, and
are consumed unchanged by the rest.

### `App\Crm\Data\DomainRowData`

One row shape for every column, because every row in mockup 01 has the same
anatomy: who it is about, what is owed, which fragment of that carries emphasis,
and the one action that discharges it.

```
projectId  string          the row's navigation target
who        string          the client, .who
what       string          the condition, .what
emphasis   ?string         the mockup's <i> fragment inside .what
action     RowActionData   label + kind + target
hot        bool            .abtn.hot — the action is also the overdue mark
```

`RowActionData.kind` is one of `post`, `tel`, `link`. The Vue side renders a
`Button` for `post`, an anchor for `tel` and `link`, and nothing else. A fourth
kind means a new affordance nobody agreed to.

### The "has the machine already tried?" predicate

The source of the mockup's *"2× erinnert, keine Reaktion"*: a count of
`AutomationRun` rows for a given rule key against a given anchor.

It stays inside `ReadInquiryColumn` and is not extracted. Only one column needs
it — *Zahlungen* reads the failed dunning off `PaymentInstallment.overdue_notified_at`,
and *Nach der Lieferung* asks whether a thread exists, neither of which is an
automation count. A shared scope here would be an abstraction over a single call
site. If `S-09` or `S-10` later grows a second one, that is when it moves to
`AutomationRun` as a scope.

### `AutomationAnchor::QuoteValidity`

`SweepDateOffsetAutomationRules` today anchors only on shoots — `per_shoot` and
`primary_shoot`, both querying `Shoot`. A quote's `valid_until` is not a shoot
date, so a quote-follow-up rule has nowhere to hang, and without one there is no
nudge trail to count.

So the enum gains a third case and the sweep a third branch, querying `Quote`
where `status = sent` and firing against the quote's `Project`. `config/automation.php`
gains one entry:

```
key            quote-follow-up
trigger_type   date_offset
anchor         quote_validity
offset_days    7
direction      before
```

Payments needs no equivalent: `PaymentInstallment.overdue_notified_at` and
`DetectOverdueInstallments` already record the failed dunning, and that trail is
the one the mockup's payment row actually describes.

## Part 1 — `S-08` · Anfragen & Angebote

`App\Quotes\Actions\ReadInquiryColumn` → `Collection<DomainRowData>`, viewer-scoped,
derived, writing nothing. Mirrors `ReadProductionBoard`.

| condition | row | action |
| --- | --- | --- |
| `Quote.status = sent`, `valid_until` inside the warning window, ≥1 `quote-follow-up` run fired against the project | *Angebot still, läuft {date} ab · {n}× erinnert, keine Reaktion* | **Anrufen** · `tel:` · hot |
| `Quote.accepted_at` set, project has no Contract | *angenommen {date} — Vertrag fehlt* | **Vertrag senden** · post · hot |
| Contract sent, unsigned for N days | *Vertrag {n} Tage unsigniert* | **Anrufen** · `tel:` |

Ordered by urgency within the block, never across blocks. Footer links `/anfragen`
and states the week's new inquiries.

`Vertrag senden` has no backing action today — `App\Contracts\Actions` holds only
`SignContract`. The story adds `SendContract` or, if sending turns out to route
through the document pipeline, wires the button to that path. Which one is an
implementation finding, not a design choice; either way the button performs
rather than navigates.

## Part 2 — `S-09` · Zahlungen

`App\Payments\Actions\ReadPaymentColumn` → `Collection<DomainRowData>`.

| condition | row | action |
| --- | --- | --- |
| installment overdue, `overdue_notified_at` older than N days | *Stripe mahnt seit {n} Tagen erfolglos · {amount}* | **Anrufen** · `tel:` · hot |
| `status = scheduled`, `due_on` passed, never invoiced | *{ordinal}. Rate {amount} — nie fällig gestellt* | **Fällig stellen** · post · hot |

The second row is the one the machine genuinely cannot do: an installment that
was never issued has no Stripe invoice to dun, so no automation covers it and it
would otherwise sit unnoticed until someone reconciles by hand.

Footer states the sum expected by month end and the number of installments
behind it.

## Part 3 — `S-10` · Nach der Lieferung

The column with no data source. Per decision 4 it carries the two additions it
needs, in this order.

### 3a. `leads.referred_by_contact_id`

`LeadSource::Referral` records *that* a client was referred and nothing about by
whom, so *"Familie Osterhoff hat Steffens & Falk empfohlen"* cannot be derived.
A nullable FK to `contacts`, set at capture when the source is `Referral`, and
surfaced on the inquiry form as an optional contact search — the same
`ContactSearchController` the rest of the CRM uses.

Nullable on purpose: a referral whose referrer nobody recorded is still a
referral, and a required field would push whoever takes the call into inventing
one.

### 3b. `galleries.expires_at`

No expiry concept exists — `expires_at` today lives only on
`gallery_email_verifications`, which is a different clock. The column gains a
nullable `expires_at` on `galleries` plus a retention default in studio settings
that stamps it at publish. Null means kept indefinitely, which is what every
existing gallery is, so the migration backfills nothing.

### 3c. The column

`App\Gallery\Actions\ReadAfterDeliveryColumn` → `Collection<DomainRowData>`.

| condition | row | action |
| --- | --- | --- |
| a booked project whose lead names a referrer, and no thank-you thread exists | *hat {referred} empfohlen* | **Bedanken** · post → `StartThread` |
| gallery `expires_at` approaching, no album offer thread | *Galerie läuft am {date} ab — Album nie angeboten* | **Album anbieten** · post → `StartThread` |

Both actions open a prefilled thread rather than sending anything. The mockup
files these under "occasions kept deliberately personal"; a thank-you the
machine sends is not a thank-you.

Footer states that review requests run automatically, and the week's replies —
the one place on this screen where automation reports success rather than
exhaustion, because that is what tells the studio the silence is intended.

## Part 4 — `S-11` · The KPI Row and the Unread Link

Four tiles where two stand today. `.kpis` is already `repeat(4,1fr)`; nothing in
CSS changes.

| tile | source |
| --- | --- |
| Pipeline € · n Anfragen · m neu diese Woche | sum of open quote totals |
| überfällig € · erwartet bis {month end} | sum of overdue installments |
| nächstes Shooting · n Punkte offen | exists |
| Angebote angenommen % · letzte 12 Monate | accepted ÷ sent, trailing 12 months |

The header gains the mockup's `.txtbtn`: *n unbeantwortet*, counting threads
whose newest `Message` has `contact_id` set — the client spoke last. This is
*unbeantwortet*, not *ungelesen*: `ThreadParticipant` carries no `last_read_at`
and this design does not add one. An unanswered thread is a fact about the
conversation; an unread one is a fact about a person, and only the first is
something the studio owes.

## Testing

Per column, before implementation:

- **Reader-action test** — rows appear on the condition, and *do not* appear when
  the automation predicate is unmet. That negative is the test that matters:
  decision 2 is the whole design, and a column that shows what the machine is
  still handling has silently become a status report.
- **Viewer scoping** — a crew role sees their own projects' rows, consistent with
  `ReadProductionBoard`.
- **Inertia prop test** — the prop is present and carries no integer identifier
  (`UebersichtTest` already asserts this screen-wide).
- **Absent, not empty** — `UebersichtTest`'s existing *"shows no column for a
  domain with no data source"* extends to each new column. A column with nothing
  owed renders nothing, not a zero.

## Acceptance

The Übersicht is opened beside `mockups/admin/01-uebersicht.html` at the same
viewport, with a seeded dataset that puts at least one row in every column, and
a screenshot goes on each PR. `DemoCrmSeeder` grows the rows each column needs —
an expiring quote with two nudges fired, an overdue installment, an
un-issued installment, a referral, an expiring gallery.

## Risks

- **The seeder becomes the spec.** Five conditions across four domains is a lot
  of demo state, and a column that only ever renders against seeded data is a
  column nobody has tested against a real studio. Each reader action's test
  builds its own state through the real code paths, as `DemoCrmSeeder` already
  does, so the seeder stays a demo rather than the only proof.
- **`SendContract` may not exist as a seam.** Part 1 names it as an
  implementation finding. If contracts turn out to send through the document
  pipeline, the button wires to that; if neither path exists, the row is dropped
  from `S-08` and stated as dropped rather than shipped as a navigation.
- **Warning windows are unspecified.** "`valid_until` inside the warning window",
  "unsigned for N days", "`overdue_notified_at` older than N days" — each needs a
  number. They are studio judgement, not architecture, and belong in the
  implementation plan with a default the studio can change later.

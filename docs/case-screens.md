# Case screens — card grammar

The inquiry and project screens rewritten in the grammar the quote editor
settled on. The drafts are a design canvas:
<https://claude.ai/code/artifact/d97e785c-57e4-4a78-b649-b6365835f900> —
page 1 holds intake, inquiry, project and an edit-state comparison sheet,
page 2 the seven project tabs.

Nothing visual is new. The quote canvas already renders the studio's own
tokens (`--font`, `--ink`, `--rule`, `--accent` from `crm.css`). What is new
is the layout grammar, and it replaces `CaseSection` and `ui/page/Section`.

## The grammar

**A card is a part of the case.** Border `--rule`, radius `--r`, white ground.
Its header carries three things and nothing else: the title, a summary of
what is inside, and the one action that belongs to it. Chevron far right.

**The summary is what the card says when it is shut.** `2 Termine · ab
11.06.2027`, not `2`. `CaseSection` shows a count today; a count answers
nothing when the card is closed.

**Editing is per card.** There is no screen-wide edit mode. Reading state
shows a quiet `Bearbeiten` in the header; editing state replaces it with
`Verwerfen` and a `Sichern` pill at the same spot. The card body swaps
between read and fields — never both, and never the same value twice in two
places.

**Shut by default what is read rarely.** Inquiry and dates open, quote,
tasks and notes shut.

**Composers, not field rows.** Anything that appends to a list — a note, a
task, a message — is one bordered box: the text field on top with no border
of its own, its options in a footer inside the box (date, audience,
attachment), the submit button bottom right. `:focus-within` colours the
box. Three labels and three borders become one border.

**Where an action sits says what it makes.** A new card (`Shooting
anlegen`, `Galerie anlegen`, `Fragebogen anlegen`) is a pill top right next
to the title. A new row inside an open card (`+ Kontakt`, `+ Termin`) is an
outline button at the bottom of that card.

**States are badges, never buttons with switching labels.** `geteilt` /
`nicht geteilt`, `Geplant` / `Erledigt`, `bezahlt` / `offen`. The switch
lives behind `Bearbeiten`.

**Free text with suggestions beats an enum with an escape hatch.**
`<input list="…">` plus `<datalist>` — the pattern `Leads/Show.vue` already
uses for type and source presets. Applies to contact roles, and to type,
source and channel at intake. It removes the `Sonstige` → `Details` pair.

**No card inside a card.** A shoot is one card; crew and timeline are flat
blocks inside it, headed by a small caps label on a rule.

## Screens

### Leads/Show.vue, Projects/Show.vue

`editing` disappears. `CaseSection` and `ui/page/Section` become one card
component: `title`, `summary`, `defaultOpen`, an optional `edit` slot, and a
header action slot. `ProjectShell`, `StageTrack`, `Rail` and the tokens stay
untouched.

Cards on the inquiry: Anfrage, Kontakte, Termine, Angebot, Aufgaben,
Notizen. On the project: Eckdaten, Termine, Aufgaben, Notizen, with the
figure band flat above them — the band is the reason the screen is open, so
it is not a section anyone collapses.

Two corrections the drafts carry:

- Dates stay editable after booking. `Leads/Show.vue` hides them today
  (`v-if="editing && !lead.booked"`); that guard goes with the global mode.
- Contacts leave the 238 px side column and become their own card: any
  number of rows, name as a typeahead against `/kontakte/suche`, role free
  text, add and remove per row. The side column cannot hold a row editor.
  The Anfrage card takes type, source and arrival in its place.

Notes keep no card-level edit. `NoteThread.vue` already edits and deletes
per entry and carries `occurred_at`; that stays on the note. System entries
have no tools.

### Leads/Create.vue

Three cards: Kontakt, Anfrage, and the submit bar. `<details>` "Erweitert"
goes away — what hid behind it is either visible or one link away:

- The type's own fields (`ProjectFields`) render under the type choice and
  change with it.
- The first shoot is captured here: date, start, end, venue, city. The
  conflict note sits in the column beside the date, not under it.
- Arrival date renders as a text line — label, value, `ändern` — not as a
  second date field. Two bordered date fields on one intake form is how the
  main date gets typed into the wrong box.

### Project tabs

| Tab | Shape |
|---|---|
| Shootings | One card per shoot. Band (kind, start, end, venue), then Crew and Ablauf as flat blocks. Past shoots start shut. |
| Aufgaben | One card: filters, composer, groups (Überfällig, Diese Woche, Später). `Erledigt` is a second card, shut. |
| Galerien | Rows per gallery with state badge; second card lists shoots without one. |
| Nachrichten | Bubbles, client left, studio right, composer on top with an attachment control instead of a date. |
| Fragebögen | One card per instance. Fields in the same two-column label-over-value grid as Eckdaten; an unanswered field renders as an input so the studio can enter what came by phone. |
| Angebot | One card per quote: number, state badge, lines, total. Superseded quotes shut. |

Tab order and counts follow `ProjectHeaderData::from()`: Übersicht, (Anfrage),
Shootings, Aufgaben, Galerien, Nachrichten, Fragebögen, Angebot.

## Copy

Six strings in `resources/js/locales/de.json`:

| Key | Today | Why it goes |
|---|---|---|
| `leads.capturedAt` | Anfragedatum | Reads as the date that was requested — which is the main date. Becomes **Eingegangen**, matching `leads.capturedVia` above it. |
| `leads.contactSearchHint` | Erst suchen, sonst entsteht ein zweiter Kontakt … | The field searches. The suggestion list shows how often a contact has been here, and `Neu anlegen` sits below the hits, not beside them. |
| `leads.noEmailHint` | Ohne E-Mail: kein Portal-Zugang … | A consequence nobody asked about, on a field everybody fills. Missing portal access belongs on the contact, where it is noticed. |
| `leads.createKicker` | Neue Anfrage | Says what the h1 below it says. A breadcrumb replaces it, as on every other screen. |
| `leads.otherDetail` | Details | The free field inherits its group's label and suggests what has been typed before. |
| `leads.advanced` | Erweitert | Names a disclosure that no longer exists. |

`project.lead` in the project's Anfrage card renders `Erfasst` today — same
wording problem as `capturedAt`, same fix.

## Backend

- `project_contacts.role` is already `string`, so free roles need no
  migration. Two things do follow: the partial unique index
  (`where role = 'primary'`) keeps `primary` reserved as a technical value,
  and the badge reads `$t('contacts.role.' + role)` — a free role needs a
  fallback to the raw string.
- Contact suggestions reuse `/kontakte/suche`; `ContactTypeahead.vue` is the
  behaviour to lift, including its ordering rule: matches are seen before
  `Neu anlegen` is reachable.
- The first shoot created from intake is one `Shoot` with `is_primary`.

## Plan

The file-level build contract is
[case-screens-implementation.md](case-screens-implementation.md).
The work is tracked in [#370](https://github.com/czepter/auri-crm/issues/370);
the six steps below are its issues.

**1 — The card component** ([#371](https://github.com/czepter/auri-crm/issues/371)). One component replacing `CaseSection` and
`ui/page/Section`, with the header, the summary, the shut state and the
edit-slot contract. No screen changes. Test: header renders summary and
action, body toggles.

**2 — Copy** ([#372](https://github.com/czepter/auri-crm/issues/372)). The six strings, plus the `Erfasst` label. Independent of
everything else and shippable alone.

**3 — Leads/Show.vue** ([#373](https://github.com/czepter/auri-crm/issues/373)). Cards, `editing` removed, contacts card with
typeahead and free roles, dates editable after booking. Largest single
change; the fallback for an untranslated role lands here.

**4 — Projects/Show.vue** ([#374](https://github.com/czepter/auri-crm/issues/374)). Same move, band kept flat.

**5 — Leads/Create.vue** ([#375](https://github.com/czepter/auri-crm/issues/375)). Three cards, type fields and first shoot visible,
arrival date as a text line, `<details>` removed.

**6 — The six tabs** ([#376](https://github.com/czepter/auri-crm/issues/376)). One per commit, in the order of the table above.
Shootings first because it carries the no-card-in-card rule.

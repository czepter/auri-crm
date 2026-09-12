# Case screens — implementation

The build contract for [docs/case-screens.md](case-screens.md). That file
says what the screens become and why; this one says which files change, what
the new interfaces look like, and what each step has to prove.

Issues: [#370](https://github.com/czepter/auri-crm/issues/370) (grammar),
[#377](https://github.com/czepter/auri-crm/issues/377) (spec),
#371–#376 (the six steps below).

Paths are relative to `apps/studio`.

---

## Step 1 — `resources/js/ui/page/Card.vue` (#371)

New component. `CaseSection.vue` and `ui/page/Section.vue` stay until step 6
removes their last caller.

```ts
defineProps<{
  title: string
  summary?: string | null      // shown beside the title, single line, ellipsis
  defaultOpen?: boolean        // default true
  editable?: boolean           // default false
}>()

defineEmits<{ edit: []; cancel: []; save: [] }>()
```

Slots: default (read view), `edit` (fields), `action` (header, left of the
chevron).

Header: title `text-[13.5px] font-semibold tracking-[-0.015em]`, summary
`text-ink-2 truncate`, then `<slot name="action" />`, then the edit controls,
then the chevron. Card frame `border border-rule rounded-[16px] bg-bg`; body
`px-[18px] pb-[18px]`.

Behaviour:

- Click on the header toggles open. Open state is local; `defaultOpen` seeds
  it.
- `editable` and not editing: a `Bearbeiten` button in `text-action`.
- `editable` and editing: `Verwerfen` (`text-ink-2`) and a `Sichern` pill.
- The three buttons call `.stop` on the click so they never toggle the card.
- Editing renders the `edit` slot instead of the default slot — never both.
- Chevron rotates 180° when open, `transition: transform .15s ease`.

The parent owns the editing flag (one `ref` per card), so a `save` handler
can post and close on success. `Card.vue` holds no form state.

Test — `resources/js/ui/page/Card.test.ts`, prior art
`resources/js/admin/components/RecordNav.test.ts`:

- renders title and summary
- header click closes, second click opens
- `defaultOpen: false` starts closed
- `editable` shows `Bearbeiten`; clicking it emits `edit` and does not close
- while editing, `Verwerfen`/`Sichern` emit `cancel`/`save`

---

## Step 2 — copy (#372)

`resources/js/locales/de.json`:

| Key | Change |
|---|---|
| `leads.capturedAt` | `Anfragedatum` → `Eingegangen` |
| `leads.contactSearchHint` | delete |
| `leads.noEmailHint` | delete |
| `leads.createKicker` | delete |
| `leads.otherDetail` | delete |
| `leads.advanced` | delete |

Callers to strip: `ContactTypeahead.vue` (search hint), `Leads/Create.vue`
(kicker, no-email hint, `otherDetail` labels, `advanced` summary). The
`otherDetail` and `advanced` markup goes fully in step 5; step 2 removes the
two hint lines and the kicker, and leaves the two remaining keys' call sites
building against a literal until step 5 lands. Do the delete in one pass —
`grep -rn "otherDetail\|advanced\|noEmailHint\|contactSearchHint\|createKicker"
resources/js` must come back empty at the end of step 5, and steps 2 and 5
land in that order.

`Projects/Show.vue` renders `leads.capturedAt` under the label `Erfasst` in
the inquiry section — that string moves to the same key.

Nothing else. No layout in this step.

---

## Step 3 — `resources/js/admin/pages/Leads/Show.vue` (#373)

### Frontend

Six `Card`s: Anfrage, Kontakte, Termine, Angebot, Aufgaben, Notizen.
`defaultOpen` true for the first three.

Delete `editing`, `edit` (the `useForm`), `save()` and every `v-if="editing"`
in the file. Each card that edits holds its own `ref` and its own
`useForm`/`router.patch`.

- **Anfrage** — read: inquiry text, then a three-cell row (Typ, Quelle,
  Eingegangen). Edit: `Textarea` plus three fields. The 238 px side column
  goes; it carried the people.
- **Kontakte** — new. Read: one row per `people[]` entry — name, role badge,
  mail, phone. Edit: a grid of name / role / mail / phone / remove, then
  `+ Kontakt`.
  - Role is `<Input list="rollen">` plus a `<datalist id="rollen">` of the
    five `ProjectContactRole` labels. Same shape as the existing
    `type-presets` datalist in this file.
  - Name is a typeahead against `/kontakte/suche`; lift the debounce and the
    result ordering from `ContactTypeahead.vue` (hits first, `Neu anlegen`
    below them). A chosen hit fills mail and phone and keeps the contact's
    `public_id`, so the row patches an existing contact instead of creating
    one.
- **Termine** — edit state no longer depends on `lead.booked`.
- **Aufgaben** and **Notizen** — the add row becomes a composer: bordered
  box, borderless textarea/input on top, footer inside the box (date, and
  audience for tasks), submit button bottom right, `focus-within:border-accent`
  on the box.
- **Notizen** keeps `editable: false` on the card. `NoteThread.vue` already
  edits and deletes per entry and carries `occurred_at`; only its input row
  becomes the composer.

`ProjectShell`, `LeadQueue`, the rail and the ↑↓ handler are untouched.

### Backend

`app/Crm/Http/Controllers/LeadCaseController.php::update` is a whole-case
PATCH today. Per-card editing sends a subset, so:

- Make `type`, `source` and the arrays optional: validate with `sometimes`
  and only write the keys that arrived. A Kontakte-card save must not have to
  resend the inquiry text.
- Drop `abort_if($booked && ($validated['shoots'] ?? []) !== [], 422)` — dates
  stay editable after booking.
- `people.*.role` joins the rules as `['nullable', 'string', 'max:60']` and
  writes to the `project_contacts.role` pivot. No `Rule::enum`.

New admin routes in `routes/admin.php`, beside the existing lead block:

```php
Route::post('/anfragen/{lead}/kontakte', [LeadContactController::class, 'store']);
Route::delete('/anfragen/{lead}/kontakte/{contact}', [LeadContactController::class, 'destroy']);
Route::post('/anfragen/{lead}/shootings', [LeadShootController::class, 'store']);
Route::delete('/shootings/{shoot}', [ShootController::class, 'destroy']);
```

`LeadContactController::store` mirrors
`app/Integration/Http/Controllers/ProjectContactController.php` — same
`resolveContact()` rule (an address on file is that person), same guard
against a duplicate `(project, contact, role)` attach — with two differences:
`role` is free text, and `primary` is refused by name rather than by enum,
because the partial unique index
(`project_contacts (project_id) where role = 'primary'`) still owns that
value.

`destroy` refuses the primary contact for the same reason.

`Project::attachContact(Contact $contact, ProjectContactRole $role)` gains a
sibling that takes a string, or its parameter widens to
`ProjectContactRole|string`. Prefer widening; three callers exist
(`CaptureInquiry`, `ProjectContactController`, tests).

### Role display

`ProjectPersonData` carries the raw string. Every renderer that does
`$t('contacts.role.' + role)` needs a fallback — put it in one helper, e.g.
`resources/js/admin/lib/roles.ts`:

```ts
export function roleLabel(role: string): string {
  const key = `contacts.role.${role}`
  const label = t(key)
  return label === key ? role : label
}
```

Callers today: `Leads/Show.vue`, `ProjectShell.vue`, `Contacts/*`.

### Tests

`tests/Feature/Crm/LeadContactTest.php` (Pest, `-e APP_SURFACE=admin`):

- a free role (`Trauredner`) attaches, survives the roundtrip and comes back
  as the raw string
- `primary` is refused with 422
- a partial PATCH of only `people` leaves `inquiry_text` untouched
- a shoot on a booked lead patches instead of 422

`resources/js/admin/lib/roles.test.ts`: known key translates, unknown key
returns the raw value.

---

## Step 4 — `resources/js/admin/pages/Projects/Show.vue` (#374)

Four `Card`s: Eckdaten, Termine, Aufgaben, Notizen. `defaultOpen` true for
the first two.

The `BandCell` row stays exactly where it is, above the cards, outside any
card. Its `v-if="editing"` derivation notes (`#derived` slots) go — there is
no screen-wide editing left to qualify.

`ProjectFields` renders in the same three-column grid in both states: label
`.cap` above the value, value either text or `Input`. Column widths must not
change between the two, so the read view uses the same grid, not a definition
list.

Delete `editing` here too, including its uses in the `#people` rail slot, the
source `RailSection` and the `#title` slot.

Test: the existing `tests/Feature/Crm` project screen tests stay green. No
new Pest test — nothing new reaches the server in this step.

---

## Step 5 — `resources/js/admin/pages/Leads/Create.vue` (#375)

### Frontend

Breadcrumb `Anfragen` + `<h1>`; `PageHead` with its kicker goes.

Two `Card`s (`editable: false`, both open — this is a form, not a read view)
and a footer bar.

**Kontakt** — the typeahead, then the person rows once `creating` is true,
then `+ Person`.

**Anfrage**, in this order:

1. `Projektname`
2. `Typ`, `Quelle`, `Eingangskanal` as `ChoiceGroup variant="segmented"`.
   `Sonstige` reveals an `Input` with the group's own `<datalist>`; the field
   carries no label of its own.
3. `Eingegangen` as a text line — `.cap` label, value, `ändern` link — that
   swaps to a `DatePicker` on click. Default value `today`.
4. `ProjectFields` for the chosen type, visible, re-keyed on type change.
5. `Haupttermin` group: `DatePicker` (280 px) with `ConflictNote` in the
   column beside it, then `call_time`, `end_time`, `venue_name`, `venue_city`.
6. `Anfragetext`

`<details>` and its `advanced` summary go.

`DatePicker size="lead"` drops back to the default size — the field is no
longer the only weighted control on the screen.

### Backend

`app/Crm/Data/CaptureInquiryData.php` gains four nullable fields beside
`date`:

```php
public ?string $call_time = null,   // date_format:H:i
public ?string $end_time = null,    // date_format:H:i
public ?string $venue_name = null,  // string, max:255
public ?string $venue_city = null,  // string, max:255
```

`app/Crm/Actions/CaptureInquiry.php` writes them onto the one `Shoot::create`
it already performs. Nothing else in that action changes; the shoot is still
exactly one and still `is_primary`.

The class comment on `CaptureInquiryData` says venue and further shoots
"belong to the project screen" — that sentence is now wrong for venue and
times. Update it.

### Tests

`tests/Feature/Crm/CaptureInquiryTest.php` (extend):

- capture with date, times and venue creates one primary shoot carrying all
  four
- capture without them still succeeds and the shoot has them null

---

## Step 6 — the seven tabs (#376)

One commit per tab, in this order. Each replaces `ui/page/Section` with
`Card` and moves its primary create action into the page header.

1. **`Projects/Shoots.vue`** — one `Card` per shoot: header date, label,
   primary marker, status badge. Body: a `BandCell` row (kind, call, end,
   venue), then Crew and Ablauf as flat blocks — a `.cap` label on an
   `inset 0 1px 0` rule, then the rows. The two inner `Section`s go; no card
   inside a card. Shoots whose date is past get `defaultOpen: false`.
   `project.addShoot` moves from the accordion header into the page actions.
2. **`Projects/Tasks.vue`** — one `Card` holding filters, composer and the
   three groups; a second `Card` `Erledigt`, `defaultOpen: false`. Composer
   as in step 3.
3. **`Projects/Galleries.vue`** — `Card` `Galerien` with a row per gallery
   (title, state badge, count, expiry, portal), `Card` `Ohne Galerie` with
   the shoots that have none. `galleries.create` into the page actions.
4. **`Projects/Messages.vue`** — `MessageThread` in a `Card`; bubbles, client
   left, studio right; composer on top with an attachment control where the
   note composer has its date.
5. **`Projects/Forms.vue`** — one `Card` per instance. Fields render in the
   same two-column label-over-value grid as `ProjectFields`, not as rows with
   a fixed label column. An unanswered field renders its input instead of the
   word `offen`. The share state becomes a badge; the toggle moves behind
   `Bearbeiten`. `formInstances.create` becomes a header pill and its
   template picker opens from there.
6. **`Projects/Quotes.vue`** — one `Card` per quote (number, state badge,
   lines, total); superseded quotes `defaultOpen: false`.
7. **`Projects/Contract.vue`** — keeps its two-column layout. The document
   gets a `Card` header carrying version and state as the summary and
   `Download` / `Sichern` / `Versenden` as actions. Signers, deadline and
   versions stay in the rail.

After tab 7: delete `resources/js/admin/components/CaseSection.vue` and
`resources/js/ui/page/Section.vue`, and check `grep -rn "CaseSection\|page/Section"
resources/js` is empty.

Existing e2e specs — `tests/e2e/project-detail.spec.ts` above all — are the
regression net here. Run them; do not add new ones.

---

## Order and coupling

1 and 2 are independent of each other and of everything else. 3 needs 1. 4
needs 1. 5 needs 1 and 2. 6 needs 1.

If [#369](https://github.com/czepter/auri-crm/issues/369) (one shell for
inquiry and project) lands first, step 3 loses the header, tab bar and close
dialog it would otherwise still carry.

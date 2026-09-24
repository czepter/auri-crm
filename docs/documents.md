# Documents

One template family covers every document type the studio sends:
`App\Documents\Models\DocumentTemplate` holds the wording (versioned), for
one `App\Documents\Enums\DocumentTemplateType` value — `quote`, `invoice`,
`credit_note`, `mail` — with an optional `shoot_kind` variant. Quotes,
invoices and credit notes render to PDF; a mail is sent as HTML.

## Merge fields

A template's `body` is HTML with placeholders in `{{ path.to.field }}` form —
dot-addressed, resolved by `App\Documents\Support\MergeFieldResolver` against
a plain array, never against a live model. An unresolvable path renders as
an empty string rather than failing the render. The resolver takes no
document type at all, which is what makes "the same merge field resolves
identically in a quote, an invoice and a mail" true by construction: every
caller resolves the same body against the same shape of array.

That array is built in exactly one place, `App\Documents\Support\
DocumentMergeDataBuilder::fromProject()` — never assembled ad hoc by a
controller or a job — from whatever the project/contact/studio settings
look like at the moment a render runs:

| Path                | Value                                          |
|----------------------|-------------------------------------------------|
| `project.name`       | The project's name.                             |
| `project.type`       | The project type, labelled (e.g. "Hochzeit").   |
| `project.bookedAt`   | The booking date, `dd.mm.yyyy`, or empty if unbooked. |
| `contact.name`       | The project's primary contact's name.           |
| `contact.email`      | The project's primary contact's email.          |
| `contact.street`     | Billing address, street and number (M-06). Empty until someone fills it in on the contact screen. |
| `contact.postcode`   | |
| `contact.city`       | |
| `studio.name`        | The studio's registered name (`legal_name`), falling back to its display name. |
| `studio.legalForm`   | e.g. "GmbH", "Einzelunternehmen".               |
| `studio.street`      | Registered street and number.                   |
| `studio.postcode`    | |
| `studio.city`        | |
| `studio.country`     | |
| `studio.taxNumber`   | Steuernummer.                                    |
| `studio.vatId`       | USt-IdNr.                                        |
| `studio.registerNumber` | Handelsregister number.                       |
| `studio.registerCourt`  | Registering court (Registergericht).          |
| `studio.representative` | Authorised representative.                    |
| `studio.website`     | |
| `studio.logoPath`    | The logo's stored path on the `local` disk (T-02, `StudioSettingsResolver::logoPath()`) — a filesystem path, not yet a URL. |
| `studio.bankHolder`  | Account holder (T-02, Bank fieldset). |
| `studio.bankIban`    | |
| `studio.bankBic`     | |

## Starter templates

`App\Documents\Support\StarterTemplateCatalog` holds ready-made templates in
code — a quote (generic and a wedding variant), an invoice (standard and
Kleinunternehmer), a credit note, and short mails. The create
screen opens on them, with **Leere Vorlage** among the cards as an equal
option rather than an escape hatch.

**A starter is a starting point, not a link.** Choosing one copies its blocks
into an unsaved draft; saving writes a tenant-owned first version through the
ordinary `createFirstVersion()` path. Nothing records which starter a template
came from, so editing an entry in a later release cannot reach a template a
studio has since made its own — there is no id to reach through.

**Entries are wording, not law.** The legal skeletons carry one line marking
them as a starting point in their `description`, where the studio reads it
while choosing — never in a body a client would receive. A test asserts both
halves of that.

Four invariants are enforced by `StarterTemplateCatalogTest` rather than by
review, because each fails silently otherwise:

| invariant | what it prevents |
|---|---|
| every block passes `DocumentBlockCatalog::allows()` for its type | a starter the server rejects on save |
| every `{{ … }}` path exists in `MergeFieldVocabulary::paths()` | a placeholder that renders empty in front of a client |
| every block type is one `StarterBlockSerializer` renders | `T-18` seeding a template with a hole in it |
| no lorem ipsum, no "hier Text einfügen" | a catalog that costs more to delete than to write |

`StarterBlockSerializer` is a **deliberate mirror** of
`resources/js/admin/documents/blocks/serialize.ts`, bounded to the block types
the catalog uses and throwing on anything else. The create screen serialises in
the browser, where the editor lives; `T-18` seeds server-side with no browser
in the loop. Same arrangement `fillSamples()` documents for
`MergeFieldResolver::PATTERN`.

Merge fields are written as plain `{{ path }}` text in an entry rather than as
the editor's `data-mf` chips. The chip form exists so the editor can show a
sample value in place of a path; a starter is written by hand in PHP, where
there is nothing to show it to. Both serialise to the same `{{ path }}`.

### The gallery block

C-17 (#253) stores the view password and the download code recoverably, which
is what lets a "Galerie online" letter carry link, password and code in one
message instead of sending the client hunting through three. Whoever forwards
that mail hands on the access — for a gallery that is shared by messenger
anyway, that is the normal case rather than a new hole.

| Path                 | Value                                          |
|----------------------|-------------------------------------------------|
| `gallery.title`      | The newest gallery of the project. Empty where none exists. |
| `gallery.link`       | apps/gallery's own address (`Gallery::publicUrl()`), never this app's own host — no guest can open that one. |
| `gallery.password`   | The view password in clear, or empty where the gallery is ungated. |
| `gallery.downloadPin` | The download code in clear, same rule. |
| `gallery.expiresOn`  | `galleries.expires_at`, `dd.mm.yyyy`. |

Staff login passwords and `client_accesses.token_hash` are not covered by this
and stay hashes: they are never shown, so nothing is bought by making them
readable.

### The invoice block

`invoice.*` is the one part of the vocabulary that is not derivable from the
project. An invoice is about one `PaymentInstallment`, so `fromProject()`
takes it as an optional third argument; every other document type passes
`null` and gets empty strings rather than a missing key, which is what keeps
a merge field used on the wrong document type rendering as nothing instead of
leaving `{{ invoice.number }}` in a PDF.

| Path                 | Value                                          |
|----------------------|-------------------------------------------------|
| `invoice.number`     | The allocated number (`RE-2026-17`), from T-04's range for `DocumentType::Invoice`. |
| `invoice.date`       | Rechnungsdatum — when `IssueInvoice` ran, `dd.mm.yyyy`. |
| `invoice.servicePeriod` | Leistungszeitraum: the **primary shoot's** date, not the invoice's. The supply happened on the shoot day. Empty when the project has no primary shoot. |
| `invoice.description` | Project name and the installment's own label ("Anzahlung", "2. Rate"), from `PaymentPlanData::labelForInstallment()`. |
| `invoice.net`        | Formatted euros. Derived *backwards* out of the installment's gross — see `App\Payments\Support\InvoiceFigures`. |
| `invoice.vatRate`    | e.g. "19 %", or "0 %" under the small-business rule. |
| `invoice.vat`        | The remainder, so net + VAT is always exactly the amount charged. |
| `invoice.gross`      | The installment's `amount` — what the client actually transfers. |
| `invoice.vatNote`    | The §19 UStG sentence under the small-business rule; empty otherwise. |
| `invoice.dueOn`      | The installment's due date, `dd.mm.yyyy`. |
| `invoice.paymentTerm` | "Zahlbar bis {date} ohne Abzug." |
| `invoice.lines`      | #411: the accepted quote's positions on an **Endrechnung** — `{title, quantity, unitPrice, discount, total, vatRate}`, formatted. The quote-level discount rides along as its own `Rabatt` line (§ 14 Abs. 4 Nr. 7: a Minderung agreed in advance is part of the Entgelt). A slider quote has one line: the hours at their rate. Empty on an Anzahlungsrechnung, which is what makes the block absent rather than blank. Not a `{{ }}` placeholder, same as `vatLines`. |
| `invoice.deposits`   | #411: every earlier issued installment of the plan — `{number, date, net, vat, gross}` — the Teilentgelte § 14 Abs. 5 S. 2 UStG makes the Endrechnung deduct. A cancelled one is absent; a credited one is deducted net of its Gutschrift. Not a `{{ }}` placeholder. |
| `invoice.totalNet` / `totalVat` / `totalGross` | #411: the whole Leistung, from the accepted quote rather than from the plan's total — a Nachtrag or a voided rate never described a supply. `invoice.net/vat/gross` stay the remainder this document actually charges. |
| `invoice.vatLines`   | #412: a list of `{rate, net, vat}`, one per tax rate, all three formatted. The installment is shared out pro rata over the accepted quote's own rate lines (`Quote::vatLines()`), rounded so the shares sum to the gross exactly. **Not a `{{ }}` placeholder** — a template body cannot loop, so this one entry is read by the layouts (`App\Payments\Support\InvoiceDocument`) and is deliberately absent from `MergeFieldVocabulary`. One rate is the ordinary case and prints the single Umsatzsteuer row the summary chain always had. |

§ 14 Abs. 4 Nr. 7 UStG is why the list exists: the Entgelt has to appear
"nach Steuersätzen aufgeschlüsselt" where more than one applies — 19 % on the
reportage and 7 % on the printed book in the same quote. The rate lives on
`quote_items.vat_rate` (basis points, null meaning the quote's own rate) and
`package_items` carries it too, so a package brings it along.

### The credit note block

`creditNote.*` is the same shape one argument further along: `fromProject()`
takes an optional fourth argument, a `CreditNote`, and every other document
type gets empty strings. It exists because a Gutschrift (#409) is not an
invoice with a minus sign — it has its own number range (`DocumentType::CreditNote`,
prefix `GS`), its own date, and a reference § 31 Abs. 5 UStDV requires.

| Path                     | Value                                      |
|--------------------------|--------------------------------------------|
| `creditNote.number`      | The allocated number (`GS-2026-3`), from the `DocumentType::CreditNote` range. |
| `creditNote.date`        | When `IssueCreditNote` ran, `dd.mm.yyyy`.  |
| `creditNote.servicePeriod` | The primary shoot's date, as on the invoice. |
| `creditNote.description` | Project name and the installment's label, as on the invoice. |
| `creditNote.reason`      | What the studio typed in the Gutschrift dialog. |
| `creditNote.net`         | Split *backwards* out of the credit note's own amount, not the installment's — a partial Gutschrift states the VAT actually given back. |
| `creditNote.vatRate`     | The rate of the supply being reversed, not today's. |
| `creditNote.vat`         | The remainder, so net + VAT is exactly the amount credited. |
| `creditNote.gross`       | The credit note's `amount`, as a positive figure. The template prints the sign. |
| `creditNote.vatNote`     | The §19 UStG sentence under the small-business rule; empty otherwise. |
| `creditNote.invoiceNumber` | The invoice this credits — the reference § 31 Abs. 5 UStDV asks for. |
| `creditNote.invoiceDate` | That invoice's own date.                   |

No `dueOn` and no `paymentTerm`: nothing is owed on a credit note, it is paid
out. Where the installment was settled through Stripe, `IssueCreditNote`
creates the refund in the same transaction that writes the row.

The rate and mode come from the project's **accepted quote** where one exists,
because Q-04 froze them there at send and that snapshot is what the client
agreed to — the studio's current setting is only the fallback for a plan with
no quote behind it.

**e-Rechnung (ZUGFeRD/XRechnung) is deliberately absent.** §14 UStG's
structured-format mandate is domestic B2B; a private client gets a PDF. If
corporate work ever needs it, it is a second output of this same renderer
against this same merge data, not a second data model.

All `studio.*` and `settings.*` fields read through
`App\Settings\Support\StudioSettingsResolver` (T-02/T-05) — the same tenant
settings row the Studio-Profil and Dokumente admin panes edit — rather than
`APP_NAME` or anything ad hoc.

`App\Documents\Support\MergeFieldVocabulary` is the single source both this
table and a template preview read from: `paths()` names every documented
path, `sample()` gives each one a realistic value, and
`DocumentTemplateController::preview()` resolves a template's current body
against exactly that sample data — which is what proves a preview has "no
unresolved placeholder" rather than merely exercising a few fields by hand.

Add a path by extending `DocumentMergeDataBuilder::fromProject()` and both
tables above in the same commit — the resolver has no knowledge of what a
template is allowed to reference beyond what that array contains.

Example:

```html
<p>Angebot für {{ contact.name }}: {{ project.name }} ({{ project.type }}).</p>
```

## Authoring: the block editor

A template's wording is edited as blocks, not as HTML.
`document_templates.blocks` holds Editor.js JSON and is the edit source of
truth; `body` is the HTML those blocks serialize to
(`resources/js/admin/documents/blocks/serialize.ts`) and keeps its exact
former meaning — `MergeFieldResolver`, `DocumentRenderer` and the render
jobs know nothing about blocks.

Which blocks a document type may use is `App\Documents\Support\
DocumentBlockCatalog` — one list, read both by the inserter palette and by
save validation, so what staff are offered and what the server accepts cannot
drift. A block type there with no case in `serialize.ts` renders to nothing;
add both in the same commit.

A template written before the editor existed has `blocks` null. Its body is
imported as a single `rawHtml` block when first opened — a textarea showing
the original markup, which the author can keep, edit, or delete; the change
persists only if they save, so a template nobody edits is never rewritten.

Merge fields are an inline tool: a `<span data-mf="contact.name">` showing the
sample value while editing, serialized back to `{{ contact.name }}`. The
picker offers `MergeFieldVocabulary::samples()`, a flat path-to-value map,
while the preview resolves against `sample()`, the same values as a nested
array — both built from one private constant, so what an author picks and what
the preview shows cannot disagree.

## Sanitising block HTML

Editor.js applies each tool's `sanitize` config when it *saves*, inside a live
browser editor. A `blocks` payload POSTed straight at the controller never
meets it, so `App\Documents\Support\InlineHtmlSanitizer` re-applies the
allow-list on the write path, in both `store()` and `update()`, before
anything is persisted. That is the copy that holds.

`resources/js/admin/documents/blocks/sanitizeInline.ts` carries the same list
for the read path, because a tool constructed from stored data assigns it
straight into a `contenteditable`. Keep the two lists identical — a tag
allowed on one side and not the other is either a hole or a silently stripped
feature. The chip's `data-mf` in particular must stay allowed on both: the
serializer reads it back to rebuild `{{ contact.name }}`.

`script`, `style`, `img`, `iframe`, `object` and `embed` are dropped whole
rather than unwrapped. Every other disallowed tag is unwrapped, keeping the
author's text — a `<div>` wraps prose someone wrote, while a `<script>` wraps
machine source that was never document text and would otherwise land in a PDF
a client reads.

## Letterhead and footer

`App\Documents\Support\DocumentRenderer` wraps a Quote, Invoice or
CreditNote's resolved body in the studio's one configured letterhead (above) and footer
(below) — `StudioSettingsResolver::documentLetterheadHtml()` /
`documentFooterHtml()`, the Dokumente pane — rather than either being pasted
into every template. Mail is not a PDF, so `DocumentRenderer` leaves a mail
body untouched.

## Why resolution happens in the job, not the controller

A document that read project data live would silently change after the
client saw it — the couple's guest count on 3 June could differ from the
guest count on 14 June, and nothing would mark the difference. A render job
pulls the project/contact data it needs at the moment it runs and bakes
the result into the HTML sent to Gotenberg. Once rendered, the PDF is the
record; nothing ever re-renders it to serve a later read.

## Versioning

Editing a template never mutates its row. `DocumentTemplate::newVersion()`
inserts a new row in the same `family_id` and moves `is_current`; the old row
is untouched, and any document already pointing at it keeps pointing at
exactly that wording. `type` and `shoot_kind` name the family and are never
accepted as edit input — they carry forward from the row `newVersion()` is
called on. This is why an edited template never touches a PDF already handed
to a client.

## Variants per shoot kind

A template family that needs different wording per shoot kind
(e.g. a wedding quote reading differently from a portrait one) gets its
own row per kind — `shoot_kind` set — rather than one template full of
conditionals. `DocumentTemplate::scopeForShootKind()` returns every generic
(no shoot kind) template plus whichever variant matches, compared case-insensitively since the kind is free text.

## Deploy

Gotenberg ships as the `gotenberg` service in `docker-compose.yml`, pinned to
major version 8. As M-00's own `docs/queue.md` already flagged: this
repository has no production deploy configuration (no `fly.toml`,
`render.yaml`, Kubernetes manifests, or `deploy/` directory — checked again
for this story) — `.github/workflows/ci.yml` runs tests only. Wherever a
deploy mechanism is added, it needs a reachable Gotenberg alongside the
worker Compose already requires, and `GOTENBERG_URL` set to it.

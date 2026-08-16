# Documents

T-12 generalises M-01's contract-only template system into one family
covering every document type the studio sends: `App\Documents\Models\
DocumentTemplate` holds the wording (versioned, exactly as M-01's
`ContractTemplate` was), for one of three `App\Documents\Enums\
DocumentTemplateType` values — `contract`, `quote`, `mail` — with an
optional `shoot_kind` variant. `Contract` is still the one document that
actually renders to a PDF today (`App\Contracts\Jobs\RenderContractJob`);
quote and mail templates exist as the same model and vocabulary, ready for
whichever story wires a quote PDF or a templated mail up to them next.

## Merge fields

A template's `body` is HTML with placeholders in `{{ path.to.field }}` form —
dot-addressed, resolved by `App\Documents\Support\MergeFieldResolver` against
a plain array, never against a live model. An unresolvable path renders as
an empty string rather than failing the render. The resolver takes no
document type at all, which is what makes "the same merge field resolves
identically in a contract, a quote and a mail" true by construction: every
caller resolves the same body against the same shape of array.

That array is built in exactly one place, `App\Documents\Support\
DocumentMergeDataBuilder::fromProject()` — never assembled ad hoc by a
controller or a job — from whatever the project/job/contact/studio settings
look like at the moment a render runs:

| Path                | Value                                          |
|----------------------|-------------------------------------------------|
| `project.name`       | The project's name.                             |
| `project.type`       | The project type, labelled (e.g. "Hochzeit").   |
| `contact.name`       | The project's primary contact's name.           |
| `contact.email`      | The project's primary contact's email.          |
| `contact.street`     | Billing address, street and number (M-06). Empty until someone fills it in on the contact screen. |
| `contact.postcode`   | |
| `contact.city`       | |
| `job.bookedAt`       | The booking date, `dd.mm.yyyy`, or empty if unbooked. |
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
| `settings.widerrufText` | The configured Widerrufsbelehrung (T-05, Dokumente pane) when the studio has it switched on — an empty string, never the stored text, when it is switched off. |

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
<p>Vertrag mit {{ contact.name }} über {{ project.name }} ({{ project.type }}).</p>
```

## Authoring: the block editor

A template's wording is edited as blocks, not as HTML.
`document_templates.blocks` holds Editor.js JSON and is the edit source of
truth; `body` is the HTML those blocks serialize to
(`resources/js/admin/documents/blocks/serialize.ts`) and keeps its exact
former meaning — `MergeFieldResolver`, `DocumentRenderer` and
`RenderContractJob` know nothing about blocks.

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

`App\Documents\Support\DocumentRenderer` wraps a Contract or Quote's
resolved body in the studio's one configured letterhead (above) and footer
(below) — `StudioSettingsResolver::documentLetterheadHtml()` /
`documentFooterHtml()`, the Dokumente pane — rather than either being pasted
into every template. Mail is not a PDF, so `DocumentRenderer` leaves a mail
body untouched.

## Why resolution happens in the job, not the controller

A contract that read project data live would silently change after
signing — the couple's guest count on 3 June could differ from the guest
count on 14 June, and nothing would mark the difference. `RenderContractJob`
pulls the project/job/contact data it needs at the moment it runs and bakes
the result into the HTML sent to Gotenberg. Once rendered, the PDF is the
record; nothing ever re-renders it to serve a later read.

## Versioning

Editing a template never mutates its row. `DocumentTemplate::newVersion()`
inserts a new row in the same `family_id` and moves `is_current`; the old row
is untouched, and any `Contract` already pointing at it (`contract_template_id`
— the column keeps its M-01 name even though it now points at
`document_templates`) keeps pointing at exactly that wording. `type` and
`shoot_kind` name the family and are never accepted as edit input — they
carry forward from the row `newVersion()` is called on. This is what M-02
needs to record which version a signature was made against, and why an
edited template never touches a PDF already handed to a client.

## Variants per shoot kind

A template family that needs different wording per `App\Crm\Enums\ShootKind`
(e.g. a wedding contract reading differently from a portrait one) gets its
own row per kind — `shoot_kind` set — rather than one template full of
conditionals. `DocumentTemplate::scopeForShootKind()` returns every generic
(no shoot kind) template plus whichever variant matches; the project contract
tab offers exactly that set, filtered to the project's own primary shoot's
kind.

## Rendering pipeline (contract)

1. Staff pick a current, type-`contract` template for a project and submit —
   the request creates a `pending` `Contract` row and dispatches
   `RenderContractJob` onto the `render` queue (`config/horizon.php`'s
   `supervisor-render`, from M-00), then returns immediately.
2. The job resolves merge fields (`DocumentMergeDataBuilder` +
   `MergeFieldResolver`), wraps the result in the shared letterhead/footer
   (`DocumentRenderer`), posts the HTML to Gotenberg
   (`POST {GOTENBERG_URL}/forms/chromium/convert/html`), and stores the
   response on the `local` filesystem disk at
   `contracts/{tenant_id}/{contract public_id}.pdf`.
3. The row moves to `rendered` (or `failed`, with the error recorded) and
   `artifact_path` is set. Every later read — admin or portal — serves that
   file. Nothing calls Gotenberg or the job again to display an existing
   contract.

## Deploy

Gotenberg ships as the `gotenberg` service in `docker-compose.yml`, pinned to
major version 8. As M-00's own `docs/queue.md` already flagged: this
repository has no production deploy configuration (no `fly.toml`,
`render.yaml`, Kubernetes manifests, or `deploy/` directory — checked again
for this story) — `.github/workflows/ci.yml` runs tests only. Wherever a
deploy mechanism is added, it needs a reachable Gotenberg alongside the
worker Compose already requires, and `GOTENBERG_URL` set to it.

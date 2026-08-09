# Contracts

`ContractTemplate` holds a contract's wording; `Contract` is one PDF rendered
from an exact version of it, for one project.

## Merge fields

A template's `body` is HTML with placeholders in `{{ path.to.field }}` form —
dot-addressed, resolved by `App\Contracts\Support\MergeFieldResolver` against
a plain array, never against a live model. An unresolvable path renders as
an empty string rather than failing the render.

Available paths, assembled fresh inside `RenderContractJob` every time it
runs:

| Path                | Value                                          |
|----------------------|-------------------------------------------------|
| `project.name`       | The project's name.                             |
| `project.type`       | The project type, labelled (e.g. "Hochzeit").   |
| `contact.name`       | The project's primary contact's name.           |
| `contact.email`      | The project's primary contact's email.          |
| `job.bookedAt`       | The booking date, `dd.mm.yyyy`, or empty if unbooked. |

Add a path by extending `RenderContractJob::mergeData()` and this table in
the same commit — the resolver has no knowledge of what a template is
allowed to reference beyond what that array contains.

Example:

```html
<p>Vertrag mit {{ contact.name }} über {{ project.name }} ({{ project.type }}).</p>
```

## Why resolution happens in the job, not the controller

A contract that read project data live would silently change after
signing — the couple's guest count on 3 June could differ from the guest
count on 14 June, and nothing would mark the difference. `RenderContractJob`
pulls the project/job/contact data it needs at the moment it runs and bakes
the result into the HTML sent to Gotenberg. Once rendered, the PDF is the
record; nothing ever re-renders it to serve a later read.

## Versioning

Editing a template never mutates its row. `ContractTemplate::newVersion()`
inserts a new row in the same `family_id` and moves `is_current`; the old row
is untouched, and any `Contract` already pointing at it (`contract_template_id`)
keeps pointing at exactly that wording. This is what M-02 needs to record
which version a signature was made against, and why an edited template
never touches a PDF already handed to a client.

## Rendering pipeline

1. Staff pick a current template for a project and submit — the request
   creates a `pending` `Contract` row and dispatches `RenderContractJob` onto
   the `render` queue (`config/horizon.php`'s `supervisor-render`, from M-00),
   then returns immediately.
2. The job resolves merge fields, posts the HTML to Gotenberg
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

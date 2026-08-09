# Historical import (X-01)

`php artisan migration:import-historical-data {path} [--commit]` imports
years of pre-existing client history — contacts, projects, jobs and
contracts — from a directory of CSV files. No specific legacy CRM is
assumed: this is the intermediate format any export gets converted into
first.

Defaults to a dry run: it reports what it would create, and every row it
cannot map with a reason, and writes nothing. Pass `--commit` to actually
write. The dry run and the commit run the identical plan — the report is
never a separate code path from the write, so it can't drift out of sync
with what `--commit` actually does.

```
php artisan migration:import-historical-data storage/app/import/2024-legacy
php artisan migration:import-historical-data storage/app/import/2024-legacy --commit
```

## Input format

`{path}` holds up to four files. Any of them may be omitted — an entity with
nothing historical to import simply contributes zero rows.

`ref` and `*_ref` columns are a legacy identifier that exists only to link
rows to each other **within one import run**. They are never stored on the
model a row resolves to, and a later run cannot reference a `ref` from an
earlier one — `jobs.csv` and `contracts.csv` can only point at a
`projects.csv` row in the same run.

### `contacts.csv`

| column | required | notes |
|---|---|---|
| `ref` | yes | unique within this file |
| `name` | yes | |
| `email` | no | if it matches an existing contact's email, that contact is reused instead of creating a duplicate — safe to re-run the same `contacts.csv` after a partial import |
| `phone` | no | |

### `projects.csv`

| column | required | notes |
|---|---|---|
| `ref` | yes | unique within this file |
| `name` | yes | |
| `type` | yes | `wedding` \| `portrait` \| `other`, case-insensitive |
| `primary_contact_ref` | yes | must match a `ref` in `contacts.csv` |

### `jobs.csv`

One row per booked project — the commitment, not a shoot day.

| column | required | notes |
|---|---|---|
| `project_ref` | yes | must match a `ref` in `projects.csv`; at most one job per project |
| `booked_at` | yes | `YYYY-MM-DD` |
| `booked_via` | no | `manual` \| `contract`, defaults to `manual` |

Imported jobs carry no `booked_by_id` — there is no staff member behind a
bulk migration to attribute the booking to.

### `contracts.csv`

A historical contract record — the studio's existing signed document, not
something this app renders. Every ordinary `Contract` row names the exact
template version it was rendered from (M-01); an imported one has no such
render, so every import shares one placeholder `ContractTemplate` (named
"Historischer Import (X-01)", `is_current = false`) created on first use and
reused after. It never appears where staff pick a template to render a new
contract from.

| column | required | notes |
|---|---|---|
| `project_ref` | yes | must match a `ref` in `projects.csv` |
| `status` | no | `pending` \| `rendered` \| `failed`, defaults to `rendered` — a historical row is assumed to already be a finished document |
| `rendered_at` | required when `status` is `rendered` | `YYYY-MM-DD` |
| `artifact_path` | no | a path already present on the `local` disk holding the scanned/historical document; stored as-is, nothing is uploaded or copied by this command |

## What "cannot be mapped" means

A row is reported as unmappable, with a reason, rather than imported
partially or guessed at. The reasons the command currently gives:

- a required column is empty (`name is required`, `project_ref is required`, …)
- a `ref` repeated within the same file — ambiguous, since a later row
  referencing it can't tell which one it means
- a `*_ref` that doesn't match any `ref` in the file it should point at
- an unrecognised `type`, `booked_via` or `status`
- a date that isn't `YYYY-MM-DD`
- a second `jobs.csv` row for a project that already has one in this import

## Limitations

- One `ref` namespace per run. Re-running with a changed `contacts.csv` finds
  contacts by email, but `projects.csv`/`jobs.csv`/`contracts.csv` have no
  such dedup — running `--commit` twice on the same project data creates
  duplicate projects, jobs and contracts. Import each dataset once.
- No CSV escaping beyond what PHP's own `fgetcsv` handles (RFC 4180 quoting).

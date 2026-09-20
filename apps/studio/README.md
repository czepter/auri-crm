# apps/studio

The Laravel application behind three of the platform's four surfaces —
`admin` (staff, Inertia + Vue), `portal` (clients, Inertia + Vue) and `api`
(public JSON). One codebase, one image, one surface per container:
`APP_SURFACE` decides which route file boots, and no other is registered.

Setup, commands and tests live in the [repository README](../../README.md);
the surface model, the module layout and the shared conventions live in
[docs/architecture.md](../../docs/architecture.md). Do not run `composer` or
`artisan` on the host — every PHP process in this repository runs in a
container (`pnpm php <command>` from the repository root).

## Layout

```
app/          domain modules (Crm, Quotes, Payments, Media, …),
              each with its own Actions/ Data/ Enums/ Http/ Models/
routes/       admin.php · portal.php · api.php · console.php — one is loaded
resources/js  Vue 3 components, per-surface entrypoints, generated.d.ts
tests/        Pest — Feature/ mirrors app/'s modules, e2e/ is Playwright
docs/         this app's own vertical documentation
```

## This app's documentation

| Document | Covers |
|---|---|
| [queue.md](docs/queue.md) | Horizon supervisors, the named queues, retrying a failed job |
| [media.md](docs/media.md) | Object storage, multipart ingest, derivatives, ZIP builds, delivery |
| [database-grants.md](docs/database-grants.md) | DB-level privilege revocation behind the append-only audit log |
| [cutover-runbook.md](docs/cutover-runbook.md) | The go-live sequence, its snapshot and its rollback |

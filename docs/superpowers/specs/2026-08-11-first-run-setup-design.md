# First-run setup: owner signup, optional MFA, studio onboarding

Date: 2026-08-11
Status: proposed
Scope: one PR

## Problem

The studio surface has no way to create its first account. Staff onboard through
`/einladung/{token}` (`routes/admin.php:87`), which requires an existing user holding
`Capability::ManageStaff` to send the invitation. Nothing creates that first user.

Today the only paths to an Owner are `php artisan db:seed` — which mints
`test@example.com` with the factory's hardcoded password `password` and role `Owner`
(`database/seeders/DatabaseSeeder.php:12`, `database/factories/UserFactory.php:14-19`) —
`E2eSeeder`, or `php artisan tinker`. The seeder path is unguarded against production:
the `isProduction()` check on `DatabaseSeeder.php:20` wraps only `DemoCrmSeeder`, not the
user creation above it.

A fresh install also lands on an empty `studio_settings` row. Everything there has a
default or a null-fallback except `display_name`, so the app runs — but documents,
portal Impressum and mail all render from placeholder-free-but-empty values.

## Goals

1. A fresh install can create its first Owner through the browser, with no shell access.
2. MFA stops being mandatory; it becomes opt-in.
3. The new Owner configures the studio before reaching the dashboard.
4. `db:seed` stops being able to create a known-password Owner in production.

## Non-goals

- Multi-studio / tenant self-signup. `tenant_id` stays dormant (`config/tenancy.php`).
- Password-confirmation (`password.confirm`) for sensitive actions. See Risks.
- Any change to the portal or api surfaces.

## Design

### 1. Owner signup — `/einrichtung`

**Middleware** `App\Http\Middleware\EnsureNoStaffExists`, alias `setup` in
`bootstrap/app.php`:

```php
abort_if(User::query()->exists(), 404);
```

`User` uses `BelongsToTenant` (`app/Models/User.php:25`), so this check is scoped to
`config('tenancy.tenant_id')`. With tenancy dormant that is the whole table. **Decision:**
this is intentional — if tenancy is ever activated, each new tenant gets its own first-run
window rather than inheriting a closed one.

**Routes**, inside the existing `guest:staff` group in `routes/admin.php`:

```php
Route::middleware('setup')->group(function () {
    Route::get('/einrichtung', [SetupController::class, 'create'])->name('setup');
    Route::post('/einrichtung', [SetupController::class, 'store']);
});
```

`einrichtung` is added to `PUBLIC_ROUTES['admin']` in `tests/Feature/AudienceMatrixTest.php:14`,
with a comment recording that the emptiness check is the authorization — the same shape the
`/einladung/{token}` entry already uses.

**`App\Http\Controllers\Staff\SetupController`**

- `create()` renders `Auth/Setup`.
- `store()` validates `name`, `email` (unique on `['tenant_id', 'email']`, matching the
  index from `create_staff_users_table.php:25`), `password` with `confirmed` and
  `Password::defaults()`. Then, inside `DB::transaction`, re-checks
  `User::query()->lockForUpdate()->exists()` and aborts 404 if a user appeared in the
  meantime — two tabs submitting at once must not both become Owner. Creates the user with
  `role: StaffRole::Owner`, logs in on the `staff` guard, regenerates the session, records
  `staff.setup_completed` through `AuditLogger`, redirects to `/einrichtung/studio`.

`tenant_id` needs no explicit value: it carries a DB default from `config('tenancy.tenant_id')`
(`app/Providers/AppServiceProvider.php:106`) and `BelongsToTenant::creating` sets it anyway.

**`SessionController::create()`** gains one line — a login form on an empty database is a
dead end:

```php
if (! User::query()->exists()) {
    return redirect('/einrichtung');
}
```

### 2. MFA becomes opt-in

`Route::middleware('mfa')` is removed from the staff group at `routes/admin.php:105`; the
group itself stays. `App\Http\Middleware\RequireConfirmedMfa` and its alias at
`bootstrap/app.php:96` are deleted rather than left orphaned.

Nothing else changes server-side. `SessionController::store` already branches on
`hasConfirmedMfa()` and sends enrolled users through `/auth/mfa`, so the TOTP challenge
survives for anyone who has opted in. Enrollment stays reachable at `/auth/mfa/enroll`
(`routes/admin.php:101-103`), which sits under `audience:staff` only.

Comments that assert mandatory MFA become false and are rewritten:
`InvitationController.php:150-153` ("not usable until TOTP is set up") and
`routes/admin.php:108-110` (OAuth consent "gated behind confirmed MFA like every other
sensitive staff screen").

### 3. Studio onboarding — `/einrichtung/studio`

**Migration:** `studio_settings.onboarding_completed_at`, nullable timestamp. `display_name`
alone cannot distinguish "filled step 1" from "finished the wizard". A timestamp rather than
a boolean, matching `staff_invitations.accepted_at`.

**Middleware** `App\Http\Middleware\RequireStudioOnboarding`, alias `onboarding`, placed in
the group slot `mfa` vacates at `routes/admin.php:105`. While `onboarding_completed_at` is
null it redirects to `/einrichtung/studio`, subject to two conditions:

- **Only `GET` requests.** The wizard submits to settings endpoints that live inside this
  same group (`routes/admin.php:177-183`, `:236`). Redirecting non-GET traffic would 302 the
  wizard's own saves back to itself — a redirect loop on the happy path. Those endpoints
  remain gated by `AuthorizesStaffSettings::authorizeSettings()` in their controllers, so
  nothing is exposed by letting them through.
- **Only users holding `Capability::ManageSettings`.** Anyone else passes through, so an
  invited Photographer is never trapped on a form they are not allowed to submit.

**Routes**, inside `audience:staff` but outside the `onboarding` group:

```php
Route::get('/einrichtung/studio', [StudioOnboardingController::class, 'show'])->name('onboarding');
Route::post('/einrichtung/studio/abschluss', [StudioOnboardingController::class, 'complete']);
```

**The five steps post to the endpoints that already exist.** No parallel controller and no
duplicated validation:

| Step | Endpoint | Route name |
|---|---|---|
| Profil | `PATCH /einstellungen` | `settings.update` |
| USt | `PATCH /einstellungen/ust` | `settings.vat.update` |
| Dokumente | `PATCH /einstellungen/dokumente` | `settings.documents.update` |
| E-Mail | `PATCH /einstellungen/email` | `settings.mail.update` |
| Portal | `PATCH /einstellungen/portal` | `settings.portal.update` |

`StudioOnboardingController::complete` stamps `onboarding_completed_at` and redirects to `/`.
This mirrors how the settings shell already works — `Settings/Index.vue:9-12` notes that panes
are client-side with one endpoint each.

Note for the Profil step: `StudioSettingsController::update` already enforces
`required_without` across `tax_number` / `vat_id`, and conditionally-required bank details via
`BankDetailsRequirement`. The wizard inherits those rules; step 1 is therefore not
"studio name only" regardless of UI copy.

**Escape hatch.** Step 1 (Profil) must be saved. From step 2 onward the shell offers
"Später abschließen", which calls `complete` and releases the user to the dashboard. Without
it, an Owner who cannot satisfy a later step's validation is redirected back to the wizard on
every login with no route out except the database.

**Vue.** `Settings/Index.vue` is 620 lines with all five forms inline. The wizard needs the
same forms, so they are extracted to `admin/pages/Settings/panes/{Profile,Vat,Documents,Email}Form.vue`
plus the existing `Portal.vue`'s form, consumed by both `Index.vue` and the new
`Auth/Onboarding.vue` shell. Copy stays terse and German, matching the existing `de.json` keys.

**Commit order inside the PR:** (1) pane extraction, green on its own; (2) owner setup +
MFA removal; (3) migration + wizard. A single PR was chosen deliberately; keeping the
extraction as its own commit is what makes a red test attributable.

### 4. Seeder fix

The `User::factory()` call at `DatabaseSeeder.php:12` moves inside the existing
`if (! app()->isProduction())` block, so `db:seed` can no longer create a known-password
Owner in production.

Side effect: `/einrichtung` returns 404 on any dev machine that has run `db:seed`, because a
user exists. `README.md` gets a line: reach the wizard with `php artisan migrate:fresh`.

## Testing

New `tests/Feature/Staff/SetupTest.php`:

- renders on an empty database
- creates an Owner, authenticates, redirects to `/einrichtung/studio`
- both verbs 404 once any user exists
- rejects a weak or unconfirmed password
- `/login` redirects to `/einrichtung` when the users table is empty

New `tests/Feature/Settings/OnboardingTest.php`:

- a staff GET is redirected to the wizard while `onboarding_completed_at` is null
- a `PATCH` to a settings endpoint is **not** redirected in that state
- a user without `ManageSettings` is not redirected
- `complete` stamps the timestamp and releases the dashboard
- once stamped, no redirect

MFA: `staffMember()` (`tests/Pest.php:163-165`) creates users `withMfa()`, so the existing
suite will stay green whether or not enforcement exists — the removal is untestable by
construction unless asserted directly. Add to `tests/Feature/Staff/MfaTest.php`: an
un-enrolled staff user reaches the dashboard. Existing enforcement assertions there and in
`tests/Feature/Staff/InvitationTest.php` are inverted.

E2E: `tests/e2e/login.spec.ts` and `tests/e2e/mfa.spec.ts` drop their enrollment-redirect
expectations. A setup spec needs a database with no users, while `E2eSeeder` creates two
Owners (`E2eSeeder.php:24-43`) — it gets its own Playwright project with an unseeded database
rather than a teardown inside the shared one.

`AudienceMatrixTest` must stay green with the new `PUBLIC_ROUTES` entry.

## Risks

**Deploy race — accepted.** Between the moment a deployment serves traffic and the moment the
first Owner is created, `/einrichtung` is an unauthenticated route that grants full ownership
to whoever reaches it first. The window is real and there is no recovery path short of
`tinker`. Mitigation is operational, not code: complete first-run setup immediately after
deploy, before the URL is shared. `docs/deployment.md` records this. A `SETUP_KEY` secret or
an artisan-only bootstrap were considered and rejected as heavier than the exposure warrants
for a single-studio install.

**MFA was carrying implicit step-up authorization.** The app has no `password.confirm`
middleware anywhere. With `RequireConfirmedMfa` gone, a stolen session cookie reaches OAuth
consent (`routes/admin.php:108-110`) and staff management with nothing in between. Out of
scope here; `password.confirm` on those two route groups is the natural follow-up.

**The onboarding gate blocks navigation, not the application.** Because it only redirects GET
requests, a staff user with an existing session and a crafted request can still write through
the settings endpoints during onboarding. Those endpoints enforce their own capability checks,
so this is a completeness note rather than a hole.

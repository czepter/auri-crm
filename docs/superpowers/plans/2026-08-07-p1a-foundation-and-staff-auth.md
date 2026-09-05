# P1a — Foundation & Staff Authentication Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up the monorepo, CI, Postgres schema base, and the surface-gating mechanism, then ship a staff-only admin interface where a user signs in with a password plus mandatory TOTP and every authentication event lands in an append-only audit log.

**Architecture:** One Laravel application (`apps/studio`) renders its staff interface with Inertia and Vue 3, styled with shadcn-vue. `APP_SURFACE` selects exactly one route file at boot, so the admin container never registers the portal or api routes — isolation is a deploy property, not a repository one. Authentication is a plain Laravel session guard named `staff`; there is no Sanctum, no CORS, and no JSON API for the admin surface, because Inertia carries typed props instead. `spatie/laravel-data` objects are those props, and `spatie/laravel-typescript-transformer` turns them into TypeScript so renaming a PHP field fails `vue-tsc` in the same pull request.

**Tech Stack:** PHP 8.5, Laravel 13, Pest 5, PostgreSQL 18, `inertiajs/inertia-laravel` 3, `spatie/laravel-data` 4, `spatie/laravel-typescript-transformer` 3, `pragmarx/google2fa`, `visus/cuid2`, Node 24, pnpm 10, Vue 3, Vite 6, Tailwind CSS 4, shadcn-vue, Vitest, Playwright, Docker Compose, GitHub Actions.

> **Revised 2026-08-08, three tasks in.** The stack line above originally read PHP 8.3, Laravel 11, Pest 3. PHP 8.3 left active support in December 2025 and is security-only, so a greenfield application starting today would begin its life on a version already past feature maintenance. The pin was also load-bearing in a way that was not obvious when it was written: Pest 5 requires PHP `^8.4`, so 8.3 silently capped the test framework at Pest 3 as well. Laravel 13 (released 2026-08-04) requires PHP `^8.3` and `inertiajs/inertia-laravel` 3.3 already declares `^11.35|^12.0|^13.0`, so nothing in the dependency graph objected. Changing this at task 3 costs one scaffold correction; changing it after the schema, the auth flow and the type-generation pipeline exist costs a migration.

## Global Constraints

- PHP 8.5, Laravel 13.x, Pest 5.x. All PHP commands run inside Docker — never assume a host `php` or `composer` binary.
- Node 24, pnpm 10. pnpm is the only package manager; do not create `package-lock.json` or `yarn.lock`.
- PostgreSQL 18. No MySQL-specific SQL, no SQLite in tests — tests run against real Postgres.
- Exactly one route file is loaded per process, chosen by `APP_SURFACE`. A route belongs to exactly one surface. Never register a route from more than one surface file.
- Every application table carries a `tenant_id` UUID column, NOT NULL, defaulting to the constant in `config('tenancy.tenant_id')`. This seam is dormant in P1 but present from the first migration.
- Every application table carries a unique `char(24)` `public_id` holding a CUID2. The `bigint` primary key is internal only: it carries foreign keys and joins, and never appears in a response body, an Inertia prop, a URL, an email, or a filename. Models expose `public_id` and hide `id`; route-model binding resolves on `public_id`.
- No cross-context database joins. Contexts reference each other by ID.
- Staff MFA is mandatory, not opt-in. A staff user without confirmed TOTP may reach only the enrollment routes.
- Every non-public route group declares its required audience in middleware. Rejection happens in middleware, before controller dispatch.
- The audit log is append-only. Updates and deletes are blocked in the model and revoked at the database role level.
- Authentication failures return uniform responses that never distinguish "wrong password" from "no such account".
- Inertia props are `spatie/laravel-data` objects, never raw arrays or models. Every prop type is generated into `resources/js/types/generated.d.ts` and consumed by the Vue page.
- Tests are written before implementation, and every task ends with a passing test run and a commit.
- Commits follow Conventional Commits.
- All timestamps are stored in UTC; `Europe/Berlin` is a display concern only.

---

## File Structure

```
client-management-portal/
├── package.json                          pnpm workspace root, cross-app scripts
├── pnpm-workspace.yaml                   workspace globs
├── docker-compose.yml                    php + postgres + mailpit for local dev
├── .github/workflows/ci.yml              one pipeline, per-concern jobs
└── apps/
    └── studio/                           Laravel 11 + Inertia + Vue 3
        ├── app/
        │   ├── Enums/StaffRole.php                   staff role enum
        │   ├── Models/User.php                       staff user
        │   ├── Models/AuditLog.php                   append-only log model
        │   ├── Concerns/BelongsToTenant.php          tenant column + global scope
        │   ├── Concerns/HasPublicId.php              cuid2 generation, hides id, route key
        │   ├── Data/StaffProfileData.php             the typed shape of the signed-in user
        │   ├── Support/AuditLogger.php               the only writer of audit rows
        │   ├── Support/Surface.php                   surface names + route registration
        │   ├── Http/Middleware/EnsureAudience.php        guard-based audience gate
        │   ├── Http/Middleware/RequireConfirmedMfa.php   blocks un-enrolled staff
        │   ├── Http/Middleware/HandleInertiaRequests.php per-surface root view + shared props
        │   └── Http/Controllers/Staff/
        │       ├── SessionController.php             login page, login, logout
        │       ├── MfaEnrollmentController.php       enroll page, confirm, recovery codes
        │       ├── MfaChallengeController.php        challenge page, verify
        │       └── DashboardController.php           the thing MFA protects
        ├── config/tenancy.php                        tenant constant
        ├── config/surface.php                        which surface this process serves
        ├── routes/
        │   ├── admin.php                             staff, Inertia, web middleware
        │   └── api.php                               gallery-facing JSON (health only in P1a)
        ├── resources/
        │   ├── css/{app,admin}.css                   shadcn tokens + per-surface theme
        │   ├── views/app-admin.blade.php             Inertia root view for the admin surface
        │   └── js/
        │       ├── admin/main.ts                     admin Vite entrypoint
        │       ├── admin/pages/Auth/Login.vue
        │       ├── admin/pages/Auth/MfaChallenge.vue
        │       ├── admin/pages/Auth/MfaEnroll.vue
        │       ├── admin/pages/Auth/RecoveryCodes.vue
        │       ├── admin/pages/Dashboard.vue
        │       ├── ui/                               shadcn-vue primitives, shared
        │       ├── lib/utils.ts                      cn() helper
        │       └── types/generated.d.ts              generated from PHP Data objects
        ├── components.json                           shadcn-vue registry
        ├── vite.config.ts                            multi-entry, one input per surface
        ├── playwright.config.ts
        ├── tests/                                    Pest: Feature/ and Unit/
        └── tests/e2e/                                Playwright journeys
```

Responsibility boundaries worth naming. `Surface` owns the entire surface decision — the allowlist, the current value, and the route registration — so there is exactly one place that knows a surface exists, and adding `portal` in P2 touches one array and adds one file. `AuditLogger` is the single writer of audit rows so retention and redaction have one home. `EnsureAudience` knows about guards and nothing else. `HasPublicId` owns every part of the internal/external identifier split — generation, hiding, and route binding — so adding the trait is sufficient and there is nothing a model author must remember separately. Controllers under `Http/Controllers/Staff/` are split by concern rather than gathered into one `AuthController`, because the MFA challenge runs in a different authentication state than the other two and merging them makes that state machine hard to read.

`resources/js/ui` is shared by every surface while `resources/js/<surface>/pages` is not. A page is surface-private by construction: the admin entrypoint globs only `./pages/**/*.vue` relative to itself, so a portal build cannot resolve an admin page even by accident.

**Not in P1a:** `packages/contracts`, OpenAPI generation, and Scramble. The gallery is the only consumer of the JSON API and it arrives in P3; generating a typed client with no consumer would be scaffolding for its own sake. The `api` surface exists in P1a with a single health route so the surface mechanism is exercised by more than one value.

---

### Task 1: Workspace skeleton, Docker, and CI

**Files:**
- Create: `package.json`, `pnpm-workspace.yaml`, `.nvmrc`, `.editorconfig`
- Create: `docker-compose.yml`
- Create: `.github/workflows/ci.yml`
- Modify: `.gitignore`

**Interfaces:**
- Consumes: nothing
- Produces: `pnpm lint`, `pnpm test`, and `pnpm build` at the repo root (all no-ops until Task 3 adds a workspace package); a `postgres` service on host port 5433 and a `php` service usable as `docker compose run --rm php <cmd>`

- [ ] **Step 1: Create the workspace manifests**

`pnpm-workspace.yaml`:

```yaml
packages:
  - "apps/*"
  - "packages/*"
```

`package.json`:

```json
{
  "name": "studio-workspace",
  "private": true,
  "packageManager": "pnpm@10.28.2",
  "engines": { "node": ">=24" },
  "scripts": {
    "lint": "pnpm -r --if-present lint",
    "test": "pnpm -r --if-present test",
    "build": "pnpm -r --if-present build",
    "php": "docker compose run --rm php"
  }
}
```

`.nvmrc`:

```
24
```

`.editorconfig`:

```ini
root = true

[*]
charset = utf-8
end_of_line = lf
insert_final_newline = true
indent_style = space
indent_size = 4
trim_trailing_whitespace = true

[*.{js,ts,vue,json,yml,yaml,md,css}]
indent_size = 2

[*.md]
trim_trailing_whitespace = false
```

- [ ] **Step 2: Create the Docker environment**

`docker-compose.yml`:

```yaml
services:
  php:
    image: php:8.3-cli
    working_dir: /app/apps/studio
    volumes:
      - .:/app
      - composer-cache:/tmp/composer
    environment:
      COMPOSER_HOME: /tmp/composer
      DB_HOST: postgres
      DB_PORT: 5432
      DB_DATABASE: studio
      DB_USERNAME: studio
      DB_PASSWORD: studio
    ports:
      # Published so the Playwright suite on the host can reach `artisan serve`
      # running inside this container.
      - "8000:8000"
    depends_on:
      postgres:
        condition: service_healthy

  postgres:
    image: postgres:18-alpine
    environment:
      POSTGRES_DB: studio
      POSTGRES_USER: studio
      POSTGRES_PASSWORD: studio
    ports:
      - "5433:5432"
    volumes:
      - pgdata:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U studio -d studio"]
      interval: 2s
      timeout: 3s
      retries: 30

  mailpit:
    image: axllent/mailpit
    ports:
      - "8025:8025"

volumes:
  pgdata:
  composer-cache:
```

- [ ] **Step 3: Verify the database container comes up**

Run: `docker compose up -d postgres && docker compose ps`
Expected: the `postgres` service reports `healthy` within about 10 seconds.

Then confirm connectivity from the host client:

Run: `PGPASSWORD=studio psql -h 127.0.0.1 -p 5433 -U studio -d studio -c 'select 1'`
Expected: one row containing `1`.

- [ ] **Step 4: Add the CI pipeline**

`.github/workflows/ci.yml`:

```yaml
name: CI

on:
  push:
    branches: [main]
  pull_request:

jobs:
  studio:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:18-alpine
        env:
          POSTGRES_DB: studio_test
          POSTGRES_USER: studio
          POSTGRES_PASSWORD: studio
        ports: ["5432:5432"]
        options: >-
          --health-cmd "pg_isready -U studio"
          --health-interval 2s
          --health-timeout 3s
          --health-retries 30
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: "8.3"
          extensions: pdo_pgsql, bcmath, gd, intl
          coverage: none
      - name: Skip when the app is not scaffolded yet
        id: guard
        run: test -f apps/studio/composer.json && echo "ready=true" >> "$GITHUB_OUTPUT" || echo "ready=false" >> "$GITHUB_OUTPUT"
      - if: steps.guard.outputs.ready == 'true'
        working-directory: apps/studio
        run: composer install --no-interaction --prefer-dist
      - if: steps.guard.outputs.ready == 'true'
        working-directory: apps/studio
        env:
          DB_CONNECTION: pgsql
          DB_HOST: 127.0.0.1
          DB_PORT: 5432
          DB_DATABASE: studio_test
          DB_USERNAME: studio
          DB_PASSWORD: studio
        run: |
          cp .env.example .env
          php artisan key:generate
          php artisan migrate --force
          ./vendor/bin/pest

  web:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: pnpm/action-setup@v4
        with: { version: 10 }
      - uses: actions/setup-node@v4
        with: { node-version: 24, cache: pnpm }
      - run: pnpm install --frozen-lockfile
      - run: pnpm lint
      - run: pnpm test
      - run: pnpm build
```

The `guard` step exists so this pipeline is green from the first commit and starts exercising the application the moment Task 2 lands. Task 2 removes it.

- [ ] **Step 5: Extend .gitignore**

```
.DS_Store
node_modules/

# PHP
apps/studio/vendor/
apps/studio/.env
apps/studio/storage/*.key
apps/studio/public/storage

# Front-end build output
apps/studio/public/build/
apps/studio/public/hot
dist/
playwright-report/
test-results/
```

- [ ] **Step 6: Verify the workspace scripts run**

Run: `pnpm install && pnpm test`
Expected: install succeeds and `pnpm test` exits 0 with no packages matched (there are no workspace packages yet).

- [ ] **Step 7: Commit**

```bash
git add package.json pnpm-workspace.yaml pnpm-lock.yaml .nvmrc .editorconfig docker-compose.yml .github/workflows/ci.yml .gitignore
git commit -m "chore: scaffold pnpm monorepo, docker dev environment, and CI"
```

---

### Task 2: Laravel scaffold with surface gating and a tested health endpoint

**Files:**
- Create: `apps/studio/**` (Laravel skeleton via Composer)
- Create: `apps/studio/config/surface.php`
- Create: `apps/studio/app/Support/Surface.php`
- Create: `apps/studio/routes/admin.php`, `apps/studio/routes/api.php`
- Create: `apps/studio/app/Http/Controllers/HealthController.php`
- Create: `apps/studio/tests/Feature/SurfaceIsolationTest.php`
- Create: `apps/studio/tests/Feature/HealthTest.php`
- Modify: `apps/studio/bootstrap/app.php`, `apps/studio/phpunit.xml`, `apps/studio/.env.example`
- Modify: `.github/workflows/ci.yml` (drop the guard step, add the api-surface test run)

**Interfaces:**
- Consumes: the `php` and `postgres` services from Task 1
- Produces: `App\Support\Surface` exposing `Surface::ALL` (`['admin', 'api']`), `Surface::current(): string`, and `Surface::registerRoutes(): void`; `config('surface.name')`; `GET /health` on the `api` surface returning `{"status":"ok","database":"ok"}`; a Pest suite invoked as `docker compose run --rm php ./vendor/bin/pest`

The scaffold and the surface mechanism ship together because the routing decision is made in `bootstrap/app.php` — the file that defines what "the application" is. A reviewer approving a Laravel skeleton whose routing was left for later would be approving something the next task has to rewrite rather than extend.

- [ ] **Step 1: Scaffold Laravel into apps/studio**

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 \
  create-project laravel/laravel apps/studio "^11.0" --no-interaction
```

- [ ] **Step 2: Install the development dependencies**

```bash
docker run --rm -v "$PWD":/app -w /app/apps/studio composer:2 \
  require --dev pestphp/pest pestphp/pest-plugin-laravel --no-interaction --with-all-dependencies
```

Then initialise Pest:

```bash
docker compose run --rm php ./vendor/bin/pest --init
```

- [ ] **Step 3: Point the app at Postgres and name the surface**

In `apps/studio/.env.example` and `apps/studio/.env`, set:

```
APP_URL=http://localhost:8000
APP_TIMEZONE=UTC
APP_SURFACE=admin

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=studio
DB_USERNAME=studio
DB_PASSWORD=studio

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=false
```

`SESSION_SECURE_COOKIE` must be `true` in every deployed environment. Only local HTTP development sets it to `false`.

In `apps/studio/phpunit.xml`, remove any `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:` lines and add, inside `<php>`:

```xml
<env name="DB_CONNECTION" value="pgsql"/>
<env name="DB_DATABASE" value="studio_test"/>
<env name="APP_SURFACE" value="admin"/>
```

The suite runs as the `admin` surface by default because that is where the staff routes live. `SurfaceIsolationTest` overrides it per case.

Create the test database:

```bash
docker compose exec postgres createdb -U studio studio_test
```

- [ ] **Step 4: Write the failing tests**

`apps/studio/tests/Feature/SurfaceIsolationTest.php`:

```php
<?php

use App\Support\Surface;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;

/**
 * Registers the routes for one surface into a clean collection and returns
 * their URIs. The surface gate is a claim about the route table, so it is
 * tested against the route table rather than against a response code.
 */
function urisForSurface(string $surface): array
{
    config(['surface.name' => $surface]);

    Route::setRoutes(new RouteCollection);
    Surface::registerRoutes();

    return collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => $route->uri())
        ->sort()
        ->values()
        ->all();
}

it('registers the admin routes only on the admin surface', function () {
    expect(urisForSurface('admin'))->toContain('login')
        ->and(urisForSurface('api'))->not->toContain('login');
});

it('registers the api routes only on the api surface', function () {
    expect(urisForSurface('api'))->toContain('api/health')
        ->and(urisForSurface('admin'))->not->toContain('api/health');
});

it('gives every surface a non-empty route table', function (string $surface) {
    expect(urisForSurface($surface))->not->toBeEmpty();
})->with(Surface::ALL);

it('refuses to boot on an unknown surface name', function () {
    config(['surface.name' => 'gallery']);

    expect(fn () => Surface::registerRoutes())
        ->toThrow(InvalidArgumentException::class, 'Unknown APP_SURFACE "gallery".');
});
```

`apps/studio/tests/Feature/HealthTest.php`:

```php
<?php

use App\Support\Surface;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // The suite defaults to the admin surface; health lives on api.
    config(['surface.name' => Surface::API]);
    Route::setRoutes(new RouteCollection);
    Surface::registerRoutes();
});

it('reports service and database health', function () {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertExactJson(['status' => 'ok', 'database' => 'ok']);
});
```

- [ ] **Step 5: Run the tests to verify they fail**

Run: `docker compose run --rm php ./vendor/bin/pest --filter="SurfaceIsolation|Health"`
Expected: FAIL — `Class "App\Support\Surface" not found`.

- [ ] **Step 6: Add the surface config and registrar**

`apps/studio/config/surface.php`:

```php
<?php

return [
    // Which surface this process serves. Exactly one route file is loaded.
    // The admin container is network-restricted and must never carry the
    // public routes; that is enforced by absence, not by middleware.
    'name' => env('APP_SURFACE', 'admin'),
];
```

`apps/studio/app/Support/Surface.php`:

```php
<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

/**
 * The single place that knows a surface exists. Adding the client portal in
 * P2 means adding one constant, one entry in ALL, and one route file.
 */
class Surface
{
    public const ADMIN = 'admin';
    public const API = 'api';

    /** P2 appends 'portal' here alongside routes/portal.php. */
    public const ALL = [self::ADMIN, self::API];

    public static function current(): string
    {
        return (string) config('surface.name');
    }

    public static function registerRoutes(): void
    {
        $surface = self::current();

        if (! in_array($surface, self::ALL, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown APP_SURFACE "%s". Expected one of: %s.',
                $surface,
                implode(', ', self::ALL),
            ));
        }

        $file = base_path("routes/{$surface}.php");

        match ($surface) {
            self::API => Route::middleware('api')->prefix('api')->group($file),
            default => Route::middleware('web')->group($file),
        };
    }
}
```

An unknown value throws rather than falling back to a default. A typo in a deployment environment variable must stop the container, because the alternative — quietly serving the wrong surface on a public host — is the exact failure this mechanism exists to prevent.

- [ ] **Step 7: Wire the registrar into the bootstrap**

Replace the `->withRouting(...)` call in `apps/studio/bootstrap/app.php` with:

```php
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: fn () => \App\Support\Surface::registerRoutes(),
    )
```

Delete `apps/studio/routes/web.php`. Neither `web:` nor `api:` is passed, so the only application routes that exist are the ones `Surface::registerRoutes()` loads. `then:` runs inside route registration, after config is loaded, which is why the surface can be read from config rather than from `env()` — `env()` returns null in a process running with a cached config.

`health: '/up'` stays outside the surface mechanism deliberately: every container needs a liveness probe regardless of which surface it serves.

- [ ] **Step 8: Create the route files and the health endpoint**

`apps/studio/routes/admin.php`:

```php
<?php

use Illuminate\Support\Facades\Route;

// Staff surface. Session-authenticated, Inertia-rendered, never public.
// Task 3 renders the login page here; Tasks 7 and 8 give it a backend.
Route::get('/login', fn () => 'login placeholder')->name('login');
```

`apps/studio/routes/api.php`:

```php
<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// Public JSON surface. In P1a this carries the health probe only; P1b adds
// the public inquiry endpoint and P3 adds the gallery read API.
Route::get('/health', HealthController::class);
```

`apps/studio/app/Http/Controllers/HealthController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        DB::connection()->getPdo();

        return response()->json(['status' => 'ok', 'database' => 'ok']);
    }
}
```

- [ ] **Step 9: Run the tests to verify they pass**

Run: `docker compose run --rm php ./vendor/bin/pest --filter="SurfaceIsolation|Health"`
Expected: PASS, 6 tests (4 isolation cases — two of which are the `Surface::ALL` data set — plus the unknown-surface case and the health case).

- [ ] **Step 10: Update CI**

In `.github/workflows/ci.yml`, delete the `Skip when the app is not scaffolded yet` step and every `if: steps.guard.outputs.ready == 'true'` condition, so the `studio` job always runs. Its steps should now read:

```yaml
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: "8.3"
          extensions: pdo_pgsql, bcmath, gd, intl
          coverage: none
      - working-directory: apps/studio
        run: composer install --no-interaction --prefer-dist
      - working-directory: apps/studio
        env:
          DB_CONNECTION: pgsql
          DB_HOST: 127.0.0.1
          DB_PORT: 5432
          DB_DATABASE: studio_test
          DB_USERNAME: studio
          DB_PASSWORD: studio
        run: |
          cp .env.example .env
          php artisan key:generate
          php artisan migrate --force
          ./vendor/bin/pest
```

- [ ] **Step 11: Commit**

```bash
git add apps/studio .github/workflows/ci.yml
git commit -m "feat(studio): scaffold Laravel 11 with surface gating and a health endpoint"
```

---

### Task 3: Inertia, Vue, Tailwind, and shadcn-vue on the admin surface

**Files:**
- Create: `apps/studio/package.json`, `apps/studio/vite.config.ts`, `apps/studio/tsconfig.json`
- Create: `apps/studio/app/Http/Middleware/HandleInertiaRequests.php`
- Create: `apps/studio/resources/views/app-admin.blade.php`
- Create: `apps/studio/resources/css/app.css`, `apps/studio/resources/css/admin.css`
- Create: `apps/studio/resources/js/admin/main.ts`
- Create: `apps/studio/resources/js/admin/pages/Auth/Login.vue`
- Create: `apps/studio/resources/js/lib/utils.ts`
- Create: `apps/studio/components.json`
- Create: `apps/studio/playwright.config.ts`, `apps/studio/tests/e2e/login-page.spec.ts`
- Create: `apps/studio/tests/Feature/InertiaRenderingTest.php`
- Modify: `apps/studio/routes/admin.php`, `apps/studio/bootstrap/app.php`, `.github/workflows/ci.yml`

**Interfaces:**
- Consumes: `Surface` (Task 2)
- Produces: the `@studio/app` workspace package with `lint` (`vue-tsc --noEmit`), `test` (`vitest run --passWithNoTests`), `build` (`vite build`), and `test:e2e` scripts; `GET /login` on the admin surface rendering the Inertia component `Auth/Login`; the `cn()` helper at `@/lib/utils`; shadcn-vue primitives under `@/ui`

The login page ships here as a static shell — fields, labels, and a submit button that posts nowhere yet. Task 7 gives it a backend and wires submission. This is the rendering pipeline's first real consumer rather than a throwaway probe.

- [ ] **Step 1: Install Inertia on the PHP side**

```bash
docker run --rm -v "$PWD":/app -w /app/apps/studio composer:2 \
  require inertiajs/inertia-laravel --no-interaction
docker compose run --rm php php artisan inertia:middleware
```

Replace the generated `apps/studio/app/Http/Middleware/HandleInertiaRequests.php` with:

```php
<?php

namespace App\Http\Middleware;

use App\Support\Surface;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Each surface has its own root view, so each surface loads its own Vite
     * entrypoint and its own theme. There is no shared HTML shell.
     */
    public function rootView(Request $request): string
    {
        return 'app-'.Surface::current();
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }
}
```

Register it on the web group in `apps/studio/bootstrap/app.php`, inside `->withMiddleware(function (Middleware $middleware) { ... })`:

```php
$middleware->web(append: [
    \App\Http\Middleware\HandleInertiaRequests::class,
]);
```

- [ ] **Step 2: Create the Node package and Vite configuration**

`apps/studio/package.json`:

```json
{
  "name": "@studio/app",
  "private": true,
  "type": "module",
  "scripts": {
    "dev": "vite",
    "build": "vite build",
    "lint": "vue-tsc --noEmit",
    "test": "vitest run --passWithNoTests",
    "test:e2e": "playwright test"
  },
  "dependencies": {
    "@inertiajs/vue3": "^2.0.0",
    "class-variance-authority": "^0.7.1",
    "clsx": "^2.1.1",
    "lucide-vue-next": "^0.469.0",
    "reka-ui": "^2.0.0",
    "tailwind-merge": "^2.6.0",
    "vue": "^3.5.13"
  },
  "devDependencies": {
    "@playwright/test": "^1.49.0",
    "@tailwindcss/vite": "^4.0.0",
    "@vitejs/plugin-vue": "^5.2.1",
    "laravel-vite-plugin": "^1.1.1",
    "tailwindcss": "^4.0.0",
    "typescript": "^5.7.2",
    "vite": "^6.0.0",
    "vitest": "^2.1.8",
    "vue-tsc": "^2.2.0"
  }
}
```

`apps/studio/vite.config.ts`:

```ts
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
  plugins: [
    // One input per surface. The admin bundle never contains portal pages,
    // because each entrypoint globs only its own pages directory.
    laravel({
      input: ['resources/js/admin/main.ts'],
      refresh: true,
    }),
    vue(),
    tailwindcss(),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
    },
  },
})
```

`apps/studio/tsconfig.json`:

```json
{
  "compilerOptions": {
    "target": "ES2022",
    "module": "ESNext",
    "moduleResolution": "bundler",
    "strict": true,
    "noEmit": true,
    "jsx": "preserve",
    "esModuleInterop": true,
    "skipLibCheck": true,
    "isolatedModules": true,
    "types": ["vite/client"],
    "baseUrl": ".",
    "paths": {
      "@/*": ["resources/js/*"]
    }
  },
  "include": ["resources/js/**/*.ts", "resources/js/**/*.d.ts", "resources/js/**/*.vue"]
}
```

Install:

```bash
pnpm install
```

- [ ] **Step 3: Add the Tailwind and shadcn-vue token layers**

`apps/studio/resources/css/app.css` — the shared design tokens every surface inherits:

```css
@import "tailwindcss";

@custom-variant dark (&:is(.dark *));

:root {
  --radius: 0.5rem;
  --background: oklch(1 0 0);
  --foreground: oklch(0.141 0.005 285.823);
  --card: oklch(1 0 0);
  --card-foreground: oklch(0.141 0.005 285.823);
  --primary: oklch(0.21 0.006 285.885);
  --primary-foreground: oklch(0.985 0 0);
  --secondary: oklch(0.967 0.001 286.375);
  --secondary-foreground: oklch(0.21 0.006 285.885);
  --muted: oklch(0.967 0.001 286.375);
  --muted-foreground: oklch(0.552 0.016 285.938);
  --destructive: oklch(0.577 0.245 27.325);
  --destructive-foreground: oklch(0.985 0 0);
  --border: oklch(0.92 0.004 286.32);
  --input: oklch(0.92 0.004 286.32);
  --ring: oklch(0.705 0.015 286.067);
}

@theme inline {
  --radius-md: var(--radius);
  --color-background: var(--background);
  --color-foreground: var(--foreground);
  --color-card: var(--card);
  --color-card-foreground: var(--card-foreground);
  --color-primary: var(--primary);
  --color-primary-foreground: var(--primary-foreground);
  --color-secondary: var(--secondary);
  --color-secondary-foreground: var(--secondary-foreground);
  --color-muted: var(--muted);
  --color-muted-foreground: var(--muted-foreground);
  --color-destructive: var(--destructive);
  --color-destructive-foreground: var(--destructive-foreground);
  --color-border: var(--border);
  --color-input: var(--input);
  --color-ring: var(--ring);
}

@layer base {
  * {
    @apply border-border;
  }

  body {
    @apply bg-background text-foreground;
  }
}
```

`apps/studio/resources/css/admin.css` — the admin theme layer:

```css
@import "./app.css";

/*
 * Admin is an internal tool used all day: tighter radii and a denser rhythm.
 * The portal overrides the same tokens in the other direction in P2, which is
 * why the primitives themselves never fork.
 */
:root {
  --radius: 0.375rem;
}
```

- [ ] **Step 4: Add the shadcn-vue registry and the cn() helper**

`apps/studio/components.json`:

```json
{
  "$schema": "https://shadcn-vue.com/schema.json",
  "style": "new-york",
  "typescript": true,
  "tsConfigPath": "./tsconfig.json",
  "tailwind": {
    "config": "",
    "css": "resources/css/app.css",
    "baseColor": "zinc",
    "cssVariables": true
  },
  "aliases": {
    "components": "@/components",
    "composables": "@/composables",
    "utils": "@/lib/utils",
    "ui": "@/ui",
    "lib": "@/lib"
  },
  "iconLibrary": "lucide"
}
```

`apps/studio/resources/js/lib/utils.ts`:

```ts
import { type ClassValue, clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}
```

`components.json` is written by hand rather than through `shadcn-vue init` so the configuration is reviewable in the diff and the step is reproducible without an interactive prompt. With it present, `add` runs non-interactively:

```bash
cd apps/studio && pnpm dlx shadcn-vue@latest add button input label card alert --yes
```

Expected: `resources/js/ui/button/`, `input/`, `label/`, `card/`, and `alert/` are created.

- [ ] **Step 5: Create the root view and the admin entrypoint**

`apps/studio/resources/views/app-admin.blade.php`:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title inertia>{{ config('app.name', 'Studio') }}</title>
    @vite('resources/js/admin/main.ts')
    @inertiaHead
</head>
<body class="min-h-screen bg-background font-sans antialiased">
    @inertia
</body>
</html>
```

`apps/studio/resources/js/admin/main.ts`:

```ts
import { createApp, h, type DefineComponent } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import '../../css/admin.css'

// The glob is relative to this file, so the admin bundle can only ever
// resolve admin pages. Surface isolation holds in the build, not just at
// runtime.
const pages = import.meta.glob<{ default: DefineComponent }>('./pages/**/*.vue')

createInertiaApp({
  title: (title) => (title ? `${title} · Studio` : 'Studio'),
  resolve: (name) => {
    const page = pages[`./pages/${name}.vue`]

    if (!page) {
      throw new Error(`Unknown admin page: ${name}`)
    }

    return page()
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .mount(el)
  },
})
```

- [ ] **Step 6: Write the failing tests**

`apps/studio/tests/Feature/InertiaRenderingTest.php`:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

// The web middleware group starts a database-backed session on every request.
uses(RefreshDatabase::class);

it('renders the login page as an inertia component', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
});

it('renders through the admin root view', function () {
    $response = $this->get('/login')->assertOk();

    expect($response->original->name())->toBe('app-admin');
});
```

`apps/studio/tests/e2e/login-page.spec.ts`:

```ts
import { expect, test } from '@playwright/test'

test('the login page renders its fields', async ({ page }) => {
  await page.goto('/login')

  await expect(page.getByLabel('Email')).toBeVisible()
  await expect(page.getByLabel('Password')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Sign in' })).toBeVisible()
})
```

`apps/studio/playwright.config.ts`:

```ts
import { defineConfig } from '@playwright/test'

// CI installs PHP natively; locally there is no host PHP, so the server runs
// in the compose container that publishes port 8000.
const serve = 'php artisan serve --host=0.0.0.0 --port=8000'

export default defineConfig({
  testDir: './tests/e2e',
  timeout: 30_000,
  use: {
    baseURL: 'http://127.0.0.1:8000',
    trace: 'on-first-retry',
  },
  webServer: {
    cwd: process.env.CI ? '.' : '../..',
    command: process.env.CI
      ? serve
      : `docker compose run --rm --service-ports -e APP_SURFACE=admin php ${serve}`,
    url: 'http://127.0.0.1:8000/up',
    reuseExistingServer: !process.env.CI,
    env: { APP_SURFACE: 'admin' },
    timeout: 120_000,
  },
})
```

`cwd` differs because `docker compose` must run from the repository root where `docker-compose.yml` lives, while `php artisan` must run from `apps/studio`.

- [ ] **Step 7: Run the tests to verify they fail**

Run: `docker compose run --rm php ./vendor/bin/pest --filter=InertiaRendering`
Expected: FAIL — `/login` still returns the string placeholder from Task 2, so `assertInertia` reports that the response is not an Inertia response.

- [ ] **Step 8: Build the login page shell and point the route at it**

`apps/studio/resources/js/admin/pages/Auth/Login.vue`:

```vue
<script setup lang="ts">
import { Button } from '@/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/ui/card'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
</script>

<template>
  <main class="flex min-h-screen items-center justify-center p-6">
    <Card class="w-full max-w-sm">
      <CardHeader>
        <CardTitle>Sign in</CardTitle>
        <CardDescription>Staff access to the studio.</CardDescription>
      </CardHeader>
      <CardContent>
        <form class="space-y-4">
          <div class="space-y-2">
            <Label for="email">Email</Label>
            <Input id="email" type="email" autocomplete="username" required />
          </div>

          <div class="space-y-2">
            <Label for="password">Password</Label>
            <Input id="password" type="password" autocomplete="current-password" required />
          </div>

          <Button type="submit" class="w-full">Sign in</Button>
        </form>
      </CardContent>
    </Card>
  </main>
</template>
```

In `apps/studio/routes/admin.php`, replace the placeholder:

```php
<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Staff surface. Session-authenticated, Inertia-rendered, never public.
Route::get('/login', fn () => Inertia::render('Auth/Login'))->name('login');
```

- [ ] **Step 9: Run the tests to verify they pass**

Run: `docker compose run --rm php ./vendor/bin/pest --filter=InertiaRendering`
Expected: PASS, 2 tests.

Then the front-end checks:

```bash
pnpm --filter @studio/app lint
pnpm --filter @studio/app build
```

Expected: `vue-tsc --noEmit` exits 0 and Vite writes `public/build/manifest.json`.

Then the journey, which needs built assets and a running server:

```bash
pnpm --filter @studio/app exec playwright install --with-deps chromium
pnpm --filter @studio/app test:e2e
```

Expected: PASS, 1 test.

- [ ] **Step 10: Add the e2e job to CI**

Append to `.github/workflows/ci.yml`:

```yaml
  e2e:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:18-alpine
        env:
          POSTGRES_DB: studio_test
          POSTGRES_USER: studio
          POSTGRES_PASSWORD: studio
        ports: ["5432:5432"]
        options: >-
          --health-cmd "pg_isready -U studio"
          --health-interval 2s
          --health-timeout 3s
          --health-retries 30
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: "8.3"
          extensions: pdo_pgsql, bcmath, gd, intl
          coverage: none
      - uses: pnpm/action-setup@v4
        with: { version: 10 }
      - uses: actions/setup-node@v4
        with: { node-version: 24, cache: pnpm }
      - run: pnpm install --frozen-lockfile
      - run: pnpm --filter @studio/app build
      - working-directory: apps/studio
        env:
          DB_CONNECTION: pgsql
          DB_HOST: 127.0.0.1
          DB_PORT: 5432
          DB_DATABASE: studio_test
          DB_USERNAME: studio
          DB_PASSWORD: studio
          APP_SURFACE: admin
        run: |
          cp .env.example .env
          sed -i 's/^DB_HOST=.*/DB_HOST=127.0.0.1/' .env
          php artisan key:generate
          php artisan migrate --force
      - run: pnpm --filter @studio/app exec playwright install --with-deps chromium
      - working-directory: apps/studio
        env:
          APP_SURFACE: admin
        run: pnpm exec playwright test
```

- [ ] **Step 11: Commit**

```bash
git add apps/studio package.json pnpm-lock.yaml .github/workflows/ci.yml
git commit -m "feat(studio): render the admin surface with Inertia, Vue, and shadcn-vue"
```

---

### Task 4: Schema conventions — tenant seam and public identifiers

**Files:**
- Create: `apps/studio/config/tenancy.php`
- Create: `apps/studio/app/Concerns/BelongsToTenant.php`
- Create: `apps/studio/app/Concerns/HasPublicId.php`
- Create: `apps/studio/tests/Feature/TenantSeamTest.php`
- Create: `apps/studio/tests/Feature/PublicIdTest.php`
- Modify: `apps/studio/app/Providers/AppServiceProvider.php`, `apps/studio/.env.example`

**Interfaces:**
- Consumes: the Laravel app from Task 2
- Produces: `Blueprint::tenantId()` and `Blueprint::publicId()` macros for migrations; `App\Concerns\BelongsToTenant` applying a global scope on `tenant_id` and auto-filling it on create; `App\Concerns\HasPublicId` generating a CUID2 on create, hiding `id`, resolving route binding on `public_id`, and exposing `Model::findByPublicId(string $publicId): ?static`; `config('tenancy.tenant_id')`

Both macros are schema conventions registered in the same provider and exercised by the same probe table, so they ship together — a reviewer approving one and rejecting the other would leave the migration helpers half-defined.

- [ ] **Step 1: Write the failing tests**

`apps/studio/tests/Feature/TenantSeamTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('seam_probes', function (Blueprint $table) {
        $table->id();
        $table->tenantId();
        $table->publicId();
        $table->string('label');
    });
});

afterEach(fn () => Schema::dropIfExists('seam_probes'));

it('fills the tenant id on create', function () {
    $probe = TenantProbe::create(['label' => 'first']);

    expect($probe->fresh()->tenant_id)->toBe(config('tenancy.tenant_id'));
});

it('hides rows belonging to another tenant', function () {
    TenantProbe::create(['label' => 'ours']);

    DB::table('seam_probes')->insert([
        'tenant_id' => '00000000-0000-4000-8000-00000000dead',
        'public_id' => 'aaaaaaaaaaaaaaaaaaaaaaaa',
        'label' => 'theirs',
    ]);

    expect(TenantProbe::pluck('label')->all())->toBe(['ours']);
});

class TenantProbe extends \Illuminate\Database\Eloquent\Model
{
    use \App\Concerns\BelongsToTenant;
    use \App\Concerns\HasPublicId;

    protected $table = 'seam_probes';

    public $timestamps = false;

    protected $fillable = ['label'];
}
```

`apps/studio/tests/Feature/PublicIdTest.php`:

```php
<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\TenantProbe;

beforeEach(function () {
    Schema::create('seam_probes', function (Blueprint $table) {
        $table->id();
        $table->tenantId();
        $table->publicId();
        $table->string('label');
    });
});

afterEach(fn () => Schema::dropIfExists('seam_probes'));

it('generates a 24 character cuid2 on create', function () {
    $probe = TenantProbe::create(['label' => 'first']);

    expect($probe->public_id)->toBeString()->toHaveLength(24)
        ->and($probe->public_id)->toMatch('/^[a-z][a-z0-9]{23}$/');
});

it('generates distinct identifiers that are not sequential', function () {
    $ids = collect(range(1, 20))
        ->map(fn (int $n) => TenantProbe::create(['label' => "probe-{$n}"])->public_id);

    expect($ids->unique())->toHaveCount(20)
        ->and($ids->sort()->values()->all())->not->toBe($ids->values()->all());
});

it('hides the internal primary key when serialising', function () {
    $probe = TenantProbe::create(['label' => 'first']);

    expect($probe->toArray())->not->toHaveKey('id')
        ->and($probe->toArray())->toHaveKey('public_id');
});

it('resolves route binding on the public id', function () {
    $probe = TenantProbe::create(['label' => 'first']);

    expect($probe->getRouteKeyName())->toBe('public_id')
        ->and($probe->getRouteKey())->toBe($probe->public_id);
});

it('finds a record by its public id', function () {
    $probe = TenantProbe::create(['label' => 'first']);

    expect(TenantProbe::findByPublicId($probe->public_id)?->id)->toBe($probe->id)
        ->and(TenantProbe::findByPublicId('nonexistentnonexistent01'))->toBeNull();
});

it('keeps the public id stable across updates', function () {
    $probe = TenantProbe::create(['label' => 'first']);
    $original = $probe->public_id;

    $probe->update(['label' => 'second']);

    expect($probe->fresh()->public_id)->toBe($original);
});
```

The two files share the `TenantProbe` model. It is declared once, in `TenantSeamTest.php` under the `Tests\Feature` namespace, and imported by `PublicIdTest.php`.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker compose run --rm php ./vendor/bin/pest --filter="TenantSeam|PublicId"`
Expected: FAIL — `Call to undefined method Blueprint::tenantId()`.

- [ ] **Step 3: Add the config**

`apps/studio/config/tenancy.php`:

```php
<?php

return [
    // Dormant multi-tenancy seam. One studio today; the column exists so
    // activating tenancy later is a data migration, not a schema rewrite.
    'tenant_id' => env('TENANT_ID', '00000000-0000-4000-8000-000000000001'),
];
```

Add `TENANT_ID=00000000-0000-4000-8000-000000000001` to `.env.example` and `.env`.

- [ ] **Step 4: Register the Blueprint macros**

The macros are runtime behaviour rather than schema, so they belong in a service provider — migrations need them registered before they run. Add to `boot()` in `apps/studio/app/Providers/AppServiceProvider.php`:

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

public function boot(): void
{
    Blueprint::macro('tenantId', function () {
        /** @var Blueprint $this */
        $column = $this->uuid('tenant_id')->default(config('tenancy.tenant_id'));
        $this->index('tenant_id');

        return $column;
    });

    Blueprint::macro('publicId', function () {
        /** @var Blueprint $this */
        return $this->char('public_id', 24)->unique();
    });

    // Filling a non-fillable attribute is silent by default, which turns a
    // factory state like withMfa() into a no-op that fails a test three tasks
    // later. Outside production, make it throw at the point of the mistake.
    Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
}
```

`char(24)` rather than `string`: CUID2 output is fixed width at the default length, and a fixed-width column keeps the unique index compact.

- [ ] **Step 5: Implement the traits**

Install the CUID2 library first:

```bash
docker run --rm -v "$PWD":/app -w /app/apps/studio composer:2 require visus/cuid2 --no-interaction
```

`apps/studio/app/Concerns/HasPublicId.php`:

```php
<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;
use Visus\Cuid2\Cuid2;

/**
 * Splits internal and external identity. The bigint primary key stays inside
 * the database — it carries foreign keys and joins. The CUID2 in public_id is
 * the only identifier that leaves the server, so sequential integers are never
 * enumerable by a client or guest.
 */
trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function (Model $model) {
            $model->public_id ??= (string) new Cuid2;
        });
    }

    public function initializeHasPublicId(): void
    {
        $this->hidden = array_values(array_unique([...$this->hidden, 'id']));
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public static function findByPublicId(string $publicId): ?static
    {
        return static::query()->where('public_id', $publicId)->first();
    }
}
```

`initializeHasPublicId()` runs per model instance, which is what makes `$hidden` additive rather than overwriting whatever the model already hides — `User` hides its password through the same array.

`apps/studio/app/Concerns/BelongsToTenant.php`:

```php
<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            $query->where($query->getModel()->getTable().'.tenant_id', config('tenancy.tenant_id'));
        });

        static::creating(function (Model $model) {
            $model->tenant_id ??= config('tenancy.tenant_id');
        });
    }
}
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `docker compose run --rm php ./vendor/bin/pest --filter="TenantSeam|PublicId"`
Expected: PASS, 8 tests (2 tenant, 6 public id).

- [ ] **Step 7: Commit**

```bash
git add apps/studio/config/tenancy.php apps/studio/app/Concerns apps/studio/app/Providers/AppServiceProvider.php apps/studio/tests/Feature/TenantSeamTest.php apps/studio/tests/Feature/PublicIdTest.php apps/studio/composer.json apps/studio/composer.lock apps/studio/.env.example
git commit -m "feat(studio): add tenant seam and CUID2 public identifiers"
```

---

### Task 5: Staff users and roles

**Files:**
- Create: `apps/studio/app/Enums/StaffRole.php`
- Modify: `apps/studio/app/Models/User.php`
- Create: `apps/studio/database/migrations/2026_08_07_000200_create_staff_users_table.php`
- Modify: `apps/studio/database/factories/UserFactory.php`
- Create: `apps/studio/tests/Feature/StaffUserTest.php`

**Interfaces:**
- Consumes: `BelongsToTenant` and `HasPublicId` (Task 4)
- Produces: `App\Models\User` with `public_id: string`, `role: StaffRole`, `mfa_secret: ?string` (encrypted), `mfa_confirmed_at: ?CarbonImmutable`, `mfa_recovery_codes: ?array` (encrypted), and `hasConfirmedMfa(): bool`; the `User::factory()->withMfa()` state

- [ ] **Step 1: Write the failing tests**

`apps/studio/tests/Feature/StaffUserTest.php`:

```php
<?php

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('creates a staff user with a role, a tenant, and a public id', function () {
    $user = User::factory()->create(['role' => StaffRole::Photographer]);

    expect($user->role)->toBe(StaffRole::Photographer)
        ->and($user->tenant_id)->toBe(config('tenancy.tenant_id'))
        ->and($user->public_id)->toHaveLength(24)
        ->and($user->hasConfirmedMfa())->toBeFalse();
});

it('hides the internal key and the credential fields when serialising', function () {
    $user = User::factory()->withMfa()->create();

    expect(array_keys($user->toArray()))
        ->not->toContain('id')
        ->not->toContain('password')
        ->not->toContain('mfa_secret')
        ->not->toContain('mfa_recovery_codes');
});

it('reports confirmed mfa once a secret is confirmed', function () {
    $user = User::factory()->withMfa()->create();

    expect($user->hasConfirmedMfa())->toBeTrue()
        ->and($user->mfa_secret)->not->toBeNull();
});

it('encrypts the mfa secret at rest', function () {
    $user = User::factory()->withMfa()->create(['mfa_secret' => 'PLAINTEXTSECRET']);

    $raw = DB::table('users')->where('id', $user->id)->value('mfa_secret');

    expect($raw)->not->toContain('PLAINTEXTSECRET');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker compose run --rm php ./vendor/bin/pest --filter=StaffUser`
Expected: FAIL — `Class "App\Enums\StaffRole" not found`.

- [ ] **Step 3: Add the role enum**

`apps/studio/app/Enums/StaffRole.php`:

```php
<?php

namespace App\Enums;

enum StaffRole: string
{
    case Owner = 'owner';
    case Photographer = 'photographer';
    case SecondShooter = 'second_shooter';
    case Editor = 'editor';
    case Assistant = 'assistant';
}
```

- [ ] **Step 4: Replace the users migration**

Delete Laravel's default `0001_01_01_000000_create_users_table.php` — the single file that creates `users`, `password_reset_tokens`, and `sessions` — and create `apps/studio/database/migrations/2026_08_07_000200_create_staff_users_table.php`. The replacement recreates `sessions` because the database session driver depends on it; `password_reset_tokens` is deliberately not recreated, since staff password resets are out of scope for P1a and clients never use passwords.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->string('role')->default('assistant');
            $table->text('mfa_secret')->nullable();
            $table->timestampTz('mfa_confirmed_at')->nullable();
            $table->text('mfa_recovery_codes')->nullable();
            $table->rememberToken();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'email']);
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
```

`mfa_recovery_codes` is `text` rather than `json` because the `encrypted:array` cast writes a ciphertext string, which is not valid JSON.

- [ ] **Step 5: Update the model**

`apps/studio/app/Models/User.php`:

```php
<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Enums\StaffRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;
    use Notifiable;

    /**
     * The MFA columns are fillable so factory states and seeders can set them.
     * They are never mass-assigned from request input: every controller that
     * changes them uses forceFill() with an explicit array.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'mfa_secret',
        'mfa_confirmed_at',
        'mfa_recovery_codes',
    ];

    protected $hidden = ['password', 'remember_token', 'mfa_secret', 'mfa_recovery_codes'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => StaffRole::class,
            'mfa_secret' => 'encrypted',
            'mfa_recovery_codes' => 'encrypted:array',
            'mfa_confirmed_at' => 'immutable_datetime',
        ];
    }

    public function hasConfirmedMfa(): bool
    {
        return $this->mfa_confirmed_at !== null;
    }
}
```

- [ ] **Step 6: Update the factory**

`apps/studio/database/factories/UserFactory.php` — replace the class body with:

```php
public function definition(): array
{
    return [
        'name' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'role' => \App\Enums\StaffRole::Owner,
    ];
}

public function withMfa(): static
{
    return $this->state(fn () => [
        'mfa_secret' => 'ABCDEFGHIJKLMNOP',
        'mfa_confirmed_at' => now(),
        'mfa_recovery_codes' => ['code-one', 'code-two'],
    ]);
}
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `docker compose run --rm php ./vendor/bin/pest --filter=StaffUser`
Expected: PASS, 4 tests.

- [ ] **Step 8: Commit**

```bash
git add apps/studio/app/Enums apps/studio/app/Models/User.php apps/studio/database apps/studio/tests/Feature/StaffUserTest.php
git commit -m "feat(studio): add staff users with roles and encrypted MFA fields"
```

---

### Task 6: Append-only audit log

**Files:**
- Create: `apps/studio/database/migrations/2026_08_07_000300_create_audit_logs_table.php`
- Create: `apps/studio/app/Models/AuditLog.php`
- Create: `apps/studio/app/Support/AuditLogger.php`
- Create: `apps/studio/tests/Feature/AuditLogTest.php`
- Create: `apps/studio/docs/database-grants.md`

**Interfaces:**
- Consumes: `BelongsToTenant` and `HasPublicId` (Task 4), `User` (Task 5)
- Produces: `AuditLogger::record(string $event, ?User $actor = null, array $context = [], ?string $subject = null): AuditLog`, written synchronously and reading IP and user agent from the current request

- [ ] **Step 1: Write the failing tests**

`apps/studio/tests/Feature/AuditLogTest.php`:

```php
<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('records an event with actor and context', function () {
    $user = User::factory()->create();

    $log = AuditLogger::record('staff.login.succeeded', $user, ['method' => 'password']);

    expect($log->event)->toBe('staff.login.succeeded')
        ->and($log->actor_id)->toBe($user->id)
        ->and($log->context)->toBe(['method' => 'password']);
});

it('records an event without an actor', function () {
    $log = AuditLogger::record('staff.login.failed', null, ['email_hash' => 'abc']);

    expect($log->actor_id)->toBeNull()
        ->and($log->event)->toBe('staff.login.failed');
});

it('refuses to update an existing entry', function () {
    $log = AuditLogger::record('staff.logout', null);

    expect(fn () => $log->update(['event' => 'tampered']))
        ->toThrow(RuntimeException::class, 'Audit log entries are append-only.');
});

it('refuses to delete an existing entry', function () {
    $log = AuditLogger::record('staff.logout', null);

    expect(fn () => $log->delete())
        ->toThrow(RuntimeException::class, 'Audit log entries are append-only.');
});

it('never stores raw credentials in context', function () {
    $log = AuditLogger::record('staff.login.failed', null, [
        'password' => 'hunter2',
        'code' => '123456',
        'email' => 'someone@example.com',
    ]);

    expect($log->context)->not->toHaveKey('password')
        ->and($log->context)->not->toHaveKey('code')
        ->and($log->context['email'])->toBe('[redacted]');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker compose run --rm php ./vendor/bin/pest --filter=AuditLog`
Expected: FAIL — `Class "App\Models\AuditLog" not found`.

- [ ] **Step 3: Create the migration**

`apps/studio/database/migrations/2026_08_07_000300_create_audit_logs_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->string('event')->index();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject')->nullable()->index();
            $table->jsonb('context')->default('{}');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
```

`actor_id` is nullable on delete rather than cascading: removing a staff account must not erase the record of what that account did.

- [ ] **Step 4: Implement the model**

`apps/studio/app/Models/AuditLog.php`:

```php
<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class AuditLog extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['context' => 'array', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Audit log entries are append-only.'));
        static::deleting(fn () => throw new RuntimeException('Audit log entries are append-only.'));
    }
}
```

- [ ] **Step 5: Implement the logger**

`apps/studio/app/Support/AuditLogger.php`:

```php
<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /** Keys dropped outright — never appear in an audit row. */
    private const FORBIDDEN = ['password', 'password_confirmation', 'code', 'secret', 'token'];

    /** Keys kept as evidence of shape but masked. */
    private const REDACTED = ['email'];

    public static function record(
        string $event,
        ?User $actor = null,
        array $context = [],
        ?string $subject = null,
    ): AuditLog {
        return AuditLog::create([
            'event' => $event,
            'actor_id' => $actor?->id,
            'subject' => $subject,
            'context' => self::sanitize($context),
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 500),
        ]);
    }

    private static function sanitize(array $context): array
    {
        foreach (self::FORBIDDEN as $key) {
            unset($context[$key]);
        }

        foreach (self::REDACTED as $key) {
            if (array_key_exists($key, $context)) {
                $context[$key] = '[redacted]';
            }
        }

        return $context;
    }
}
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `docker compose run --rm php ./vendor/bin/pest --filter=AuditLog`
Expected: PASS, 5 tests.

- [ ] **Step 7: Document the database-level enforcement**

Model guards stop application bugs; they do not stop a console session or a compromised credential. Record the deployment requirement in `apps/studio/docs/database-grants.md`:

````markdown
# Database grants

The application connects as a role that cannot rewrite history. Run once per
environment, as a superuser, after the first migration:

```sql
REVOKE UPDATE, DELETE, TRUNCATE ON TABLE audit_logs FROM studio_app;
GRANT INSERT, SELECT ON TABLE audit_logs TO studio_app;
```

Migrations run as a separate, more privileged role (`studio_migrator`) so that
schema changes remain possible while the request-path role stays constrained.

This is intentionally not applied by a migration: a migration running as the
app role could simply grant the privileges back, which would make the control
decorative.
````

- [ ] **Step 8: Commit**

```bash
git add apps/studio/app/Models/AuditLog.php apps/studio/app/Support/AuditLogger.php apps/studio/database/migrations apps/studio/tests/Feature/AuditLogTest.php apps/studio/docs/database-grants.md
git commit -m "feat(studio): add append-only audit log with context sanitisation"
```

---

### Task 7: Staff password login, end to end

**Files:**
- Create: `apps/studio/app/Http/Controllers/Staff/SessionController.php`
- Create: `apps/studio/app/Http/Controllers/Staff/DashboardController.php`
- Create: `apps/studio/resources/js/admin/pages/Dashboard.vue`
- Modify: `apps/studio/resources/js/admin/pages/Auth/Login.vue`
- Create: `apps/studio/tests/Feature/Staff/LoginTest.php`
- Create: `apps/studio/tests/e2e/login.spec.ts`
- Modify: `apps/studio/routes/admin.php`, `apps/studio/config/auth.php`, `apps/studio/config/session.php`, `apps/studio/.env.example`
- Delete: `apps/studio/tests/e2e/login-page.spec.ts`

**Interfaces:**
- Consumes: `User` (Task 5), `AuditLogger` (Task 6), the Inertia pipeline (Task 3)
- Produces: a `staff` session guard; `GET /login` rendering `Auth/Login`; `POST /login` redirecting to `/` on success, to `/auth/mfa` when the account has confirmed MFA, and back to `/login` with a uniform validation error otherwise; `POST /logout`; `GET /` rendering `Dashboard`

The endpoint, the page that calls it, and the browser journey that proves the pair works ship in one task. Splitting them would let a reviewer approve a login endpoint nobody can reach, or a form that posts into a void.

- [ ] **Step 1: Write the failing tests**

`apps/studio/tests/Feature/Staff/LoginTest.php`:

```php
<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(fn () => RateLimiter::clear('staff-login|127.0.0.1'));

it('signs in a user without mfa and starts a staff session', function () {
    $user = User::factory()->create(['email' => 'owner@studio.test', 'password' => 'correct-horse']);

    $this->post('/login', ['email' => 'owner@studio.test', 'password' => 'correct-horse'])
        ->assertRedirect('/');

    $this->assertAuthenticatedAs($user, 'staff');
});

it('sends a user with confirmed mfa to the challenge without starting a session', function () {
    User::factory()->withMfa()->create(['email' => 'owner@studio.test', 'password' => 'correct-horse']);

    $this->post('/login', ['email' => 'owner@studio.test', 'password' => 'correct-horse'])
        ->assertRedirect('/auth/mfa');

    $this->assertGuest('staff');
});

it('returns an identical error for a wrong password and an unknown account', function () {
    User::factory()->create(['email' => 'owner@studio.test', 'password' => 'correct-horse']);

    $this->from('/login')
        ->post('/login', ['email' => 'owner@studio.test', 'password' => 'nope'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

    RateLimiter::clear('staff-login|127.0.0.1');

    $this->from('/login')
        ->post('/login', ['email' => 'ghost@studio.test', 'password' => 'nope'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
});

it('throttles repeated failures from one address', function () {
    User::factory()->create(['email' => 'owner@studio.test', 'password' => 'correct-horse']);

    foreach (range(1, 5) as $attempt) {
        $this->from('/login')->post('/login', [
            'email' => 'owner@studio.test',
            'password' => 'nope',
        ])->assertSessionHasErrors('email');
    }

    // The correct password is now refused too: the limit is on the address.
    $this->from('/login')->post('/login', [
        'email' => 'owner@studio.test',
        'password' => 'correct-horse',
    ])->assertSessionHasErrors(['email' => 'Too many attempts. Try again later.']);

    $this->assertGuest('staff');
    expect(AuditLog::where('event', 'staff.login.throttled')->count())->toBe(1);
});

it('audits successful and failed logins without storing the password', function () {
    User::factory()->create(['email' => 'owner@studio.test', 'password' => 'correct-horse']);

    $this->post('/login', ['email' => 'owner@studio.test', 'password' => 'nope']);
    $this->post('/login', ['email' => 'owner@studio.test', 'password' => 'correct-horse']);

    $events = AuditLog::orderBy('id')->pluck('event')->all();

    expect($events)->toBe(['staff.login.failed', 'staff.login.succeeded'])
        ->and(AuditLog::all()->pluck('context')->flatten()->all())->not->toContain('correct-horse');
});

it('signs out and clears the session', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'staff')
        ->post('/logout')
        ->assertRedirect('/login');

    $this->assertGuest('staff');
    expect(AuditLog::where('event', 'staff.logout')->count())->toBe(1);
});

it('redirects an unauthenticated visitor from the dashboard to the login page', function () {
    $this->get('/')->assertRedirect('/login');
});
```

`apps/studio/tests/e2e/login.spec.ts` — replaces `login-page.spec.ts`:

```ts
import { expect, test } from '@playwright/test'

test('a staff user without MFA signs in and reaches the dashboard', async ({ page }) => {
  await page.goto('/login')

  await page.getByLabel('Email').fill('e2e-basic@studio.test')
  await page.getByLabel('Password').fill('correct-horse')
  await page.getByRole('button', { name: 'Sign in' }).click()

  await expect(page).toHaveURL('/')
  await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible()
})

test('a wrong password shows an error and stays on the login page', async ({ page }) => {
  await page.goto('/login')

  await page.getByLabel('Email').fill('e2e-basic@studio.test')
  await page.getByLabel('Password').fill('definitely-wrong')
  await page.getByRole('button', { name: 'Sign in' }).click()

  await expect(page.getByText('These credentials do not match our records.')).toBeVisible()
  await expect(page).toHaveURL('/login')
})
```

The journey needs a seeded account. Create `apps/studio/database/seeders/E2eSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Accounts used only by the Playwright suite. Never registered in
 * DatabaseSeeder — it is invoked explicitly by the e2e setup.
 */
class E2eSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'e2e-basic@studio.test'],
            [
                'name' => 'E2E Basic',
                'password' => 'correct-horse',
                'role' => StaffRole::Owner,
            ],
        );
    }
}
```

Seed before serving by changing the `serve` constant in `apps/studio/playwright.config.ts`:

```ts
const serve =
  'sh -c "php artisan db:seed --class=E2eSeeder --force && php artisan serve --host=0.0.0.0 --port=8000"'
```

Both branches of `webServer.command` pick this up unchanged, so the local Docker invocation and the CI invocation stay in step.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker compose run --rm php ./vendor/bin/pest --filter=LoginTest`
Expected: FAIL — `POST /login` returns 405, because Task 3 registered only the GET route.

- [ ] **Step 3: Configure the staff guard and the per-surface session cookie**

In `apps/studio/config/auth.php`, add to `guards`:

```php
'staff' => [
    'driver' => 'session',
    'provider' => 'users',
],
```

In `apps/studio/config/session.php`, make the cookie name surface-specific:

```php
'cookie' => env(
    'SESSION_COOKIE',
    'studio_'.env('APP_SURFACE', 'admin').'_session',
),
```

Two surfaces on two hosts with two cookie names means a portal session is never transmitted to the admin host in the first place, rather than being transmitted and rejected.

Add to `.env.example` and `.env`:

```
SESSION_LIFETIME=720
```

720 minutes is the 12-hour idle window from the vision's session table.

- [ ] **Step 4: Implement the controllers**

`apps/studio/app/Http/Controllers/Staff/SessionController.php`:

```php
<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const DECAY_SECONDS = 300;

    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'staff-login|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            AuditLogger::record('staff.login.throttled', null, ['email' => $credentials['email']]);

            throw ValidationException::withMessages([
                'email' => __('Too many attempts. Try again later.'),
            ]);
        }

        $user = User::where('email', $credentials['email'])->first();

        // Always hash, even for an unknown account, so response timing does
        // not reveal whether the address exists.
        $passwordMatches = Hash::check(
            $credentials['password'],
            $user?->password ?? '$2y$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinv',
        );

        if (! $user || ! $passwordMatches) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            AuditLogger::record('staff.login.failed', null, ['email' => $credentials['email']]);

            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        RateLimiter::clear($key);

        if ($user->hasConfirmedMfa()) {
            $request->session()->put('mfa.pending_user_id', $user->id);
            AuditLogger::record('staff.login.mfa_required', $user);

            return redirect('/auth/mfa');
        }

        Auth::guard('staff')->login($user);
        $request->session()->regenerate();
        AuditLogger::record('staff.login.succeeded', $user, ['method' => 'password']);

        return redirect()->intended('/');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user('staff');

        Auth::guard('staff')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        AuditLogger::record('staff.logout', $user);

        return redirect('/login');
    }
}
```

The throttle raises a `ValidationException` rather than returning HTTP 429 because Inertia expects a redirect-with-errors for a rejected form submission; a bare 429 surfaces as an error overlay instead of a message on the field. The event is still distinguishable for monitoring — `staff.login.throttled` is its own audit row.

`apps/studio/app/Http/Controllers/Staff/DashboardController.php`:

```php
<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        // Real counts arrive in P1b with leads and jobs. The page exists now
        // so the MFA gate and the audience gate have something to protect.
        return Inertia::render('Dashboard', [
            'leads' => 0,
            'jobs' => 0,
        ]);
    }
}
```

- [ ] **Step 5: Register the routes**

`apps/studio/routes/admin.php`:

```php
<?php

use App\Http\Controllers\Staff\DashboardController;
use App\Http\Controllers\Staff\SessionController;
use Illuminate\Support\Facades\Route;

// Staff surface. Session-authenticated, Inertia-rendered, never public.
Route::middleware('guest:staff')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store']);
});

Route::middleware('auth:staff')->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy']);
    Route::get('/', DashboardController::class)->name('dashboard');
});
```

`auth:staff` is a placeholder that Task 9 replaces with `audience:staff`.

In `apps/studio/bootstrap/app.php`, inside `->withMiddleware(...)`, tell Laravel where to send unauthenticated visitors and where to send already-authenticated ones:

```php
$middleware->redirectGuestsTo('/login');
$middleware->redirectUsersTo('/');
```

- [ ] **Step 6: Wire the login form and add the dashboard page**

`apps/studio/resources/js/admin/pages/Auth/Login.vue` — replace the static form:

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Alert, AlertDescription } from '@/ui/alert'
import { Button } from '@/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/ui/card'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

const form = useForm({ email: '', password: '' })

function submit() {
  form.post('/login', { onFinish: () => form.reset('password') })
}
</script>

<template>
  <main class="flex min-h-screen items-center justify-center p-6">
    <Card class="w-full max-w-sm">
      <CardHeader>
        <CardTitle>Sign in</CardTitle>
        <CardDescription>Staff access to the studio.</CardDescription>
      </CardHeader>
      <CardContent>
        <form class="space-y-4" @submit.prevent="submit">
          <Alert v-if="form.errors.email" variant="destructive">
            <AlertDescription>{{ form.errors.email }}</AlertDescription>
          </Alert>

          <div class="space-y-2">
            <Label for="email">Email</Label>
            <Input
              id="email"
              v-model="form.email"
              type="email"
              autocomplete="username"
              required
            />
          </div>

          <div class="space-y-2">
            <Label for="password">Password</Label>
            <Input
              id="password"
              v-model="form.password"
              type="password"
              autocomplete="current-password"
              required
            />
          </div>

          <Button type="submit" class="w-full" :disabled="form.processing">Sign in</Button>
        </form>
      </CardContent>
    </Card>
  </main>
</template>
```

`apps/studio/resources/js/admin/pages/Dashboard.vue`:

```vue
<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/ui/card'

defineProps<{ leads: number; jobs: number }>()
</script>

<template>
  <main class="mx-auto max-w-4xl space-y-6 p-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
      <Button variant="secondary" @click="router.post('/logout')">Sign out</Button>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
      <Card>
        <CardHeader><CardTitle class="text-sm font-medium">Open leads</CardTitle></CardHeader>
        <CardContent class="text-3xl font-semibold tabular-nums">{{ leads }}</CardContent>
      </Card>
      <Card>
        <CardHeader><CardTitle class="text-sm font-medium">Active jobs</CardTitle></CardHeader>
        <CardContent class="text-3xl font-semibold tabular-nums">{{ jobs }}</CardContent>
      </Card>
    </div>
  </main>
</template>
```

Delete `apps/studio/tests/e2e/login-page.spec.ts` — `login.spec.ts` covers rendering as part of a journey that also proves the form works.

- [ ] **Step 7: Run the tests to verify they pass**

Run: `docker compose run --rm php ./vendor/bin/pest --filter=LoginTest`
Expected: PASS, 7 tests.

Then the journey:

```bash
pnpm --filter @studio/app lint
pnpm --filter @studio/app build
pnpm --filter @studio/app test:e2e
```

Expected: `vue-tsc` exits 0, the build succeeds, and both Playwright tests pass.

- [ ] **Step 8: Commit**

```bash
git add apps/studio/app/Http/Controllers/Staff apps/studio/resources/js/admin/pages apps/studio/routes/admin.php apps/studio/config apps/studio/bootstrap/app.php apps/studio/.env.example apps/studio/database/seeders apps/studio/tests apps/studio/playwright.config.ts
git rm apps/studio/tests/e2e/login-page.spec.ts
git commit -m "feat(studio): add staff password login with throttling and uniform failures"
```

---

### Task 8: Mandatory TOTP enrollment and challenge, end to end

**Files:**
- Create: `apps/studio/app/Http/Controllers/Staff/MfaEnrollmentController.php`
- Create: `apps/studio/app/Http/Controllers/Staff/MfaChallengeController.php`
- Create: `apps/studio/app/Http/Middleware/RequireConfirmedMfa.php`
- Create: `apps/studio/resources/js/admin/pages/Auth/MfaEnroll.vue`
- Create: `apps/studio/resources/js/admin/pages/Auth/MfaChallenge.vue`
- Create: `apps/studio/resources/js/admin/pages/Auth/RecoveryCodes.vue`
- Create: `apps/studio/tests/Feature/Staff/MfaTest.php`
- Create: `apps/studio/tests/e2e/mfa.spec.ts`
- Modify: `apps/studio/routes/admin.php`, `apps/studio/bootstrap/app.php`, `apps/studio/database/seeders/E2eSeeder.php`

**Interfaces:**
- Consumes: `User` (Task 5), `AuditLogger` (Task 6), the `staff` guard and the `mfa.pending_user_id` session key (Task 7)
- Produces: `GET /auth/mfa/enroll` rendering `Auth/MfaEnroll` with `secret: string` and `otpauthUri: string`; `POST /auth/mfa/confirm` redirecting to `/auth/mfa/recovery-codes`; `GET /auth/mfa/recovery-codes` rendering `Auth/RecoveryCodes` with `codes: string[]`; `GET /auth/mfa` rendering `Auth/MfaChallenge`; `POST /auth/mfa` establishing the session; the middleware alias `mfa` redirecting un-enrolled staff to enrollment

- [ ] **Step 1: Install the TOTP library**

```bash
docker run --rm -v "$PWD":/app -w /app/apps/studio composer:2 require pragmarx/google2fa --no-interaction
```

- [ ] **Step 2: Write the failing tests**

`apps/studio/tests/Feature/Staff/MfaTest.php`:

```php
<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('staff-login|127.0.0.1');
    RateLimiter::clear('staff-mfa|127.0.0.1');
});

it('sends an enrolled-but-unconfirmed user to enrollment instead of the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'staff')
        ->get('/')
        ->assertRedirect('/auth/mfa/enroll');
});

it('issues a secret and an otpauth uri on the enrollment page', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'staff')
        ->get('/auth/mfa/enroll')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/MfaEnroll')
            ->where('secret', fn (string $secret) => strlen($secret) === 16)
            ->where('otpauthUri', fn (string $uri) => str_starts_with($uri, 'otpauth://totp/'))
        );
});

it('reuses the same secret when the enrollment page is reloaded', function () {
    $user = User::factory()->create();

    $first = $this->actingAs($user, 'staff')->get('/auth/mfa/enroll');
    $second = $this->actingAs($user, 'staff')->get('/auth/mfa/enroll');

    expect($first->viewData('page')['props']['secret'])
        ->toBe($second->viewData('page')['props']['secret']);
});

it('confirms enrollment with a valid code and shows recovery codes once', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'staff');

    $secret = $this->get('/auth/mfa/enroll')->viewData('page')['props']['secret'];
    $code = (new Google2FA)->getCurrentOtp($secret);

    $this->post('/auth/mfa/confirm', ['code' => $code])
        ->assertRedirect('/auth/mfa/recovery-codes');

    $this->get('/auth/mfa/recovery-codes')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/RecoveryCodes')
            ->has('codes', 8)
        );

    expect($user->fresh()->hasConfirmedMfa())->toBeTrue();

    // The flash is consumed; a reload does not show the codes again.
    $this->get('/auth/mfa/recovery-codes')->assertRedirect('/');
});

it('rejects confirmation with an invalid code', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'staff');
    $this->get('/auth/mfa/enroll');

    $this->from('/auth/mfa/enroll')
        ->post('/auth/mfa/confirm', ['code' => '000000'])
        ->assertSessionHasErrors(['code' => 'That code is not valid.']);

    expect($user->fresh()->hasConfirmedMfa())->toBeFalse();
});

it('allows the dashboard once mfa is confirmed', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'staff');

    $secret = $this->get('/auth/mfa/enroll')->viewData('page')['props']['secret'];
    $this->post('/auth/mfa/confirm', ['code' => (new Google2FA)->getCurrentOtp($secret)]);

    $this->get('/')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard'));
});

it('completes login with a valid totp code', function () {
    $secret = (new Google2FA)->generateSecretKey();
    $user = User::factory()->withMfa()->create(['mfa_secret' => $secret]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/auth/mfa');

    $this->post('/auth/mfa', ['code' => (new Google2FA)->getCurrentOtp($secret)])
        ->assertRedirect('/');

    $this->assertAuthenticatedAs($user, 'staff');
});

it('rejects an invalid totp code and leaves the session unauthenticated', function () {
    $secret = (new Google2FA)->generateSecretKey();
    $user = User::factory()->withMfa()->create(['mfa_secret' => $secret]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->from('/auth/mfa')
        ->post('/auth/mfa', ['code' => '000000'])
        ->assertSessionHasErrors(['code' => 'That code is not valid.']);

    $this->assertGuest('staff');
    expect(AuditLog::where('event', 'staff.mfa.failed')->count())->toBe(1);
});

it('refuses the challenge page and the verify route without a pending login', function () {
    $this->get('/auth/mfa')->assertRedirect('/login');
    $this->post('/auth/mfa', ['code' => '123456'])->assertRedirect('/login');
});

it('throttles repeated code guesses', function () {
    $secret = (new Google2FA)->generateSecretKey();
    $user = User::factory()->withMfa()->create(['mfa_secret' => $secret]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    foreach (range(1, 5) as $attempt) {
        $this->from('/auth/mfa')
            ->post('/auth/mfa', ['code' => '000000'])
            ->assertSessionHasErrors('code');
    }

    $this->from('/auth/mfa')
        ->post('/auth/mfa', ['code' => (new Google2FA)->getCurrentOtp($secret)])
        ->assertSessionHasErrors(['code' => 'Too many attempts. Try again later.']);

    $this->assertGuest('staff');
});

it('accepts a recovery code once and then invalidates it', function () {
    $user = User::factory()->withMfa()->create(['mfa_recovery_codes' => ['rescue-me']]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/auth/mfa', ['code' => 'rescue-me'])->assertRedirect('/');

    expect($user->fresh()->mfa_recovery_codes)->toBe([]);
    expect(AuditLog::where('event', 'staff.mfa.recovery_code_used')->count())->toBe(1);
});
```

`apps/studio/tests/e2e/mfa.spec.ts`:

```ts
import { expect, test } from '@playwright/test'
import { authenticator } from 'otplib'

const MFA_SECRET = 'JBSWY3DPEHPK3PXP'

test('a staff user with MFA is challenged before reaching the dashboard', async ({ page }) => {
  await page.goto('/login')

  await page.getByLabel('Email').fill('e2e-mfa@studio.test')
  await page.getByLabel('Password').fill('correct-horse')
  await page.getByRole('button', { name: 'Sign in' }).click()

  await expect(page).toHaveURL('/auth/mfa')

  await page.getByLabel('Authentication code').fill(authenticator.generate(MFA_SECRET))
  await page.getByRole('button', { name: 'Verify' }).click()

  await expect(page).toHaveURL('/')
  await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible()
})

test('a staff user without MFA is forced through enrollment', async ({ page }) => {
  await page.goto('/login')

  await page.getByLabel('Email').fill('e2e-basic@studio.test')
  await page.getByLabel('Password').fill('correct-horse')
  await page.getByRole('button', { name: 'Sign in' }).click()

  await expect(page).toHaveURL('/auth/mfa/enroll')
  await expect(page.getByRole('heading', { name: 'Set up two-factor authentication' })).toBeVisible()
})
```

Install the code generator used by the journey:

```bash
pnpm add -D --filter @studio/app otplib
```

Extend `apps/studio/database/seeders/E2eSeeder.php` with the MFA account, and note that `e2e-basic@studio.test` now lands on enrollment rather than the dashboard — update `login.spec.ts` accordingly in Step 7:

```php
        User::updateOrCreate(
            ['email' => 'e2e-mfa@studio.test'],
            [
                'name' => 'E2E MFA',
                'password' => 'correct-horse',
                'role' => StaffRole::Owner,
                'mfa_secret' => 'JBSWY3DPEHPK3PXP',
                'mfa_confirmed_at' => now(),
                'mfa_recovery_codes' => [],
            ],
        );
```

- [ ] **Step 3: Run the tests to verify they fail**

Run: `docker compose run --rm php ./vendor/bin/pest --filter=MfaTest`
Expected: FAIL — `GET /auth/mfa/enroll` returns 404.

- [ ] **Step 4: Implement enrollment**

`apps/studio/app/Http/Controllers/Staff/MfaEnrollmentController.php`:

```php
<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

class MfaEnrollmentController extends Controller
{
    public function __construct(private readonly Google2FA $google2fa) {}

    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user('staff');

        if ($user->hasConfirmedMfa()) {
            return redirect('/');
        }

        // Reusing an existing unconfirmed secret makes a page reload harmless:
        // the QR code the user is midway through scanning stays valid.
        if ($user->mfa_secret === null) {
            $user->forceFill(['mfa_secret' => $this->google2fa->generateSecretKey()])->save();
            AuditLogger::record('staff.mfa.enrollment_started', $user);
        }

        return Inertia::render('Auth/MfaEnroll', [
            'secret' => $user->mfa_secret,
            'otpauthUri' => $this->google2fa->getQRCodeUrl(
                config('app.name'),
                $user->email,
                $user->mfa_secret,
            ),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $user = $request->user('staff');
        $data = $request->validate(['code' => ['required', 'string']]);

        abort_if($user->mfa_secret === null, 409, 'Start enrollment first.');

        if (! $this->google2fa->verifyKey($user->mfa_secret, $data['code'])) {
            AuditLogger::record('staff.mfa.enrollment_failed', $user);

            throw ValidationException::withMessages(['code' => __('That code is not valid.')]);
        }

        $recoveryCodes = collect(range(1, 8))
            ->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))
            ->all();

        $user->forceFill([
            'mfa_confirmed_at' => now(),
            'mfa_recovery_codes' => $recoveryCodes,
        ])->save();

        AuditLogger::record('staff.mfa.enrolled', $user);

        // Flashed rather than rendered inline: the codes are shown exactly
        // once, and a reload must not resurrect them.
        return redirect('/auth/mfa/recovery-codes')->with('recovery_codes', $recoveryCodes);
    }

    public function recoveryCodes(Request $request): Response|RedirectResponse
    {
        $codes = $request->session()->get('recovery_codes');

        if (! is_array($codes)) {
            return redirect('/');
        }

        return Inertia::render('Auth/RecoveryCodes', ['codes' => $codes]);
    }
}
```

- [ ] **Step 5: Implement the login challenge**

`apps/studio/app/Http/Controllers/Staff/MfaChallengeController.php`:

```php
<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

class MfaChallengeController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const DECAY_SECONDS = 300;

    public function __construct(private readonly Google2FA $google2fa) {}

    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->session()->get('mfa.pending_user_id') === null) {
            return redirect('/login');
        }

        return Inertia::render('Auth/MfaChallenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $pendingId = $request->session()->get('mfa.pending_user_id');

        if ($pendingId === null) {
            return redirect('/login');
        }

        $data = $request->validate(['code' => ['required', 'string']]);

        $key = 'staff-mfa|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            AuditLogger::record('staff.mfa.throttled', null);

            throw ValidationException::withMessages([
                'code' => __('Too many attempts. Try again later.'),
            ]);
        }

        $user = User::findOrFail($pendingId);

        if (! $this->accepts($user, $data['code'])) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            AuditLogger::record('staff.mfa.failed', $user);

            throw ValidationException::withMessages(['code' => __('That code is not valid.')]);
        }

        RateLimiter::clear($key);
        $request->session()->forget('mfa.pending_user_id');

        Auth::guard('staff')->login($user);
        $request->session()->regenerate();
        AuditLogger::record('staff.login.succeeded', $user, ['method' => 'totp']);

        return redirect()->intended('/');
    }

    private function accepts(User $user, string $code): bool
    {
        if ($this->google2fa->verifyKey((string) $user->mfa_secret, $code)) {
            return true;
        }

        $stored = $user->mfa_recovery_codes ?? [];

        $remaining = collect($stored)
            ->reject(fn (string $candidate) => hash_equals($candidate, $code));

        if ($remaining->count() === count($stored)) {
            return false;
        }

        $user->forceFill(['mfa_recovery_codes' => $remaining->values()->all()])->save();
        AuditLogger::record('staff.mfa.recovery_code_used', $user);

        return true;
    }
}
```

- [ ] **Step 6: Implement the enforcement middleware**

`apps/studio/app/Http/Middleware/RequireConfirmedMfa.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff MFA is mandatory. An authenticated but un-enrolled user is sent to
 * enrollment rather than shown an error, because enrollment is the only
 * action available to them.
 */
class RequireConfirmedMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user('staff')?->hasConfirmedMfa()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Multi-factor authentication enrollment is required.'], 403)
                : redirect('/auth/mfa/enroll');
        }

        return $next($request);
    }
}
```

Register the alias in `apps/studio/bootstrap/app.php`:

```php
$middleware->alias([
    'mfa' => \App\Http\Middleware\RequireConfirmedMfa::class,
]);
```

- [ ] **Step 7: Wire the routes and build the pages**

Replace `apps/studio/routes/admin.php`:

```php
<?php

use App\Http\Controllers\Staff\DashboardController;
use App\Http\Controllers\Staff\MfaChallengeController;
use App\Http\Controllers\Staff\MfaEnrollmentController;
use App\Http\Controllers\Staff\SessionController;
use Illuminate\Support\Facades\Route;

// Staff surface. Session-authenticated, Inertia-rendered, never public.
Route::middleware('guest:staff')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store']);

    // The challenge runs between password and session: authenticated by the
    // pending-user session key, not by the staff guard.
    Route::get('/auth/mfa', [MfaChallengeController::class, 'create']);
    Route::post('/auth/mfa', [MfaChallengeController::class, 'store']);
});

Route::middleware('auth:staff')->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy']);

    Route::get('/auth/mfa/enroll', [MfaEnrollmentController::class, 'create']);
    Route::post('/auth/mfa/confirm', [MfaEnrollmentController::class, 'confirm']);
    Route::get('/auth/mfa/recovery-codes', [MfaEnrollmentController::class, 'recoveryCodes']);

    Route::middleware('mfa')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
    });
});
```

`apps/studio/resources/js/admin/pages/Auth/MfaChallenge.vue`:

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Alert, AlertDescription } from '@/ui/alert'
import { Button } from '@/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/ui/card'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

const form = useForm({ code: '' })

function submit() {
  form.post('/auth/mfa', { onFinish: () => form.reset('code') })
}
</script>

<template>
  <main class="flex min-h-screen items-center justify-center p-6">
    <Card class="w-full max-w-sm">
      <CardHeader>
        <CardTitle>Two-factor authentication</CardTitle>
        <CardDescription>Enter the code from your authenticator app.</CardDescription>
      </CardHeader>
      <CardContent>
        <form class="space-y-4" @submit.prevent="submit">
          <Alert v-if="form.errors.code" variant="destructive">
            <AlertDescription>{{ form.errors.code }}</AlertDescription>
          </Alert>

          <div class="space-y-2">
            <Label for="code">Authentication code</Label>
            <Input
              id="code"
              v-model="form.code"
              inputmode="numeric"
              autocomplete="one-time-code"
              required
            />
          </div>

          <Button type="submit" class="w-full" :disabled="form.processing">Verify</Button>
        </form>
      </CardContent>
    </Card>
  </main>
</template>
```

`apps/studio/resources/js/admin/pages/Auth/MfaEnroll.vue`:

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Alert, AlertDescription } from '@/ui/alert'
import { Button } from '@/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/ui/card'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

defineProps<{ secret: string; otpauthUri: string }>()

const form = useForm({ code: '' })

function submit() {
  form.post('/auth/mfa/confirm', { onFinish: () => form.reset('code') })
}
</script>

<template>
  <main class="flex min-h-screen items-center justify-center p-6">
    <Card class="w-full max-w-md">
      <CardHeader>
        <CardTitle>Set up two-factor authentication</CardTitle>
        <CardDescription>Required for every staff account.</CardDescription>
      </CardHeader>
      <CardContent class="space-y-4">
        <p class="text-sm text-muted-foreground">
          Add this key to your authenticator app, then enter the six-digit code it shows.
        </p>

        <code class="block rounded-md bg-muted px-3 py-2 font-mono text-sm tracking-widest">
          {{ secret }}
        </code>

        <a class="text-sm underline underline-offset-4" :href="otpauthUri">
          Open in an authenticator app
        </a>

        <form class="space-y-4" @submit.prevent="submit">
          <Alert v-if="form.errors.code" variant="destructive">
            <AlertDescription>{{ form.errors.code }}</AlertDescription>
          </Alert>

          <div class="space-y-2">
            <Label for="code">Authentication code</Label>
            <Input
              id="code"
              v-model="form.code"
              inputmode="numeric"
              autocomplete="one-time-code"
              required
            />
          </div>

          <Button type="submit" class="w-full" :disabled="form.processing">Confirm</Button>
        </form>
      </CardContent>
    </Card>
  </main>
</template>
```

`apps/studio/resources/js/admin/pages/Auth/RecoveryCodes.vue`:

```vue
<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/ui/card'

defineProps<{ codes: string[] }>()
</script>

<template>
  <main class="flex min-h-screen items-center justify-center p-6">
    <Card class="w-full max-w-md">
      <CardHeader>
        <CardTitle>Recovery codes</CardTitle>
        <CardDescription>
          Store these somewhere safe. They are shown once and each works a single time.
        </CardDescription>
      </CardHeader>
      <CardContent class="space-y-4">
        <ul class="grid grid-cols-2 gap-2 rounded-md bg-muted p-3 font-mono text-sm">
          <li v-for="code in codes" :key="code">{{ code }}</li>
        </ul>

        <Button class="w-full" @click="router.visit('/')">I have saved these</Button>
      </CardContent>
    </Card>
  </main>
</template>
```

Update `apps/studio/tests/e2e/login.spec.ts`: the first journey's account now lands on enrollment, so replace its two closing assertions with

```ts
  await expect(page).toHaveURL('/auth/mfa/enroll')
  await expect(page.getByRole('heading', { name: 'Set up two-factor authentication' })).toBeVisible()
```

and rename the test to `a staff user signs in and is sent to MFA enrollment`. The wrong-password journey is unchanged.

- [ ] **Step 8: Run the tests to verify they pass**

Run: `docker compose run --rm php ./vendor/bin/pest --filter=MfaTest`
Expected: PASS, 12 tests.

Then the full PHP suite and the journeys:

```bash
docker compose run --rm php ./vendor/bin/pest
pnpm --filter @studio/app lint
pnpm --filter @studio/app build
pnpm --filter @studio/app test:e2e
```

Expected: every PHP test passes, `vue-tsc` exits 0, and all four Playwright tests pass.

- [ ] **Step 9: Commit**

```bash
git add apps/studio/app/Http apps/studio/resources/js/admin/pages apps/studio/routes/admin.php apps/studio/bootstrap/app.php apps/studio/database/seeders apps/studio/tests apps/studio/composer.json apps/studio/composer.lock package.json pnpm-lock.yaml
git commit -m "feat(studio): require TOTP enrollment and add the MFA login challenge"
```

---

### Task 9: Audience middleware and the audience matrix test

**Files:**
- Create: `apps/studio/app/Http/Middleware/EnsureAudience.php`
- Create: `apps/studio/tests/Feature/AudienceMatrixTest.php`
- Modify: `apps/studio/routes/admin.php`, `apps/studio/bootstrap/app.php`

**Interfaces:**
- Consumes: the `staff` guard (Task 7), `Surface` (Task 2)
- Produces: the middleware alias `audience:<guard>`, rejecting any request not authenticated by the named guard before controller dispatch; a matrix test enumerating every route on every surface

- [ ] **Step 1: Write the failing tests**

`apps/studio/tests/Feature/AudienceMatrixTest.php`:

```php
<?php

use App\Models\User;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/**
 * Routes that are deliberately reachable without an audience. Listing them
 * here makes adding a public route a decision someone makes on purpose rather
 * than an omission nobody notices.
 */
const PUBLIC_ROUTES = [
    'admin' => ['login', 'auth/mfa'],
    'api' => ['api/health'],
];

function routesForSurface(string $surface): array
{
    config(['surface.name' => $surface]);

    Route::setRoutes(new RouteCollection);
    Surface::registerRoutes();

    return collect(Route::getRoutes()->getRoutes())->all();
}

it('declares an audience on every non-public route of every surface', function (string $surface) {
    $undeclared = collect(routesForSurface($surface))
        ->reject(fn ($route) => in_array($route->uri(), PUBLIC_ROUTES[$surface], true))
        ->reject(fn ($route) => collect($route->gatherMiddleware())
            ->contains(fn ($m) => str_starts_with((string) $m, 'audience:')))
        ->map(fn ($route) => $route->methods()[0].' '.$route->uri())
        ->values()
        ->all();

    expect($undeclared)->toBe([]);
})->with(Surface::ALL);

it('rejects unauthenticated access to staff routes', function () {
    $this->get('/')->assertRedirect('/login');
    $this->post('/logout')->assertRedirect('/login');
});

it('rejects a session authenticated against a different guard', function () {
    $user = User::factory()->withMfa()->create();

    // Authenticated as `web`, but the route declares `staff`.
    $this->actingAs($user, 'web');

    $this->get('/')->assertRedirect('/login');
});

it('accepts a correctly authenticated staff session', function () {
    $user = User::factory()->withMfa()->create();

    $this->actingAs($user, 'staff');

    $this->get('/')->assertOk();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker compose run --rm php ./vendor/bin/pest --filter=AudienceMatrix`
Expected: FAIL — the first case lists `GET /`, `POST logout`, and the enrollment routes as undeclared, because they carry `auth:staff` rather than `audience:staff`.

- [ ] **Step 3: Implement the middleware**

`apps/studio/app/Http/Middleware/EnsureAudience.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Asserts that the request was authenticated by the guard matching the
 * declared audience. Audiences never overlap: a client or guest session must
 * not reach a staff route even if that route's policy would allow it, because
 * policies run too late to be a first line of defence.
 *
 * This is the second of two layers. The first is the surface itself — a staff
 * route does not exist in the portal or api process at all.
 */
class EnsureAudience
{
    public function handle(Request $request, Closure $next, string $audience): Response
    {
        if (! Auth::guard($audience)->check()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], 401)
                : redirect()->guest('/login');
        }

        Auth::shouldUse($audience);

        return $next($request);
    }
}
```

Register the alias in `apps/studio/bootstrap/app.php`:

```php
$middleware->alias([
    'mfa' => \App\Http\Middleware\RequireConfirmedMfa::class,
    'audience' => \App\Http\Middleware\EnsureAudience::class,
]);
```

- [ ] **Step 4: Declare the audience on the staff routes**

In `apps/studio/routes/admin.php`, change the authenticated group's middleware from `'auth:staff'` to `'audience:staff'`:

```php
Route::middleware('audience:staff')->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy']);

    Route::get('/auth/mfa/enroll', [MfaEnrollmentController::class, 'create']);
    Route::post('/auth/mfa/confirm', [MfaEnrollmentController::class, 'confirm']);
    Route::get('/auth/mfa/recovery-codes', [MfaEnrollmentController::class, 'recoveryCodes']);

    Route::middleware('mfa')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
    });
});
```

`redirect()->guest('/login')` stores the intended URL, which is what makes `redirect()->intended('/')` in `SessionController::store()` return the user to the page they were aiming at.

- [ ] **Step 5: Run the full suite**

Run: `docker compose run --rm php ./vendor/bin/pest`
Expected: PASS — every test from Tasks 2 through 9, including the four matrix cases. The Task 7 and 8 tests must still pass unchanged, since `audience:staff` is a stricter superset of `auth:staff`.

- [ ] **Step 6: Commit**

```bash
git add apps/studio/app/Http/Middleware/EnsureAudience.php apps/studio/bootstrap/app.php apps/studio/routes/admin.php apps/studio/tests/Feature/AudienceMatrixTest.php
git commit -m "feat(studio): gate routes by audience and assert coverage with a matrix test"
```

---

### Task 10: Typed Inertia props and response hygiene

**Files:**
- Create: `apps/studio/app/Data/StaffProfileData.php`
- Create: `apps/studio/config/typescript-transformer.php`
- Create: `apps/studio/resources/js/types/generated.d.ts` (generated, committed)
- Create: `apps/studio/resources/js/types/inertia.d.ts`
- Create: `apps/studio/tests/Feature/NoInternalIdsTest.php`
- Modify: `apps/studio/app/Http/Middleware/HandleInertiaRequests.php`, `apps/studio/app/Http/Controllers/Staff/DashboardController.php`
- Modify: `apps/studio/resources/js/admin/pages/Dashboard.vue`, `apps/studio/tsconfig.json`
- Modify: `.github/workflows/ci.yml`

**Interfaces:**
- Consumes: the routes from Tasks 7 through 9, `HasPublicId` (Task 4)
- Produces: `App\Data\StaffProfileData::fromModel(User $user): self` with `id: string`, `name: string`, `email: string`, `role: string`, `mfaConfirmed: bool`; the generated TypeScript namespace `App.Data.StaffProfileData` in `resources/js/types/generated.d.ts`; the shared Inertia prop `auth.user`; a CI check failing when the generated types are stale; a test failing when any response emits an integer identifier

This is what replaces the OpenAPI codegen the Nuxt design needed. The boundary is still PHP-to-TypeScript, but it now runs inside one application, so the generator reads PHP classes rather than an HTTP specification.

- [ ] **Step 1: Install the data and transformer packages**

```bash
docker run --rm -v "$PWD":/app -w /app/apps/studio composer:2 \
  require spatie/laravel-data --no-interaction
docker run --rm -v "$PWD":/app -w /app/apps/studio composer:2 \
  require --dev spatie/laravel-typescript-transformer --no-interaction
docker compose run --rm php php artisan vendor:publish \
  --provider="Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerServiceProvider"
```

In `apps/studio/config/typescript-transformer.php`, set:

```php
    'auto_discover_types' => [app_path()],

    'transformers' => [
        Spatie\TypeScriptTransformer\Transformers\EnumTransformer::class,
        Spatie\LaravelData\Support\TypeScriptTransformer\DataTypeScriptTransformer::class,
    ],

    'output_file' => resource_path('js/types/generated.d.ts'),

    'writer' => Spatie\TypeScriptTransformer\Writers\TypeDefinitionWriter::class,
```

`TypeDefinitionWriter` emits `declare namespace App.Data { ... }`, which makes the types globally available without an import — the shape a `.d.ts` in the TypeScript project should have. `ModuleWriter` would emit a module requiring an explicit import path instead.

- [ ] **Step 2: Write the failing tests**

`apps/studio/tests/Feature/NoInternalIdsTest.php`:

```php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Walks a decoded response body and returns the paths of any key that looks
 * like an identifier but holds an integer. Passing a model straight into
 * Inertia::render instead of wrapping it in a Data object is the ordinary way
 * the internal key escapes, and it should fail here rather than in review.
 */
function integerIdPaths(mixed $node, string $path = '$'): array
{
    if (! is_array($node)) {
        return [];
    }

    $found = [];

    foreach ($node as $key => $value) {
        $childPath = $path.'.'.$key;

        if (is_string($key) && (str_ends_with($key, 'id') || str_ends_with($key, 'ids')) && is_int($value)) {
            $found[] = $childPath;
        }

        $found = [...$found, ...integerIdPaths($value, $childPath)];
    }

    return $found;
}

it('never puts an integer identifier into an authenticated inertia page prop', function () {
    $user = User::factory()->withMfa()->create();

    $props = $this->actingAs($user, 'staff')->get('/')->assertOk()->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
});

it('never puts an integer identifier into the login page', function () {
    $props = $this->get('/login')->assertOk()->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
});

it('exposes the signed-in user as a public id', function () {
    $user = User::factory()->withMfa()->create();

    $props = $this->actingAs($user, 'staff')->get('/')->assertOk()->viewData('page')['props'];

    expect($props['auth']['user']['id'])->toBe($user->public_id)
        ->and($props['auth']['user'])->not->toHaveKey('mfa_secret');
});

it('detects an integer id when one is present', function () {
    expect(integerIdPaths(['data' => ['id' => 7, 'name' => 'x']]))->toBe(['$.data.id']);
    expect(integerIdPaths(['data' => ['id' => 'abc', 'name' => 'x']]))->toBe([]);
});
```

The last case tests the detector itself. A guard that silently matches nothing is worse than no guard, and this is the cheapest way to know it works.

- [ ] **Step 3: Run the tests to verify they fail**

Run: `docker compose run --rm php ./vendor/bin/pest --filter=NoInternalIds`
Expected: FAIL — `exposes the signed-in user as a public id` errors on an undefined `auth` key, because nothing shares the user yet.

- [ ] **Step 4: Add the Data object**

`apps/studio/app/Data/StaffProfileData.php`:

```php
<?php

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The typed shape of the signed-in staff user as the admin surface sees it.
 * `id` is the CUID2, never the primary key — that is the whole point of the
 * object existing rather than passing the model.
 */
#[TypeScript]
class StaffProfileData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public string $role,
        public bool $mfaConfirmed,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->public_id,
            name: $user->name,
            email: $user->email,
            role: $user->role->value,
            mfaConfirmed: $user->hasConfirmedMfa(),
        );
    }
}
```

- [ ] **Step 5: Share it as an Inertia prop**

In `apps/studio/app/Http/Middleware/HandleInertiaRequests.php`, replace `share()`:

```php
    public function share(Request $request): array
    {
        $user = $request->user('staff');

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user === null ? null : StaffProfileData::fromModel($user),
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }
```

Add `use App\Data\StaffProfileData;` to the imports.

- [ ] **Step 6: Generate the types and consume them**

```bash
docker compose run --rm php php artisan typescript:transform
```

Expected: `apps/studio/resources/js/types/generated.d.ts` is written containing `App.Data.StaffProfileData` with `id: string`.

The generated file declares the Data objects, but Inertia's `usePage().props` is typed by the package and knows nothing about the props the middleware shares. Declare those once, in `apps/studio/resources/js/types/inertia.d.ts`:

```ts
declare module '@inertiajs/core' {
  interface PageProps {
    auth: { user: App.Data.StaffProfileData | null }
    flash: { status: string | null }
  }
}

export {}
```

`apps/studio/tsconfig.json` already includes `resources/js/**/*.d.ts` from Task 3, so both files load without further configuration.

Now consume the type in `apps/studio/resources/js/admin/pages/Dashboard.vue` by replacing the script block:

```vue
<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { Button } from '@/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/ui/card'

defineProps<{ leads: number; jobs: number }>()

// The dashboard is behind audience:staff, so the shared user is never null here.
const user = computed(() => usePage().props.auth.user!)
</script>
```

and adding the name to the heading row, immediately after the `<h1>`:

```vue
      <p class="text-sm text-muted-foreground">Signed in as {{ user.name }}</p>
```

Renaming `mfaConfirmed` to anything else in the PHP class now fails `vue-tsc` the moment a page reads it, which is the guarantee the Nuxt design bought with an OpenAPI round trip.

- [ ] **Step 7: Run the tests to verify they pass**

```bash
docker compose run --rm php ./vendor/bin/pest --filter=NoInternalIds
docker compose run --rm php ./vendor/bin/pest
pnpm --filter @studio/app lint
pnpm --filter @studio/app build
```

Expected: 4 hygiene cases pass, the full PHP suite passes, `vue-tsc --noEmit` exits 0, and the build succeeds.

To confirm the guarantee this task exists for, rename `mfaConfirmed` to `mfaDone` in `StaffProfileData`, run `php artisan typescript:transform`, and reference `user.mfaConfirmed` in `Dashboard.vue`. `pnpm --filter @studio/app lint` must fail. Revert both changes before committing.

- [ ] **Step 8: Add the staleness gate to CI**

In the `studio` job of `.github/workflows/ci.yml`, after the Pest step:

```yaml
      - name: Assert the generated TypeScript types are current
        working-directory: apps/studio
        run: |
          php artisan typescript:transform
          git diff --exit-code -- resources/js/types/generated.d.ts \
            || { echo "Generated types are stale. Run 'php artisan typescript:transform' and commit."; exit 1; }
```

- [ ] **Step 9: Commit**

```bash
git add apps/studio/app/Data apps/studio/config/typescript-transformer.php apps/studio/resources/js/types apps/studio/app/Http/Middleware/HandleInertiaRequests.php apps/studio/resources/js/admin/pages/Dashboard.vue apps/studio/tests/Feature/NoInternalIdsTest.php apps/studio/composer.json apps/studio/composer.lock .github/workflows/ci.yml
git commit -m "feat(studio): type Inertia props from PHP and guard against internal id leaks"
```

---

## Definition of Done for P1a

- [ ] `docker compose run --rm php ./vendor/bin/pest` passes with zero skipped tests
- [ ] `pnpm lint && pnpm test && pnpm build` passes at the workspace root
- [ ] `pnpm --filter @studio/app test:e2e` passes against a locally served admin surface
- [ ] CI is green on all three jobs (`studio`, `web`, `e2e`)
- [ ] A staff user can sign in at `http://localhost:8000`, is forced through TOTP enrollment on first sign-in, sees their recovery codes exactly once, and lands on the dashboard
- [ ] Booting with `APP_SURFACE=api` serves `/api/health` and registers no admin route; booting with `APP_SURFACE=admin` serves `/login` and registers no api route — both asserted by `SurfaceIsolationTest`
- [ ] Booting with an unrecognised `APP_SURFACE` throws rather than starting
- [ ] `audit_logs` contains rows for login success, failure, throttling, MFA enrollment, MFA failure, recovery-code use, and logout
- [ ] Every non-public route on every surface carries an `audience:` declaration, enforced by the matrix test
- [ ] `users` and `audit_logs` both carry a unique `public_id`, and no Inertia prop emits an integer identifier — enforced by `NoInternalIdsTest`
- [ ] `resources/js/types/generated.d.ts` is current, consumed by at least one page, and CI fails if it goes stale
- [ ] `apps/studio/docs/database-grants.md` records the `audit_logs` privilege revocation as a deployment step

## Deferred to later plans

- The `portal` surface and the `client` guard (P2) — one constant in `Surface::ALL`, one route file, one Vite entrypoint, one theme layer
- The public inquiry endpoint on the `api` surface (P1b, alongside leads)
- `packages/contracts`, Scramble, and OpenAPI generation (P3) — the Astro gallery is the JSON API's first typed consumer, and generating a client before then would be scaffolding without a consumer
- Staff password reset and invitation flows (P1b, alongside real user management)
- Real dashboard metrics replacing the zeroes (P1b)
- Role-based policies — the enum exists in P1a, but nothing branches on it until there are resources to authorize (P1b)
- Network-level restriction of the admin surface, which is a deployment concern rather than an application one
- Server-side rendering for Inertia, which the vision rules out for both authenticated surfaces

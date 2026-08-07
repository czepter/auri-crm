# P1a — Foundation & Staff Authentication Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up the monorepo, CI, and Postgres schema base, then ship a staff-only admin application where a user logs in with a password plus mandatory TOTP and every authentication event is recorded in an append-only audit log.

**Architecture:** A pnpm-workspace monorepo holds a Laravel API (`apps/api`) and a Nuxt admin SPA (`apps/admin`), with generated OpenAPI types in `packages/contracts`. PHP and Postgres run in Docker because the host has no PHP toolchain. Authentication uses Laravel Sanctum's stateful cookie mode against a dedicated `staff` guard, so the audience of a request is decided by which guard authenticated it — the mechanism later phases extend with `client` and `guest` guards rather than replace.

**Tech Stack:** PHP 8.3, Laravel 11, Pest 3, PostgreSQL 18, Laravel Sanctum, `pragmarx/google2fa`, `dedoc/scramble` (OpenAPI), Node 24, pnpm 10, Nuxt 4, Vitest, Playwright, Docker Compose, GitHub Actions.

## Global Constraints

- PHP 8.3, Laravel 11.x, Pest 3.x. All PHP commands run inside Docker — never assume a host `php` or `composer` binary.
- Node 24, pnpm 10. pnpm is the only package manager; do not create `package-lock.json` or `yarn.lock`.
- PostgreSQL 18. No MySQL-specific SQL, no SQLite in tests — tests run against real Postgres.
- Every application table carries a `tenant_id` UUID column, NOT NULL, defaulting to the constant in `config('tenancy.tenant_id')`. This seam is dormant in P1 but present from the first migration.
- Every application table carries a unique `char(24)` `public_id` holding a CUID2. The `bigint` primary key is internal only: it carries foreign keys and joins, and never appears in an API response, a URL, an email, or a filename. Models expose `public_id` and hide `id`; route-model binding resolves on `public_id`.
- No cross-context database joins. Contexts reference each other by ID.
- Staff MFA is mandatory, not opt-in. A staff user without confirmed TOTP may reach only the enrollment endpoints.
- Every API route group declares its required audience in middleware. Rejection happens in middleware, before controller dispatch.
- The audit log is append-only. Updates and deletes are blocked in the model and revoked at the database role level.
- Authentication failures return uniform responses that never distinguish "wrong password" from "no such account".
- Tests are written before implementation, and every task ends with a passing test run and a commit.
- Commits follow Conventional Commits.
- All timestamps are stored in UTC; `Europe/Berlin` is a display concern only.

---

## File Structure

```
client-management-portal/
├── package.json                          pnpm workspace root, cross-app scripts
├── pnpm-workspace.yaml                   workspace globs
├── docker-compose.yml                    php-fpm/cli + postgres + mailpit for local dev
├── .github/workflows/ci.yml              one pipeline, per-app jobs
├── apps/
│   ├── api/                              Laravel 11
│   │   ├── app/
│   │   │   ├── Enums/StaffRole.php               staff role enum
│   │   │   ├── Models/User.php                   staff user
│   │   │   ├── Models/AuditLog.php               append-only log model
│   │   │   ├── Concerns/BelongsToTenant.php      tenant column + global scope
│   │   │   ├── Concerns/HasPublicId.php          cuid2 generation, hides id, route key
│   │   │   ├── Support/AuditLogger.php           the only writer of audit rows
│   │   │   ├── Http/Middleware/EnsureAudience.php        guard-based audience gate
│   │   │   ├── Http/Middleware/RequireConfirmedMfa.php   blocks un-enrolled staff
│   │   │   └── Http/Controllers/Staff/
│   │   │       ├── SessionController.php         login, logout, me
│   │   │       ├── MfaEnrollmentController.php   enroll, confirm
│   │   │       └── MfaChallengeController.php    verify code after password
│   │   ├── config/tenancy.php            tenant constant
│   │   ├── database/migrations/          schema
│   │   ├── routes/api.php                route groups with audience middleware
│   │   └── tests/                        Pest: Feature/ and Unit/
│   └── admin/                            Nuxt 4 SPA
│       ├── app/pages/login.vue           email + password, then TOTP step
│       ├── app/pages/index.vue           authenticated shell
│       ├── app/middleware/auth.ts        route guard
│       ├── app/composables/useSession.ts session state + API calls
│       └── tests/e2e/login.spec.ts       Playwright
└── packages/
    └── contracts/
        ├── package.json                  @studio/contracts
        ├── openapi.json                  generated, committed for diff visibility
        └── src/schema.d.ts               generated TypeScript types
```

Responsibility boundaries worth naming: `AuditLogger` is the single writer of audit rows so that retention and redaction have one place to live. `EnsureAudience` knows about guards and nothing else. `HasPublicId` owns every part of the internal/external identifier split — generation, hiding, and route binding — so that adding the trait is sufficient and there is nothing a model author must remember separately. Controllers under `Http/Controllers/Staff/` are split by concern (session, enrollment, challenge) rather than gathered into one `AuthController`, because the MFA challenge has a different authentication state than the other two and merging them makes that state machine hard to read.

---

### Task 1: Workspace skeleton and CI

**Files:**
- Create: `package.json`, `pnpm-workspace.yaml`, `.nvmrc`, `.editorconfig`
- Create: `docker-compose.yml`
- Create: `.github/workflows/ci.yml`
- Modify: `.gitignore`

**Interfaces:**
- Consumes: nothing
- Produces: `pnpm lint` and `pnpm test` at the repo root (both no-ops until later tasks add workspaces); a `postgres` service on port 5433 and a `php` service usable as `docker compose run --rm php <cmd>`

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
    "api": "docker compose run --rm php"
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

[*.{js,ts,vue,json,yml,yaml,md}]
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
    working_dir: /app/apps/api
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
  api:
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
      - name: Skip when API not scaffolded yet
        id: guard
        run: test -f apps/api/composer.json && echo "ready=true" >> "$GITHUB_OUTPUT" || echo "ready=false" >> "$GITHUB_OUTPUT"
      - if: steps.guard.outputs.ready == 'true'
        working-directory: apps/api
        run: composer install --no-interaction --prefer-dist
      - if: steps.guard.outputs.ready == 'true'
        working-directory: apps/api
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

The `guard` step exists so this pipeline is green from the first commit and starts exercising the API the moment Task 2 lands. Remove it once `apps/api` exists.

- [ ] **Step 5: Extend .gitignore**

```
.DS_Store
node_modules/

# PHP
apps/api/vendor/
apps/api/.env
apps/api/storage/*.key
apps/api/public/storage

# Node
.nuxt/
.output/
dist/
playwright-report/
test-results/
```

- [ ] **Step 6: Verify the workspace scripts run**

Run: `pnpm install && pnpm test`
Expected: install succeeds and `pnpm test` exits 0 with no packages matched (there are no workspaces yet).

- [ ] **Step 7: Commit**

```bash
git add package.json pnpm-workspace.yaml pnpm-lock.yaml .nvmrc .editorconfig docker-compose.yml .github/workflows/ci.yml .gitignore
git commit -m "chore: scaffold pnpm monorepo, docker dev environment, and CI"
```

---

### Task 2: Laravel API scaffold with a tested health endpoint

**Files:**
- Create: `apps/api/**` (Laravel skeleton via Composer)
- Create: `apps/api/app/Http/Controllers/HealthController.php`
- Create: `apps/api/tests/Feature/HealthTest.php`
- Modify: `apps/api/routes/api.php`, `apps/api/phpunit.xml`, `apps/api/.env.example`
- Modify: `.github/workflows/ci.yml` (drop the guard step)

**Interfaces:**
- Consumes: the `php` and `postgres` services from Task 1
- Produces: `GET /api/health` returning `{"status":"ok","database":"ok"}`; a working Pest suite invoked as `docker compose run --rm php ./vendor/bin/pest`

- [ ] **Step 1: Scaffold Laravel into apps/api**

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 \
  create-project laravel/laravel apps/api "^11.0" --no-interaction
```

- [ ] **Step 2: Install the API dependencies**

```bash
docker run --rm -v "$PWD":/app -w /app/apps/api composer:2 \
  require laravel/sanctum pragmarx/google2fa dedoc/scramble --no-interaction
docker run --rm -v "$PWD":/app -w /app/apps/api composer:2 \
  require --dev pestphp/pest pestphp/pest-plugin-laravel --no-interaction --with-all-dependencies
```

Then initialise Pest:

```bash
docker compose run --rm php ./vendor/bin/pest --init
```

- [ ] **Step 3: Point the app at Postgres**

In `apps/api/.env.example` and `apps/api/.env`, set:

```
APP_TIMEZONE=UTC
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=studio
DB_USERNAME=studio
DB_PASSWORD=studio
```

In `apps/api/phpunit.xml`, remove any `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` lines and add:

```xml
<env name="DB_CONNECTION" value="pgsql"/>
<env name="DB_DATABASE" value="studio_test"/>
```

Create the test database:

```bash
docker compose exec postgres createdb -U studio studio_test
```

- [ ] **Step 4: Write the failing test**

`apps/api/tests/Feature/HealthTest.php`:

```php
<?php

it('reports service and database health', function () {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertExactJson(['status' => 'ok', 'database' => 'ok']);
});
```

- [ ] **Step 5: Run the test to verify it fails**

Run: `docker compose run --rm php ./vendor/bin/pest --filter=HealthTest`
Expected: FAIL — the route does not exist, so the response is 404.

- [ ] **Step 6: Implement the endpoint**

`apps/api/app/Http/Controllers/HealthController.php`:

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

In `apps/api/routes/api.php`:

```php
<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);
```

If `routes/api.php` does not exist (Laravel 11 omits it by default), create it and register it in `apps/api/bootstrap/app.php`:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `docker compose run --rm php ./vendor/bin/pest --filter=HealthTest`
Expected: PASS, 1 test.

- [ ] **Step 8: Remove the CI guard step**

In `.github/workflows/ci.yml`, delete the `Skip when API not scaffolded yet` step and every `if: steps.guard.outputs.ready == 'true'` condition, so the API job always runs.

- [ ] **Step 9: Commit**

```bash
git add apps/api .github/workflows/ci.yml
git commit -m "feat(api): scaffold Laravel 11 on Postgres with a health endpoint"
```

---

### Task 3: Schema conventions — tenant seam and public identifiers

**Files:**
- Create: `apps/api/config/tenancy.php`
- Create: `apps/api/app/Concerns/BelongsToTenant.php`
- Create: `apps/api/app/Concerns/HasPublicId.php`
- Create: `apps/api/tests/Feature/TenantSeamTest.php`
- Create: `apps/api/tests/Feature/PublicIdTest.php`
- Modify: `apps/api/app/Providers/AppServiceProvider.php`, `apps/api/.env.example`

**Interfaces:**
- Consumes: the Laravel app from Task 2
- Produces: `Blueprint::tenantId()` and `Blueprint::publicId()` macros for migrations; `App\Concerns\BelongsToTenant` applying a global scope on `tenant_id` and auto-filling it on create; `App\Concerns\HasPublicId` generating a CUID2 on create, hiding `id`, resolving route binding on `public_id`, and exposing `Model::findByPublicId(string $publicId): ?static`; `config('tenancy.tenant_id')`

Both macros are schema conventions registered in the same provider and exercised by the same probe table, so they ship together — a reviewer approving one and rejecting the other would leave the migration helpers half-defined.

- [ ] **Step 1: Write the failing test**

`apps/api/tests/Feature/TenantSeamTest.php`:

```php
<?php

use Illuminate\Database\Schema\Blueprint;
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
        'label' => 'theirs',
    ]);

    expect(TenantProbe::pluck('label')->all())->toBe(['ours']);
});
```

Add the probe model at the bottom of the same file:

```php
class TenantProbe extends \Illuminate\Database\Eloquent\Model
{
    use \App\Concerns\BelongsToTenant;
    use \App\Concerns\HasPublicId;

    protected $table = 'seam_probes';
    public $timestamps = false;
    protected $fillable = ['label'];
}
```

`apps/api/tests/Feature/PublicIdTest.php`:

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

The two test files share the `TenantProbe` model, so declare it once — in `TenantSeamTest.php` under a `Tests\Feature` namespace declaration at the top of the file — and import it in `PublicIdTest.php` as shown.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker compose run --rm php ./vendor/bin/pest --filter="TenantSeam|PublicId"`
Expected: FAIL — `Call to undefined method Blueprint::tenantId()`.

- [ ] **Step 3: Add the config**

`apps/api/config/tenancy.php`:

```php
<?php

return [
    // Dormant multi-tenancy seam. One studio today; the column exists so
    // activating tenancy later is a data migration, not a schema rewrite.
    'tenant_id' => env('TENANT_ID', '00000000-0000-4000-8000-000000000001'),
];
```

Add `TENANT_ID=00000000-0000-4000-8000-000000000001` to `.env.example` and `.env`.

- [ ] **Step 4: Register the Blueprint macro**

The macro is runtime behaviour rather than schema, so it belongs in a service provider — migrations would need it registered before they run. Add to `boot()` in `apps/api/app/Providers/AppServiceProvider.php`:

```php
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
}
```

`char(24)` rather than `string`: CUID2 output is fixed width at the default length, and a fixed-width column keeps the unique index compact.

- [ ] **Step 5: Implement the traits**

Install the CUID2 library first:

```bash
docker run --rm -v "$PWD":/app -w /app/apps/api composer:2 require visus/cuid2 --no-interaction
```

`apps/api/app/Concerns/HasPublicId.php`:

```php
<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;
use Visus\Cuid2\Cuid2;

/**
 * Splits internal and external identity. The bigint primary key stays inside
 * the database — it carries foreign keys and joins. The CUID2 in public_id is
 * the only identifier that crosses the API boundary, so sequential integers
 * are never enumerable by a client or guest.
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

`apps/api/app/Concerns/BelongsToTenant.php`:

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
git add apps/api/config/tenancy.php apps/api/app/Concerns apps/api/app/Providers/AppServiceProvider.php apps/api/tests/Feature/TenantSeamTest.php apps/api/tests/Feature/PublicIdTest.php apps/api/composer.json apps/api/composer.lock apps/api/.env.example
git commit -m "feat(api): add tenant seam and CUID2 public identifiers"
```

---

### Task 4: Staff users and roles

**Files:**
- Create: `apps/api/app/Enums/StaffRole.php`
- Modify: `apps/api/app/Models/User.php`
- Create: `apps/api/database/migrations/2026_08_07_000200_create_staff_users_table.php`
- Modify: `apps/api/database/factories/UserFactory.php`
- Create: `apps/api/tests/Feature/StaffUserTest.php`

**Interfaces:**
- Consumes: `BelongsToTenant` and `HasPublicId` (Task 3)
- Produces: `App\Models\User` with `public_id: string`, `role: StaffRole`, `mfa_secret: ?string` (encrypted), `mfa_confirmed_at: ?CarbonImmutable`, and `hasConfirmedMfa(): bool`; `User::factory()->withMfa()` state

- [ ] **Step 1: Write the failing test**

`apps/api/tests/Feature/StaffUserTest.php`:

```php
<?php

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

- [ ] **Step 2: Run the test to verify it fails**

Run: `docker compose run --rm php ./vendor/bin/pest --filter=StaffUser`
Expected: FAIL — `Class "App\Enums\StaffRole" not found`.

- [ ] **Step 3: Add the role enum**

`apps/api/app/Enums/StaffRole.php`:

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

Delete Laravel's default `..._create_users_table.php` and create `apps/api/database/migrations/2026_08_07_000200_create_staff_users_table.php`:

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
            $table->json('mfa_recovery_codes')->nullable();
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

Also delete the default `..._create_password_reset_tokens_table` entries if they live in the same file — staff password resets are out of scope for P1a, and clients never use passwords.

- [ ] **Step 5: Update the model**

`apps/api/app/Models/User.php`:

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

    protected $fillable = ['name', 'email', 'password', 'role'];

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

`apps/api/database/factories/UserFactory.php` — replace the class body with:

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
git add apps/api/app/Enums apps/api/app/Models/User.php apps/api/database apps/api/tests/Feature/StaffUserTest.php
git commit -m "feat(api): add staff users with roles and encrypted MFA fields"
```

---

### Task 5: Append-only audit log

**Files:**
- Create: `apps/api/database/migrations/2026_08_07_000300_create_audit_logs_table.php`
- Create: `apps/api/app/Models/AuditLog.php`
- Create: `apps/api/app/Support/AuditLogger.php`
- Create: `apps/api/tests/Feature/AuditLogTest.php`
- Create: `apps/api/docs/database-grants.md`

**Interfaces:**
- Consumes: `BelongsToTenant` (Task 3), `User` (Task 4)
- Produces: `AuditLogger::record(string $event, ?User $actor, array $context = [], ?string $subject = null): AuditLog`, dispatched synchronously and reading IP and user agent from the current request

- [ ] **Step 1: Write the failing test**

`apps/api/tests/Feature/AuditLogTest.php`:

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

`apps/api/app/Models/AuditLog.php`:

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

`apps/api/app/Support/AuditLogger.php`:

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

Model guards stop application bugs; they do not stop a console session or a compromised credential. Record the deployment requirement in `apps/api/docs/database-grants.md`:

```markdown
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
```

- [ ] **Step 8: Commit**

```bash
git add apps/api/app/Models/AuditLog.php apps/api/app/Support/AuditLogger.php apps/api/database/migrations apps/api/tests/Feature/AuditLogTest.php apps/api/docs/database-grants.md
git commit -m "feat(api): add append-only audit log with context sanitisation"
```

---

### Task 6: Staff password login

**Files:**
- Create: `apps/api/app/Http/Controllers/Staff/SessionController.php`
- Create: `apps/api/tests/Feature/Staff/LoginTest.php`
- Modify: `apps/api/routes/api.php`, `apps/api/config/auth.php`, `apps/api/bootstrap/app.php`, `apps/api/config/sanctum.php`, `apps/api/.env.example`

**Interfaces:**
- Consumes: `User` (Task 4), `AuditLogger` (Task 5)
- Produces: `POST /api/staff/auth/login`, `POST /api/staff/auth/logout`, `GET /api/staff/me`; a `staff` auth guard backed by the session; login responds `{"mfa_required": true}` with HTTP 200 and no session when the account has confirmed MFA, otherwise establishes the session

- [ ] **Step 1: Write the failing tests**

`apps/api/tests/Feature/Staff/LoginTest.php`:

```php
<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(fn () => RateLimiter::clear('staff-login|127.0.0.1'));

it('logs in a user without mfa and exposes the profile', function () {
    User::factory()->create(['email' => 'owner@studio.test', 'password' => 'correct-horse']);

    $this->postJson('/api/staff/auth/login', [
        'email' => 'owner@studio.test',
        'password' => 'correct-horse',
    ])->assertOk()->assertJson(['mfa_required' => false]);

    $profile = $this->getJson('/api/staff/me')
        ->assertOk()
        ->assertJsonPath('email', 'owner@studio.test');

    // The API exposes the CUID2, never the internal primary key.
    expect($profile->json('id'))->toBeString()->toHaveLength(24);
});

it('does not establish a session when mfa is confirmed', function () {
    User::factory()->withMfa()->create(['email' => 'owner@studio.test', 'password' => 'correct-horse']);

    $this->postJson('/api/staff/auth/login', [
        'email' => 'owner@studio.test',
        'password' => 'correct-horse',
    ])->assertOk()->assertJson(['mfa_required' => true]);

    $this->getJson('/api/staff/me')->assertUnauthorized();
});

it('returns an identical response for a wrong password and an unknown account', function () {
    User::factory()->create(['email' => 'owner@studio.test', 'password' => 'correct-horse']);

    $wrongPassword = $this->postJson('/api/staff/auth/login', [
        'email' => 'owner@studio.test',
        'password' => 'nope',
    ]);

    RateLimiter::clear('staff-login|127.0.0.1');

    $unknownAccount = $this->postJson('/api/staff/auth/login', [
        'email' => 'ghost@studio.test',
        'password' => 'nope',
    ]);

    expect($wrongPassword->status())->toBe(422)
        ->and($unknownAccount->status())->toBe(422)
        ->and($wrongPassword->json())->toBe($unknownAccount->json());
});

it('throttles repeated failures from one address', function () {
    User::factory()->create(['email' => 'owner@studio.test', 'password' => 'correct-horse']);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/staff/auth/login', [
            'email' => 'owner@studio.test',
            'password' => 'nope',
        ])->assertStatus(422);
    }

    $this->postJson('/api/staff/auth/login', [
        'email' => 'owner@studio.test',
        'password' => 'correct-horse',
    ])->assertStatus(429);
});

it('audits successful and failed logins without storing the password', function () {
    User::factory()->create(['email' => 'owner@studio.test', 'password' => 'correct-horse']);

    $this->postJson('/api/staff/auth/login', ['email' => 'owner@studio.test', 'password' => 'nope']);
    $this->postJson('/api/staff/auth/login', ['email' => 'owner@studio.test', 'password' => 'correct-horse']);

    $events = AuditLog::orderBy('id')->pluck('event')->all();

    expect($events)->toBe(['staff.login.failed', 'staff.login.succeeded'])
        ->and(AuditLog::all()->pluck('context')->flatten()->all())->not->toContain('correct-horse');
});

it('logs out and clears the session', function () {
    User::factory()->create(['email' => 'owner@studio.test', 'password' => 'correct-horse']);

    $this->postJson('/api/staff/auth/login', ['email' => 'owner@studio.test', 'password' => 'correct-horse']);
    $this->postJson('/api/staff/auth/logout')->assertNoContent();
    $this->getJson('/api/staff/me')->assertUnauthorized();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker compose run --rm php ./vendor/bin/pest --filter=LoginTest`
Expected: FAIL — all routes return 404.

- [ ] **Step 3: Configure the staff guard and stateful API**

In `apps/api/config/auth.php`, add to `guards`:

```php
'staff' => [
    'driver' => 'session',
    'provider' => 'users',
],
```

In `apps/api/bootstrap/app.php`, inside `->withMiddleware(function (Middleware $middleware) { ... })`:

```php
$middleware->api(prepend: [
    \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
]);
```

In `.env.example` and `.env`:

```
APP_URL=http://localhost:8000
SESSION_DRIVER=database
SESSION_DOMAIN=localhost
SESSION_SECURE_COOKIE=false
SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:3000
FRONTEND_URL=http://localhost:3000
```

In `apps/api/config/cors.php`, set `'paths' => ['api/*', 'sanctum/csrf-cookie']` and `'supports_credentials' => true`, with `'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:3000')]`.

`SESSION_SECURE_COOKIE` must be `true` in every deployed environment. Only local HTTP development sets it to `false`.

- [ ] **Step 4: Implement the controller**

`apps/api/app/Http/Controllers/Staff/SessionController.php`:

```php
<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const DECAY_SECONDS = 300;

    public function store(Request $request): JsonResponse
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
            ])->status(429);
        }

        $user = User::where('email', $credentials['email'])->first();

        // Always hash, even for unknown accounts, so response timing does not
        // reveal whether the address exists.
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

            return response()->json(['mfa_required' => true]);
        }

        Auth::guard('staff')->login($user);
        $request->session()->regenerate();
        AuditLogger::record('staff.login.succeeded', $user, ['method' => 'password']);

        return response()->json(['mfa_required' => false]);
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user('staff');

        return response()->json([
            'id' => $user->public_id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'mfa_confirmed' => $user->hasConfirmedMfa(),
        ]);
    }

    public function destroy(Request $request): \Illuminate\Http\Response
    {
        $user = $request->user('staff');
        Auth::guard('staff')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        AuditLogger::record('staff.logout', $user);

        return response()->noContent();
    }
}
```

- [ ] **Step 5: Register the routes**

In `apps/api/routes/api.php`:

```php
use App\Http\Controllers\Staff\SessionController;

Route::prefix('staff')->group(function () {
    Route::post('/auth/login', [SessionController::class, 'store']);

    Route::middleware('auth:staff')->group(function () {
        Route::get('/me', [SessionController::class, 'show']);
        Route::post('/auth/logout', [SessionController::class, 'destroy']);
    });
});
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `docker compose run --rm php ./vendor/bin/pest --filter=LoginTest`
Expected: PASS, 6 tests.

- [ ] **Step 7: Commit**

```bash
git add apps/api/app/Http/Controllers/Staff/SessionController.php apps/api/routes/api.php apps/api/config apps/api/bootstrap/app.php apps/api/.env.example apps/api/tests/Feature/Staff/LoginTest.php
git commit -m "feat(api): add staff password login with throttling and uniform failures"
```

---

### Task 7: Mandatory TOTP enrollment and challenge

**Files:**
- Create: `apps/api/app/Http/Controllers/Staff/MfaEnrollmentController.php`
- Create: `apps/api/app/Http/Controllers/Staff/MfaChallengeController.php`
- Create: `apps/api/app/Http/Middleware/RequireConfirmedMfa.php`
- Create: `apps/api/tests/Feature/Staff/MfaTest.php`
- Modify: `apps/api/routes/api.php`, `apps/api/bootstrap/app.php`

**Interfaces:**
- Consumes: `User` (Task 4), `AuditLogger` (Task 5), the `staff` guard and pending-MFA session key from Task 6
- Produces: `POST /api/staff/auth/mfa/enroll` → `{secret, otpauth_uri}`; `POST /api/staff/auth/mfa/confirm` → `{recovery_codes: string[]}`; `POST /api/staff/auth/mfa/verify` → establishes the session; middleware alias `mfa` blocking staff without confirmed MFA

- [ ] **Step 1: Write the failing tests**

`apps/api/tests/Feature/Staff/MfaTest.php`:

```php
<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('staff-login|127.0.0.1');
    RateLimiter::clear('staff-mfa|127.0.0.1');
});

function login(User $user): void
{
    test()->postJson('/api/staff/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk();
}

it('issues a secret and otpauth uri on enrollment', function () {
    $user = User::factory()->create();
    login($user);

    $response = $this->postJson('/api/staff/auth/mfa/enroll')->assertOk();

    expect($response->json('secret'))->toBeString()->toHaveLength(16)
        ->and($response->json('otpauth_uri'))->toStartWith('otpauth://totp/');
});

it('confirms enrollment with a valid code and returns recovery codes', function () {
    $user = User::factory()->create();
    login($user);

    $secret = $this->postJson('/api/staff/auth/mfa/enroll')->json('secret');
    $code = (new Google2FA)->getCurrentOtp($secret);

    $response = $this->postJson('/api/staff/auth/mfa/confirm', ['code' => $code])->assertOk();

    expect($response->json('recovery_codes'))->toHaveCount(8)
        ->and($user->fresh()->hasConfirmedMfa())->toBeTrue();
});

it('rejects confirmation with an invalid code', function () {
    $user = User::factory()->create();
    login($user);
    $this->postJson('/api/staff/auth/mfa/enroll');

    $this->postJson('/api/staff/auth/mfa/confirm', ['code' => '000000'])->assertStatus(422);

    expect($user->fresh()->hasConfirmedMfa())->toBeFalse();
});

it('blocks business routes until mfa is confirmed', function () {
    $user = User::factory()->create();
    login($user);

    $this->getJson('/api/staff/dashboard')
        ->assertStatus(403)
        ->assertJson(['message' => 'Multi-factor authentication enrollment is required.']);
});

it('allows business routes once mfa is confirmed', function () {
    $user = User::factory()->create();
    login($user);
    $secret = $this->postJson('/api/staff/auth/mfa/enroll')->json('secret');
    $this->postJson('/api/staff/auth/mfa/confirm', ['code' => (new Google2FA)->getCurrentOtp($secret)]);

    $this->getJson('/api/staff/dashboard')->assertOk();
});

it('completes login with a valid totp code', function () {
    $secret = (new Google2FA)->generateSecretKey();
    $user = User::factory()->withMfa()->create(['mfa_secret' => $secret]);

    $this->postJson('/api/staff/auth/login', ['email' => $user->email, 'password' => 'password'])
        ->assertJson(['mfa_required' => true]);

    $this->postJson('/api/staff/auth/mfa/verify', ['code' => (new Google2FA)->getCurrentOtp($secret)])
        ->assertOk();

    $this->getJson('/api/staff/me')->assertOk()->assertJsonPath('email', $user->email);
});

it('rejects an invalid totp code and leaves the session unauthenticated', function () {
    $secret = (new Google2FA)->generateSecretKey();
    $user = User::factory()->withMfa()->create(['mfa_secret' => $secret]);

    $this->postJson('/api/staff/auth/login', ['email' => $user->email, 'password' => 'password']);
    $this->postJson('/api/staff/auth/mfa/verify', ['code' => '000000'])->assertStatus(422);

    $this->getJson('/api/staff/me')->assertUnauthorized();
    expect(AuditLog::where('event', 'staff.mfa.failed')->count())->toBe(1);
});

it('refuses verification without a pending login', function () {
    $this->postJson('/api/staff/auth/mfa/verify', ['code' => '123456'])->assertUnauthorized();
});

it('throttles repeated code guesses', function () {
    $secret = (new Google2FA)->generateSecretKey();
    $user = User::factory()->withMfa()->create(['mfa_secret' => $secret]);
    $this->postJson('/api/staff/auth/login', ['email' => $user->email, 'password' => 'password']);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/staff/auth/mfa/verify', ['code' => '000000'])->assertStatus(422);
    }

    $this->postJson('/api/staff/auth/mfa/verify', ['code' => (new Google2FA)->getCurrentOtp($secret)])
        ->assertStatus(429);
});

it('accepts a recovery code once and then invalidates it', function () {
    $user = User::factory()->withMfa()->create(['mfa_recovery_codes' => ['rescue-me']]);

    $this->postJson('/api/staff/auth/login', ['email' => $user->email, 'password' => 'password']);
    $this->postJson('/api/staff/auth/mfa/verify', ['code' => 'rescue-me'])->assertOk();

    expect($user->fresh()->mfa_recovery_codes)->toBe([]);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker compose run --rm php ./vendor/bin/pest --filter=MfaTest`
Expected: FAIL — the enrollment route returns 404.

- [ ] **Step 3: Implement enrollment**

`apps/api/app/Http/Controllers/Staff/MfaEnrollmentController.php`:

```php
<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class MfaEnrollmentController extends Controller
{
    public function __construct(private readonly Google2FA $google2fa) {}

    public function enroll(Request $request): JsonResponse
    {
        $user = $request->user('staff');

        abort_if($user->hasConfirmedMfa(), 409, 'Multi-factor authentication is already enrolled.');

        $secret = $this->google2fa->generateSecretKey();
        $user->forceFill(['mfa_secret' => $secret])->save();

        AuditLogger::record('staff.mfa.enrollment_started', $user);

        return response()->json([
            'secret' => $secret,
            'otpauth_uri' => $this->google2fa->getQRCodeUrl(
                config('app.name'),
                $user->email,
                $secret,
            ),
        ]);
    }

    public function confirm(Request $request): JsonResponse
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

        return response()->json(['recovery_codes' => $recoveryCodes]);
    }
}
```

- [ ] **Step 4: Implement the login challenge**

`apps/api/app/Http/Controllers/Staff/MfaChallengeController.php`:

```php
<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class MfaChallengeController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const DECAY_SECONDS = 300;

    public function __construct(private readonly Google2FA $google2fa) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string']]);

        $pendingId = $request->session()->get('mfa.pending_user_id');
        abort_if($pendingId === null, 401);

        $key = 'staff-mfa|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            AuditLogger::record('staff.mfa.throttled', null);

            throw ValidationException::withMessages([
                'code' => __('Too many attempts. Try again later.'),
            ])->status(429);
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

        return response()->json(['mfa_required' => false]);
    }

    private function accepts(User $user, string $code): bool
    {
        if ($this->google2fa->verifyKey((string) $user->mfa_secret, $code)) {
            return true;
        }

        $remaining = collect($user->mfa_recovery_codes ?? [])
            ->reject(fn (string $candidate) => hash_equals($candidate, $code));

        if ($remaining->count() === count($user->mfa_recovery_codes ?? [])) {
            return false;
        }

        $user->forceFill(['mfa_recovery_codes' => $remaining->values()->all()])->save();
        AuditLogger::record('staff.mfa.recovery_code_used', $user);

        return true;
    }
}
```

- [ ] **Step 5: Implement the enforcement middleware**

`apps/api/app/Http/Middleware/RequireConfirmedMfa.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireConfirmedMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user('staff')?->hasConfirmedMfa()) {
            return response()->json(
                ['message' => 'Multi-factor authentication enrollment is required.'],
                403,
            );
        }

        return $next($request);
    }
}
```

Register the alias in `apps/api/bootstrap/app.php`:

```php
$middleware->alias([
    'mfa' => \App\Http\Middleware\RequireConfirmedMfa::class,
]);
```

- [ ] **Step 6: Wire the routes**

Replace the staff group in `apps/api/routes/api.php`:

```php
use App\Http\Controllers\Staff\MfaChallengeController;
use App\Http\Controllers\Staff\MfaEnrollmentController;
use App\Http\Controllers\Staff\SessionController;

Route::prefix('staff')->group(function () {
    Route::post('/auth/login', [SessionController::class, 'store']);
    Route::post('/auth/mfa/verify', MfaChallengeController::class);

    Route::middleware('auth:staff')->group(function () {
        Route::get('/me', [SessionController::class, 'show']);
        Route::post('/auth/logout', [SessionController::class, 'destroy']);
        Route::post('/auth/mfa/enroll', [MfaEnrollmentController::class, 'enroll']);
        Route::post('/auth/mfa/confirm', [MfaEnrollmentController::class, 'confirm']);

        Route::middleware('mfa')->group(function () {
            Route::get('/dashboard', fn () => response()->json(['leads' => 0, 'jobs' => 0]));
        });
    });
});
```

The dashboard endpoint is a real, minimal stand-in that P1b replaces with actual counts. It exists now so the MFA gate and the admin shell have something to protect and render.

- [ ] **Step 7: Run the tests to verify they pass**

Run: `docker compose run --rm php ./vendor/bin/pest --filter=MfaTest`
Expected: PASS, 10 tests.

- [ ] **Step 8: Commit**

```bash
git add apps/api/app/Http apps/api/routes/api.php apps/api/bootstrap/app.php apps/api/tests/Feature/Staff/MfaTest.php
git commit -m "feat(api): require TOTP enrollment and add MFA login challenge"
```

---

### Task 8: Audience middleware and the audience matrix test

**Files:**
- Create: `apps/api/app/Http/Middleware/EnsureAudience.php`
- Create: `apps/api/tests/Feature/AudienceMatrixTest.php`
- Modify: `apps/api/routes/api.php`, `apps/api/bootstrap/app.php`

**Interfaces:**
- Consumes: the `staff` guard (Task 6)
- Produces: middleware alias `audience:<name>` rejecting any request not authenticated by the named guard, before controller dispatch; a matrix test enumerating every route in the application

- [ ] **Step 1: Write the failing test**

`apps/api/tests/Feature/AudienceMatrixTest.php`:

```php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/**
 * Every API route must declare its audience. Routes that are deliberately
 * public are listed here explicitly, so adding a public route is a decision
 * someone makes on purpose rather than an omission nobody notices.
 */
const PUBLIC_ROUTES = [
    'api/health',
    'api/staff/auth/login',
    'api/staff/auth/mfa/verify',
    'sanctum/csrf-cookie',
];

it('declares an audience on every non-public api route', function () {
    $undeclared = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/'))
        ->reject(fn ($route) => in_array($route->uri(), PUBLIC_ROUTES, true))
        ->reject(fn ($route) => collect($route->gatherMiddleware())
            ->contains(fn ($m) => str_starts_with((string) $m, 'audience:')))
        ->map(fn ($route) => $route->methods()[0].' '.$route->uri())
        ->values()
        ->all();

    expect($undeclared)->toBe([]);
});

it('rejects unauthenticated access to staff routes', function () {
    $this->getJson('/api/staff/me')->assertUnauthorized();
    $this->getJson('/api/staff/dashboard')->assertUnauthorized();
});

it('rejects a session that is not the declared audience', function () {
    $user = User::factory()->withMfa()->create();

    // Authenticate against a different guard than the route declares.
    $this->actingAs($user, 'web');

    $this->getJson('/api/staff/dashboard')->assertUnauthorized();
});

it('accepts a correctly authenticated staff session', function () {
    $user = User::factory()->withMfa()->create();

    $this->actingAs($user, 'staff');

    $this->getJson('/api/staff/dashboard')->assertOk();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker compose run --rm php ./vendor/bin/pest --filter=AudienceMatrix`
Expected: FAIL — the first test lists `GET api/staff/me` and friends as undeclared.

- [ ] **Step 3: Implement the middleware**

`apps/api/app/Http/Middleware/EnsureAudience.php`:

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
 */
class EnsureAudience
{
    public function handle(Request $request, Closure $next, string $audience): Response
    {
        if (! Auth::guard($audience)->check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        Auth::shouldUse($audience);

        return $next($request);
    }
}
```

Register the alias in `apps/api/bootstrap/app.php`:

```php
$middleware->alias([
    'mfa' => \App\Http\Middleware\RequireConfirmedMfa::class,
    'audience' => \App\Http\Middleware\EnsureAudience::class,
]);
```

- [ ] **Step 4: Declare the audience on the staff routes**

In `apps/api/routes/api.php`, change the authenticated group's middleware from `'auth:staff'` to `['audience:staff']`:

```php
Route::middleware('audience:staff')->group(function () {
    Route::get('/me', [SessionController::class, 'show']);
    Route::post('/auth/logout', [SessionController::class, 'destroy']);
    Route::post('/auth/mfa/enroll', [MfaEnrollmentController::class, 'enroll']);
    Route::post('/auth/mfa/confirm', [MfaEnrollmentController::class, 'confirm']);

    Route::middleware('mfa')->group(function () {
        Route::get('/dashboard', fn () => response()->json(['leads' => 0, 'jobs' => 0]));
    });
});
```

- [ ] **Step 5: Run the full suite**

Run: `docker compose run --rm php ./vendor/bin/pest`
Expected: PASS — every test from Tasks 2 through 8, including the four matrix tests. The Task 6 and 7 tests must still pass unchanged, since `audience:staff` is a stricter superset of `auth:staff`.

- [ ] **Step 6: Commit**

```bash
git add apps/api/app/Http/Middleware/EnsureAudience.php apps/api/bootstrap/app.php apps/api/routes/api.php apps/api/tests/Feature/AudienceMatrixTest.php
git commit -m "feat(api): gate routes by audience and assert coverage with a matrix test"
```

---

### Task 9: Response hygiene, OpenAPI generation, and the contracts package

**Files:**
- Create: `packages/contracts/package.json`
- Create: `packages/contracts/openapi.json` (generated, committed)
- Create: `packages/contracts/src/index.ts`
- Create: `apps/api/app/Http/Resources/StaffProfileResource.php`
- Create: `apps/api/tests/Feature/NoInternalIdsTest.php`
- Create: `apps/api/config/scramble.php`
- Modify: `apps/api/app/Http/Controllers/Staff/SessionController.php`, `.github/workflows/ci.yml`

**Interfaces:**
- Consumes: the routes from Tasks 6 through 8, `HasPublicId` (Task 3)
- Produces: `@studio/contracts` exporting `paths` and `components` types from `src/schema.d.ts` plus a named `StaffProfile` whose `id` is a string; the npm script `pnpm --filter @studio/contracts generate`; a CI check failing when the committed spec is stale; a test failing when any endpoint emits an integer `id`

- [ ] **Step 1: Write the failing response-hygiene test**

`apps/api/tests/Feature/NoInternalIdsTest.php`:

```php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

/**
 * Walks a decoded response body and returns the paths of any key that looks
 * like an identifier but holds an integer. Returning a model directly instead
 * of through a resource is the ordinary way the internal key escapes, and it
 * should fail here rather than in review.
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

it('never returns an integer identifier from an authenticated staff endpoint', function (string $uri) {
    $secret = (new Google2FA)->generateSecretKey();
    $user = User::factory()->withMfa()->create(['mfa_secret' => $secret]);

    $this->actingAs($user, 'staff');

    $response = $this->getJson($uri)->assertOk();

    expect(integerIdPaths($response->json()))->toBe([]);
})->with(['/api/staff/me', '/api/staff/dashboard']);

it('never returns an integer identifier from the login endpoints', function () {
    $user = User::factory()->create(['email' => 'owner@studio.test', 'password' => 'password']);

    $login = $this->postJson('/api/staff/auth/login', [
        'email' => 'owner@studio.test',
        'password' => 'password',
    ])->assertOk();

    expect(integerIdPaths($login->json()))->toBe([]);
});

it('detects an integer id when one is present', function () {
    expect(integerIdPaths(['data' => ['id' => 7, 'name' => 'x']]))->toBe(['$.data.id']);
    expect(integerIdPaths(['data' => ['id' => 'abc', 'name' => 'x']]))->toBe([]);
});
```

The third case tests the detector itself. A guard that silently matches nothing is worse than no guard, and this is the cheapest way to know it works.

- [ ] **Step 2: Run the test to verify the detector works and the endpoints are clean**

Run: `docker compose run --rm php ./vendor/bin/pest --filter=NoInternalIds`
Expected: PASS, 4 tests — Task 6 already returns `public_id` from `/api/staff/me`, so this locks in behaviour that exists rather than driving new code. If it fails, an endpoint added since Task 6 is leaking; fix that endpoint before continuing.

- [ ] **Step 3: Publish the spec from the API**

Generate the Scramble config and restrict it to the API prefix:

```bash
docker compose run --rm php php artisan vendor:publish --provider="Dedoc\Scramble\ScrambleServiceProvider" --tag=scramble-config
```

In `apps/api/config/scramble.php`, set `'api_path' => 'api'` and `'export_path' => '../../packages/contracts/openapi.json'`.

- [ ] **Step 4: Create the contracts package**

`packages/contracts/package.json`:

```json
{
  "name": "@studio/contracts",
  "version": "0.0.0",
  "private": true,
  "type": "module",
  "main": "src/index.ts",
  "types": "src/index.ts",
  "scripts": {
    "generate": "openapi-typescript ./openapi.json -o ./src/schema.d.ts",
    "test": "tsc --noEmit"
  },
  "devDependencies": {
    "openapi-typescript": "^7.4.0",
    "typescript": "^5.6.0"
  }
}
```

`packages/contracts/src/index.ts`:

```ts
export type { paths, components, operations } from './schema'

import type { components } from './schema'

export type StaffProfile = components['schemas']['StaffProfile']
```

`packages/contracts/tsconfig.json`:

```json
{
  "compilerOptions": {
    "target": "ES2022",
    "module": "ESNext",
    "moduleResolution": "bundler",
    "strict": true,
    "noEmit": true,
    "skipLibCheck": true
  },
  "include": ["src"]
}
```

- [ ] **Step 5: Annotate the profile response so the schema has a name**

Scramble infers response shapes from return types. Give the profile a dedicated resource so the generated schema is named `StaffProfile` rather than an inline object.

Create `apps/api/app/Http/Resources/StaffProfileResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffProfileResource extends JsonResource
{
    /** @return array{id: string, name: string, email: string, role: string, mfa_confirmed: bool} */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'mfa_confirmed' => $this->hasConfirmedMfa(),
        ];
    }
}
```

In `SessionController::show()`, return `StaffProfileResource::make($request->user('staff'))->response()`. The existing `LoginTest` assertion on `email` still passes because `JsonResource` wrapping is disabled by default in Laravel 11 only if `JsonResource::withoutWrapping()` is called — add that call to `AppServiceProvider::boot()` so responses stay unwrapped and the Task 6 tests remain green.

- [ ] **Step 6: Generate and verify**

```bash
docker compose run --rm php php artisan scramble:export
pnpm install
pnpm --filter @studio/contracts generate
pnpm --filter @studio/contracts test
```

Expected: `openapi.json` contains `/api/staff/me`, `src/schema.d.ts` is written with `StaffProfile.id` typed as `string`, and `tsc --noEmit` exits 0.

Then re-run the PHP suite to confirm the resource swap did not change any response shape:

Run: `docker compose run --rm php ./vendor/bin/pest`
Expected: PASS — in particular `LoginTest` and `NoInternalIdsTest` are unchanged.

- [ ] **Step 7: Add the staleness gate to CI**

In the `api` job of `.github/workflows/ci.yml`, after the Pest step:

```yaml
      - name: Assert the committed OpenAPI spec is current
        working-directory: apps/api
        env:
          DB_CONNECTION: pgsql
          DB_HOST: 127.0.0.1
          DB_PORT: 5432
          DB_DATABASE: studio_test
          DB_USERNAME: studio
          DB_PASSWORD: studio
        run: |
          php artisan scramble:export
          git diff --exit-code -- ../../packages/contracts/openapi.json \
            || { echo "OpenAPI spec is stale. Run 'php artisan scramble:export' and commit."; exit 1; }
```

- [ ] **Step 8: Commit**

```bash
git add packages/contracts apps/api/config/scramble.php apps/api/app/Http/Resources apps/api/app/Http/Controllers/Staff/SessionController.php apps/api/app/Providers/AppServiceProvider.php apps/api/tests/Feature/NoInternalIdsTest.php .github/workflows/ci.yml pnpm-lock.yaml
git commit -m "feat(contracts): generate OpenAPI types, guard against internal id leaks"
```

---

### Task 10: Admin shell with login

**Files:**
- Create: `apps/admin/**` (Nuxt 4 scaffold)
- Create: `apps/admin/app/composables/useSession.ts`
- Create: `apps/admin/app/middleware/auth.ts`
- Create: `apps/admin/app/pages/login.vue`
- Create: `apps/admin/app/pages/index.vue`
- Create: `apps/admin/tests/e2e/login.spec.ts`
- Create: `apps/admin/playwright.config.ts`
- Modify: `.github/workflows/ci.yml`

**Interfaces:**
- Consumes: `POST /api/staff/auth/login`, `POST /api/staff/auth/mfa/verify`, `GET /api/staff/me`, `GET /api/staff/dashboard`, and `@studio/contracts` types
- Produces: a Nuxt SPA at `http://localhost:3000` with `/login` and a protected `/`

- [ ] **Step 1: Scaffold Nuxt**

```bash
pnpm dlx nuxi@latest init apps/admin --package-manager pnpm --no-install --git-init=false
pnpm add --filter @studio/admin @studio/contracts
pnpm add --filter @studio/admin -D @playwright/test
pnpm install
```

Set `apps/admin/package.json` name to `@studio/admin` and add scripts:

```json
"scripts": {
  "dev": "nuxt dev",
  "build": "nuxt build",
  "lint": "nuxt typecheck",
  "test": "vitest run --passWithNoTests",
  "test:e2e": "playwright test"
}
```

In `apps/admin/nuxt.config.ts`:

```ts
export default defineNuxtConfig({
  ssr: false,
  devtools: { enabled: true },
  runtimeConfig: {
    public: { apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000' },
  },
})
```

SSR is off for admin: it is a private, authenticated tool where server rendering buys nothing and would require forwarding session cookies through the Nuxt server.

- [ ] **Step 2: Write the failing end-to-end test**

`apps/admin/tests/e2e/login.spec.ts`:

```ts
import { test, expect } from '@playwright/test'

test('redirects an anonymous visitor to the login page', async ({ page }) => {
  await page.goto('/')
  await expect(page).toHaveURL(/\/login$/)
})

test('signs in with password and TOTP and reaches the dashboard', async ({ page }) => {
  await page.goto('/login')
  await page.getByLabel('Email').fill(process.env.E2E_EMAIL!)
  await page.getByLabel('Password').fill(process.env.E2E_PASSWORD!)
  await page.getByRole('button', { name: 'Continue' }).click()

  await expect(page.getByLabel('Authentication code')).toBeVisible()
  await page.getByLabel('Authentication code').fill(process.env.E2E_TOTP!)
  await page.getByRole('button', { name: 'Verify' }).click()

  await expect(page).toHaveURL('/')
  await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible()
})

test('shows a generic error for bad credentials', async ({ page }) => {
  await page.goto('/login')
  await page.getByLabel('Email').fill('nobody@studio.test')
  await page.getByLabel('Password').fill('wrong')
  await page.getByRole('button', { name: 'Continue' }).click()

  await expect(page.getByRole('alert')).toContainText('do not match our records')
})
```

`apps/admin/playwright.config.ts`:

```ts
import { defineConfig } from '@playwright/test'

export default defineConfig({
  testDir: './tests/e2e',
  use: { baseURL: 'http://localhost:3000' },
  webServer: {
    command: 'pnpm dev',
    url: 'http://localhost:3000',
    reuseExistingServer: !process.env.CI,
  },
})
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `pnpm --filter @studio/admin test:e2e`
Expected: FAIL — `/login` does not exist, so the first assertion times out.

- [ ] **Step 4: Implement the session composable**

`apps/admin/app/composables/useSession.ts`:

```ts
import type { StaffProfile } from '@studio/contracts'

const profile = ref<StaffProfile | null>(null)

export function useSession() {
  const { public: config } = useRuntimeConfig()
  const base = config.apiBase

  async function csrf() {
    await $fetch('/sanctum/csrf-cookie', { baseURL: base, credentials: 'include' })
  }

  function xsrfHeader(): Record<string, string> {
    const token = document.cookie
      .split('; ')
      .find((c) => c.startsWith('XSRF-TOKEN='))
      ?.split('=')[1]

    return token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {}
  }

  async function login(email: string, password: string): Promise<{ mfaRequired: boolean }> {
    await csrf()
    const response = await $fetch<{ mfa_required: boolean }>('/api/staff/auth/login', {
      baseURL: base,
      method: 'POST',
      credentials: 'include',
      headers: xsrfHeader(),
      body: { email, password },
    })

    return { mfaRequired: response.mfa_required }
  }

  async function verify(code: string): Promise<void> {
    await $fetch('/api/staff/auth/mfa/verify', {
      baseURL: base,
      method: 'POST',
      credentials: 'include',
      headers: xsrfHeader(),
      body: { code },
    })
  }

  async function refresh(): Promise<StaffProfile | null> {
    try {
      profile.value = await $fetch<StaffProfile>('/api/staff/me', {
        baseURL: base,
        credentials: 'include',
      })
    } catch {
      profile.value = null
    }

    return profile.value
  }

  async function logout(): Promise<void> {
    await $fetch('/api/staff/auth/logout', {
      baseURL: base,
      method: 'POST',
      credentials: 'include',
      headers: xsrfHeader(),
    })
    profile.value = null
  }

  return { profile: readonly(profile), login, verify, refresh, logout }
}
```

- [ ] **Step 5: Implement the route guard and pages**

`apps/admin/app/middleware/auth.ts`:

```ts
export default defineNuxtRouteMiddleware(async (to) => {
  if (to.path === '/login') return

  const { refresh } = useSession()
  if (!(await refresh())) {
    return navigateTo('/login')
  }
})
```

`apps/admin/app/pages/login.vue`:

```vue
<script setup lang="ts">
definePageMeta({ middleware: [] })

const { login, verify } = useSession()
const step = ref<'credentials' | 'totp'>('credentials')
const email = ref('')
const password = ref('')
const code = ref('')
const error = ref('')

async function submitCredentials() {
  error.value = ''
  try {
    const { mfaRequired } = await login(email.value, password.value)
    if (mfaRequired) {
      step.value = 'totp'
      return
    }
    await navigateTo('/')
  } catch (e: any) {
    error.value = e?.data?.message ?? 'Sign in failed.'
  }
}

async function submitCode() {
  error.value = ''
  try {
    await verify(code.value)
    await navigateTo('/')
  } catch (e: any) {
    error.value = e?.data?.message ?? 'Sign in failed.'
  }
}
</script>

<template>
  <main>
    <h1>Sign in</h1>

    <p v-if="error" role="alert">{{ error }}</p>

    <form v-if="step === 'credentials'" @submit.prevent="submitCredentials">
      <label for="email">Email</label>
      <input id="email" v-model="email" type="email" autocomplete="username" required>

      <label for="password">Password</label>
      <input id="password" v-model="password" type="password" autocomplete="current-password" required>

      <button type="submit">Continue</button>
    </form>

    <form v-else @submit.prevent="submitCode">
      <label for="code">Authentication code</label>
      <input id="code" v-model="code" inputmode="numeric" autocomplete="one-time-code" required>

      <button type="submit">Verify</button>
    </form>
  </main>
</template>
```

`apps/admin/app/pages/index.vue`:

```vue
<script setup lang="ts">
definePageMeta({ middleware: ['auth'] })

const { profile, logout } = useSession()
const { data: stats } = await useFetch<{ leads: number; jobs: number }>('/api/staff/dashboard', {
  baseURL: useRuntimeConfig().public.apiBase,
  credentials: 'include',
})
</script>

<template>
  <main>
    <h1>Dashboard</h1>
    <p>Signed in as {{ profile?.name }} ({{ profile?.role }})</p>
    <dl>
      <dt>Leads</dt><dd>{{ stats?.leads ?? 0 }}</dd>
      <dt>Jobs</dt><dd>{{ stats?.jobs ?? 0 }}</dd>
    </dl>
    <button type="button" @click="logout().then(() => navigateTo('/login'))">Sign out</button>
  </main>
</template>
```

`apps/admin/app/app.vue`:

```vue
<template>
  <NuxtPage />
</template>
```

- [ ] **Step 6: Seed a deterministic end-to-end account**

Create `apps/api/database/seeders/E2ESeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class E2ESeeder extends Seeder
{
    public const SECRET = 'ABCDEFGHIJKLMNOP';

    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'e2e@studio.test'],
            [
                'name' => 'E2E Owner',
                'password' => 'e2e-password',
                'role' => StaffRole::Owner,
                'mfa_secret' => self::SECRET,
                'mfa_confirmed_at' => now(),
                'mfa_recovery_codes' => [],
            ],
        );
    }
}
```

Run it and compute a current code for the local run:

```bash
docker compose run --rm php php artisan db:seed --class=E2ESeeder
docker compose run --rm php php -r 'require "vendor/autoload.php"; echo (new PragmaRX\Google2FA\Google2FA)->getCurrentOtp("ABCDEFGHIJKLMNOP"), PHP_EOL;'
```

- [ ] **Step 7: Run the end-to-end tests to verify they pass**

Start the API, then run Playwright with the seeded credentials:

```bash
docker compose up -d postgres
docker compose run --rm --service-ports -d php php artisan serve --host=0.0.0.0 --port=8000
E2E_EMAIL=e2e@studio.test E2E_PASSWORD=e2e-password E2E_TOTP=$(docker compose run --rm php php -r 'require "vendor/autoload.php"; echo (new PragmaRX\Google2FA\Google2FA)->getCurrentOtp("ABCDEFGHIJKLMNOP");') \
  pnpm --filter @studio/admin test:e2e
```

Expected: PASS, 3 tests.

- [ ] **Step 8: Add the end-to-end job to CI**

Append to `.github/workflows/ci.yml`:

```yaml
  e2e:
    runs-on: ubuntu-latest
    needs: [api, web]
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
    env:
      DB_CONNECTION: pgsql
      DB_HOST: 127.0.0.1
      DB_PORT: 5432
      DB_DATABASE: studio_test
      DB_USERNAME: studio
      DB_PASSWORD: studio
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: "8.3", extensions: "pdo_pgsql, bcmath, gd, intl" }
      - uses: pnpm/action-setup@v4
        with: { version: 10 }
      - uses: actions/setup-node@v4
        with: { node-version: 24, cache: pnpm }
      - working-directory: apps/api
        run: |
          composer install --no-interaction --prefer-dist
          cp .env.example .env
          php artisan key:generate
          php artisan migrate --force
          php artisan db:seed --class=E2ESeeder
          php artisan serve --host=127.0.0.1 --port=8000 &
      - run: pnpm install --frozen-lockfile
      - run: pnpm exec playwright install --with-deps chromium
      - name: Run end-to-end tests
        working-directory: apps/admin
        env:
          NUXT_PUBLIC_API_BASE: http://127.0.0.1:8000
          E2E_EMAIL: e2e@studio.test
          E2E_PASSWORD: e2e-password
        run: |
          export E2E_TOTP=$(cd ../api && php -r 'require "vendor/autoload.php"; echo (new PragmaRX\Google2FA\Google2FA)->getCurrentOtp("ABCDEFGHIJKLMNOP");')
          pnpm test:e2e
```

- [ ] **Step 9: Run the whole suite**

```bash
docker compose run --rm php ./vendor/bin/pest
pnpm lint && pnpm test && pnpm build
```

Expected: all PHP tests pass; typecheck, unit tests, and builds succeed across the workspace.

- [ ] **Step 10: Commit**

```bash
git add apps/admin apps/api/database/seeders/E2ESeeder.php .github/workflows/ci.yml pnpm-lock.yaml
git commit -m "feat(admin): add Nuxt admin shell with password and TOTP sign-in"
```

---

## Definition of Done for P1a

- [ ] `docker compose run --rm php ./vendor/bin/pest` passes with zero skipped tests
- [ ] `pnpm lint && pnpm test && pnpm build` passes at the workspace root
- [ ] `pnpm --filter @studio/admin test:e2e` passes against a locally served API
- [ ] CI is green on all three jobs (`api`, `web`, `e2e`)
- [ ] A staff user can sign in at `http://localhost:3000`, is forced through TOTP enrollment on first login, and lands on the dashboard
- [ ] `audit_logs` contains rows for login success, failure, throttling, MFA enrollment, MFA failure, and logout
- [ ] Every non-public API route carries an `audience:` declaration, enforced by the matrix test
- [ ] `users` and `audit_logs` both carry a unique `public_id`, and no endpoint emits an integer identifier — enforced by `NoInternalIdsTest`
- [ ] `packages/contracts/openapi.json` is current, and CI fails if it goes stale
- [ ] `apps/api/docs/database-grants.md` records the `audit_logs` privilege revocation as a deployment step

## Deferred to later plans

- Staff password reset and invitation flows (P1b, alongside real user management)
- Real dashboard metrics replacing the stub endpoint (P1b)
- Role-based policies — the enum exists in P1a, but nothing branches on it until there are resources to authorize (P1b)
- Network-level restriction of the admin surface, which is a deployment concern rather than an application one
- Client and guest guards, added by P2 and P3 respectively, each extending the audience matrix test

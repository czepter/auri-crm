# P1b Sprint 2 — Intake and Lead Board Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** An inquiry taken by phone is typed into one German form, creates a contact, a project, a lead and its primary shoot in a single transaction, warns if that date is already taken without blocking the save, and appears on a six-column board.

**Architecture:** A new `App\Crm` bounded context under `app/Crm/` holding models, enums, actions, typed Data objects and controllers. Five tables join the existing tenant/public-id seam. The two facts the screens display that could tempt a denormalised column — date conflicts and board position — are queries, not state. Server-side rendering is Inertia + Vue 3 as P1a established; the board and capture form are ordinary pages behind `audience:staff` and `mfa`.

**Tech Stack:** PHP 8.5, Laravel 13, Postgres 18, Pest 5, Inertia 3 + Vue 3.5, Tailwind 4 with shadcn-vue primitives, `spatie/laravel-data` 4 → `generated.d.ts`, vue-i18n 11, Playwright.

**Spec:** [`../specs/2026-08-09-p1b-crm-core-design.md`](../specs/2026-08-09-p1b-crm-core-design.md)
**Mockups:** `mockups/admin/02-anfragen.html`, `mockups/admin/03-anfrage-neu.html`

---

## Global Constraints

Every task's requirements implicitly include this section.

- **There is no host PHP.** Every PHP, composer and artisan command runs in Docker: `docker compose run --rm php <command>`. Frontend commands (`pnpm`) run on the host.
- **Postgres only, never SQLite.** Two constraints in this sprint are partial unique indexes, which SQLite would silently omit while the tests still passed.
- **Every new table carries `tenant_id` and `public_id` from its first migration**, via the `tenantId()` and `publicId()` Blueprint macros in `app/Providers/AppServiceProvider.php`.
- **No integer identifier in any Inertia prop, JSON body, URL, email or filename.** Route-model binding resolves on `public_id` through `HasPublicId::getRouteKeyName()`. `tests/Feature/NoInternalIdsTest.php` enforces it and grows this sprint.
- **Every non-public route declares an `audience:`.** `tests/Feature/AudienceMatrixTest.php` enforces it.
- **Tests before implementation.** Each task writes the failing test, runs it to see it fail, then implements.
- **All new models** use `BelongsToTenant`, `HasPublicId` and `HasFactory`, and declare `newFactory()` explicitly — Laravel's default factory resolution does not find `Database\Factories\Crm\*` from `App\Crm\Models\*`.
- **Timestamps are `timestamptz`** (`timestampsTz()`). `shoots.date` is a `date` column and `call_time`/`end_time` are `time` columns.
- **German is the only UI language.** User-visible copy resolves through the vue-i18n catalog or a Laravel `lang/de` file. Route names, identifiers, comments and commit messages stay English.
- **Conventional Commits**, one commit per task minimum.
- **Definition of Done** for the sprint is in the spec, §9. Do not close a task by weakening it.

---

## File Structure

New, in `apps/studio/`:

```
app/Crm/
  Enums/            ProjectType.php  LeadSource.php  LeadStage.php  CaptureChannel.php
                    ProjectContactRole.php  ShootKind.php  ShootStatus.php
  Models/           Contact.php  Project.php  Lead.php  Shoot.php
  Actions/          CaptureInquiry.php  AdvanceLeadStage.php  CheckDateAvailability.php
  Data/             ContactSuggestionData.php  DateConflictData.php  LeadCardData.php
                    CaptureInquiryData.php
  Events/           LeadCaptured.php  LeadStageChanged.php  LeadLost.php
  Http/Controllers/ LeadBoardController.php  InquiryCaptureController.php
                    LeadStageController.php  ContactController.php
                    ContactSearchController.php  AvailabilityController.php
database/migrations/  2026_08_09_000100_create_contacts_table.php
                      2026_08_09_000200_create_projects_table.php
                      2026_08_09_000300_create_project_contacts_table.php
                      2026_08_09_000400_create_leads_table.php
                      2026_08_09_000500_create_shoots_table.php
database/factories/Crm/  ContactFactory.php  ProjectFactory.php  LeadFactory.php  ShootFactory.php
database/seeders/     DemoCrmSeeder.php
resources/js/locales/ de.json
resources/js/admin/pages/Leads/   Board.vue  Create.vue
resources/js/admin/pages/Contacts/  Index.vue  Edit.vue
resources/js/admin/components/  ContactTypeahead.vue  LeadCard.vue  ConflictNote.vue
tests/Feature/Crm/  ContactTest.php  ProjectTest.php  LeadTest.php  ShootTest.php
                    LeadStageTest.php  AvailabilityTest.php  ContactSearchTest.php
                    CaptureInquiryTest.php  LeadBoardTest.php  ContactCrudTest.php
tests/Feature/LocalizationTest.php
tests/e2e/capture.spec.ts
lang/de/validation.php  lang/de/auth.php  lang/de/passwords.php
```

Modified: `routes/admin.php` · `resources/js/admin/main.ts` · `resources/js/admin/pages/Auth/{Login,MfaEnroll,MfaChallenge,RecoveryCodes}.vue` · `resources/js/admin/pages/Dashboard.vue` · `app/Http/Controllers/Staff/DashboardController.php` · `config/app.php` (locale) · `apps/studio/package.json` · `tests/Feature/NoInternalIdsTest.php`.

`app/Crm/Data/*` is picked up automatically by `TypeScriptTransformerServiceProvider`, which already transforms all of `app_path()`. Enums need `#[TypeScript]` to appear in `generated.d.ts`.

---

## Task Dependency Order

```
1 (i18n) ─┐
2 (Contact) ─┬─ 3 (Project + ProjectContact) ─┬─ 4 (Lead) ── 6 (transitions) ─┐
            │                                  └─ 5 (Shoot) ─┬────────────────┤
            ├─ 10 (Contacts CRUD)                            └─ 7 (endpoints) ─┴─ 8 (capture) ── 9 (board)
```

Tasks 1 and 2 share no file and may run in parallel. Everything after is sequential.

---

# Task 1: German locale and the i18n catalog

**Files:**
- Create: `apps/studio/lang/de/validation.php`, `apps/studio/lang/de/auth.php`, `apps/studio/lang/de/passwords.php`
- Create: `apps/studio/resources/js/locales/de.json`
- Create: `apps/studio/tests/Feature/LocalizationTest.php`
- Modify: `apps/studio/resources/js/admin/main.ts`
- Modify: `apps/studio/resources/js/admin/pages/Auth/Login.vue`, `MfaEnroll.vue`, `MfaChallenge.vue`, `RecoveryCodes.vue`, `apps/studio/resources/js/admin/pages/Dashboard.vue`
- Modify: `apps/studio/package.json`, `apps/studio/.env.example`, `apps/studio/config/app.php`

**Interfaces:**
- Produces: a global `$t(key)` available in every admin Vue component; the catalog file `resources/js/locales/de.json`; German Laravel validation messages. Later tasks add keys to `de.json` under their own top-level namespace (`leads`, `contacts`) and never hardcode a user-visible string.

- [ ] **Step 1: Write the failing test**

Create `apps/studio/tests/Feature/LocalizationTest.php`:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('runs in german', function () {
    expect(config('app.locale'))->toBe('de')
        ->and(config('app.fallback_locale'))->toBe('de');
});

it('renders validation failures in german', function () {
    // Asserted through the translator rather than through the login rules, so
    // this test keeps testing the catalog even if SessionController's rules
    // change.
    expect(__('validation.required', ['attribute' => __('validation.attributes.email')]))
        ->toBe('Das Feld E-Mail muss ausgefüllt werden.');

    $this->post('/login', ['email' => '', 'password' => ''])->assertSessionHasErrors('email');
});

it('renders the throttle message in german', function () {
    expect(__('auth.throttle', ['seconds' => 30]))
        ->toBe('Zu viele Anmeldeversuche. Bitte in 30 Sekunden erneut versuchen.');
});
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `docker compose run --rm php ./vendor/bin/pest tests/Feature/LocalizationTest.php`
Expected: FAIL — `config('app.locale')` is `en`.

- [ ] **Step 3: Switch the application locale**

In `apps/studio/config/app.php`, change the two locale defaults:

```php
'locale' => env('APP_LOCALE', 'de'),
'fallback_locale' => env('APP_FALLBACK_LOCALE', 'de'),
```

In `apps/studio/.env.example` and your local `.env`, set:

```
APP_LOCALE=de
APP_FALLBACK_LOCALE=de
```

- [ ] **Step 4: Publish and translate the Laravel language files**

Run: `docker compose run --rm php php artisan lang:publish`

That writes `lang/en/*`. Create `lang/de/auth.php`:

```php
<?php

return [
    'failed' => 'Diese Zugangsdaten stimmen nicht mit unseren Daten überein.',
    'password' => 'Das angegebene Passwort ist nicht korrekt.',
    'throttle' => 'Zu viele Anmeldeversuche. Bitte in :seconds Sekunden erneut versuchen.',
];
```

Create `lang/de/passwords.php`:

```php
<?php

return [
    'reset' => 'Das Passwort wurde zurückgesetzt.',
    'sent' => 'Der Link zum Zurücksetzen wurde versendet.',
    'throttled' => 'Bitte einen Moment warten und es dann erneut versuchen.',
    'token' => 'Dieser Link zum Zurücksetzen des Passworts ist ungültig.',
    'user' => 'Zu dieser E-Mail-Adresse wurde kein Konto gefunden.',
];
```

Create `lang/de/validation.php` by copying `lang/en/validation.php` and translating. The keys this sprint actually exercises — translate at minimum these, and the `attributes` array, leaving the rest of the file's English text in place rather than deleting keys:

```php
<?php

return [
    'after_or_equal' => 'Das Feld :attribute muss ein Datum nach dem :date oder gleich diesem sein.',
    'date' => 'Das Feld :attribute muss ein gültiges Datum sein.',
    'email' => 'Das Feld :attribute muss eine gültige E-Mail-Adresse sein.',
    'in' => 'Der gewählte Wert für :attribute ist ungültig.',
    'max' => [
        'string' => 'Das Feld :attribute darf höchstens :max Zeichen lang sein.',
    ],
    'prohibited_unless' => 'Das Feld :attribute ist unzulässig.',
    'required' => 'Das Feld :attribute muss ausgefüllt werden.',
    'required_without' => 'Das Feld :attribute muss ausgefüllt werden, wenn :values fehlt.',
    'unique' => ':attribute ist bereits vergeben.',

    'attributes' => [
        'email' => 'E-Mail',
        'password' => 'Passwort',
        'code' => 'Code',
        'name' => 'Name',
        'phone' => 'Telefon',
        'project_name' => 'Projektname',
        'type' => 'Typ',
        'source' => 'Quelle',
        'captured_via' => 'Eingangskanal',
        'date' => 'Haupttermin',
        'inquiry_text' => 'Anfragetext',
        'partner_name' => 'Partner/in',
        'contact_id' => 'Kontakt',
        'stage' => 'Status',
        'reason' => 'Grund',
    ],
];
```

Delete `lang/en/` — a fallback locale of `de` never reaches it, and leaving it invites a half-translated file to look intentional.

- [ ] **Step 5: Run the backend part of the test**

Run: `docker compose run --rm php ./vendor/bin/pest tests/Feature/LocalizationTest.php`
Expected: PASS, all three.

- [ ] **Step 6: Install and register vue-i18n**

Run on the host: `pnpm --filter @studio/app add vue-i18n@^11`

Create `apps/studio/resources/js/locales/de.json`. This is the whole catalog for what exists today; later tasks append top-level namespaces:

```json
{
  "common": {
    "cancel": "Abbrechen",
    "save": "Speichern",
    "signOut": "Abmelden",
    "search": "Suchen"
  },
  "auth": {
    "signIn": "Anmelden",
    "signInTitle": "Anmelden",
    "email": "E-Mail",
    "password": "Passwort",
    "mfaTitle": "Bestätigung in zwei Schritten",
    "mfaCode": "6-stelliger Code",
    "mfaConfirm": "Bestätigen",
    "mfaEnrollTitle": "Zwei-Faktor-Authentisierung einrichten",
    "mfaEnrollHint": "Den QR-Code mit der Authenticator-App scannen und den angezeigten Code eingeben.",
    "recoveryTitle": "Wiederherstellungscodes",
    "recoveryHint": "Diese Codes werden nur ein einziges Mal angezeigt. Jetzt sicher aufbewahren.",
    "recoveryContinue": "Weiter zur Übersicht"
  },
  "dashboard": {
    "title": "Übersicht",
    "signedInAs": "Angemeldet als {name}",
    "openLeads": "Offene Anfragen",
    "activeJobs": "Laufende Aufträge"
  }
}
```

Modify `apps/studio/resources/js/admin/main.ts` — add the import, the instance, and the `.use()`:

```ts
import { createApp, h, type DefineComponent } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { createI18n } from 'vue-i18n'
import de from '../locales/de.json'
import '../../css/admin.css'

// German is the only locale. The catalog exists so a second one is a file
// rather than a rewrite, not because one is planned.
const i18n = createI18n({
  legacy: false,
  locale: 'de',
  fallbackLocale: 'de',
  messages: { de },
  datetimeFormats: {
    de: {
      short: { day: '2-digit', month: '2-digit', year: 'numeric' },
    },
  },
  numberFormats: {
    de: {
      currency: { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 },
    },
  },
})

const pages = import.meta.glob<{ default: DefineComponent }>('./pages/**/*.vue')

createInertiaApp({
  title: (title) => (title ? `${title} · Studio` : 'Studio'),
  resolve: (name) => {
    const page = pages[`./pages/${name}.vue`]

    if (!page) {
      throw new Error(`Unknown admin page: ${name}`)
    }

    return page().then((module) => module.default)
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(i18n)
      .mount(el)
  },
})
```

- [ ] **Step 7: Retrofit the five existing pages**

In each of `pages/Auth/Login.vue`, `pages/Auth/MfaEnroll.vue`, `pages/Auth/MfaChallenge.vue`, `pages/Auth/RecoveryCodes.vue` and `pages/Dashboard.vue`, replace every user-visible literal with a catalog lookup. For example, in `Login.vue` the title and the submit button become:

```vue
<CardTitle>{{ $t('auth.signInTitle') }}</CardTitle>
...
<Button type="submit" class="w-full" :disabled="form.processing">{{ $t('auth.signIn') }}</Button>
```

and in `Dashboard.vue`:

```vue
<h1 class="text-2xl font-semibold tracking-tight">{{ $t('dashboard.title') }}</h1>
<Button variant="secondary" @click="router.post('/logout')">{{ $t('common.signOut') }}</Button>
...
<p class="text-sm text-muted-foreground">{{ $t('dashboard.signedInAs', { name: user.name }) }}</p>
...
<CardTitle class="text-sm font-medium">{{ $t('dashboard.openLeads') }}</CardTitle>
...
<CardTitle class="text-sm font-medium">{{ $t('dashboard.activeJobs') }}</CardTitle>
```

Add any key you need to `de.json` as you go. When you finish, grep for leftovers:

```bash
grep -rnE '>[A-Z][a-z]+ [a-z]+<' apps/studio/resources/js/admin/pages/
```

Expected: no hits that are user-visible English.

- [ ] **Step 8: Verify the frontend still typechecks, tests and builds**

Run on the host:

```bash
pnpm --filter @studio/app lint
pnpm --filter @studio/app test
pnpm --filter @studio/app build
```

Expected: all clean. If `vue-tsc` complains that `$t` is untyped in templates, that is expected with `legacy: false` — vue-i18n ships the global `$t` augmentation via `vue-i18n/dist/vue-i18n.d.ts`; ensure `"types"` in `tsconfig.json` does not exclude it, and if it still fails, import `useI18n()` in the affected component's `<script setup>` and use `t(...)` instead of `$t(...)`.

- [ ] **Step 9: Update the e2e specs for German copy**

`tests/e2e/login.spec.ts` and `tests/e2e/mfa.spec.ts` select on English text that no longer exists. Update every selector to the German string, e.g. `getByRole('button', { name: 'Anmelden' })`.

Run: `pnpm --filter @studio/app test:e2e`
Expected: 4 passed.

> Before running e2e locally, check for a leftover container from an earlier run: `docker ps --filter publish=8000` and stop anything listed. Playwright's `reuseExistingServer` will otherwise attach to a stale server running pre-change code.

- [ ] **Step 10: Commit**

```bash
git add apps/studio/lang apps/studio/config/app.php apps/studio/.env.example \
        apps/studio/resources/js apps/studio/package.json pnpm-lock.yaml \
        apps/studio/tests/Feature/LocalizationTest.php apps/studio/tests/e2e
git commit -m "feat(studio): run the admin surface in german"
```

---

# Task 2: The CRM namespace and Contact

**Files:**
- Create: `apps/studio/app/Crm/Models/Contact.php`
- Create: `apps/studio/database/migrations/2026_08_09_000100_create_contacts_table.php`
- Create: `apps/studio/database/factories/Crm/ContactFactory.php`
- Create: `apps/studio/tests/Feature/Crm/ContactTest.php`

**Interfaces:**
- Produces: `App\Crm\Models\Contact` with `$fillable = ['name', 'email', 'phone']`, a `projects()` belongs-to-many through `project_contacts` (declared in Task 3), and `Database\Factories\Crm\ContactFactory` with a `withoutEmail()` state.

- [ ] **Step 1: Write the failing test**

Create `apps/studio/tests/Feature/Crm/ContactTest.php`:

```php
<?php

use App\Crm\Models\Contact;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('creates a contact with a tenant and a public id', function () {
    $contact = Contact::factory()->create(['name' => 'Annika Steffens']);

    expect($contact->tenant_id)->toBe(config('tenancy.tenant_id'))
        ->and($contact->public_id)->toHaveLength(24)
        ->and($contact->getRouteKeyName())->toBe('public_id');
});

it('hides the primary key from serialization', function () {
    $contact = Contact::factory()->create();

    expect($contact->toArray())->not->toHaveKey('id');
});

it('rejects a second contact with the same email', function () {
    Contact::factory()->create(['email' => 'a.steffens@posteo.de']);

    Contact::factory()->create(['email' => 'a.steffens@posteo.de']);
})->throws(QueryException::class);

it('allows any number of contacts without an email', function () {
    Contact::factory()->withoutEmail()->count(3)->create();

    expect(Contact::whereNull('email')->count())->toBe(3);
});

it('allows the same email under a different tenant', function () {
    Contact::factory()->create(['email' => 'shared@example.test']);

    DB::table('contacts')->insert([
        'tenant_id' => '00000000-0000-4000-8000-00000000dead',
        'public_id' => 'bbbbbbbbbbbbbbbbbbbbbbbb',
        'name' => 'Someone Else',
        'email' => 'shared@example.test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(Contact::count())->toBe(1)
        ->and(DB::table('contacts')->count())->toBe(2);
});
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `docker compose run --rm php ./vendor/bin/pest tests/Feature/Crm/ContactTest.php`
Expected: FAIL — `Class "App\Crm\Models\Contact" not found`.

- [ ] **Step 3: Write the migration**

Create `apps/studio/database/migrations/2026_08_09_000100_create_contacts_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->timestampsTz();
        });

        // A partial unique index — Laravel's unique() takes no where clause.
        // Vision §3.1: a couple sharing an inbox is one contact holding that
        // address; a contact with no address simply has no portal access. If
        // the address could repeat, a P2 magic link would resolve to more than
        // one person and "who signed this contract" would have no answer.
        DB::statement(
            'create unique index contacts_tenant_email_unique
             on contacts (tenant_id, email) where email is not null'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
```

- [ ] **Step 4: Write the model and factory**

Create `apps/studio/app/Crm/Models/Contact.php`:

```php
<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Database\Factories\Crm\ContactFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A person, role-agnostic. One row per address: a household sharing an inbox
 * is one contact, a partner with their own address is a second contact.
 */
class Contact extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['name', 'email', 'phone'];

    protected static function newFactory(): Factory
    {
        return ContactFactory::new();
    }
}
```

Create `apps/studio/database/factories/Crm/ContactFactory.php`:

```php
<?php

namespace Database\Factories\Crm;

use App\Crm\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('0171 #######'),
        ];
    }

    public function withoutEmail(): static
    {
        return $this->state(fn () => ['email' => null]);
    }
}
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `docker compose run --rm php ./vendor/bin/pest tests/Feature/Crm/ContactTest.php`
Expected: 5 passed.

- [ ] **Step 6: Run the whole suite**

Run: `docker compose run --rm php ./vendor/bin/pest`
Expected: all green, nothing skipped.

- [ ] **Step 7: Commit**

```bash
git add apps/studio/app/Crm apps/studio/database apps/studio/tests
git commit -m "feat(studio): add the crm context and the contact model"
```

---

# Task 3: Project and ProjectContact

**Files:**
- Create: `apps/studio/app/Crm/Enums/ProjectType.php`, `apps/studio/app/Crm/Enums/ProjectContactRole.php`
- Create: `apps/studio/app/Crm/Models/Project.php`, `apps/studio/app/Crm/Models/ProjectContact.php`
- Create: `apps/studio/database/migrations/2026_08_09_000200_create_projects_table.php`, `..._000300_create_project_contacts_table.php`
- Create: `apps/studio/database/factories/Crm/ProjectFactory.php`
- Create: `apps/studio/tests/Feature/Crm/ProjectTest.php`
- Modify: `apps/studio/app/Crm/Models/Contact.php` (add the inverse relation)

**Interfaces:**
- Consumes: `App\Crm\Models\Contact` from Task 2.
- Produces:
  - `ProjectType: string { Wedding, Portrait, Other }` with `label(): string`
  - `ProjectContactRole: string { Primary, Partner, Payer, Planner, Vendor }`
  - `Project` with `$fillable = ['name', 'type', 'portal_visible']`, casts `type => ProjectType`, `portal_visible => bool`; relations `contacts()` (belongsToMany with `role` pivot), `primaryContact()`, `lead()` (Task 4), `shoots()` and `primaryShoot()` (Task 5)
  - `Project::attachContact(Contact $contact, ProjectContactRole $role): void`
  - `ProjectFactory` with a `wedding()` state

- [ ] **Step 1: Write the failing test**

Create `apps/studio/tests/Feature/Crm/ProjectTest.php`:

```php
<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Enums\ProjectType;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('is not visible in the portal until someone says so', function () {
    expect(Project::factory()->create()->portal_visible)->toBeFalse();
});

it('casts its type', function () {
    $project = Project::factory()->create(['type' => ProjectType::Wedding]);

    expect($project->fresh()->type)->toBe(ProjectType::Wedding)
        ->and(ProjectType::Wedding->label())->toBe('Hochzeit');
});

it('attaches contacts with roles', function () {
    $project = Project::factory()->create();
    $she = Contact::factory()->create(['name' => 'Annika Steffens']);
    $he = Contact::factory()->create(['name' => 'Ruben Falk']);

    $project->attachContact($she, ProjectContactRole::Primary);
    $project->attachContact($he, ProjectContactRole::Partner);

    expect($project->contacts()->count())->toBe(2)
        ->and($project->primaryContact()->name)->toBe('Annika Steffens');
});

it('refuses a second primary contact on one project', function () {
    $project = Project::factory()->create();

    $project->attachContact(Contact::factory()->create(), ProjectContactRole::Primary);
    $project->attachContact(Contact::factory()->create(), ProjectContactRole::Primary);
})->throws(QueryException::class);

it('allows the same person to be primary on two projects', function () {
    $contact = Contact::factory()->create();

    Project::factory()->create()->attachContact($contact, ProjectContactRole::Primary);
    Project::factory()->create()->attachContact($contact, ProjectContactRole::Primary);

    expect($contact->projects()->count())->toBe(2);
});

it('refuses the same person twice in the same role on one project', function () {
    $project = Project::factory()->create();
    $contact = Contact::factory()->create();

    $project->attachContact($contact, ProjectContactRole::Payer);
    $project->attachContact($contact, ProjectContactRole::Payer);
})->throws(QueryException::class);
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `docker compose run --rm php ./vendor/bin/pest tests/Feature/Crm/ProjectTest.php`
Expected: FAIL — `Class "App\Crm\Enums\ProjectContactRole" not found`.

- [ ] **Step 3: Write the enums**

Create `apps/studio/app/Crm/Enums/ProjectType.php`:

```php
<?php

namespace App\Crm\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Lives on Project, not Job: mockup 03 collects it at capture, months before
 * a Job exists, and a project's nature does not change at conversion.
 * Deviation from vision §3.1, recorded in the P1b spec §2.2.
 */
#[TypeScript]
enum ProjectType: string
{
    case Wedding = 'wedding';
    case Portrait = 'portrait';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Wedding => 'Hochzeit',
            self::Portrait => 'Portrait',
            self::Other => 'Sonstige',
        };
    }
}
```

Create `apps/studio/app/Crm/Enums/ProjectContactRole.php`:

```php
<?php

namespace App\Crm\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ProjectContactRole: string
{
    case Primary = 'primary';
    case Partner = 'partner';
    case Payer = 'payer';
    case Planner = 'planner';
    case Vendor = 'vendor';

    public function label(): string
    {
        return match ($this) {
            self::Primary => 'Hauptkontakt',
            self::Partner => 'Partner/in',
            self::Payer => 'Zahlt',
            self::Planner => 'Planung',
            self::Vendor => 'Dienstleister',
        };
    }
}
```

- [ ] **Step 4: Write the migrations**

Create `apps/studio/database/migrations/2026_08_09_000200_create_projects_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->string('name');
            $table->string('type');

            // Defaults false so a raw inquiry cannot surface in a client portal
            // the moment it arrives (vision §3.1). Nothing reads it until P2.
            $table->boolean('portal_visible')->default(false);

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
```

Create `apps/studio/database/migrations/2026_08_09_000300_create_project_contacts_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_contacts', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestampsTz();

            $table->unique(['project_id', 'contact_id', 'role']);
        });

        // Every screen reads "the client" off this pivot. Two primaries make
        // that read ambiguous with no error anywhere to notice it.
        DB::statement(
            "create unique index project_contacts_one_primary
             on project_contacts (project_id) where role = 'primary'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('project_contacts');
    }
};
```

- [ ] **Step 5: Write the models and the factory**

Create `apps/studio/app/Crm/Models/Project.php`:

```php
<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Enums\ProjectType;
use Database\Factories\Crm\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * The relationship, from inquiry to archive. Carries no pipeline status: a
 * project whose engagement shoot is delivered while the wedding day is unshot
 * has no single state (vision §3.1). Board position is derived.
 */
class Project extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['name', 'type', 'portal_visible'];

    protected function casts(): array
    {
        return [
            'type' => ProjectType::class,
            'portal_visible' => 'boolean',
        ];
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'project_contacts')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function attachContact(Contact $contact, ProjectContactRole $role): void
    {
        $this->contacts()->attach($contact, [
            'role' => $role->value,
            'tenant_id' => config('tenancy.tenant_id'),
            'public_id' => (string) new \Visus\Cuid2\Cuid2,
        ]);
    }

    public function primaryContact(): ?Contact
    {
        return $this->contacts()
            ->wherePivot('role', ProjectContactRole::Primary->value)
            ->first();
    }

    protected static function newFactory(): Factory
    {
        return ProjectFactory::new();
    }
}
```

> `attachContact` fills `tenant_id` and `public_id` by hand because `attach()` writes through the query builder, which bypasses the model events `BelongsToTenant` and `HasPublicId` hook into. This is the one place in the sprint where the seam is not automatic.

> There is deliberately **no `ProjectContact` model**. Nothing in this sprint queries the pivot as an entity — `withPivot('role')` gives the role on the related contact, and `attachContact` fills the seam columns. Adding a Pivot subclass now would be an interface with one consumer that doesn't exist. Add it in sprint 3 if the project screen needs to edit roles directly.

Create `apps/studio/database/factories/Crm/ProjectFactory.php`:

```php
<?php

namespace Database\Factories\Crm;

use App\Crm\Enums\ProjectType;
use App\Crm\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'name' => fake()->lastName().' — Portrait',
            'type' => ProjectType::Portrait,
        ];
    }

    public function wedding(): static
    {
        return $this->state(fn () => [
            'name' => fake()->lastName().' & '.fake()->lastName().' — Hochzeit',
            'type' => ProjectType::Wedding,
        ]);
    }
}
```

Add the inverse relation to `apps/studio/app/Crm/Models/Contact.php`:

```php
    public function projects(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_contacts')
            ->withPivot('role')
            ->withTimestamps();
    }
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `docker compose run --rm php ./vendor/bin/pest tests/Feature/Crm/ProjectTest.php`
Expected: 6 passed.

- [ ] **Step 7: Run the whole suite and commit**

```bash
docker compose run --rm php ./vendor/bin/pest
git add apps/studio/app/Crm apps/studio/database apps/studio/tests
git commit -m "feat(studio): add projects and typed project contacts"
```

---

# Task 4: Lead

**Files:**
- Create: `apps/studio/app/Crm/Enums/LeadStage.php`, `LeadSource.php`, `CaptureChannel.php`
- Create: `apps/studio/app/Crm/Models/Lead.php`
- Create: `apps/studio/database/migrations/2026_08_09_000400_create_leads_table.php`
- Create: `apps/studio/database/factories/Crm/LeadFactory.php`
- Create: `apps/studio/tests/Feature/Crm/LeadTest.php`
- Modify: `apps/studio/app/Crm/Models/Project.php` (add `lead()`)

**Interfaces:**
- Consumes: `Project` from Task 3, `App\Models\User` from P1a.
- Produces:
  - `LeadStage: string { New, Contacted, QuoteSent, QuoteAccepted, ContractSent, Booked }` with `label(): string`, `position(): int`, `next(): ?self`, `canTransitionTo(self $next): bool`
  - `LeadSource: string { Referral, Website, Instagram, WeddingFair, Other }` with `label()`
  - `CaptureChannel: string { Phone, Email, InPerson, Other }` with `label()`
  - `Lead` with `$fillable = ['project_id','source','stage','stage_entered_at','inquiry_text','captured_via','captured_by_id']`; `isLost(): bool`; relations `project()`, `capturedBy()`
  - `LeadFactory` with `atStage(LeadStage $stage)` and `lost(string $reason)` states

- [ ] **Step 1: Write the failing test**

Create `apps/studio/tests/Feature/Crm/LeadTest.php`:

```php
<?php

use App\Crm\Enums\CaptureChannel;
use App\Crm\Enums\LeadSource;
use App\Crm\Enums\LeadStage;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('starts at the new stage and records when it got there', function () {
    $lead = Lead::factory()->create();

    expect($lead->stage)->toBe(LeadStage::New)
        ->and($lead->stage_entered_at)->not->toBeNull()
        ->and($lead->isLost())->toBeFalse();
});

it('allows only one lead per project', function () {
    $project = Project::factory()->create();

    Lead::factory()->for($project)->create();
    Lead::factory()->for($project)->create();
})->throws(QueryException::class);

it('knows the funnel order', function () {
    expect(LeadStage::New->position())->toBe(0)
        ->and(LeadStage::Booked->position())->toBe(5)
        ->and(LeadStage::New->next())->toBe(LeadStage::Contacted)
        ->and(LeadStage::Booked->next())->toBeNull();
});

it('permits forward transitions and refuses backward ones', function () {
    expect(LeadStage::New->canTransitionTo(LeadStage::Contacted))->toBeTrue()
        // Skipping forward is legitimate: a client accepts on the phone
        // before a formal quote was ever sent.
        ->and(LeadStage::New->canTransitionTo(LeadStage::Booked))->toBeTrue()
        ->and(LeadStage::QuoteSent->canTransitionTo(LeadStage::Contacted))->toBeFalse()
        ->and(LeadStage::Contacted->canTransitionTo(LeadStage::Contacted))->toBeFalse();
});

it('keeps the stage a lead was lost in', function () {
    $lead = Lead::factory()->atStage(LeadStage::QuoteSent)->lost('Budget')->create();

    expect($lead->isLost())->toBeTrue()
        ->and($lead->lost_reason)->toBe('Budget')
        ->and($lead->stage)->toBe(LeadStage::QuoteSent);
});

it('labels its enums in german', function () {
    expect(LeadStage::QuoteAccepted->label())->toBe('Angebot angenommen')
        ->and(LeadSource::WeddingFair->label())->toBe('Hochzeitsmesse')
        ->and(CaptureChannel::Phone->label())->toBe('Telefonisch');
});
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `docker compose run --rm php ./vendor/bin/pest tests/Feature/Crm/LeadTest.php`
Expected: FAIL — `Class "App\Crm\Enums\CaptureChannel" not found`.

- [ ] **Step 3: Write the three enums**

Create `apps/studio/app/Crm/Enums/LeadStage.php`:

```php
<?php

namespace App\Crm\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The sales funnel (vision §7). `quote_accepted` is its own stage because
 * "they said yes, send the contract" is the one funnel moment where the studio
 * is the bottleneck, and folding it into `quote_sent` hides it.
 *
 * Lost is deliberately NOT a case here. It is reachable from any stage, and a
 * seventh value would erase which stage the lead died in — the number the
 * funnel is actually measured on. See Lead::$lost_at.
 */
#[TypeScript]
enum LeadStage: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case QuoteSent = 'quote_sent';
    case QuoteAccepted = 'quote_accepted';
    case ContractSent = 'contract_sent';
    case Booked = 'booked';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Neu',
            self::Contacted => 'Kontaktiert',
            self::QuoteSent => 'Angebot',
            self::QuoteAccepted => 'Angebot angenommen',
            self::ContractSent => 'Vertrag',
            self::Booked => 'Gebucht',
        };
    }

    public function position(): int
    {
        return array_search($this, self::cases(), true);
    }

    public function next(): ?self
    {
        return self::cases()[$this->position() + 1] ?? null;
    }

    /**
     * Forward only, but skipping is allowed: a client can accept on the phone
     * before any formal quote existed, and forcing staff through the
     * intermediate stages would make the funnel a fiction.
     */
    public function canTransitionTo(self $next): bool
    {
        return $next->position() > $this->position();
    }
}
```

Create `apps/studio/app/Crm/Enums/LeadSource.php`:

```php
<?php

namespace App\Crm\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A fixed list rather than free text: it is the only fact that cannot be read
 * back out of the inquiry text later, and its entire value is being countable
 * (mockup 03, line 28).
 */
#[TypeScript]
enum LeadSource: string
{
    case Referral = 'referral';
    case Website = 'website';
    case Instagram = 'instagram';
    case WeddingFair = 'wedding_fair';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Referral => 'Empfehlung',
            self::Website => 'Website',
            self::Instagram => 'Instagram',
            self::WeddingFair => 'Hochzeitsmesse',
            self::Other => 'Sonstige',
        };
    }
}
```

Create `apps/studio/app/Crm/Enums/CaptureChannel.php`:

```php
<?php

namespace App\Crm\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How the inquiry reached the studio — a different question from LeadSource,
 * which is how the client heard about the studio. Both are unrecoverable
 * after the fact, which is why both are stored.
 */
#[TypeScript]
enum CaptureChannel: string
{
    case Phone = 'phone';
    case Email = 'email';
    case InPerson = 'in_person';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Phone => 'Telefonisch',
            self::Email => 'Per E-Mail',
            self::InPerson => 'Persönlich',
            self::Other => 'Sonstige',
        };
    }
}
```

- [ ] **Step 4: Write the migration**

Create `apps/studio/database/migrations/2026_08_09_000400_create_leads_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('source');
            $table->string('stage')->default('new');

            // Stored, not derived: the card's age is time in the CURRENT stage
            // ("8 Tage still" in Kontaktiert vs "vor 2 Std." in Neu), which
            // cannot be computed without a transitions table. One timestamp
            // answers the only question asked of it; the full history lives in
            // audit_logs.
            $table->timestampTz('stage_entered_at');

            $table->timestampTz('lost_at')->nullable();
            $table->string('lost_reason')->nullable();
            $table->text('inquiry_text')->nullable();
            $table->string('captured_via');
            $table->foreignId('captured_by_id')->constrained('users');
            $table->timestampsTz();

            $table->index(['tenant_id', 'stage']);
            $table->index('lost_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
```

- [ ] **Step 5: Write the model and factory**

Create `apps/studio/app/Crm/Models/Lead.php`:

```php
<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Enums\CaptureChannel;
use App\Crm\Enums\LeadSource;
use App\Crm\Enums\LeadStage;
use App\Models\User;
use Database\Factories\Crm\LeadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The pursuit. One per project, created at inquiry.
 */
class Lead extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'project_id',
        'source',
        'stage',
        'stage_entered_at',
        'lost_at',
        'lost_reason',
        'inquiry_text',
        'captured_via',
        'captured_by_id',
    ];

    protected function casts(): array
    {
        return [
            'stage' => LeadStage::class,
            'source' => LeadSource::class,
            'captured_via' => CaptureChannel::class,
            'stage_entered_at' => 'immutable_datetime',
            'lost_at' => 'immutable_datetime',
        ];
    }

    public function isLost(): bool
    {
        return $this->lost_at !== null;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by_id');
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('lost_at');
    }

    protected static function newFactory(): Factory
    {
        return LeadFactory::new();
    }
}
```

Create `apps/studio/database/factories/Crm/LeadFactory.php`:

```php
<?php

namespace Database\Factories\Crm;

use App\Crm\Enums\CaptureChannel;
use App\Crm\Enums\LeadSource;
use App\Crm\Enums\LeadStage;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'source' => fake()->randomElement(LeadSource::cases()),
            'stage' => LeadStage::New,
            'stage_entered_at' => now(),
            'inquiry_text' => fake()->paragraph(),
            'captured_via' => CaptureChannel::Phone,
            'captured_by_id' => User::factory(),
        ];
    }

    public function atStage(LeadStage $stage, ?int $daysAgo = null): static
    {
        return $this->state(fn () => [
            'stage' => $stage,
            'stage_entered_at' => now()->subDays($daysAgo ?? 0),
        ]);
    }

    public function lost(string $reason = 'Budget'): static
    {
        return $this->state(fn () => [
            'lost_at' => now(),
            'lost_reason' => $reason,
        ]);
    }
}
```

Add to `apps/studio/app/Crm/Models/Project.php`:

```php
    public function lead(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Lead::class);
    }
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `docker compose run --rm php ./vendor/bin/pest tests/Feature/Crm/LeadTest.php`
Expected: 6 passed.

- [ ] **Step 7: Run the whole suite and commit**

```bash
docker compose run --rm php ./vendor/bin/pest
git add apps/studio/app/Crm apps/studio/database apps/studio/tests
git commit -m "feat(studio): add the lead funnel with a stage entry timestamp"
```

---

# Task 5: Shoot

**Files:**
- Create: `apps/studio/app/Crm/Enums/ShootKind.php`, `ShootStatus.php`
- Create: `apps/studio/app/Crm/Models/Shoot.php`
- Create: `apps/studio/database/migrations/2026_08_09_000500_create_shoots_table.php`
- Create: `apps/studio/database/factories/Crm/ShootFactory.php`
- Create: `apps/studio/tests/Feature/Crm/ShootTest.php`
- Modify: `apps/studio/app/Crm/Models/Project.php` (add `shoots()` and `primaryShoot()`)

**Interfaces:**
- Consumes: `Project` from Task 3.
- Produces:
  - `ShootKind: string { Engagement, Wedding, Reception, Portrait, Other }` with `label()`
  - `ShootStatus: string { Requested, Booked, Prep, Shot, Culling, Editing, Ready, Delivered, Archived }` with `label()` — transitions arrive in sprint 3 (P-03); this sprint only ever writes `Requested`
  - `Shoot` with `$fillable = ['project_id','kind','label','date','call_time','end_time','venue_name','venue_city','status','is_primary','sort_order']`, casts `date => 'immutable_date'`, `is_primary => bool`; `venue(): ?string` joining name and city
  - `Project::primaryShoot()` (hasOne, `where is_primary`)
  - `ShootFactory` with `primary()` and `on(string $date)` states

> `label` is both a column and a method name on other models here. On `Shoot` it is a column (the human name of the occasion, e.g. "Freie Trauung"), and the enums carry the `label()` methods. Do not add a `label()` method to `Shoot`.

- [ ] **Step 1: Write the failing test**

Create `apps/studio/tests/Feature/Crm/ShootTest.php`:

```php
<?php

use App\Crm\Enums\ShootStatus;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('starts as requested', function () {
    expect(Shoot::factory()->create()->status)->toBe(ShootStatus::Requested);
});

it('stores the date as a date and the times as times', function () {
    $shoot = Shoot::factory()->create([
        'date' => '2026-06-14',
        'call_time' => '13:30',
        'end_time' => '23:00',
    ]);

    $raw = DB::table('shoots')->where('id', $shoot->id)->first();

    expect($raw->date)->toBe('2026-06-14')
        ->and($shoot->fresh()->date->format('d.m.Y'))->toBe('14.06.2026')
        ->and($raw->call_time)->toStartWith('13:30');
});

it('allows a shoot with no date yet', function () {
    expect(Shoot::factory()->create(['date' => null])->date)->toBeNull();
});

it('refuses a second primary shoot on one project', function () {
    $project = Project::factory()->create();

    Shoot::factory()->for($project)->primary()->create();
    Shoot::factory()->for($project)->primary()->create();
})->throws(QueryException::class);

it('allows any number of non-primary shoots on one project', function () {
    $project = Project::factory()->create();

    Shoot::factory()->for($project)->primary()->create();
    Shoot::factory()->for($project)->count(3)->create();

    expect($project->shoots()->count())->toBe(4)
        ->and($project->primaryShoot->is_primary)->toBeTrue();
});

it('renders a venue only when it has one', function () {
    $withVenue = Shoot::factory()->create(['venue_name' => 'Gut Basthorst', 'venue_city' => 'Basthorst']);
    $without = Shoot::factory()->create(['venue_name' => null, 'venue_city' => null]);

    expect($withVenue->venue())->toBe('Gut Basthorst, Basthorst')
        ->and($without->venue())->toBeNull();
});
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `docker compose run --rm php ./vendor/bin/pest tests/Feature/Crm/ShootTest.php`
Expected: FAIL — `Class "App\Crm\Models\Shoot" not found`.

- [ ] **Step 3: Write the enums**

Create `apps/studio/app/Crm/Enums/ShootKind.php`:

```php
<?php

namespace App\Crm\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ShootKind: string
{
    case Engagement = 'engagement';
    case Wedding = 'wedding';
    case Reception = 'reception';
    case Portrait = 'portrait';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Engagement => 'Verlobungsshooting',
            self::Wedding => 'Hochzeitstag',
            self::Reception => 'Feier',
            self::Portrait => 'Portrait',
            self::Other => 'Sonstige',
        };
    }
}
```

Create `apps/studio/app/Crm/Enums/ShootStatus.php`:

```php
<?php

namespace App\Crm\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The forward-only production pipeline (vision §7). The full enum exists now
 * so the column never has to be widened, but sprint 2 only ever writes
 * Requested — the transition machinery is P-03, in sprint 3.
 */
#[TypeScript]
enum ShootStatus: string
{
    case Requested = 'requested';
    case Booked = 'booked';
    case Prep = 'prep';
    case Shot = 'shot';
    case Culling = 'culling';
    case Editing = 'editing';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Angefragt',
            self::Booked => 'Gebucht',
            self::Prep => 'Vorbereitung',
            self::Shot => 'Fotografiert',
            self::Culling => 'Auswahl',
            self::Editing => 'Bearbeitung',
            self::Ready => 'Fertig',
            self::Delivered => 'Geliefert',
            self::Archived => 'Archiviert',
        };
    }
}
```

- [ ] **Step 4: Write the migration**

Create `apps/studio/database/migrations/2026_08_09_000500_create_shoots_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shoots', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('label')->nullable();

            // A date column, never a timestamp: a shooting day is a calendar
            // day and must not shift with a timezone (vision §8). Nullable
            // because a portrait lead legitimately has no date yet ("Termin
            // offen", mockup 02) — and a required date would force staff to
            // invent one, corrupting the column the availability check reads.
            $table->date('date')->nullable();
            $table->time('call_time')->nullable();
            $table->time('end_time')->nullable();

            $table->string('venue_name')->nullable();
            $table->string('venue_city')->nullable();
            $table->string('status')->default('requested');
            $table->boolean('is_primary')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestampsTz();

            // The availability scan: one indexed lookup per date.
            $table->index(['tenant_id', 'date']);
        });

        // Vision §3.1: exactly one shoot per project is primary. Set at intake,
        // never derived — the engagement shoot is the earliest date and is
        // never the headline occasion.
        DB::statement(
            'create unique index shoots_one_primary
             on shoots (project_id) where is_primary'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('shoots');
    }
};
```

- [ ] **Step 5: Write the model and factory**

Create `apps/studio/app/Crm/Models/Shoot.php`:

```php
<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Enums\ShootKind;
use App\Crm\Enums\ShootStatus;
use Database\Factories\Crm\ShootFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One shooting occasion. The production unit: a date, a place, a crew and its
 * own pipeline. Hangs off Project rather than Job so a requested date and a
 * booked date are the same row in two states (vision §3.1).
 */
class Shoot extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'project_id',
        'kind',
        'label',
        'date',
        'call_time',
        'end_time',
        'venue_name',
        'venue_city',
        'status',
        'is_primary',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'kind' => ShootKind::class,
            'status' => ShootStatus::class,
            'date' => 'immutable_date',
            'is_primary' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function venue(): ?string
    {
        $parts = array_filter([$this->venue_name, $this->venue_city]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    protected static function newFactory(): Factory
    {
        return ShootFactory::new();
    }
}
```

Create `apps/studio/database/factories/Crm/ShootFactory.php`:

```php
<?php

namespace Database\Factories\Crm;

use App\Crm\Enums\ShootKind;
use App\Crm\Enums\ShootStatus;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shoot>
 */
class ShootFactory extends Factory
{
    protected $model = Shoot::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'kind' => ShootKind::Portrait,
            'date' => fake()->dateTimeBetween('+1 month', '+18 months')->format('Y-m-d'),
            'status' => ShootStatus::Requested,
            'is_primary' => false,
            'sort_order' => 0,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }

    public function on(?string $date): static
    {
        return $this->state(fn () => ['date' => $date]);
    }
}
```

Add to `apps/studio/app/Crm/Models/Project.php`:

```php
    public function shoots(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Shoot::class)->orderBy('sort_order');
    }

    public function primaryShoot(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Shoot::class)->where('is_primary', true);
    }
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `docker compose run --rm php ./vendor/bin/pest tests/Feature/Crm/ShootTest.php`
Expected: 6 passed.

- [ ] **Step 7: Run the whole suite and commit**

```bash
docker compose run --rm php ./vendor/bin/pest
git add apps/studio/app/Crm apps/studio/database apps/studio/tests
git commit -m "feat(studio): add shoots with one primary per project"
```

---

# Task 6: Stage transitions

**Files:**
- Create: `apps/studio/app/Crm/Actions/AdvanceLeadStage.php`
- Create: `apps/studio/app/Crm/Events/LeadStageChanged.php`, `LeadLost.php`, `LeadCaptured.php`
- Create: `apps/studio/tests/Feature/Crm/LeadStageTest.php`

**Interfaces:**
- Consumes: `Lead`, `LeadStage` from Task 4; `App\Support\AuditLogger` from P1a.
- Produces:
  - `AdvanceLeadStage::__invoke(Lead $lead, LeadStage $next, ?User $actor = null): Lead` — throws `DomainException` on an illegal transition or on a lost lead
  - `AdvanceLeadStage::markLost(Lead $lead, string $reason, ?User $actor = null): Lead`
  - Events `LeadCaptured(Lead $lead)`, `LeadStageChanged(Lead $lead, LeadStage $from, LeadStage $to)`, `LeadLost(Lead $lead, string $reason)`
  - Audit events `lead.stage_changed` and `lead.lost`

- [ ] **Step 1: Write the failing test**

Create `apps/studio/tests/Feature/Crm/LeadStageTest.php`:

```php
<?php

use App\Crm\Actions\AdvanceLeadStage;
use App\Crm\Enums\LeadStage;
use App\Crm\Events\LeadLost;
use App\Crm\Events\LeadStageChanged;
use App\Crm\Models\Lead;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('advances a lead and restamps the stage entry time', function () {
    $lead = Lead::factory()->atStage(LeadStage::New, daysAgo: 9)->create();

    $advanced = app(AdvanceLeadStage::class)($lead, LeadStage::Contacted);

    expect($advanced->stage)->toBe(LeadStage::Contacted)
        ->and($advanced->stage_entered_at->isToday())->toBeTrue();
});

it('emits an event carrying both stages', function () {
    Event::fake([LeadStageChanged::class]);
    $lead = Lead::factory()->create();

    app(AdvanceLeadStage::class)($lead, LeadStage::QuoteSent);

    Event::assertDispatched(LeadStageChanged::class, fn ($e) => $e->from === LeadStage::New
        && $e->to === LeadStage::QuoteSent
        && $e->lead->is($lead));
});

it('writes an audit row naming the transition', function () {
    $actor = User::factory()->create();
    $lead = Lead::factory()->create();

    app(AdvanceLeadStage::class)($lead, LeadStage::Contacted, $actor);

    $row = AuditLog::where('event', 'lead.stage_changed')->sole();

    expect($row->actor_id)->toBe($actor->id)
        ->and($row->subject)->toBe($lead->public_id)
        ->and($row->context['from'])->toBe('new')
        ->and($row->context['to'])->toBe('contacted');
});

it('refuses a backward transition', function () {
    $lead = Lead::factory()->atStage(LeadStage::QuoteSent)->create();

    app(AdvanceLeadStage::class)($lead, LeadStage::New);
})->throws(DomainException::class);

it('refuses to advance a lost lead', function () {
    $lead = Lead::factory()->lost('Budget')->create();

    app(AdvanceLeadStage::class)($lead, LeadStage::Contacted);
})->throws(DomainException::class);

it('marks a lead lost from any stage without changing the stage', function () {
    Event::fake([LeadLost::class]);
    $lead = Lead::factory()->atStage(LeadStage::ContractSent)->create();

    $lost = app(AdvanceLeadStage::class)->markLost($lead, 'Anderer Fotograf');

    expect($lost->isLost())->toBeTrue()
        ->and($lost->stage)->toBe(LeadStage::ContractSent)
        ->and($lost->lost_reason)->toBe('Anderer Fotograf');

    Event::assertDispatched(LeadLost::class);
});
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `docker compose run --rm php ./vendor/bin/pest tests/Feature/Crm/LeadStageTest.php`
Expected: FAIL — `Class "App\Crm\Actions\AdvanceLeadStage" not found`.

- [ ] **Step 3: Write the three events**

Create `apps/studio/app/Crm/Events/LeadCaptured.php`:

```php
<?php

namespace App\Crm\Events;

use App\Crm\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;

class LeadCaptured
{
    use Dispatchable;

    public function __construct(public readonly Lead $lead) {}
}
```

Create `apps/studio/app/Crm/Events/LeadStageChanged.php`:

```php
<?php

namespace App\Crm\Events;

use App\Crm\Enums\LeadStage;
use App\Crm\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;

class LeadStageChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Lead $lead,
        public readonly LeadStage $from,
        public readonly LeadStage $to,
    ) {}
}
```

Create `apps/studio/app/Crm/Events/LeadLost.php`:

```php
<?php

namespace App\Crm\Events;

use App\Crm\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;

class LeadLost
{
    use Dispatchable;

    public function __construct(
        public readonly Lead $lead,
        public readonly string $reason,
    ) {}
}
```

- [ ] **Step 4: Write the action**

Create `apps/studio/app/Crm/Actions/AdvanceLeadStage.php`:

```php
<?php

namespace App\Crm\Actions;

use App\Crm\Enums\LeadStage;
use App\Crm\Events\LeadLost;
use App\Crm\Events\LeadStageChanged;
use App\Crm\Models\Lead;
use App\Models\User;
use App\Support\AuditLogger;
use DomainException;

/**
 * The only writer of Lead.stage. No transitions table: the ledger is
 * audit_logs, which P1a already made append-only, and stage_entered_at holds
 * the one derived fact the board needs.
 */
class AdvanceLeadStage
{
    public function __invoke(Lead $lead, LeadStage $next, ?User $actor = null): Lead
    {
        if ($lead->isLost()) {
            throw new DomainException("Lead {$lead->public_id} is lost and cannot advance.");
        }

        $from = $lead->stage;

        if (! $from->canTransitionTo($next)) {
            throw new DomainException(
                "Illegal lead transition {$from->value} -> {$next->value}."
            );
        }

        $lead->forceFill([
            'stage' => $next,
            'stage_entered_at' => now(),
        ])->save();

        AuditLogger::record(
            event: 'lead.stage_changed',
            actor: $actor ?? auth('staff')->user(),
            context: ['from' => $from->value, 'to' => $next->value],
            subject: $lead->public_id,
        );

        LeadStageChanged::dispatch($lead, $from, $next);

        return $lead;
    }

    public function markLost(Lead $lead, string $reason, ?User $actor = null): Lead
    {
        if ($lead->isLost()) {
            throw new DomainException("Lead {$lead->public_id} is already lost.");
        }

        // The stage is deliberately left alone: which stage a lead died in is
        // the number the funnel is measured on.
        $lead->forceFill([
            'lost_at' => now(),
            'lost_reason' => $reason,
        ])->save();

        AuditLogger::record(
            event: 'lead.lost',
            actor: $actor ?? auth('staff')->user(),
            context: ['stage' => $lead->stage->value, 'reason' => $reason],
            subject: $lead->public_id,
        );

        LeadLost::dispatch($lead, $reason);

        return $lead;
    }
}
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `docker compose run --rm php ./vendor/bin/pest tests/Feature/Crm/LeadStageTest.php`
Expected: 6 passed.

- [ ] **Step 6: Run the whole suite and commit**

```bash
docker compose run --rm php ./vendor/bin/pest
git add apps/studio/app/Crm apps/studio/tests
git commit -m "feat(studio): guard lead stage transitions and audit every one"
```

---

# Task 7: Contact search and date availability

**Files:**
- Create: `apps/studio/app/Crm/Actions/CheckDateAvailability.php`
- Create: `apps/studio/app/Crm/Data/DateConflictData.php`, `ContactSuggestionData.php`
- Create: `apps/studio/app/Crm/Http/Controllers/ContactSearchController.php`, `AvailabilityController.php`
- Create: `apps/studio/tests/Feature/Crm/AvailabilityTest.php`, `ContactSearchTest.php`
- Modify: `apps/studio/routes/admin.php`

**Interfaces:**
- Consumes: `Contact`, `Project`, `Lead`, `Shoot` from Tasks 2–5.
- Produces:
  - `DateConflictData { string $projectId; string $projectName; ?string $venue; string $kind; }`
  - `ContactSuggestionData { string $id; string $name; ?string $email; ?string $phone; int $projectCount; }`
  - `CheckDateAvailability::forDate(?string $date, ?Project $except = null): array` — list of `DateConflictData`, empty when `$date` is null
  - `CheckDateAvailability::forDates(array $dates, ?Project $except = null): array` — `Y-m-d` ⇒ list of `DateConflictData`, **one query regardless of how many dates**
  - Routes `GET /kontakte/suche` (`contacts.search`) and `GET /verfuegbarkeit` (`availability.show`), both JSON, both `audience:staff` + `mfa`

- [ ] **Step 1: Write the failing tests**

Create `apps/studio/tests/Feature/Crm/AvailabilityTest.php`:

```php
<?php

use App\Crm\Actions\CheckDateAvailability;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function bookedOn(string $date, string $name = 'Meier & Kowalski'): Project
{
    $project = Project::factory()->wedding()->create(['name' => $name]);
    Lead::factory()->for($project)->create();
    Shoot::factory()->for($project)->primary()->on($date)->create([
        'venue_name' => 'Gut Basthorst',
        'venue_city' => 'Basthorst',
    ]);

    return $project;
}

it('reports nothing for a free date', function () {
    bookedOn('2026-06-14');

    expect(app(CheckDateAvailability::class)->forDate('2026-06-20'))->toBe([]);
});

it('reports the colliding project with its name and venue', function () {
    bookedOn('2026-06-14');

    $conflicts = app(CheckDateAvailability::class)->forDate('2026-06-14');

    expect($conflicts)->toHaveCount(1)
        ->and($conflicts[0]->projectName)->toBe('Meier & Kowalski')
        ->and($conflicts[0]->venue)->toBe('Gut Basthorst, Basthorst')
        ->and($conflicts[0]->projectId)->toHaveLength(24);
});

it('ignores lost leads', function () {
    $project = bookedOn('2026-06-14');
    $project->lead->forceFill(['lost_at' => now(), 'lost_reason' => 'Budget'])->save();

    expect(app(CheckDateAvailability::class)->forDate('2026-06-14'))->toBe([]);
});

it('reports nothing when there is no date', function () {
    bookedOn('2026-06-14');

    expect(app(CheckDateAvailability::class)->forDate(null))->toBe([]);
});

it('excludes the project being checked', function () {
    $project = bookedOn('2026-06-14');

    expect(app(CheckDateAvailability::class)->forDate('2026-06-14', $project))->toBe([]);
});

it('does not query once per date', function () {
    bookedOn('2026-06-14');
    bookedOn('2026-08-22', 'Vehse & Rieck');

    DB::enableQueryLog();
    $result = app(CheckDateAvailability::class)->forDates([
        '2026-06-14', '2026-08-22', '2026-09-05', '2026-03-14',
    ]);
    $queries = DB::getRawQueryLog();
    DB::disableQueryLog();

    // Two, regardless of how many dates: the shoots query, plus the eager load
    // of their projects. Four dates must not mean four lookups.
    expect($queries)->toHaveCount(2)
        ->and($result['2026-06-14'])->toHaveCount(1)
        ->and($result['2026-08-22'])->toHaveCount(1)
        ->and($result)->not->toHaveKey('2026-09-05');
});

it('serves the availability endpoint to a signed-in staff user', function () {
    bookedOn('2026-06-14');
    $user = User::factory()->withMfa()->create();

    $this->actingAs($user, 'staff')
        ->getJson('/verfuegbarkeit?datum=2026-06-14')
        ->assertOk()
        ->assertJsonCount(1, 'conflicts')
        ->assertJsonPath('conflicts.0.projectName', 'Meier & Kowalski');
});

it('rejects an unauthenticated availability check', function () {
    $this->get('/verfuegbarkeit?datum=2026-06-14')->assertRedirect('/login');
});
```

Create `apps/studio/tests/Feature/Crm/ContactSearchTest.php`:

```php
<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
});

it('matches on a name fragment, case insensitively', function () {
    Contact::factory()->create(['name' => 'Steffi Wehrmann']);
    Contact::factory()->create(['name' => 'Annika Steffens']);
    Contact::factory()->create(['name' => 'Paul Rieck']);

    $this->actingAs($this->staff, 'staff')
        ->getJson('/kontakte/suche?q=stef')
        ->assertOk()
        ->assertJsonCount(2, 'contacts');
});

it('matches on an email fragment', function () {
    Contact::factory()->create(['name' => 'Annika Steffens', 'email' => 'a.steffens@posteo.de']);

    $this->actingAs($this->staff, 'staff')
        ->getJson('/kontakte/suche?q=posteo')
        ->assertOk()
        ->assertJsonPath('contacts.0.name', 'Annika Steffens');
});

it('reports how many projects a contact already has', function () {
    $contact = Contact::factory()->create(['name' => 'Annika Steffens']);
    Project::factory()->create()->attachContact($contact, ProjectContactRole::Primary);

    $this->actingAs($this->staff, 'staff')
        ->getJson('/kontakte/suche?q=annika')
        ->assertOk()
        ->assertJsonPath('contacts.0.projectCount', 1);
});

it('exposes the public id and never the primary key', function () {
    $contact = Contact::factory()->create(['name' => 'Annika Steffens']);

    $this->actingAs($this->staff, 'staff')
        ->getJson('/kontakte/suche?q=annika')
        ->assertOk()
        ->assertJsonPath('contacts.0.id', $contact->public_id);
});

it('returns nothing for a query shorter than two characters', function () {
    Contact::factory()->create(['name' => 'Annika Steffens']);

    $this->actingAs($this->staff, 'staff')
        ->getJson('/kontakte/suche?q=a')
        ->assertOk()
        ->assertJsonCount(0, 'contacts');
});

it('rejects an unauthenticated search', function () {
    $this->get('/kontakte/suche?q=annika')->assertRedirect('/login');
});
```

- [ ] **Step 2: Run them to make sure they fail**

Run: `docker compose run --rm php ./vendor/bin/pest tests/Feature/Crm/AvailabilityTest.php tests/Feature/Crm/ContactSearchTest.php`
Expected: FAIL — `Class "App\Crm\Actions\CheckDateAvailability" not found`.

- [ ] **Step 3: Write the Data objects**

Create `apps/studio/app/Crm/Data/DateConflictData.php`:

```php
<?php

namespace App\Crm\Data;

use App\Crm\Models\Shoot;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One other shoot already on a date. Never persisted anywhere — see the P1b
 * spec §5.2 for why a stored has_conflict flag would be a lie with nothing to
 * clear it.
 */
#[TypeScript]
class DateConflictData extends Data
{
    public function __construct(
        public string $projectId,
        public string $projectName,
        public ?string $venue,
        public string $kind,
    ) {}

    public static function fromShoot(Shoot $shoot): self
    {
        return new self(
            projectId: $shoot->project->public_id,
            projectName: $shoot->project->name,
            venue: $shoot->venue(),
            kind: $shoot->kind->label(),
        );
    }
}
```

Create `apps/studio/app/Crm/Data/ContactSuggestionData.php`:

```php
<?php

namespace App\Crm\Data;

use App\Crm\Models\Contact;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ContactSuggestionData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $email,
        public ?string $phone,
        public int $projectCount,
    ) {}

    public static function fromModel(Contact $contact): self
    {
        return new self(
            id: $contact->public_id,
            name: $contact->name,
            email: $contact->email,
            phone: $contact->phone,
            // projects_count comes from withCount() on the query — never
            // lazy-loaded here, or the typeahead becomes an N+1.
            projectCount: (int) ($contact->projects_count ?? 0),
        );
    }
}
```

- [ ] **Step 4: Write the availability action**

Create `apps/studio/app/Crm/Actions/CheckDateAvailability.php`:

```php
<?php

namespace App\Crm\Actions;

use App\Crm\Data\DateConflictData;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;

/**
 * Vision §7: availability scans shoots.date for projects whose lead is not
 * lost. Joining through leads is what expresses "not lost" — a booked project
 * still has its lead, at stage booked.
 *
 * This warns; it never blocks. The check answers "is somebody already on this
 * day", which is not the question "may this be saved" (mockup 03, lines 31-37).
 */
class CheckDateAvailability
{
    /** @return list<DateConflictData> */
    public function forDate(?string $date, ?Project $except = null): array
    {
        if ($date === null) {
            return [];
        }

        return $this->forDates([$date], $except)[$date] ?? [];
    }

    /**
     * @param  list<string>  $dates  Y-m-d
     * @return array<string, list<DateConflictData>>  only dates with conflicts
     */
    public function forDates(array $dates, ?Project $except = null): array
    {
        $dates = array_values(array_unique(array_filter($dates)));

        if ($dates === []) {
            return [];
        }

        // One query for the whole board. The per-card version of this is the
        // obvious way to write it and is an N+1 nobody notices at eleven cards.
        $shoots = Shoot::query()
            ->with('project')
            ->whereIn('date', $dates)
            ->whereHas('project.lead', fn ($lead) => $lead->whereNull('lost_at'))
            ->when($except, fn ($query) => $query->where('project_id', '!=', $except->id))
            ->get();

        return $shoots
            ->groupBy(fn (Shoot $shoot) => $shoot->date->format('Y-m-d'))
            ->map(fn ($group) => $group->map(DateConflictData::fromShoot(...))->values()->all())
            ->all();
    }
}
```

- [ ] **Step 5: Write the two controllers**

Create `apps/studio/app/Crm/Http/Controllers/AvailabilityController.php`:

```php
<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Actions\CheckDateAvailability;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function __invoke(Request $request, CheckDateAvailability $availability): JsonResponse
    {
        $validated = $request->validate([
            'datum' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return response()->json([
            'conflicts' => $availability->forDate($validated['datum'] ?? null),
        ]);
    }
}
```

Create `apps/studio/app/Crm/Http/Controllers/ContactSearchController.php`:

```php
<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Data\ContactSuggestionData;
use App\Crm\Models\Contact;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Feeds the typeahead on the capture form. The ordering it enforces —
 * search before you type — is the anti-duplicate mechanism: ClientAccess
 * hangs off Contact, so a repeat client must resolve to the same row.
 */
class ContactSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['contacts' => []]);
        }

        $contacts = Contact::query()
            ->withCount('projects')
            ->where(fn ($query) => $query
                ->where('name', 'ilike', "%{$term}%")
                ->orWhere('email', 'ilike', "%{$term}%"))
            ->orderBy('name')
            ->limit(8)
            ->get();

        return response()->json([
            'contacts' => ContactSuggestionData::collect($contacts),
        ]);
    }
}
```

> `ilike` is Postgres-specific and intentional. `where('name', 'like', ...)` is case-sensitive on Postgres, which would make the typeahead miss `Steffens` when someone types `steff` — and a typeahead that misses an existing contact creates the duplicate it exists to prevent.

- [ ] **Step 6: Register the routes**

In `apps/studio/routes/admin.php`, inside the existing `Route::middleware('mfa')->group(...)`, add:

```php
        Route::get('/kontakte/suche', ContactSearchController::class)->name('contacts.search');
        Route::get('/verfuegbarkeit', AvailabilityController::class)->name('availability.show');
```

with the matching imports at the top:

```php
use App\Crm\Http\Controllers\AvailabilityController;
use App\Crm\Http\Controllers\ContactSearchController;
```

> `/kontakte/suche` must be registered **before** any `/kontakte/{contact}` route (Task 10), or Laravel binds `suche` as a contact public id and returns 404.

- [ ] **Step 7: Run the tests to verify they pass**

Run: `docker compose run --rm php ./vendor/bin/pest tests/Feature/Crm/AvailabilityTest.php tests/Feature/Crm/ContactSearchTest.php`
Expected: 8 + 6 passed.

- [ ] **Step 8: Regenerate the TypeScript types**

Run: `docker compose run --rm php php artisan typescript:transform`

`resources/js/types/generated.d.ts` should gain `App.Crm.Data.DateConflictData`, `App.Crm.Data.ContactSuggestionData` and the enums. Commit the regenerated file — CI fails if it is stale.

- [ ] **Step 9: Run the whole suite and commit**

```bash
docker compose run --rm php ./vendor/bin/pest
git add apps/studio/app/Crm apps/studio/routes apps/studio/tests apps/studio/resources/js/types
git commit -m "feat(studio): derive date conflicts and search contacts by fragment"
```

---

# Task 8: The capture form

**Files:**
- Create: `apps/studio/app/Crm/Data/CaptureInquiryData.php`
- Create: `apps/studio/app/Crm/Actions/CaptureInquiry.php`
- Create: `apps/studio/app/Crm/Http/Controllers/InquiryCaptureController.php`
- Create: `apps/studio/resources/js/admin/pages/Leads/Create.vue`
- Create: `apps/studio/resources/js/admin/components/ContactTypeahead.vue`, `ConflictNote.vue`
- Create: `apps/studio/tests/Feature/Crm/CaptureInquiryTest.php`
- Create: `apps/studio/tests/e2e/capture.spec.ts`
- Modify: `apps/studio/routes/admin.php`, `apps/studio/resources/js/locales/de.json`, `apps/studio/database/seeders/E2eSeeder.php`

**Interfaces:**
- Consumes: everything from Tasks 2–7.
- Produces:
  - `CaptureInquiryData` — the validated request payload
  - `CaptureInquiry::__invoke(CaptureInquiryData $data, User $actor): Project`
  - Routes `GET /anfragen/neu` (`leads.create`) and `POST /anfragen` (`leads.store`)
  - Catalog namespace `leads.*` in `de.json`

- [ ] **Step 1: Write the failing test**

Create `apps/studio/tests/Feature/Crm/CaptureInquiryTest.php`:

```php
<?php

use App\Crm\Enums\CaptureChannel;
use App\Crm\Enums\LeadSource;
use App\Crm\Enums\LeadStage;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Enums\ProjectType;
use App\Crm\Enums\ShootStatus;
use App\Crm\Actions\CaptureInquiry;
use App\Crm\Data\CaptureInquiryData;
use App\Crm\Events\LeadCaptured;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
});

function payload(array $overrides = []): array
{
    return array_merge([
        'contact_name' => 'Annika Steffens',
        'contact_email' => 'a.steffens@posteo.de',
        'contact_phone' => '0171 3948820',
        'partner_name' => 'Ruben Falk',
        'project_name' => 'Steffens & Falk — Hochzeit',
        'type' => ProjectType::Wedding->value,
        'source' => LeadSource::Referral->value,
        'captured_via' => CaptureChannel::Phone->value,
        'date' => '2026-06-14',
        'inquiry_text' => 'Wir heiraten am 14. Juni 2026 auf Gut Basthorst.',
    ], $overrides);
}

it('creates the whole graph from one post', function () {
    $this->actingAs($this->staff, 'staff')
        ->post('/anfragen', payload())
        ->assertRedirect();

    $project = Project::sole();
    $lead = Lead::sole();
    $shoot = Shoot::sole();

    expect($project->name)->toBe('Steffens & Falk — Hochzeit')
        ->and($project->type)->toBe(ProjectType::Wedding)
        ->and($project->portal_visible)->toBeFalse()
        ->and($lead->stage)->toBe(LeadStage::New)
        ->and($lead->captured_by_id)->toBe($this->staff->id)
        ->and($lead->project_id)->toBe($project->id)
        ->and($shoot->is_primary)->toBeTrue()
        ->and($shoot->status)->toBe(ShootStatus::Requested)
        ->and($shoot->date->format('Y-m-d'))->toBe('2026-06-14');
});

it('creates the partner as a second contact with the partner role', function () {
    $this->actingAs($this->staff, 'staff')->post('/anfragen', payload());

    $project = Project::sole();

    expect(Contact::count())->toBe(2)
        ->and($project->primaryContact()->name)->toBe('Annika Steffens')
        ->and($project->contacts()->wherePivot('role', ProjectContactRole::Partner->value)->first()->name)
        ->toBe('Ruben Falk');
});

it('reuses an existing contact rather than creating a second one', function () {
    $existing = Contact::factory()->create(['name' => 'Annika Steffens', 'email' => 'a.steffens@posteo.de']);

    $this->actingAs($this->staff, 'staff')
        ->post('/anfragen', payload(['contact_id' => $existing->public_id]));

    expect(Contact::where('name', 'Annika Steffens')->count())->toBe(1)
        ->and(Project::sole()->primaryContact()->id)->toBe($existing->id);
});

it('saves without a partner', function () {
    $this->actingAs($this->staff, 'staff')
        ->post('/anfragen', payload(['partner_name' => null]))
        ->assertRedirect();

    expect(Contact::count())->toBe(1);
});

it('saves without a date', function () {
    $this->actingAs($this->staff, 'staff')
        ->post('/anfragen', payload(['date' => null]))
        ->assertRedirect();

    expect(Shoot::sole()->date)->toBeNull();
});

it('saves without an email, because a contact without one is honest', function () {
    $this->actingAs($this->staff, 'staff')
        ->post('/anfragen', payload(['contact_email' => null]))
        ->assertRedirect();

    expect(Contact::sole()->email)->toBeNull();
});

it('warns about a conflicting date but saves anyway', function () {
    $other = Project::factory()->wedding()->create(['name' => 'Meier & Kowalski']);
    Lead::factory()->for($other)->create();
    Shoot::factory()->for($other)->primary()->on('2026-06-14')->create();

    $this->actingAs($this->staff, 'staff')
        ->post('/anfragen', payload())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Project::count())->toBe(2)
        ->and(Shoot::where('date', '2026-06-14')->count())->toBe(2);
});

it('creates nothing when validation fails', function () {
    $this->actingAs($this->staff, 'staff')
        ->post('/anfragen', payload(['type' => 'not-a-type']))
        ->assertSessionHasErrors('type');

    expect(Project::count())->toBe(0)
        ->and(Contact::count())->toBe(0);
});

it('rolls the whole graph back if any part fails mid-transaction', function () {
    // LeadCaptured fires inside the transaction, after every row is written —
    // the only cheap place to prove the rollback is real rather than proving
    // that validation runs first.
    Event::listen(LeadCaptured::class, fn () => throw new RuntimeException('boom'));

    expect(fn () => app(CaptureInquiry::class)(
        CaptureInquiryData::from(payload()),
        $this->staff,
    ))->toThrow(RuntimeException::class);

    expect(Project::count())->toBe(0)
        ->and(Contact::count())->toBe(0)
        ->and(Lead::count())->toBe(0)
        ->and(Shoot::count())->toBe(0);
});

it('requires a contact name when no existing contact was chosen', function () {
    $this->actingAs($this->staff, 'staff')
        ->post('/anfragen', payload(['contact_name' => null, 'contact_id' => null]))
        ->assertSessionHasErrors('contact_name');
});

it('renders the capture page with its options and no integer ids', function () {
    $props = $this->actingAs($this->staff, 'staff')
        ->get('/anfragen/neu')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['types'])->toHaveCount(3)
        ->and($props['sources'])->toHaveCount(5)
        ->and($props['channels'])->toHaveCount(4)
        ->and(integerIdPaths($props))->toBe([]);
});
```

> **Before running this**, move the `integerIdPaths()` helper out of `tests/Feature/NoInternalIdsTest.php` and into `tests/Pest.php` (the "Functions" section at the bottom, replacing the placeholder `something()`), leaving the comment with it. It is now used by three test files, and a global function defined in one test file is only available to another if Pest happens to load them in the right order — which is not a property to depend on.

- [ ] **Step 2: Run it to make sure it fails**

Run: `docker compose run --rm php ./vendor/bin/pest tests/Feature/Crm/CaptureInquiryTest.php`
Expected: FAIL — 404, the route does not exist.

- [ ] **Step 3: Write the request Data object**

Create `apps/studio/app/Crm/Data/CaptureInquiryData.php`:

```php
<?php

namespace App\Crm\Data;

use App\Crm\Enums\CaptureChannel;
use App\Crm\Enums\LeadSource;
use App\Crm\Enums\ProjectType;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

class CaptureInquiryData extends Data
{
    public function __construct(
        public ?string $contact_id,
        public ?string $contact_name,
        public ?string $contact_email,
        public ?string $contact_phone,
        public ?string $partner_name,
        public string $project_name,
        public ProjectType $type,
        public LeadSource $source,
        public CaptureChannel $captured_via,
        public ?string $date,
        public ?string $inquiry_text,
    ) {}

    public static function rules(): array
    {
        return [
            'contact_id' => ['nullable', 'string', 'size:24'],
            'contact_name' => ['nullable', 'string', 'max:255', 'required_without:contact_id'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:64'],
            'partner_name' => ['nullable', 'string', 'max:255'],
            'project_name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ProjectType::class)],
            'source' => ['required', Rule::enum(LeadSource::class)],
            'captured_via' => ['required', Rule::enum(CaptureChannel::class)],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'inquiry_text' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
```

- [ ] **Step 4: Write the action**

Create `apps/studio/app/Crm/Actions/CaptureInquiry.php`:

```php
<?php

namespace App\Crm\Actions;

use App\Crm\Data\CaptureInquiryData;
use App\Crm\Enums\LeadStage;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Enums\ProjectType;
use App\Crm\Enums\ShootKind;
use App\Crm\Enums\ShootStatus;
use App\Crm\Events\LeadCaptured;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Manual entry is P1's only capture path, so every Project, Lead, Contact and
 * Shoot in the system is born here (mockup 03, line 13). One transaction: a
 * half-created inquiry is worse than a rejected one.
 */
class CaptureInquiry
{
    public function __invoke(CaptureInquiryData $data, User $actor): Project
    {
        return DB::transaction(function () use ($data, $actor) {
            $contact = $this->resolveContact($data);

            $project = Project::create([
                'name' => $data->project_name,
                'type' => $data->type,
                // Never true at capture: a raw inquiry must not surface in a
                // client portal the moment it arrives (vision §3.1).
                'portal_visible' => false,
            ]);

            $project->attachContact($contact, ProjectContactRole::Primary);

            // One field in the UI, a second ProjectContact underneath — so in
            // P2 the partner gets their own portal login rather than sharing.
            if (filled($data->partner_name)) {
                $partner = Contact::create(['name' => $data->partner_name]);
                $project->attachContact($partner, ProjectContactRole::Partner);
            }

            $lead = Lead::create([
                'project_id' => $project->id,
                'source' => $data->source,
                'stage' => LeadStage::New,
                'stage_entered_at' => now(),
                'inquiry_text' => $data->inquiry_text,
                'captured_via' => $data->captured_via,
                'captured_by_id' => $actor->id,
            ]);

            Shoot::create([
                'project_id' => $project->id,
                'kind' => $this->kindFor($data->type),
                'date' => $data->date,
                'status' => ShootStatus::Requested,
                'is_primary' => true,
                'sort_order' => 0,
            ]);

            AuditLogger::record(
                event: 'lead.captured',
                actor: $actor,
                context: ['source' => $data->source->value, 'via' => $data->captured_via->value],
                subject: $lead->public_id,
            );

            LeadCaptured::dispatch($lead);

            return $project;
        });
    }

    private function resolveContact(CaptureInquiryData $data): Contact
    {
        if (filled($data->contact_id)) {
            return Contact::query()->where('public_id', $data->contact_id)->firstOrFail();
        }

        return Contact::create([
            'name' => $data->contact_name,
            'email' => $data->contact_email,
            'phone' => $data->contact_phone,
        ]);
    }

    private function kindFor(ProjectType $type): ShootKind
    {
        return match ($type) {
            ProjectType::Wedding => ShootKind::Wedding,
            ProjectType::Portrait => ShootKind::Portrait,
            ProjectType::Other => ShootKind::Other,
        };
    }
}
```

- [ ] **Step 5: Write the controller and routes**

Create `apps/studio/app/Crm/Http/Controllers/InquiryCaptureController.php`:

```php
<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Actions\CaptureInquiry;
use App\Crm\Data\CaptureInquiryData;
use App\Crm\Enums\CaptureChannel;
use App\Crm\Enums\LeadSource;
use App\Crm\Enums\ProjectType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InquiryCaptureController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Leads/Create', [
            'types' => self::options(ProjectType::cases()),
            'sources' => self::options(LeadSource::cases()),
            'channels' => self::options(CaptureChannel::cases()),
        ]);
    }

    public function store(CaptureInquiryData $data, CaptureInquiry $capture): RedirectResponse
    {
        $capture($data, auth('staff')->user());

        // A literal path, not route('leads.index') — that route is defined in
        // Task 9, and a named-route lookup here would make every POST test in
        // this task fail with RouteNotFoundException instead of the assertion
        // it is actually about.
        return redirect('/anfragen');
    }

    /** @param  array<int, \BackedEnum&\App\Crm\Enums\HasLabel>  $cases */
    private static function options(array $cases): array
    {
        return array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->label()],
            $cases,
        );
    }
}
```

Create `apps/studio/app/Crm/Enums/HasLabel.php` so that docblock is honest:

```php
<?php

namespace App\Crm\Enums;

interface HasLabel
{
    public function label(): string;
}
```

and add `implements HasLabel` to `ProjectType`, `LeadSource`, `CaptureChannel`, `LeadStage`, `ProjectContactRole`, `ShootKind` and `ShootStatus` (they all already have the method).

In `apps/studio/routes/admin.php`, inside the `mfa` group, add — **after** the two Task 7 routes:

```php
        Route::get('/anfragen/neu', [InquiryCaptureController::class, 'create'])->name('leads.create');
        Route::post('/anfragen', [InquiryCaptureController::class, 'store'])->name('leads.store');
```

- [ ] **Step 6: Run the backend tests**

Run: `docker compose run --rm php ./vendor/bin/pest tests/Feature/Crm/CaptureInquiryTest.php`
Expected: all pass. Inertia's test assertions read the page props server-side and never resolve the Vue component, so the last test passes before `Leads/Create.vue` exists — the browser is where its absence would show, which is what the e2e spec in Step 9 is for.

- [ ] **Step 7: Build the Vue components**

Add to `apps/studio/resources/js/locales/de.json` a `leads` namespace:

```json
  "leads": {
    "createKicker": "Neue Anfrage",
    "createTitle": "Anfrage erfassen",
    "capturedBy": "{channel} · {name}",
    "contact": "Kontakt",
    "contactChange": "ändern",
    "contactSearchHint": "Erst suchen, dann tippen — sonst entsteht ein zweiter Kontakt für dieselbe Person.",
    "contactCreate": "„{term}\" als neuen Kontakt anlegen",
    "contactProjects": "{count}. Projekt",
    "name": "Name",
    "partner": "Partner/in",
    "optional": "optional",
    "email": "E-Mail",
    "phone": "Telefon",
    "noEmailHint": "Ohne E-Mail: kein Portal-Zugang und keine Eingangsbestätigung. Anlegen geht trotzdem.",
    "projectName": "Projektname",
    "projectNameHint": "Vorgeschlagen aus Kontakt und Typ. Überschreibbar.",
    "type": "Typ",
    "source": "Quelle",
    "mainDate": "Haupttermin",
    "conflict": "Belegt — {project}{venue}. Speichern ist trotzdem möglich; der Hinweis bleibt an der Anfrage.",
    "conflictOpen": "Belegtes Projekt ansehen",
    "inquiryText": "Anfragetext",
    "inquiryTextHint": "Ort, Gästezahl, weitere Shootings und Paket später im Projekt.",
    "submit": "Anfrage anlegen"
  }
```

Create `apps/studio/resources/js/admin/components/ConflictNote.vue`:

```vue
<script setup lang="ts">
// Warns, never blocks. The check answers "is somebody already on this day",
// which is not the question "may this be saved" — and a blocking form teaches
// staff to type a wrong date to get past it, corrupting the very column the
// check reads.
defineProps<{ conflicts: App.Crm.Data.DateConflictData[] }>()
</script>

<template>
  <div v-if="conflicts.length" class="mt-2 space-y-1">
    <p
      v-for="conflict in conflicts"
      :key="conflict.projectId"
      class="rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm"
    >
      {{ $t('leads.conflict', {
        project: conflict.projectName,
        venue: conflict.venue ? `, ${conflict.venue}` : '',
      }) }}
    </p>
  </div>
</template>
```

Create `apps/studio/resources/js/admin/components/ContactTypeahead.vue`:

```vue
<script setup lang="ts">
import { ref, watch } from 'vue'
import { Input } from '@/ui/input'

// The ordering here IS the anti-duplicate mechanism, not a convenience:
// ClientAccess hangs off Contact, so a repeat client must resolve to the same
// row. "Create new" only becomes reachable once a search has run and the
// existing matches have been seen. A plain text field cannot do this.
const model = defineModel<App.Crm.Data.ContactSuggestionData | null>({ required: true })
const emit = defineEmits<{ createNew: [term: string] }>()

const term = ref('')
const results = ref<App.Crm.Data.ContactSuggestionData[]>([])
const searched = ref(false)

let timer: ReturnType<typeof setTimeout> | undefined

watch(term, (value) => {
  clearTimeout(timer)
  searched.value = false

  if (value.trim().length < 2) {
    results.value = []
    return
  }

  timer = setTimeout(async () => {
    const response = await fetch(`/kontakte/suche?q=${encodeURIComponent(value)}`, {
      headers: { Accept: 'application/json' },
    })
    results.value = (await response.json()).contacts
    searched.value = true
  }, 250)
})

function choose(contact: App.Crm.Data.ContactSuggestionData) {
  model.value = contact
  results.value = []
}
</script>

<template>
  <div v-if="model" class="flex items-center gap-3 rounded-md border p-3">
    <div class="flex-1">
      <div class="text-sm font-medium">
        {{ model.name }}
        <span v-if="model.projectCount" class="ml-1 text-xs text-muted-foreground">
          {{ $t('leads.contactProjects', { count: model.projectCount + 1 }) }}
        </span>
      </div>
      <div class="text-xs text-muted-foreground">
        {{ [model.email, model.phone].filter(Boolean).join(' · ') }}
      </div>
    </div>
    <button type="button" class="text-sm underline" @click="model = null">
      {{ $t('leads.contactChange') }}
    </button>
  </div>

  <div v-else>
    <Input v-model="term" type="search" :placeholder="$t('common.search')" />

    <ul v-if="searched" class="mt-1 rounded-md border">
      <li>
        <button
          type="button"
          class="w-full px-3 py-2 text-left text-sm hover:bg-accent"
          @click="emit('createNew', term)"
        >
          {{ $t('leads.contactCreate', { term }) }}
        </button>
      </li>
      <li v-for="contact in results" :key="contact.id">
        <button
          type="button"
          class="w-full px-3 py-2 text-left text-sm hover:bg-accent"
          @click="choose(contact)"
        >
          <span class="font-medium">{{ contact.name }}</span>
          <span class="ml-2 text-xs text-muted-foreground">{{ contact.email }}</span>
        </button>
      </li>
    </ul>

    <p class="mt-1 text-xs text-muted-foreground">{{ $t('leads.contactSearchHint') }}</p>
  </div>
</template>
```

Create `apps/studio/resources/js/admin/pages/Leads/Create.vue`. Structure per mockup 03: the contact block first, then project name, then type and source side by side, then the main date with its conflict note, then the inquiry text.

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import ContactTypeahead from '@/admin/components/ContactTypeahead.vue'
import ConflictNote from '@/admin/components/ConflictNote.vue'

type Option = { value: string; label: string }

const props = defineProps<{ types: Option[]; sources: Option[]; channels: Option[] }>()

const contact = ref<App.Crm.Data.ContactSuggestionData | null>(null)
const conflicts = ref<App.Crm.Data.DateConflictData[]>([])

const form = useForm({
  contact_id: null as string | null,
  contact_name: '',
  contact_email: '',
  contact_phone: '',
  partner_name: '',
  project_name: '',
  type: props.types[0].value,
  source: props.sources[0].value,
  captured_via: props.channels[0].value,
  date: '',
  inquiry_text: '',
})

watch(contact, (value) => {
  form.contact_id = value?.id ?? null
  form.contact_name = value?.name ?? ''
  proposeProjectName()
})

watch(() => form.type, proposeProjectName)

// Proposes itself from contact and type, so the "Betreff" costs nothing.
// Only ever overwrites a value the user has not touched.
const nameTouched = ref(false)

function proposeProjectName() {
  if (nameTouched.value) return

  const who = contact.value?.name ?? form.contact_name
  const label = props.types.find((t) => t.value === form.type)?.label ?? ''

  form.project_name = who && label ? `${who} — ${label}` : ''
}

watch(() => form.date, async (date) => {
  if (!date) {
    conflicts.value = []
    return
  }

  const response = await fetch(`/verfuegbarkeit?datum=${date}`, {
    headers: { Accept: 'application/json' },
  })
  conflicts.value = (await response.json()).conflicts
})

function submit() {
  form.post('/anfragen')
}
</script>

<template>
  <main class="mx-auto max-w-2xl space-y-6 p-6">
    <div>
      <p class="text-xs uppercase tracking-wide text-muted-foreground">{{ $t('leads.createKicker') }}</p>
      <h1 class="text-2xl font-semibold tracking-tight">{{ $t('leads.createTitle') }}</h1>
    </div>

    <form class="space-y-5" @submit.prevent="submit">
      <div>
        <Label>{{ $t('leads.contact') }}</Label>
        <ContactTypeahead v-model="contact" @create-new="(term) => (form.contact_name = term)" />
      </div>

      <div v-if="!contact" class="grid gap-4 sm:grid-cols-2">
        <div>
          <Label for="contact_name">{{ $t('leads.name') }}</Label>
          <Input id="contact_name" v-model="form.contact_name" @input="proposeProjectName" />
          <p v-if="form.errors.contact_name" class="text-sm text-destructive">{{ form.errors.contact_name }}</p>
        </div>
        <div>
          <Label for="partner_name">{{ $t('leads.partner') }}</Label>
          <Input id="partner_name" v-model="form.partner_name" />
        </div>
        <div>
          <Label for="contact_email">{{ $t('leads.email') }}</Label>
          <Input id="contact_email" v-model="form.contact_email" type="email" />
        </div>
        <div>
          <Label for="contact_phone">{{ $t('leads.phone') }}</Label>
          <Input id="contact_phone" v-model="form.contact_phone" type="tel" />
        </div>
        <p class="text-xs text-muted-foreground sm:col-span-2">{{ $t('leads.noEmailHint') }}</p>
      </div>

      <div>
        <Label for="project_name">{{ $t('leads.projectName') }}</Label>
        <Input id="project_name" v-model="form.project_name" @input="nameTouched = true" />
        <p class="text-xs text-muted-foreground">{{ $t('leads.projectNameHint') }}</p>
        <p v-if="form.errors.project_name" class="text-sm text-destructive">{{ form.errors.project_name }}</p>
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <Label for="type">{{ $t('leads.type') }}</Label>
          <select id="type" v-model="form.type" class="h-9 w-full rounded-md border px-3 text-sm">
            <option v-for="option in types" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
        </div>
        <div>
          <Label for="source">{{ $t('leads.source') }}</Label>
          <select id="source" v-model="form.source" class="h-9 w-full rounded-md border px-3 text-sm">
            <option v-for="option in sources" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
        </div>
      </div>

      <div>
        <Label for="date">{{ $t('leads.mainDate') }}</Label>
        <!-- Native date input: renders dd.mm.yyyy under de-DE with no picker
             library and no parsing code. -->
        <Input id="date" v-model="form.date" type="date" />
        <ConflictNote :conflicts="conflicts" />
      </div>

      <div>
        <Label for="inquiry_text">{{ $t('leads.inquiryText') }}</Label>
        <textarea
          id="inquiry_text"
          v-model="form.inquiry_text"
          rows="8"
          class="w-full rounded-md border p-3 text-sm"
        />
        <p class="text-xs text-muted-foreground">{{ $t('leads.inquiryTextHint') }}</p>
      </div>

      <div class="flex items-center gap-4">
        <Button type="submit" :disabled="form.processing">{{ $t('leads.submit') }}</Button>
        <a href="/anfragen" class="text-sm underline">{{ $t('common.cancel') }}</a>
      </div>
    </form>
  </main>
</template>
```

- [ ] **Step 8: Run the tests, lint and build**

```bash
docker compose run --rm php ./vendor/bin/pest tests/Feature/Crm/CaptureInquiryTest.php
pnpm --filter @studio/app lint
pnpm --filter @studio/app build
```

Expected: 10 passed, lint and build clean.

- [ ] **Step 9: Write the e2e spec**

Create `apps/studio/tests/e2e/capture.spec.ts`. It signs in as the seeded MFA user (reuse the helper `mfa.spec.ts` already uses), then:

```ts
import { test, expect } from '@playwright/test'
import { signIn } from './helpers'

test('captures an inquiry and lands on the board', async ({ page }) => {
  await signIn(page)

  await page.goto('/anfragen/neu')
  await page.getByLabel('Name').fill('Annika Steffens')
  await page.getByLabel('Partner/in').fill('Ruben Falk')
  await page.getByLabel('Telefon').fill('0171 3948820')
  await page.getByLabel('Haupttermin').fill('2026-06-14')
  await page.getByRole('button', { name: 'Anfrage anlegen' }).click()

  await expect(page).toHaveURL(/\/anfragen$/)
  await expect(page.getByText('Annika Steffens & Ruben Falk')).toBeVisible()
})
```

If `tests/e2e/helpers.ts` does not exist, extract the sign-in steps from `mfa.spec.ts` into it first, and update `mfa.spec.ts` to import it — the same flow copied into a third spec is the version that drifts.

This spec depends on the board from Task 9. Write it now, mark it `test.fixme()` if the board does not exist yet, and remove the marker in Task 9.

- [ ] **Step 10: Regenerate types, run everything, commit**

```bash
docker compose run --rm php php artisan typescript:transform
docker compose run --rm php ./vendor/bin/pest
pnpm lint && pnpm test && pnpm build
git add apps/studio
git commit -m "feat(studio): capture an inquiry as one transaction"
```

---

# Task 9: The lead board

**Files:**
- Create: `apps/studio/app/Crm/Data/LeadCardData.php`
- Create: `apps/studio/app/Crm/Http/Controllers/LeadBoardController.php`, `LeadStageController.php`
- Create: `apps/studio/resources/js/admin/pages/Leads/Board.vue`, `apps/studio/resources/js/admin/components/LeadCard.vue`
- Create: `apps/studio/database/seeders/DemoCrmSeeder.php`
- Create: `apps/studio/tests/Feature/Crm/LeadBoardTest.php`
- Modify: `apps/studio/routes/admin.php`, `de.json`, `apps/studio/tests/e2e/capture.spec.ts`, `apps/studio/app/Http/Controllers/Staff/DashboardController.php`

**Interfaces:**
- Consumes: everything from Tasks 2–8.
- Produces:
  - `LeadCardData { string $id; string $type; string $names; ?string $date; int $shootCount; ?string $venue; string $source; string $stage; int $daysInStage; list<DateConflictData> $conflicts; }`
  - Routes `GET /anfragen` (`leads.index`) and `POST /anfragen/{lead}/stufe` (`leads.stage.update`)
  - `DemoCrmSeeder` — eleven leads spread across the six stages, one with a date collision, matching mockup 02's cast

- [ ] **Step 1: Write the failing test**

Create `apps/studio/tests/Feature/Crm/LeadBoardTest.php`:

```php
<?php

use App\Crm\Enums\LeadStage;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
});

function leadOn(string $date, LeadStage $stage = LeadStage::New, int $daysAgo = 0, string $name = 'Meier & Kowalski'): Lead
{
    $project = Project::factory()->wedding()->create(['name' => $name]);
    Shoot::factory()->for($project)->primary()->on($date)->create();

    return Lead::factory()->for($project)->atStage($stage, $daysAgo)->create();
}

function boardProps(): array
{
    return test()->actingAs(test()->staff, 'staff')
        ->get('/anfragen')->assertOk()->viewData('page')['props'];
}

it('groups projects into the six funnel columns in order', function () {
    leadOn('2026-06-14', LeadStage::New);
    leadOn('2026-07-11', LeadStage::Contacted, name: 'Petersen');
    leadOn('2026-08-22', LeadStage::Booked, name: 'Vehse & Rieck');

    $columns = boardProps()['columns'];

    expect(array_column($columns, 'stage'))
        ->toBe(['new', 'contacted', 'quote_sent', 'quote_accepted', 'contract_sent', 'booked'])
        ->and($columns[0]['label'])->toBe('Neu')
        ->and($columns[0]['cards'])->toHaveCount(1)
        ->and($columns[1]['cards'])->toHaveCount(1)
        ->and($columns[2]['cards'])->toHaveCount(0)
        ->and($columns[5]['cards'])->toHaveCount(1);
});

it('keeps lost leads off the board entirely', function () {
    leadOn('2026-06-14')->forceFill(['lost_at' => now(), 'lost_reason' => 'Budget'])->save();

    $counts = array_map(fn ($c) => count($c['cards']), boardProps()['columns']);

    expect(array_sum($counts))->toBe(0);
});

it('renders the contact names, the primary date and the shoot count', function () {
    $lead = leadOn('2026-06-14', name: 'Steffens & Falk');
    $project = $lead->project;
    $project->attachContact(Contact::factory()->create(['name' => 'Annika Steffens']), ProjectContactRole::Primary);
    $project->attachContact(Contact::factory()->create(['name' => 'Ruben Falk']), ProjectContactRole::Partner);
    Shoot::factory()->for($project)->on('2026-03-01')->create();

    $card = boardProps()['columns'][0]['cards'][0];

    expect($card['names'])->toBe('Annika Steffens & Ruben Falk')
        ->and($card['date'])->toBe('2026-06-14')
        ->and($card['shootCount'])->toBe(2)
        ->and($card['id'])->toBe($project->public_id);
});

it('counts days in the current stage, not since creation', function () {
    leadOn('2026-06-14', LeadStage::Contacted, daysAgo: 8);

    expect(boardProps()['columns'][1]['cards'][0]['daysInStage'])->toBe(8);
});

it('flags a card whose primary date collides with another open project', function () {
    leadOn('2026-06-14', name: 'Meier & Kowalski');
    leadOn('2026-06-14', name: 'Kaltenbach & Adler');

    $cards = boardProps()['columns'][0]['cards'];

    expect($cards[0]['conflicts'])->toHaveCount(1)
        ->and($cards[1]['conflicts'])->toHaveCount(1)
        ->and($cards[0]['conflicts'][0]['projectName'])->toBe('Kaltenbach & Adler');
});

it('clears the flag when the colliding project moves, without writing to the lead', function () {
    $first = leadOn('2026-06-14', name: 'Meier & Kowalski');
    $second = leadOn('2026-06-14', name: 'Kaltenbach & Adler');

    $before = $first->fresh()->updated_at;

    $second->project->primaryShoot->update(['date' => '2026-06-21']);

    $cards = boardProps()['columns'][0]['cards'];

    expect($cards[0]['conflicts'])->toBe([])
        ->and($cards[1]['conflicts'])->toBe([])
        ->and($first->fresh()->updated_at->eq($before))->toBeTrue();
});

it('resolves every conflict on the board in a bounded number of queries', function () {
    foreach (range(1, 12) as $i) {
        leadOn(sprintf('2026-06-%02d', $i), name: "Projekt {$i}");
    }

    DB::enableQueryLog();
    boardProps();
    $count = count(DB::getRawQueryLog());
    DB::disableQueryLog();

    // Session, user, projects, contacts, shoots, conflicts — a fixed set.
    // A per-card conflict lookup would put this well above 20.
    expect($count)->toBeLessThan(20);
});

it('advances a lead from the board', function () {
    $lead = leadOn('2026-06-14');

    $this->actingAs($this->staff, 'staff')
        ->post("/anfragen/{$lead->public_id}/stufe", ['stage' => 'contacted'])
        ->assertRedirect('/anfragen');

    expect($lead->fresh()->stage)->toBe(LeadStage::Contacted);
});

it('refuses a backward move from the board', function () {
    $lead = leadOn('2026-06-14', LeadStage::QuoteSent);

    $this->actingAs($this->staff, 'staff')
        ->post("/anfragen/{$lead->public_id}/stufe", ['stage' => 'new'])
        ->assertSessionHasErrors('stage');

    expect($lead->fresh()->stage)->toBe(LeadStage::QuoteSent);
});

it('puts no integer identifier on the board', function () {
    leadOn('2026-06-14');

    expect(integerIdPaths(boardProps()))->toBe([]);
});
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `docker compose run --rm php ./vendor/bin/pest tests/Feature/Crm/LeadBoardTest.php`
Expected: FAIL — 404 on `/anfragen`.

- [ ] **Step 3: Write the card Data object**

Create `apps/studio/app/Crm/Data/LeadCardData.php`:

```php
<?php

namespace App\Crm\Data;

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Lead;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a lead is triaged on, one fact per line. No value and no column sum:
 * Quote is lead-scoped and arrives in P2, and a placeholder number is a row
 * nobody can act on (P1b spec §2.3).
 */
#[TypeScript]
class LeadCardData extends Data
{
    /** @param  list<DateConflictData>  $conflicts */
    public function __construct(
        public string $id,
        public string $type,
        public string $names,
        public ?string $date,
        public int $shootCount,
        public ?string $venue,
        public string $source,
        public string $stage,
        public int $daysInStage,
        public array $conflicts,
    ) {}

    /** @param  list<DateConflictData>  $conflicts */
    public static function fromLead(Lead $lead, array $conflicts): self
    {
        $project = $lead->project;
        $primary = $project->primaryShoot;

        return new self(
            id: $project->public_id,
            type: $project->type->label(),
            names: $project->contacts
                ->sortBy(fn ($contact) => $contact->pivot->role === ProjectContactRole::Primary->value ? 0 : 1)
                ->pluck('name')
                ->join(' & ') ?: $project->name,
            date: $primary?->date?->format('Y-m-d'),
            shootCount: $project->shoots->count(),
            venue: $primary?->venue(),
            source: $lead->source->label(),
            stage: $lead->stage->value,
            daysInStage: (int) $lead->stage_entered_at->startOfDay()->diffInDays(now()->startOfDay()),
            conflicts: $conflicts,
        );
    }
}
```

- [ ] **Step 4: Write the controllers**

Create `apps/studio/app/Crm/Http/Controllers/LeadBoardController.php`:

```php
<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Actions\CheckDateAvailability;
use App\Crm\Data\LeadCardData;
use App\Crm\Enums\LeadStage;
use App\Crm\Models\Lead;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class LeadBoardController extends Controller
{
    public function __invoke(CheckDateAvailability $availability): Response
    {
        $leads = Lead::query()
            ->open()
            ->with(['project.contacts', 'project.shoots', 'project.primaryShoot'])
            ->orderByDesc('stage_entered_at')
            ->get();

        // One conflict query for the whole board. Resolving per card is the
        // obvious implementation and is an N+1 nobody notices at eleven cards.
        $dates = $leads
            ->map(fn (Lead $lead) => $lead->project->primaryShoot?->date?->format('Y-m-d'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $conflictsByDate = $availability->forDates($dates);

        $cards = $leads->map(function (Lead $lead) use ($conflictsByDate) {
            $date = $lead->project->primaryShoot?->date?->format('Y-m-d');

            // Every shoot on the date, minus this project's own.
            $conflicts = collect($conflictsByDate[$date] ?? [])
                ->reject(fn ($conflict) => $conflict->projectId === $lead->project->public_id)
                ->values()
                ->all();

            return LeadCardData::fromLead($lead, $conflicts);
        });

        $columns = collect(LeadStage::cases())->map(fn (LeadStage $stage) => [
            'stage' => $stage->value,
            'label' => $stage->label(),
            'cards' => $cards->where('stage', $stage->value)->values()->all(),
        ])->all();

        return Inertia::render('Leads/Board', ['columns' => $columns]);
    }
}
```

> The columns are grouped in PHP, in `LeadStage::cases()` order. The funnel order is not the alphabetical order of the stored strings, and encoding it in SQL means a `case` expression that has to be kept in sync with the enum.

Create `apps/studio/app/Crm/Http/Controllers/LeadStageController.php`:

```php
<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Actions\AdvanceLeadStage;
use App\Crm\Enums\LeadStage;
use App\Crm\Models\Lead;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeadStageController extends Controller
{
    public function update(Request $request, Lead $lead, AdvanceLeadStage $advance): RedirectResponse
    {
        $validated = $request->validate([
            'stage' => ['required', Rule::enum(LeadStage::class)],
        ]);

        try {
            $advance($lead, LeadStage::from($validated['stage']), auth('staff')->user());
        } catch (DomainException $e) {
            // A rejected transition is a validation failure to the user, not a
            // 500 — the board renders it next to the card they clicked.
            throw ValidationException::withMessages(['stage' => $e->getMessage()]);
        }

        return redirect('/anfragen');
    }
}
```

Add the routes to `apps/studio/routes/admin.php`, inside the `mfa` group, **after** `/anfragen/neu`:

```php
        Route::get('/anfragen', LeadBoardController::class)->name('leads.index');
        Route::post('/anfragen/{lead}/stufe', [LeadStageController::class, 'update'])->name('leads.stage.update');
```

- [ ] **Step 5: Run the backend tests**

Run: `docker compose run --rm php ./vendor/bin/pest tests/Feature/Crm/LeadBoardTest.php`
Expected: 10 passed, except the one asserting the page renders — that needs the Vue page.

- [ ] **Step 6: Build the board UI**

Add to `de.json`:

```json
  "board": {
    "title": "Anfragen",
    "create": "Anfrage anlegen",
    "shootCount": "{count} Shooting | {count} Shootings",
    "openDate": "Termin offen",
    "advance": "Weiter",
    "conflict": "Haupttermin belegt — {project}",
    "daysInStage": "vor {count} Tag | vor {count} Tagen",
    "today": "heute",
    "stale": "{count} Tage still",
    "empty": "Keine Anfragen in dieser Spalte."
  }
```

Create `apps/studio/resources/js/admin/components/LeadCard.vue`:

```vue
<script setup lang="ts">
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps<{ card: App.Crm.Data.LeadCardData; nextStage: string | null }>()

// Vision §7 nudges at 3 and 7 days. Colouring the age is what lets the board
// show what needs chasing without a separate view.
const stale = computed(() => props.card.daysInStage >= 7)

const dateLabel = computed(() =>
  props.card.date ? new Date(props.card.date).toLocaleDateString('de-DE') : null,
)

function advance() {
  if (props.nextStage) {
    router.post(`/anfragen/${props.card.id}/stufe`, { stage: props.nextStage })
  }
}
</script>

<template>
  <article class="group rounded-lg border p-3 transition hover:border-foreground/30">
    <p class="text-[11px] uppercase tracking-wide text-muted-foreground">{{ card.type }}</p>
    <h3 class="text-sm font-medium">{{ card.names }}</h3>

    <dl class="mt-2 space-y-0.5 text-xs text-muted-foreground">
      <dd>
        {{ dateLabel ?? $t('board.openDate') }}
        <template v-if="dateLabel"> · {{ $t('board.shootCount', card.shootCount) }}</template>
      </dd>
      <dd v-if="card.venue">{{ card.venue }}</dd>
      <dd>{{ card.source }}</dd>
    </dl>

    <p
      v-for="conflict in card.conflicts"
      :key="conflict.projectId"
      class="mt-2 rounded border border-destructive/30 bg-destructive/5 px-2 py-1 text-xs"
    >
      {{ $t('board.conflict', { project: conflict.projectName }) }}
    </p>

    <footer class="mt-3 flex items-center justify-between">
      <span class="text-xs tabular-nums" :class="stale ? 'text-destructive' : 'text-muted-foreground'">
        {{ card.daysInStage === 0 ? $t('board.today')
           : stale ? $t('board.stale', card.daysInStage)
           : $t('board.daysInStage', card.daysInStage) }}
      </span>
      <button
        v-if="nextStage"
        type="button"
        class="text-xs underline opacity-0 transition group-hover:opacity-100"
        @click="advance"
      >
        {{ $t('board.advance') }}
      </button>
    </footer>
  </article>
</template>
```

> The advance control appears on hover only, per clickdummy §card-anatomy — the arrow does not sit on every card.

Create `apps/studio/resources/js/admin/pages/Leads/Board.vue`:

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import LeadCard from '@/admin/components/LeadCard.vue'

type Column = { stage: string; label: string; cards: App.Crm.Data.LeadCardData[] }

const props = defineProps<{ columns: Column[] }>()

function nextStage(index: number): string | null {
  return props.columns[index + 1]?.stage ?? null
}
</script>

<template>
  <main class="space-y-6 p-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold tracking-tight">{{ $t('board.title') }}</h1>
      <Button as-child><Link href="/anfragen/neu">{{ $t('board.create') }}</Link></Button>
    </div>

    <div class="grid grid-cols-6 gap-8 overflow-x-auto">
      <section v-for="(column, index) in columns" :key="column.stage" class="min-w-56 space-y-1.5">
        <header class="flex items-baseline gap-2 pb-2">
          <h2 class="text-sm font-semibold">{{ column.label }}</h2>
          <span class="rounded px-1.5 text-xs tabular-nums text-muted-foreground">{{ column.cards.length }}</span>
        </header>

        <LeadCard
          v-for="card in column.cards"
          :key="card.id"
          :card="card"
          :next-stage="nextStage(index)"
        />

        <p v-if="!column.cards.length" class="text-xs text-muted-foreground">{{ $t('board.empty') }}</p>
      </section>
    </div>
  </main>
</template>
```

- [ ] **Step 7: Write the demo seeder**

Create `apps/studio/database/seeders/DemoCrmSeeder.php` — mockup 02's cast, built through the factories so the seeder keeps proving the model works:

```php
<?php

namespace Database\Seeders;

use App\Crm\Enums\LeadSource;
use App\Crm\Enums\LeadStage;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Enums\ProjectType;
use App\Crm\Enums\ShootKind;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The cast from mockup 02, so the board can be eyeballed against the screen it
 * was designed from. Two projects deliberately share 2026-06-14 (the conflict
 * flag), one has no date (Termin offen) and one has been silent for eight days
 * (the stale colouring).
 */
class DemoCrmSeeder extends Seeder
{
    public function run(): void
    {
        $staff = User::query()->first() ?? User::factory()->create();

        $cast = [
            ['Sarah Brandt', 'Tim Erdmann', ProjectType::Wedding, '2026-09-05', LeadStage::New, 0, LeadSource::Referral, 'Hof Eggers', 'Winsen'],
            ['Familie Osterhoff', null, ProjectType::Portrait, '2026-03-14', LeadStage::New, 1, LeadSource::Website, 'Studio', null],
            ['Nina Kaltenbach', 'Ruben Adler', ProjectType::Wedding, '2026-06-14', LeadStage::New, 1, LeadSource::Instagram, 'Gut Basthorst', 'Basthorst'],
            ['Charlotte Vehse', 'Paul Rieck', ProjectType::Wedding, '2026-08-22', LeadStage::Contacted, 8, LeadSource::Website, 'Schloss Reinbek', 'Reinbek'],
            ['Mira Sundermann', null, ProjectType::Portrait, null, LeadStage::Contacted, 4, LeadSource::Referral, null, null],
            ['Jasmin Petersen', 'Kolja Petersen', ProjectType::Wedding, '2026-07-11', LeadStage::Contacted, 2, LeadSource::Referral, 'Landhaus Scherrer', 'Hamburg'],
            ['Annika Reuter', 'Finn Sölter', ProjectType::Wedding, '2026-05-30', LeadStage::QuoteSent, 3, LeadSource::WeddingFair, 'Elbspeicher', 'Hamburg'],
            ['Lea Hoffmann', 'Jonas Behr', ProjectType::Wedding, '2026-10-03', LeadStage::QuoteSent, 6, LeadSource::Instagram, 'Gut Wulksfelde', 'Tangstedt'],
            ['Marie Ahrens', 'David Kroll', ProjectType::Wedding, '2026-07-25', LeadStage::QuoteAccepted, 1, LeadSource::Referral, 'Kaispeicher', 'Hamburg'],
            ['Sophie Lang', 'Erik Voss', ProjectType::Wedding, '2026-09-19', LeadStage::ContractSent, 2, LeadSource::Website, 'Gut Basthorst', 'Basthorst'],
            ['Meier & Kowalski', null, ProjectType::Wedding, '2026-06-14', LeadStage::Booked, 12, LeadSource::Referral, 'Gut Basthorst', 'Basthorst'],
        ];

        foreach ($cast as [$name, $partner, $type, $date, $stage, $daysAgo, $source, $venue, $city]) {
            $project = Project::create([
                'name' => trim($name.($partner ? ' & '.$partner : '')).' — '.$type->label(),
                'type' => $type,
            ]);

            $project->attachContact(
                Contact::create(['name' => $name, 'email' => static::emailFor($name)]),
                ProjectContactRole::Primary,
            );

            if ($partner) {
                $project->attachContact(
                    Contact::create(['name' => $partner]),
                    ProjectContactRole::Partner,
                );
            }

            Shoot::create([
                'project_id' => $project->id,
                'kind' => $type === ProjectType::Wedding ? ShootKind::Wedding : ShootKind::Portrait,
                'date' => $date,
                'venue_name' => $venue,
                'venue_city' => $city,
                'is_primary' => true,
                'sort_order' => 0,
            ]);

            Lead::factory()->for($project)->atStage($stage, $daysAgo)->create([
                'source' => $source,
                'captured_by_id' => $staff->id,
            ]);
        }
    }

    private static function emailFor(string $name): string
    {
        return str($name)->lower()->ascii()->replaceMatches('/[^a-z]+/', '.')->trim('.').'@example.test';
    }
}
```

Register it in `DatabaseSeeder::run()` behind a guard so it never runs in production:

```php
        if (! app()->isProduction()) {
            $this->call(DemoCrmSeeder::class);
        }
```

Run: `docker compose run --rm php php artisan migrate:fresh --seed` and open `/anfragen` to eyeball it against `mockups/admin/02-anfragen.html`.

- [ ] **Step 8: Wire the dashboard count**

`app/Http/Controllers/Staff/DashboardController.php` currently hardcodes `'leads' => 0` with a comment saying real counts arrive in P1b. Replace with `Lead::query()->open()->count()`; leave `jobs` at `0` with a comment pointing at sprint 4, where `Job` is created.

- [ ] **Step 9: Finish the e2e spec**

Remove the `test.fixme()` marker from `tests/e2e/capture.spec.ts` and run:

```bash
docker ps --filter publish=8000   # stop anything listed first
pnpm --filter @studio/app test:e2e
```

Expected: all specs pass, including the new capture journey.

- [ ] **Step 10: Regenerate types, run everything, commit**

```bash
docker compose run --rm php php artisan typescript:transform
docker compose run --rm php ./vendor/bin/pest
pnpm lint && pnpm test && pnpm build
git add apps/studio
git commit -m "feat(studio): render the six-column lead board with derived conflicts"
```

---

# Task 10: Contacts list and edit

**Files:**
- Create: `apps/studio/app/Crm/Http/Controllers/ContactController.php`
- Create: `apps/studio/resources/js/admin/pages/Contacts/Index.vue`, `Edit.vue`
- Create: `apps/studio/tests/Feature/Crm/ContactCrudTest.php`
- Modify: `apps/studio/routes/admin.php`, `de.json`, `apps/studio/tests/Feature/NoInternalIdsTest.php`

**Interfaces:**
- Consumes: `Contact` from Task 2.
- Produces: routes `GET /kontakte` (`contacts.index`), `GET /kontakte/{contact}` (`contacts.edit`), `PATCH /kontakte/{contact}` (`contacts.update`), all bound on `public_id`.

- [ ] **Step 1: Write the failing test**

Create `apps/studio/tests/Feature/Crm/ContactCrudTest.php`:

```php
<?php

use App\Crm\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
});

it('lists contacts alphabetically with their project counts', function () {
    Contact::factory()->create(['name' => 'Zora Winter']);
    Contact::factory()->create(['name' => 'Annika Steffens']);

    $props = $this->actingAs($this->staff, 'staff')
        ->get('/kontakte')->assertOk()->viewData('page')['props'];

    expect(array_column($props['contacts'], 'name'))->toBe(['Annika Steffens', 'Zora Winter'])
        ->and(integerIdPaths($props))->toBe([]);
});

it('binds the edit route on the public id, not the primary key', function () {
    $contact = Contact::factory()->create();

    $this->actingAs($this->staff, 'staff')->get("/kontakte/{$contact->public_id}")->assertOk();
    $this->actingAs($this->staff, 'staff')->get("/kontakte/{$contact->id}")->assertNotFound();
});

it('updates a contact', function () {
    $contact = Contact::factory()->create(['name' => 'Anika Steffens']);

    $this->actingAs($this->staff, 'staff')
        ->patch("/kontakte/{$contact->public_id}", [
            'name' => 'Annika Steffens',
            'email' => $contact->email,
            'phone' => $contact->phone,
        ])
        ->assertRedirect('/kontakte');

    expect($contact->fresh()->name)->toBe('Annika Steffens');
});

it('refuses an email that already belongs to someone else', function () {
    Contact::factory()->create(['email' => 'taken@example.test']);
    $contact = Contact::factory()->create();

    $this->actingAs($this->staff, 'staff')
        ->patch("/kontakte/{$contact->public_id}", ['name' => $contact->name, 'email' => 'taken@example.test'])
        ->assertSessionHasErrors('email');
});

it('allows clearing an email', function () {
    $contact = Contact::factory()->create();

    $this->actingAs($this->staff, 'staff')
        ->patch("/kontakte/{$contact->public_id}", ['name' => $contact->name, 'email' => null])
        ->assertRedirect('/kontakte');

    expect($contact->fresh()->email)->toBeNull();
});

it('rejects unauthenticated access to every contact route', function () {
    $contact = Contact::factory()->create();

    $this->get('/kontakte')->assertRedirect('/login');
    $this->get("/kontakte/{$contact->public_id}")->assertRedirect('/login');
    $this->patch("/kontakte/{$contact->public_id}", [])->assertRedirect('/login');
});
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `docker compose run --rm php ./vendor/bin/pest tests/Feature/Crm/ContactCrudTest.php`
Expected: FAIL — 404 on `/kontakte`.

- [ ] **Step 3: Write the controller**

Create `apps/studio/app/Crm/Http/Controllers/ContactController.php`:

```php
<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Data\ContactSuggestionData;
use App\Crm\Models\Contact;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(): Response
    {
        $contacts = Contact::query()
            ->withCount('projects')
            ->orderBy('name')
            ->get();

        return Inertia::render('Contacts/Index', [
            'contacts' => ContactSuggestionData::collect($contacts),
        ]);
    }

    public function edit(Contact $contact): Response
    {
        return Inertia::render('Contacts/Edit', [
            'contact' => ContactSuggestionData::fromModel($contact->loadCount('projects')),
        ]);
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable', 'email', 'max:255',
                // Mirrors the partial unique index. Without this the database
                // throws a 500 where the form should show a field error.
                Rule::unique('contacts', 'email')
                    ->where('tenant_id', config('tenancy.tenant_id'))
                    ->ignore($contact->id),
            ],
            'phone' => ['nullable', 'string', 'max:64'],
        ]);

        $contact->update($validated);

        return redirect('/kontakte');
    }
}
```

- [ ] **Step 4: Register the routes**

In `apps/studio/routes/admin.php`, inside the `mfa` group. **Order matters** — `/kontakte/suche` from Task 7 must stay above `/kontakte/{contact}`:

```php
        Route::get('/kontakte/suche', ContactSearchController::class)->name('contacts.search');
        Route::get('/kontakte', [ContactController::class, 'index'])->name('contacts.index');
        Route::get('/kontakte/{contact}', [ContactController::class, 'edit'])->name('contacts.edit');
        Route::patch('/kontakte/{contact}', [ContactController::class, 'update'])->name('contacts.update');
```

- [ ] **Step 5: Build the two pages**

Add to `de.json`:

```json
  "contacts": {
    "title": "Kontakte",
    "name": "Name",
    "email": "E-Mail",
    "phone": "Telefon",
    "projects": "Projekte",
    "noEmail": "—",
    "editTitle": "Kontakt bearbeiten",
    "empty": "Noch keine Kontakte."
  }
```

Create `apps/studio/resources/js/admin/pages/Contacts/Index.vue`:

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

defineProps<{ contacts: App.Crm.Data.ContactSuggestionData[] }>()
</script>

<template>
  <main class="mx-auto max-w-4xl space-y-6 p-6">
    <h1 class="text-2xl font-semibold tracking-tight">{{ $t('contacts.title') }}</h1>

    <table v-if="contacts.length" class="w-full text-sm">
      <thead class="text-left text-xs text-muted-foreground">
        <tr>
          <th class="py-2 font-medium">{{ $t('contacts.name') }}</th>
          <th class="py-2 font-medium">{{ $t('contacts.email') }}</th>
          <th class="py-2 font-medium">{{ $t('contacts.phone') }}</th>
          <th class="py-2 text-right font-medium">{{ $t('contacts.projects') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="contact in contacts" :key="contact.id" class="border-t">
          <td class="py-2">
            <Link :href="`/kontakte/${contact.id}`" class="font-medium underline-offset-2 hover:underline">
              {{ contact.name }}
            </Link>
          </td>
          <td class="py-2 text-muted-foreground">{{ contact.email ?? $t('contacts.noEmail') }}</td>
          <td class="py-2 text-muted-foreground">{{ contact.phone ?? $t('contacts.noEmail') }}</td>
          <td class="py-2 text-right tabular-nums">{{ contact.projectCount }}</td>
        </tr>
      </tbody>
    </table>

    <p v-else class="text-sm text-muted-foreground">{{ $t('contacts.empty') }}</p>
  </main>
</template>
```

Create `apps/studio/resources/js/admin/pages/Contacts/Edit.vue`:

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

const props = defineProps<{ contact: App.Crm.Data.ContactSuggestionData }>()

const form = useForm({
  name: props.contact.name,
  email: props.contact.email ?? '',
  phone: props.contact.phone ?? '',
})

function submit() {
  form.patch(`/kontakte/${props.contact.id}`)
}
</script>

<template>
  <main class="mx-auto max-w-lg space-y-6 p-6">
    <h1 class="text-2xl font-semibold tracking-tight">{{ $t('contacts.editTitle') }}</h1>

    <form class="space-y-4" @submit.prevent="submit">
      <div>
        <Label for="name">{{ $t('contacts.name') }}</Label>
        <Input id="name" v-model="form.name" />
        <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
      </div>
      <div>
        <Label for="email">{{ $t('contacts.email') }}</Label>
        <Input id="email" v-model="form.email" type="email" />
        <p v-if="form.errors.email" class="text-sm text-destructive">{{ form.errors.email }}</p>
      </div>
      <div>
        <Label for="phone">{{ $t('contacts.phone') }}</Label>
        <Input id="phone" v-model="form.phone" type="tel" />
      </div>

      <div class="flex items-center gap-4">
        <Button type="submit" :disabled="form.processing">{{ $t('common.save') }}</Button>
        <a href="/kontakte" class="text-sm underline">{{ $t('common.cancel') }}</a>
      </div>
    </form>
  </main>
</template>
```

> `form.patch` sends an empty string for a cleared email, which the `nullable` rule treats as `''`, not `null` — Laravel's `ConvertEmptyStringsToNull` middleware is on by default in the `web` group and turns it into `null` before validation, which is what the "allows clearing an email" test depends on. If that test fails with a unique-constraint error on `''`, that middleware has been removed.

- [ ] **Step 6: Grow the hygiene test**

Add to `apps/studio/tests/Feature/NoInternalIdsTest.php`:

```php
it('never puts an integer identifier into any crm page', function (string $path) {
    $user = User::factory()->withMfa()->create();

    $props = $this->actingAs($user, 'staff')->get($path)->assertOk()->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
})->with(['/anfragen', '/anfragen/neu', '/kontakte']);
```

- [ ] **Step 7: Run everything and commit**

```bash
docker compose run --rm php ./vendor/bin/pest
pnpm lint && pnpm test && pnpm build
pnpm --filter @studio/app test:e2e
git add apps/studio
git commit -m "feat(studio): list and edit contacts"
```

- [ ] **Step 8: Close the sprint**

Walk the Definition of Done in the spec, §9, and check every box against a command you actually ran. In particular confirm by hand:

```bash
# every new route declares an audience
docker compose run --rm php ./vendor/bin/pest tests/Feature/AudienceMatrixTest.php

# generated types are not stale
docker compose run --rm php php artisan typescript:transform
git diff --exit-code apps/studio/resources/js/types/generated.d.ts
```

---

## Notes for the reviewer

Four things in this sprint are easy to implement in a way that passes its own test and is still wrong. Check these specifically:

1. **The conflict flag must not be stored.** Grep for any column, cache or model attribute named like `has_conflict`. The test that proves it is "clears the flag when the colliding project moves, without writing to the lead" — confirm it asserts the *absence* of a write, not just the absence of a flag.
2. **The typeahead's ordering is a constraint, not a UX preference.** The "create new contact" affordance must be unreachable until a search has run. An implementation that shows both a search box and a name field at once satisfies every unit test and reintroduces the duplicate-contact problem the screen exists to prevent.
3. **The availability check must warn and never block.** Confirm there is no validation rule on `date` that rejects a taken date, and that the capture test asserting a successful save with a live conflict is actually exercising a real collision.
4. **The board's conflict resolution must not be per card.** The query-count test is the guard; confirm it fails if `forDate()` is called inside the card loop.

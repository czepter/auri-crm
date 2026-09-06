# Studio-Onboarding-Mail-Journey Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A freshly created studio gets four mails around its own setup (email
verification, welcome, a two-day reminder if unfinished, and a "your
addresses" mail on completion), verifies the owner's email address via link
or code, and every outgoing mail — the three new product mails and the
fourteen existing studio mails — renders through one MJML-compiled layout
instead of Laravel's Markdown default theme.

**Architecture:** MJML sources under `apps/studio/resources/mail/` compile at
build time to checked-in Blade views under
`resources/views/vendor/mail/html/` (`layout.blade.php`, `button.blade.php`
only — everything else falls back to Laravel's own components). A
`View::composer` on `mail::html.layout` injects the current studio's brand
whenever a render doesn't already carry an explicit one; two new custom
top-level mail views (`mail.product-message`, `mail.blocks-message`) pass an
explicit `Auri CRM` brand for the three new product-branded notifications and
for the existing `StaffInvited`'s multi-block body, bypassing the composer's
default. Email verification mirrors the existing `UserEmailChange`
link-token pattern, adding a rate-limited numeric code for the case the link
model didn't need.

**Tech Stack:** Laravel 12 (Pest), Vue 3 + Inertia (Vitest/Playwright not
touched by this plan), MJML 4 (Node, build-time only), PostgreSQL.

**Spec:** [docs/superpowers/specs/2026-09-05-studio-onboarding-mail-journey-design.md](../specs/2026-09-05-studio-onboarding-mail-journey-design.md)

## Global Constraints

- MJML compiles at build time via `php artisan mail:build` (`spatie/mjml-php`,
  which itself shells out to the `mjml` Node package — Node is still never in
  the runtime image, only wherever this command runs: dev machines and CI).
  The output is checked into git and CI fails the `studio` job if the
  compiled files are stale (spec §1, §CI).
- Only `mail::html.layout` and `mail::html.button` are published/overridden.
  Every other Laravel mail component (`message`, `header`, `footer`,
  `subcopy`, `panel`, `table`) keeps the framework default (spec §1
  "Reichweite").
- Sentinel tokens (`__SLOT__`, `__BRAND__`, …) are placed in the MJML source
  and replaced with Blade syntax **after** compilation — never raw `{{ }}` in
  the `.mjml` files themselves (spec §1, "Platzhalter").
- Two mail identities: **Produkt** (brand text "Auri CRM", literal — never
  `config('app.name')`, which is unset in this repo; sender defaults to
  `MAIL_FROM_ADDRESS`; no `AppliesStudioMailIdentity`) for the four new
  mails, and **Studio** (brand = `display_name`/logo, `AppliesStudioMailIdentity`
  for sender/signature/footer) for the fourteen existing ones. No new trait
  for the product side (spec §2 decision table, "Trait: keiner").
- Mail copy lives in PHP, not `de.json` (spec §"Entscheidungen", "Mail-Texte").
  CLAUDE.md's e-mail-copy rules apply: every line must say something the
  reader cannot already see on their own screen — no feature tours, no
  narrating what a button does, no invented numbers, no reassurance nobody
  asked for.
- None of the four new notifications are added to `App\Notifications\Support\StudioEvents::ALL`
  (no per-event mail/in-app preference exists for them; spec
  §"Entscheidungen", "Präferenzen").
- All four go through `App\Notifications\NotificationService::send()`, never
  the `Notification` facade or `->notify()` directly (E-07, the app's single
  fan-out choke point). Only `StudioEmailVerification` implements
  `App\Notifications\Contracts\Transactional` (skips quiet hours) — the other
  three do not (spec §3).
- Every command that builds a client-facing or portal URL from a
  non-HTTP-request context (the reminder command) uses `config('portal.url')`,
  never `route()` — `App\Media\Actions\NotifyZipWaiters` is the existing
  precedent, because a process running under `APP_SURFACE=admin` has no
  portal routes registered at all.
- Studio tests run with `-e APP_SURFACE=admin` on the container (this repo's
  standing instruction, not spec-specific).

---

## File Structure

New files:

- `apps/studio/resources/mail/layout.mjml` — MJML source for the shared layout.
- `apps/studio/resources/mail/button.mjml` — MJML source for the shared button.
- `apps/studio/app/Notifications/Console/Commands/BuildMailTemplatesCommand.php` — `php artisan mail:build`, compiles the above to checked-in Blade via `spatie/mjml-php`.
- `apps/studio/resources/views/vendor/mail/html/layout.blade.php` — generated, checked in.
- `apps/studio/resources/views/vendor/mail/html/button.blade.php` — generated, checked in.
- `apps/studio/resources/views/mail/product-message.blade.php` — hand-written; the top-level view for a simple product mail (greeting/intro/one action/outro/salutation), explicit `Auri CRM` brand.
- `apps/studio/resources/views/mail/blocks-message.blade.php` — hand-written; the top-level view for "intro + N feature blocks with their own button" mails, shared by product mails (explicit brand) and `StaffInvited` (no brand — falls through to the studio composer).
- `apps/studio/database/migrations/2026_09_05_100100_add_email_verified_at_to_users_table.php`
- `apps/studio/database/migrations/2026_09_05_100200_create_user_email_verifications_table.php`
- `apps/studio/database/migrations/2026_09_05_100300_add_onboarding_reminder_sent_at_to_studio_settings_table.php`
- `apps/studio/app/Models/UserEmailVerification.php` — mirrors `UserEmailChange`, plus a rate-limited code.
- `apps/studio/app/Notifications/Studio/StudioEmailVerification.php`
- `apps/studio/app/Notifications/Studio/StudioWelcome.php`
- `apps/studio/app/Notifications/Studio/StudioReady.php`
- `apps/studio/app/Notifications/Studio/StudioSetupPending.php`
- `apps/studio/app/Notifications/Actions/RemindOnboarding.php`
- `apps/studio/app/Notifications/Console/Commands/RemindOnboardingCommand.php`
- `apps/studio/app/Http/Controllers/Staff/EmailVerificationController.php` — link (`confirm`) and code (`confirmCode`) endpoints.
- `apps/studio/resources/js/admin/pages/Auth/panes/EmailVerificationForm.vue` — the code field shown in the wizard's first step.
- Pest test files (one per task, listed in each task below).

Modified files:

- `apps/studio/app/Http/Controllers/Staff/SetupController.php` — creates the verification record, sends `StudioEmailVerification` and `StudioWelcome`.
- `apps/studio/app/Settings/Http/Controllers/StudioOnboardingController.php` — sends `StudioReady`; `show()` passes `emailVerified`.
- `apps/studio/app/Settings/Support/StudioSettingsResolver.php` — adds `onboardingReminderSent(): bool`.
- `apps/studio/app/Providers/AppServiceProvider.php` — registers the `mail::html.layout` composer.
- `apps/studio/app/Notifications/StaffInvited.php` — new copy, renders through `mail.blocks-message`.
- `apps/studio/routes/admin.php` — two new routes.
- `apps/studio/routes/console.php` — one new schedule entry.
- `apps/studio/resources/js/admin/pages/Auth/Onboarding.vue` — mounts `EmailVerificationForm` in step 1.
- `apps/studio/lang/de/auth.php` — two new uniform-failure strings.
- `apps/studio/resources/js/locales/de.json` — two new `onboarding.*` labels.
- `apps/studio/package.json` — `mjml` devDependency (the Node package `spatie/mjml-php` shells out to; no npm script needed, the command is `php artisan mail:build`).
- `apps/studio/composer.json` — `spatie/mjml-php` in `require-dev`.
- `docker/php/Dockerfile` — `nodejs` + `corepack enable`, so the dev PHP container can run `mail:build`.
- `.github/workflows/ci.yml` — Node setup plus one new step in the `studio` job.

---

### Task 1: MJML build pipeline

**Files:**
- Create: `apps/studio/resources/mail/layout.mjml`
- Create: `apps/studio/resources/mail/button.mjml`
- Create: `apps/studio/app/Notifications/Console/Commands/BuildMailTemplatesCommand.php`
- Generated (by running the command, then committed): `apps/studio/resources/views/vendor/mail/html/layout.blade.php`, `apps/studio/resources/views/vendor/mail/html/button.blade.php`
- Modify: `apps/studio/composer.json`, `apps/studio/package.json`, `docker/php/Dockerfile`
- Test: `apps/studio/tests/Feature/Notifications/MailLayoutTest.php`

**Interfaces:**
- Produces: the compiled Blade files at `resources/views/vendor/mail/html/{layout,button}.blade.php`, which every later task's mail rendering depends on. Their Blade variables: `$slot`, `$header`, `$footer`, `$subcopy` (layout — Laravel's own, safe with `{{ }}` since they're `Htmlable`), `$brand`, `$logoUrl` (layout — ours, `??`-guarded); `$url` (button — Laravel's own), `$accent` (button — ours, `??`-guarded).

- [ ] **Step 1: Write the MJML source for the layout**

```xml
<!-- apps/studio/resources/mail/layout.mjml -->
<mjml>
  <mj-head>
    <mj-attributes>
      <mj-all font-family="Helvetica, Arial, sans-serif" />
      <mj-text font-size="14px" line-height="1.6" color="#1a1a1a" />
    </mj-attributes>
  </mj-head>
  <mj-body background-color="#f4f4f5" width="600px">
    <mj-section background-color="#ffffff" padding="32px 32px 0 32px">
      <mj-column>
        <mj-raw>__LOGO_IF__</mj-raw>
        <mj-image src="__LOGO_URL__" alt="__BRAND__" align="left" width="120px" padding="0 0 16px 0" />
        <mj-raw>__LOGO_ENDIF__</mj-raw>
        <mj-text font-size="15px" font-weight="700" padding="0 0 16px 0">__BRAND__</mj-text>
      </mj-column>
    </mj-section>
    <mj-section background-color="#ffffff" padding="0 32px 32px 32px">
      <mj-column>
        <mj-text>__HEADER__</mj-text>
        <mj-text>__SLOT__</mj-text>
        <mj-text font-size="12.5px" color="#6b7280">__SUBCOPY__</mj-text>
      </mj-column>
    </mj-section>
    <mj-section padding="16px 32px 32px 32px">
      <mj-column>
        <mj-text font-size="12px" color="#9ca3af">__FOOTER__</mj-text>
      </mj-column>
    </mj-section>
  </mj-body>
</mjml>
```

- [ ] **Step 2: Write the MJML source for the button**

```xml
<!-- apps/studio/resources/mail/button.mjml -->
<mjml>
  <mj-body>
    <mj-section padding="8px 0">
      <mj-column>
        <mj-button href="__URL__" background-color="__ACCENT__" color="#ffffff" font-weight="600" border-radius="6px" inner-padding="12px 24px">__SLOT__</mj-button>
      </mj-column>
    </mj-section>
  </mj-body>
</mjml>
```

- [ ] **Step 3: Add `spatie/mjml-php` and the `mjml` Node package**

[spatie/mjml-php](https://github.com/spatie/mjml-php) wraps a small bundled
Node script (`vendor/spatie/mjml-php/bin/mjml.mjs`) that itself does
`import mjml2html from 'mjml'` — so the `mjml` npm package still has to be
resolvable from `apps/studio/node_modules` (Node's module resolution walks
up from the vendor script's own directory until it finds it there), and
`node` itself has to be on `PATH` wherever `php artisan mail:build` runs.
Neither ships in the runtime image — this only ever runs on a dev machine or
in CI, never from a served request.

`apps/studio/composer.json` — add to `"require-dev"` (alphabetically, after
`spatie/laravel-typescript-transformer`):

```json
"spatie/mjml-php": "^3.0"
```

`apps/studio/package.json` — add to `"devDependencies"`:

```json
"mjml": "^4.15.0"
```

```bash
composer require --dev spatie/mjml-php
pnpm install
```

- [ ] **Step 4: Put a `node` binary in the dev PHP container**

`docker compose run --rm php ...` is how every artisan/Pest command in this
repo already runs (`working_dir: /app/apps/studio` in `docker-compose.yml`,
the whole repo bind-mounted). `docker/php/Dockerfile` (`php:8.5-cli`, no
Node) is the image that container runs — a different file from
`docker/app/Dockerfile`'s production `app` stage, which stays exactly as it
is (still no Node in anything that gets deployed).

In `docker/php/Dockerfile`, add `nodejs` to the existing apt package list and
enable corepack right after it (so `pnpm` resolves once `apps/studio/package.json`
is installed on the bind-mounted volume — the same `mjml` devDependency
Step 3 added):

```dockerfile
        postgresql-client-18 \
        libvips-tools \
        libheif-plugin-aomenc \
        # mail:build (App\Notifications\Console\Commands\BuildMailTemplatesCommand)
        # shells out to a Node script bundled inside spatie/mjml-php — the
        # only reason Node is in this dev image at all.
        nodejs \
    && corepack enable \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-avif \
```

(`docker-php-ext-configure` and everything after it is the existing line —
only the two lines above it and `corepack enable` are new.)

```bash
docker compose build php
```

- [ ] **Step 5: Write the build command**

```php
<?php

// app/Notifications/Console/Commands/BuildMailTemplatesCommand.php

namespace App\Notifications\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Spatie\Mjml\Mjml;
use Spatie\Mjml\ValidationLevel;

/**
 * Compiles resources/mail/*.mjml into the checked-in Blade views under
 * resources/views/vendor/mail/html — see docs/superpowers/specs/
 * 2026-09-05-studio-onboarding-mail-journey-design.md §1 for why this runs
 * at build time (dev machine, CI) and never at request time.
 */
class BuildMailTemplatesCommand extends Command
{
    protected $signature = 'mail:build';

    protected $description = 'Compiles the MJML mail sources into the checked-in Blade layout and button views.';

    // Single-pass, simultaneous replacement (strtr with an array never
    // rescans its own output), so no replacement text may itself contain
    // another sentinel — none of these do.
    private const SENTINELS = [
        '__SLOT__' => '{{ $slot }}',
        '__HEADER__' => '{{ $header ?? \'\' }}',
        '__FOOTER__' => '{{ $footer ?? \'\' }}',
        '__SUBCOPY__' => '{{ $subcopy ?? \'\' }}',
        '__BRAND__' => '{{ $brand ?? config(\'app.name\') }}',
        '__LOGO_URL__' => '{{ $logoUrl ?? null }}',
        '__LOGO_IF__' => '@if(! empty($logoUrl))',
        '__LOGO_ENDIF__' => '@endif',
        '__ACCENT__' => '{{ $accent ?? \'#111111\' }}',
        '__URL__' => '{{ $url }}',
    ];

    public function handle(): int
    {
        $sourceDir = resource_path('mail');
        $outputDir = resource_path('views/vendor/mail/html');

        File::ensureDirectoryExists($outputDir);

        foreach (File::files($sourceDir) as $file) {
            if ($file->getExtension() !== 'mjml') {
                continue;
            }

            $result = Mjml::new()
                ->validationLevel(ValidationLevel::Skip)
                ->convert(File::get($file->getPathname()));

            if ($result->hasErrors()) {
                foreach ($result->errors() as $error) {
                    $this->error($error->formattedMessage());
                }

                return self::FAILURE;
            }

            $blade = strtr($result->html(), self::SENTINELS);

            File::put($outputDir.'/'.$file->getFilenameWithoutExtension().'.blade.php', $blade);
        }

        return self::SUCCESS;
    }
}
```

- [ ] **Step 6: Run the build**

```bash
docker compose run --rm php php artisan mail:build
```

Expected: `apps/studio/resources/views/vendor/mail/html/layout.blade.php` and `button.blade.php` now exist and contain the `{{ }}`/`@if` replacements from Step 5, not the sentinel tokens. If it fails with a "node: command not found"-shaped error, `MJML_NODE_PATH` needs to point at wherever `node` actually lives in that container (see the package's README section "Specifying the path to nodejs executable") — Step 4's `nodejs` apt package should already put it on `PATH`, so this would mean Step 4 was skipped or the image wasn't rebuilt.

- [ ] **Step 7: Write the failing proof test**

This is the spec's "Zuerst zu beweisen" for §1: that Laravel accepts a
partially-published component set and falls back to its own `message`,
`header`, `footer` for an **unmodified** notification (`StaffInvited`, not
yet touched by this plan).

```php
<?php

// apps/studio/tests/Feature/Notifications/MailLayoutTest.php

use App\Enums\StaffRole;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Notifications\StaffInvited;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders an unmodified notification through the compiled layout without error', function () {
    $owner = User::factory()->create(['role' => StaffRole::Owner]);
    $invitation = StaffInvitation::factory()->create(['invited_by' => $owner->id]);

    $html = (new StaffInvited($invitation, 'https://example.test/einladung/abc', app(StudioSettingsResolver::class)))
        ->toMail($owner)
        ->render();

    expect((string) $html)
        ->toContain('Zugang einrichten')
        ->and((string) $html)->not->toBeEmpty();
});
```

- [ ] **Step 8: Run it, confirm it fails only if the files are missing**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Notifications/MailLayoutTest.php`

Expected at this point: PASS (Steps 1–6 already produced the compiled files — this step is the proof-of-life check, not a red/green cycle, since there is no old behavior to break here). If it fails, read the exception: a missing `mail::html.*` view means Step 6 was skipped or the sentinel replacement produced invalid Blade.

- [ ] **Step 9: Commit**

```bash
git add apps/studio/resources/mail apps/studio/app/Notifications/Console/Commands/BuildMailTemplatesCommand.php \
  apps/studio/resources/views/vendor/mail apps/studio/composer.json apps/studio/composer.lock \
  apps/studio/package.json pnpm-lock.yaml docker/php/Dockerfile \
  apps/studio/tests/Feature/Notifications/MailLayoutTest.php
git commit -m "feat(mail): compile MJML layout and button at build time"
```

---

### Task 2: Two-brand rendering (composer + product/blocks views)

**Files:**
- Modify: `apps/studio/app/Providers/AppServiceProvider.php`
- Create: `apps/studio/resources/views/mail/product-message.blade.php`
- Create: `apps/studio/resources/views/mail/blocks-message.blade.php`
- Test: `apps/studio/tests/Feature/Notifications/TwoBrandMailTest.php`

**Interfaces:**
- Consumes: `App\Settings\Support\StudioSettingsResolver::displayName(): ?string`, `::logoPath(): ?string`, `::attributes(): array` (has `accent_color`); `config('portal.url')`.
- Produces: `mail.product-message` (data: `brand` required, `greeting`/`introLines`/`actionText`/`actionUrl`/`outroLines`/`salutation` from `MailMessage`'s own built-ins) and `mail.blocks-message` (same, plus optional `brand` and `blocks` — a list of `['heading' => string, 'body' => string, 'buttonLabel' => string, 'buttonUrl' => string]`). Later tasks call these via `MailMessage::view($view, $data)`.

This is the spec's "Zuerst zu beweisen" for §2 — proven here with disposable
test-double notifications (mirroring the existing convention in
`NotificationServiceTest`), not yet with the real business notifications
(those don't exist until Tasks 3/5/6/8).

- [ ] **Step 1: Write the failing test**

```php
<?php

// apps/studio/tests/Feature/Notifications/TwoBrandMailTest.php

use App\Enums\StaffRole;
use App\Models\User;
use App\Settings\Models\StudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

class TwoBrandProductDemo extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->view('mail.product-message', ['brand' => 'Auri CRM'])
            ->greeting('Hallo Nina,')
            ->line('Testzeile.');
    }
}

it('renders a product mail with the fixed product brand, not the studio one', function () {
    StudioSettings::query()->update(['display_name' => 'Studio Nordlicht']);
    Cache::store('redis')->flush();

    $html = (string) (new TwoBrandProductDemo)->toMail(User::factory()->create())->render();

    expect($html)->toContain('Auri CRM')->and($html)->not->toContain('Studio Nordlicht');
});

it("renders the studio's own brand when a mail does not pass one explicitly", function () {
    StudioSettings::query()->update(['display_name' => 'Studio Nordlicht']);
    Cache::store('redis')->flush();

    $html = (string) (new class extends Notification
    {
        public function via(object $notifiable): array
        {
            return ['mail'];
        }

        public function toMail(object $notifiable): MailMessage
        {
            return (new MailMessage)->greeting('Hallo Nina,')->line('Testzeile.');
        }
    })->toMail(User::factory()->create())->render();

    expect($html)->toContain('Studio Nordlicht');
});
```

- [ ] **Step 2: Run it, confirm it fails**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Notifications/TwoBrandMailTest.php`

Expected: FAIL — `mail.product-message` view does not exist yet, and no composer sets `$brand` for the second case either (the compiled layout falls back to `config('app.name')`, which is unset in this app, so the first assertion of the second test fails too).

- [ ] **Step 3: Register the layout composer**

In `apps/studio/app/Providers/AppServiceProvider.php`, add to `boot()` (near the existing `Blueprint::macro` calls):

```php
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Support\Facades\View;
```

```php
// Studio-branded by default: every existing notification's mail keeps
// working unmodified, because this only fires when nobody has already
// passed an explicit brand. The three product mails (and StaffInvited's
// shared blocks view) set 'brand' explicitly and this returns immediately —
// see resources/views/mail/{product,blocks}-message.blade.php.
View::composer('mail::html.layout', function ($view) {
    if (array_key_exists('brand', $view->getData())) {
        return;
    }

    $settings = app(StudioSettingsResolver::class);

    $view->with([
        'brand' => $settings->displayName() ?? config('app.name'),
        'logoUrl' => $settings->logoPath()
            ? rtrim((string) config('portal.url'), '/').'/branding/logo'
            : null,
    ]);
});

// Accent is not brand-differentiated (spec §2's table has no Accent row):
// every mail, product or studio, shows the current tenant's own accent, with
// a neutral fallback before any studio has set one.
View::composer('mail::html.button', function ($view) {
    $accent = app(StudioSettingsResolver::class)->attributes()['accent_color'] ?? null;
    $view->with('accent', $accent ?: '#111111');
});
```

- [ ] **Step 4: Write the product-message view**

```blade
{{-- apps/studio/resources/views/mail/product-message.blade.php --}}
<x-mail::layout :brand="$brand">
    <x-slot:header></x-slot:header>

    @if (! empty($greeting))
        <p>{{ $greeting }}</p>
    @endif

    @foreach ($introLines as $line)
        <p>{{ $line }}</p>
    @endforeach

    @isset($actionText)
        <x-mail::button :url="$actionUrl">{{ $actionText }}</x-mail::button>
    @endisset

    @foreach ($outroLines as $line)
        <p>{{ $line }}</p>
    @endforeach

    @if (! empty($salutation))
        <p>{{ $salutation }}</p>
    @endif

    <x-slot:footer>
        <p>Auri CRM ist Open Source. Rückmeldungen landen auf GitHub: <a href="https://github.com/czepter/auri-crm/issues">github.com/czepter/auri-crm/issues</a></p>
    </x-slot:footer>
</x-mail::layout>
```

- [ ] **Step 5: Write the blocks-message view**

```blade
{{-- apps/studio/resources/views/mail/blocks-message.blade.php --}}
<x-mail::layout @if(isset($brand)) :brand="$brand" @endif>
    <x-slot:header></x-slot:header>

    @if (! empty($greeting))
        <p>{{ $greeting }}</p>
    @endif

    @foreach ($introLines as $line)
        <p>{{ $line }}</p>
    @endforeach

    @isset($actionText)
        <x-mail::button :url="$actionUrl">{{ $actionText }}</x-mail::button>
    @endisset

    @foreach ($blocks ?? [] as $block)
        <hr>
        <p><strong>{{ $block['heading'] }}</strong></p>
        <p>{!! $block['body'] !!}</p>
        <x-mail::button :url="$block['buttonUrl']">{{ $block['buttonLabel'] }}</x-mail::button>
    @endforeach

    @foreach ($outroLines as $line)
        <p>{{ $line }}</p>
    @endforeach

    @if (! empty($salutation))
        <p>{{ $salutation }}</p>
    @endif

    <x-slot:footer>
        @isset($brand)
            <p>Auri CRM ist Open Source. Rückmeldungen landen auf GitHub: <a href="https://github.com/czepter/auri-crm/issues">github.com/czepter/auri-crm/issues</a></p>
        @endisset
    </x-slot:footer>
</x-mail::layout>
```

`$block['body']` is written raw (`{!! !!}`) because the notification classes
that build it write trusted, hand-authored HTML (e.g. `<strong>`) directly —
never user input.

- [ ] **Step 6: Run the test again, confirm it passes**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Notifications/TwoBrandMailTest.php`

Expected: PASS.

- [ ] **Step 7: Re-run Task 1's test to confirm no regression**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Notifications/MailLayoutTest.php`

Expected: PASS — `StaffInvited` still renders unmodified through the same layout, now with the composer active.

- [ ] **Step 8: Commit**

```bash
git add apps/studio/app/Providers/AppServiceProvider.php apps/studio/resources/views/mail \
  apps/studio/tests/Feature/Notifications/TwoBrandMailTest.php
git commit -m "feat(mail): two-brand layout via composer and explicit-brand views"
```

---

### Task 3: Email verification — model, notification, controller, routes

**Files:**
- Create: `apps/studio/database/migrations/2026_09_05_100100_add_email_verified_at_to_users_table.php`
- Create: `apps/studio/database/migrations/2026_09_05_100200_create_user_email_verifications_table.php`
- Create: `apps/studio/app/Models/UserEmailVerification.php`
- Create: `apps/studio/app/Notifications/Studio/StudioEmailVerification.php`
- Create: `apps/studio/app/Http/Controllers/Staff/EmailVerificationController.php`
- Modify: `apps/studio/app/Http/Controllers/Staff/SetupController.php`
- Modify: `apps/studio/routes/admin.php`
- Modify: `apps/studio/lang/de/auth.php`
- Test: `apps/studio/tests/Feature/Staff/EmailVerificationTest.php`

**Interfaces:**
- Consumes: `App\Notifications\NotificationService::send(mixed $notifiable, Notification $notification): void`; `mail.product-message` view from Task 2.
- Produces: `UserEmailVerification::hash(string $value): string`, `::forToken(string $token): ?self`, `::forUser(User $user): ?self`, `->matchesCode(string $code): bool`, constants `VALID_FOR_MINUTES = 30`, `MAX_ATTEMPTS = 5` — all consumed only inside this task, but the shape mirrors `UserEmailChange` for anyone touching either later.

- [ ] **Step 1: Write the failing tests**

```php
<?php

// apps/studio/tests/Feature/Staff/EmailVerificationTest.php

use App\Enums\StaffRole;
use App\Models\User;
use App\Models\UserEmailVerification;
use App\Notifications\Studio\StudioEmailVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function createOwner(): User
{
    test()->post('/einrichtung', [
        'name' => 'Christian Zepter',
        'email' => 'christian@nordlicht.test',
        'password' => 'ein-sehr-langes-passwort',
        'password_confirmation' => 'ein-sehr-langes-passwort',
    ]);

    return User::where('email', 'christian@nordlicht.test')->sole();
}

it('sends a verification mail on setup', function () {
    Notification::fake();

    $user = createOwner();

    Notification::assertSentTo($user, StudioEmailVerification::class);
});

it('consumes the record and stamps the user via the link', function () {
    $user = createOwner();
    $verification = UserEmailVerification::where('user_id', $user->id)->sole();
    $token = 'known-test-token';
    $verification->forceFill(['token_hash' => UserEmailVerification::hash($token)])->save();

    $this->get("/e-mail-bestaetigen/{$token}")->assertRedirect('/login');

    expect($user->fresh()->email_verified_at)->not->toBeNull()
        ->and($verification->fresh()->consumed_at)->not->toBeNull();
});

it('consumes the record and stamps the user via the right code', function () {
    $user = createOwner();
    $verification = UserEmailVerification::where('user_id', $user->id)->sole();
    $verification->forceFill(['code_hash' => UserEmailVerification::hash('123456')])->save();

    $this->actingAs($user, 'staff')
        ->post('/einrichtung/e-mail-bestaetigen', ['code' => '123456'])
        ->assertRedirect();

    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

it('rejects a wrong code and counts the attempt', function () {
    $user = createOwner();
    $verification = UserEmailVerification::where('user_id', $user->id)->sole();
    $verification->forceFill(['code_hash' => UserEmailVerification::hash('123456')])->save();

    $this->actingAs($user, 'staff')
        ->post('/einrichtung/e-mail-bestaetigen', ['code' => '000000'])
        ->assertSessionHasErrors('code');

    expect($verification->fresh()->attempts)->toBe(1)
        ->and($user->fresh()->email_verified_at)->toBeNull();
});

it('locks out the sixth attempt even with the right code', function () {
    $user = createOwner();
    $verification = UserEmailVerification::where('user_id', $user->id)->sole();
    $verification->forceFill(['code_hash' => UserEmailVerification::hash('123456'), 'attempts' => 5])->save();

    $this->actingAs($user, 'staff')
        ->post('/einrichtung/e-mail-bestaetigen', ['code' => '123456'])
        ->assertSessionHasErrors('code');

    expect($user->fresh()->email_verified_at)->toBeNull();
});

it('treats an expired record the same as a consumed or missing one', function () {
    $user = createOwner();
    $verification = UserEmailVerification::where('user_id', $user->id)->sole();
    $token = 'known-test-token';
    $verification->forceFill(['token_hash' => UserEmailVerification::hash($token), 'expires_at' => now()->subMinute()])->save();

    $this->get("/e-mail-bestaetigen/{$token}")->assertRedirect('/login')->assertSessionHas('status');

    expect($user->fresh()->email_verified_at)->toBeNull();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Staff/EmailVerificationTest.php`

Expected: FAIL — `UserEmailVerification`, the routes, the controller and the `SetupController` wiring don't exist yet.

- [ ] **Step 3: Write the migrations**

```php
<?php

// database/migrations/2026_09_05_100100_add_email_verified_at_to_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestampTz('email_verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_verified_at');
        });
    }
};
```

```php
<?php

// database/migrations/2026_09_05_100200_create_user_email_verifications_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_email_verifications', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash')->unique();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampsTz();
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_email_verifications');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php

// app/Models/UserEmailVerification.php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEmailVerification extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    public const VALID_FOR_MINUTES = 30;

    public const MAX_ATTEMPTS = 5;

    protected $fillable = ['user_id', 'token_hash', 'code_hash', 'expires_at'];

    protected $hidden = ['token_hash', 'code_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeLive(Builder $query): void
    {
        $query->whereNull('consumed_at')->where('expires_at', '>', now());
    }

    public static function hash(string $value): string
    {
        return hash('sha256', $value);
    }

    /** Uniform for expired, already used and never existed. */
    public static function forToken(string $token): ?self
    {
        return static::query()->live()->where('token_hash', self::hash($token))->first();
    }

    /** The current live record for this user, if any — attempts are checked against it by the caller. */
    public static function forUser(User $user): ?self
    {
        return static::query()->live()->where('user_id', $user->id)->latest('id')->first();
    }

    public function matchesCode(string $code): bool
    {
        return hash_equals($this->code_hash, self::hash($code));
    }
}
```

- [ ] **Step 5: Add the two uniform-failure strings**

In `apps/studio/lang/de/auth.php`, add (after the existing `email_taken` line, before the closing `];`):

```php
    // Uniform for a wrong code, an expired one and one already used up on
    // attempts — which of those it was is not something to tell the browser.
    'email_verification_invalid' => 'Dieser Code ist nicht gültig.',
    'email_verification_link_dead' => 'Dieser Link gilt nicht mehr.',
```

- [ ] **Step 6: Write the notification**

```php
<?php

// app/Notifications/Studio/StudioEmailVerification.php

namespace App\Notifications\Studio;

use App\Models\UserEmailVerification;
use App\Notifications\Contracts\Transactional;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudioEmailVerification extends Notification implements Transactional
{
    public function __construct(
        private readonly string $name,
        private readonly string $code,
        private readonly string $url,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Bestätige deine E-Mail-Adresse')
            ->view('mail.product-message', ['brand' => 'Auri CRM'])
            ->greeting("Hallo {$this->name},")
            ->line('bitte bestätige die E-Mail-Adresse deines Auri-CRM-Kontos mit diesem Code:')
            ->line($this->code)
            ->line('Der Code gilt '.UserEmailVerification::VALID_FOR_MINUTES.' Minuten.')
            ->action('E-Mail-Adresse bestätigen', $this->url)
            ->line('Oder diesen Link öffnen:')
            ->line($this->url);
    }
}
```

- [ ] **Step 7: Write the controller**

```php
<?php

// app/Http/Controllers/Staff/EmailVerificationController.php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\UserEmailVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class EmailVerificationController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 300;

    public function confirm(string $token): RedirectResponse
    {
        $verification = UserEmailVerification::forToken($token);

        if ($verification === null) {
            return redirect('/login')->with('status', __('auth.email_verification_link_dead'));
        }

        $verification->forceFill(['consumed_at' => now()])->save();
        $verification->user->forceFill(['email_verified_at' => now()])->save();

        return redirect('/login')->with('status', 'E-Mail-Adresse bestätigt.');
    }

    public function confirmCode(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string']]);

        $key = 'email-verification|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'code' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        $user = $request->user('staff');
        $verification = UserEmailVerification::forUser($user);

        if ($verification === null
            || $verification->attempts >= UserEmailVerification::MAX_ATTEMPTS
            || ! $verification->matchesCode($data['code'])
        ) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            $verification?->increment('attempts');

            throw ValidationException::withMessages(['code' => __('auth.email_verification_invalid')]);
        }

        RateLimiter::clear($key);
        $verification->forceFill(['consumed_at' => now()])->save();
        $user->forceFill(['email_verified_at' => now()])->save();

        return back();
    }
}
```

- [ ] **Step 8: Register the routes**

In `apps/studio/routes/admin.php`, right after the existing
`/profil/e-mail-bestaetigen/{token}` route (the "neither guest nor staff"
one):

```php
// Same reasoning as profile.email.confirm just above: the mail is as likely
// opened on a phone with no session as in the browser that requested it.
Route::get('/e-mail-bestaetigen/{token}', [EmailVerificationController::class, 'confirm']);
```

And inside the existing `Route::middleware('audience:staff')->group(...)`
block, right after the `/einrichtung/studio/abschluss` route (before the
nested `onboarding` middleware group — this must stay reachable while
onboarding is unfinished, same as the wizard routes themselves):

```php
Route::post('/einrichtung/e-mail-bestaetigen', [EmailVerificationController::class, 'confirmCode']);
```

Add the `use App\Http\Controllers\Staff\EmailVerificationController;` import
at the top of the file alongside the other `Staff` controller imports.

- [ ] **Step 9: Wire `SetupController::store()`**

In `apps/studio/app/Http/Controllers/Staff/SetupController.php`, add the
`NotificationService` parameter and the verification record, right after the
existing `AuditLogger::record('staff.setup_completed', $user);` line and
before the `return redirect('/einrichtung/studio');`:

```php
use App\Models\UserEmailVerification;
use App\Notifications\NotificationService;
use App\Notifications\Studio\StudioEmailVerification;
```

```php
public function store(Request $request, NotificationService $notifications): RedirectResponse
{
    // ...unchanged validation and $user = DB::transaction(...) block...

    Auth::guard('staff')->login($user);
    $request->session()->regenerate();
    AuditLogger::record('staff.setup_completed', $user);

    $token = bin2hex(random_bytes(32));
    $code = (string) random_int(100000, 999999);

    UserEmailVerification::create([
        'user_id' => $user->id,
        'token_hash' => UserEmailVerification::hash($token),
        'code_hash' => UserEmailVerification::hash($code),
        'expires_at' => now()->addMinutes(UserEmailVerification::VALID_FOR_MINUTES),
    ]);

    $notifications->send($user, new StudioEmailVerification($user->name, $code, url("/e-mail-bestaetigen/{$token}")));

    return redirect('/einrichtung/studio');
}
```

(`StudioWelcome` is added to this same method in Task 5, right after the
line above — not duplicated here to avoid two tasks racing to edit the same
line; Task 5 appends below it.)

- [ ] **Step 10: Run the tests, verify they pass**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Staff/EmailVerificationTest.php`

Expected: PASS.

- [ ] **Step 11: Run the full existing setup suite to confirm no regression**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Staff/SetupTest.php tests/Feature/Staff/SetupConcurrencyTest.php`

Expected: PASS — the existing "creates the first owner…" test only asserts
the redirect and the auth state, not the absence of a queued mail.

- [ ] **Step 12: Commit**

```bash
git add apps/studio/database/migrations apps/studio/app/Models/UserEmailVerification.php \
  apps/studio/app/Notifications/Studio/StudioEmailVerification.php \
  apps/studio/app/Http/Controllers/Staff/EmailVerificationController.php \
  apps/studio/app/Http/Controllers/Staff/SetupController.php \
  apps/studio/routes/admin.php apps/studio/lang/de/auth.php \
  apps/studio/tests/Feature/Staff/EmailVerificationTest.php
git commit -m "feat(auth): verify the owner's email by link or code"
```

---

### Task 4: Onboarding wizard — code field

**Files:**
- Create: `apps/studio/resources/js/admin/pages/Auth/panes/EmailVerificationForm.vue`
- Modify: `apps/studio/resources/js/admin/pages/Auth/Onboarding.vue`
- Modify: `apps/studio/app/Settings/Http/Controllers/StudioOnboardingController.php`
- Modify: `apps/studio/resources/js/locales/de.json`

**Interfaces:**
- Consumes: `POST /einrichtung/e-mail-bestaetigen` from Task 3 (returns validation errors on the `code` field, or a redirect back on success).

No new automated test: this component is a plain Inertia form with no
branching logic of its own (mirrors `ProfileForm.vue` and its siblings,
none of which carry a unit test in this repo — they're proven end-to-end by
Playwright). The endpoint it calls is already covered by Task 3's Pest
suite.

- [ ] **Step 1: Pass `emailVerified` into the wizard's props**

In `apps/studio/app/Settings/Http/Controllers/StudioOnboardingController.php`,
add one key to the `Inertia::render('Auth/Onboarding', [...])` array in
`show()`:

```php
'emailVerified' => auth('staff')->user()->email_verified_at !== null,
```

- [ ] **Step 2: Add the two labels**

In `apps/studio/resources/js/locales/de.json`, inside the existing
`"onboarding"` object, add two entries after `"skip"`:

```json
"emailCode": "Code aus der E-Mail",
"emailConfirm": "Bestätigen"
```

- [ ] **Step 3: Write the component**

```vue
<!-- apps/studio/resources/js/admin/pages/Auth/panes/EmailVerificationForm.vue -->
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { Button } from '@/ui/button'

const form = useForm({ code: '' })

function submit() {
  // preserveState: true for the same reason Onboarding.vue's own comment
  // gives for every pane's save — without it, the redirect this endpoint
  // returns would remount the wizard and reset stepIndex to 0.
  form.post('/einrichtung/e-mail-bestaetigen', {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => form.reset('code'),
  })
}
</script>

<template>
  <form class="mb-8 rounded-md bg-muted p-4" @submit.prevent="submit">
    <Field :label="$t('onboarding.emailCode')" id="ob-code" :error="form.errors.code">
      <div class="flex gap-2.5">
        <Input
          id="ob-code"
          v-model="form.code"
          class="tabular-nums"
          type="text"
          inputmode="numeric"
          autocomplete="one-time-code"
        />
        <Button type="submit" :disabled="form.processing">{{ $t('onboarding.emailConfirm') }}</Button>
      </div>
    </Field>
  </form>
</template>
```

- [ ] **Step 4: Mount it in the wizard's first step**

In `apps/studio/resources/js/admin/pages/Auth/Onboarding.vue`:

```vue
import EmailVerificationForm from '@/admin/pages/Auth/panes/EmailVerificationForm.vue'
```

Add `emailVerified: boolean` to the `defineProps<{ ... }>()` block.

```vue
<EmailVerificationForm v-if="step() === 'profile' && !emailVerified" />
<ProfileForm v-if="step() === 'profile'" ref="paneRef" :settings="settings" @saved="advance" />
```

- [ ] **Step 5: Manual check**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Settings/OnboardingTest.php tests/Feature/Staff/EmailVerificationTest.php`

Expected: PASS — this task touches no backend logic those suites exercise,
this is a regression check only.

- [ ] **Step 6: Commit**

```bash
git add apps/studio/resources/js/admin/pages/Auth/panes/EmailVerificationForm.vue \
  apps/studio/resources/js/admin/pages/Auth/Onboarding.vue \
  apps/studio/app/Settings/Http/Controllers/StudioOnboardingController.php \
  apps/studio/resources/js/locales/de.json
git commit -m "feat(onboarding): confirm the owner's email from the wizard"
```

---

### Task 5: StudioWelcome

**Files:**
- Create: `apps/studio/app/Notifications/Studio/StudioWelcome.php`
- Modify: `apps/studio/app/Http/Controllers/Staff/SetupController.php`
- Test: extend `apps/studio/tests/Feature/Staff/EmailVerificationTest.php` (co-located: both mails come from the same `store()` call)

**Interfaces:**
- Consumes: `mail.blocks-message` view from Task 2.

- [ ] **Step 1: Write the failing test**

Append to `apps/studio/tests/Feature/Staff/EmailVerificationTest.php`:

```php
use App\Notifications\Studio\StudioWelcome;

it('also sends the welcome mail on setup', function () {
    Notification::fake();

    $user = createOwner();

    Notification::assertSentTo($user, StudioWelcome::class);
});
```

- [ ] **Step 2: Run it, confirm it fails**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Staff/EmailVerificationTest.php --filter="also sends the welcome"`

Expected: FAIL — `StudioWelcome` doesn't exist and isn't sent yet.

- [ ] **Step 3: Write the notification**

```php
<?php

// app/Notifications/Studio/StudioWelcome.php

namespace App\Notifications\Studio;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudioWelcome extends Notification
{
    public function __construct(private readonly string $name) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Willkommen bei Auri CRM')
            ->view('mail.blocks-message', [
                'brand' => 'Auri CRM',
                'blocks' => [
                    [
                        'heading' => 'Ein eigener Bereich für deine Kunden',
                        'body' => 'Angebot, Vertrag und Rechnung liegen im Kundenportal bereit. Deine Kunden melden sich <strong>ohne Passwort</strong> an, unterschreiben den Vertrag im Browser und zahlen per Überweisung oder Karte.',
                        'buttonLabel' => 'Portal einrichten',
                        'buttonUrl' => '/einstellungen/portal',
                    ],
                    [
                        'heading' => 'Galerien, die Gäste ohne Konto sehen',
                        'body' => 'Fertige Bilder gehen als Galerie raus statt als Link mit Ablaufdatum. Gäste öffnen sie <strong>ohne Konto</strong>, markieren Favoriten und laden ihre Auswahl als ZIP herunter.',
                        'buttonLabel' => 'Galerie anlegen',
                        'buttonUrl' => '/galerien/neu',
                    ],
                    [
                        'heading' => 'Anfragen und Antworten in einem Posteingang',
                        'body' => 'Dein Studio hat eine <strong>eigene Mailadresse für Anfragen</strong>. Was dort ankommt, landet im Posteingang. Antworten deiner Kunden hängen sich von selbst an den richtigen Auftrag.',
                        'buttonLabel' => 'Posteingang ansehen',
                        'buttonUrl' => '/posteingang',
                    ],
                ],
            ])
            ->greeting("Hallo {$this->name},")
            ->line('willkommen bei Auri CRM. Ab jetzt liegt alles zu deinen Aufträgen an einem Ort, von der ersten Anfrage bis zu den fertigen Bildern. Drei Funktionen, die du vielleicht noch nicht kennst:')
            ->action('Studio öffnen', '/')
            ->line('Viele Grüße')
            ->line('dein Auri-CRM-Team');
    }
}
```

- [ ] **Step 4: Wire it into `SetupController::store()`**

Directly below the `$notifications->send($user, new StudioEmailVerification(...));`
line added in Task 3:

```php
use App\Notifications\Studio\StudioWelcome;
```

```php
$notifications->send($user, new StudioWelcome($user->name));
```

- [ ] **Step 5: Run the test, verify it passes**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Staff/EmailVerificationTest.php`

Expected: PASS (all tests in the file, including the new one).

- [ ] **Step 6: Commit**

```bash
git add apps/studio/app/Notifications/Studio/StudioWelcome.php \
  apps/studio/app/Http/Controllers/Staff/SetupController.php \
  apps/studio/tests/Feature/Staff/EmailVerificationTest.php
git commit -m "feat(mail): send the welcome mail on studio setup"
```

---

### Task 6: StudioReady

**Files:**
- Create: `apps/studio/app/Notifications/Studio/StudioReady.php`
- Modify: `apps/studio/app/Settings/Http/Controllers/StudioOnboardingController.php`
- Test: `apps/studio/tests/Feature/Settings/StudioReadyMailTest.php`

**Interfaces:**
- Consumes: `App\Engagement\Support\InboundAddress::catchAll(string $displayName, string $secret): ?string`, `App\Settings\Support\StudioSettingsResolver::inboundSecret(): ?string`, `mail.blocks-message` view.

- [ ] **Step 1: Write the failing test**

```php
<?php

// apps/studio/tests/Feature/Settings/StudioReadyMailTest.php

use App\Enums\StaffRole;
use App\Notifications\Studio\StudioReady;
use App\Settings\Models\StudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    // See tests/Feature/Settings/OnboardingTest.php's own beforeEach: the
    // global hook stamps every studio as already onboarded, this test needs
    // the unfinished state to exercise complete() honestly.
    StudioSettings::query()->update(['onboarding_completed_at' => null, 'inbound_secret' => 'a-known-secret']);
    Cache::store('redis')->flush();
});

it('sends the ready mail carrying the inbound address on completion', function () {
    Notification::fake();
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')->post('/einrichtung/studio/abschluss')->assertRedirect('/');

    Notification::assertSentTo($owner, function (StudioReady $notification) use ($owner) {
        $html = (string) $notification->toMail($owner)->render();

        return str_contains($html, '@');
    });
});
```

- [ ] **Step 2: Run it, confirm it fails**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Settings/StudioReadyMailTest.php`

Expected: FAIL — `StudioReady` doesn't exist.

- [ ] **Step 3: Write the notification**

```php
<?php

// app/Notifications/Studio/StudioReady.php

namespace App\Notifications\Studio;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudioReady extends Notification
{
    public function __construct(
        private readonly string $name,
        private readonly ?string $inboundAddress,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Deine Adressen bei Auri CRM')
            ->view('mail.blocks-message', [
                'brand' => 'Auri CRM',
                'blocks' => [
                    [
                        'heading' => 'Anfragen per Mail',
                        'body' => ($this->inboundAddress ?? '').'<br>Die kannst du auf deiner Website verlinken.',
                        'buttonLabel' => 'Posteingang öffnen',
                        'buttonUrl' => '/posteingang',
                    ],
                    [
                        'heading' => 'Dein Kundenportal',
                        'body' => config('portal.url').'<br>Gebuchte Kunden bekommen ihren Zugang per Mail.',
                        'buttonLabel' => 'Portal ansehen',
                        'buttonUrl' => config('portal.url'),
                    ],
                ],
            ])
            ->greeting("Hallo {$this->name},")
            ->line('die Einrichtung steht. Dein Studio hat dabei zwei eigene Adressen bekommen.')
            ->action('Erste Anfrage anlegen', '/anfragen/neu')
            ->line('Viele Grüße')
            ->line('Auri CRM');
    }
}
```

- [ ] **Step 4: Wire it into `StudioOnboardingController::complete()`**

```php
use App\Engagement\Support\InboundAddress;
use App\Notifications\NotificationService;
use App\Notifications\Studio\StudioReady;
```

```php
public function complete(StudioSettingsResolver $settings, NotificationService $notifications): RedirectResponse
{
    $this->authorizeSettings();

    $owner = auth('staff')->user();

    $settings->set('onboarding_completed_at', now(), $owner);

    $inboundAddress = $settings->inboundSecret() === null
        ? null
        : InboundAddress::catchAll($settings->displayName() ?? '', $settings->inboundSecret());

    $notifications->send($owner, new StudioReady($owner->name, $inboundAddress));

    return redirect('/');
}
```

- [ ] **Step 5: Run the test, verify it passes**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Settings/StudioReadyMailTest.php`

Expected: PASS.

- [ ] **Step 6: Run the existing onboarding suite to confirm no regression**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Settings/OnboardingTest.php`

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add apps/studio/app/Notifications/Studio/StudioReady.php \
  apps/studio/app/Settings/Http/Controllers/StudioOnboardingController.php \
  apps/studio/tests/Feature/Settings/StudioReadyMailTest.php
git commit -m "feat(mail): send the ready mail on onboarding completion"
```

---

### Task 7: Reminder — migration, action, notification, command, schedule

**Files:**
- Create: `apps/studio/database/migrations/2026_09_05_100300_add_onboarding_reminder_sent_at_to_studio_settings_table.php`
- Create: `apps/studio/app/Notifications/Studio/StudioSetupPending.php`
- Create: `apps/studio/app/Notifications/Actions/RemindOnboarding.php`
- Create: `apps/studio/app/Notifications/Console/Commands/RemindOnboardingCommand.php`
- Modify: `apps/studio/app/Settings/Support/StudioSettingsResolver.php`
- Modify: `apps/studio/routes/console.php`
- Test: `apps/studio/tests/Feature/Notifications/RemindOnboardingTest.php`

**Interfaces:**
- Consumes: `App\Notifications\NotificationService::send()`; `App\Settings\Support\StudioSettingsResolver::set(string $key, mixed $value, ?User $actor): void`.
- Produces: `App\Settings\Support\StudioSettingsResolver::onboardingReminderSent(): bool`; `App\Notifications\Actions\RemindOnboarding::__invoke(): int` (count of mails sent — 0 or 1, one owner per tenant).

- [ ] **Step 1: Write the migration**

```php
<?php

// database/migrations/2026_09_05_100300_add_onboarding_reminder_sent_at_to_studio_settings_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->timestampTz('onboarding_reminder_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn('onboarding_reminder_sent_at');
        });
    }
};
```

- [ ] **Step 2: Write the failing test**

```php
<?php

// apps/studio/tests/Feature/Notifications/RemindOnboardingTest.php

use App\Enums\StaffRole;
use App\Models\User;
use App\Notifications\Actions\RemindOnboarding;
use App\Notifications\Studio\StudioSetupPending;
use App\Settings\Models\StudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    StudioSettings::query()->update(['onboarding_completed_at' => null, 'onboarding_reminder_sent_at' => null]);
    Cache::store('redis')->flush();
});

it('reminds the earliest owner once the studio is unfinished after two days', function () {
    Notification::fake();
    $owner = User::factory()->create(['role' => StaffRole::Owner, 'created_at' => now()->subDays(3)]);

    $count = app(RemindOnboarding::class)();

    expect($count)->toBe(1);
    Notification::assertSentTo($owner, StudioSetupPending::class);
    expect(StudioSettings::query()->sole()->onboarding_reminder_sent_at)->not->toBeNull();
});

it('sends nothing before two days have passed', function () {
    Notification::fake();
    User::factory()->create(['role' => StaffRole::Owner, 'created_at' => now()->subHours(12)]);

    expect(app(RemindOnboarding::class)())->toBe(0);
    Notification::assertNothingSent();
});

it('sends nothing once onboarding is complete', function () {
    Notification::fake();
    User::factory()->create(['role' => StaffRole::Owner, 'created_at' => now()->subDays(3)]);
    StudioSettings::query()->update(['onboarding_completed_at' => now()]);
    Cache::store('redis')->flush();

    expect(app(RemindOnboarding::class)())->toBe(0);
    Notification::assertNothingSent();
});

it('does not send a second reminder on a repeated run', function () {
    Notification::fake();
    User::factory()->create(['role' => StaffRole::Owner, 'created_at' => now()->subDays(3)]);

    app(RemindOnboarding::class)();
    $count = app(RemindOnboarding::class)();

    expect($count)->toBe(0);
    Notification::assertSentToTimes(User::sole(), StudioSetupPending::class, 1);
});

it('registers the reminder on the daily schedule', function () {
    $schedule = app(Illuminate\Console\Scheduling\Schedule::class);
    $events = collect($schedule->events())
        ->filter(fn ($event) => str_contains($event->command ?? '', 'notifications:remind-onboarding'));

    expect($events)->not->toBeEmpty()
        ->and($events->first()->expression)->toBe('0 9 * * *');
});
```

- [ ] **Step 3: Run the tests, verify they fail**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Notifications/RemindOnboardingTest.php`

Expected: FAIL — none of the new classes exist.

- [ ] **Step 4: Add the resolver getter**

In `apps/studio/app/Settings/Support/StudioSettingsResolver.php`, right after
the existing `onboardingCompleted()` method:

```php
/** Whether App\Notifications\Actions\RemindOnboarding has already sent its one nudge. */
public function onboardingReminderSent(): bool
{
    return $this->row()->onboarding_reminder_sent_at !== null;
}
```

- [ ] **Step 5: Write the notification**

```php
<?php

// app/Notifications/Studio/StudioSetupPending.php

namespace App\Notifications\Studio;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudioSetupPending extends Notification
{
    public function __construct(private readonly string $name) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Noch ein paar Einstellungen')
            ->view('mail.product-message', ['brand' => 'Auri CRM'])
            ->greeting("Hallo {$this->name},")
            ->line('um mit Auri CRM richtig durchzustarten, fehlen dir noch ein paar Einstellungen.')
            ->action('Weiter einrichten', '/einrichtung/studio');
    }
}
```

- [ ] **Step 6: Write the action**

```php
<?php

// app/Notifications/Actions/RemindOnboarding.php

namespace App\Notifications\Actions;

use App\Enums\StaffRole;
use App\Models\User;
use App\Notifications\NotificationService;
use App\Notifications\Studio\StudioSetupPending;
use App\Settings\Support\StudioSettingsResolver;

/**
 * The two-day nudge, run daily. Only ever fires once per studio —
 * onboarding_reminder_sent_at is a one-way flag, the same pattern
 * payment_installments.overdue_notified_at already uses.
 */
class RemindOnboarding
{
    public function __construct(
        private readonly StudioSettingsResolver $settings,
        private readonly NotificationService $notifications,
    ) {}

    public function __invoke(): int
    {
        if ($this->settings->onboardingCompleted() || $this->settings->onboardingReminderSent()) {
            return 0;
        }

        $owner = User::query()->where('role', StaffRole::Owner)->oldest('created_at')->first();

        if ($owner === null || $owner->created_at->gt(now()->subDays(2))) {
            return 0;
        }

        $this->notifications->send($owner, new StudioSetupPending($owner->name));
        $this->settings->set('onboarding_reminder_sent_at', now(), null);

        return 1;
    }
}
```

- [ ] **Step 7: Write the command**

```php
<?php

// app/Notifications/Console/Commands/RemindOnboardingCommand.php

namespace App\Notifications\Console\Commands;

use App\Notifications\Actions\RemindOnboarding;
use Illuminate\Console\Command;

class RemindOnboardingCommand extends Command
{
    protected $signature = 'notifications:remind-onboarding';

    protected $description = 'Sends the setup-pending reminder to a studio whose owner has not finished onboarding within two days.';

    public function handle(RemindOnboarding $remind): int
    {
        $count = $remind();

        $this->info($count > 0 ? 'Sent the onboarding reminder.' : 'Nothing to remind.');

        return self::SUCCESS;
    }
}
```

- [ ] **Step 8: Register the schedule**

In `apps/studio/routes/console.php`, after the existing
`Schedule::command('notifications:send-digests')->dailyAt('07:00');` line:

```php
// The onboarding nudge — see App\Notifications\Actions\RemindOnboarding for
// the three-condition guard. 09:00 so it lands with the rest of the morning
// mail, not overnight.
Schedule::command('notifications:remind-onboarding')->dailyAt('09:00');
```

- [ ] **Step 9: Run the tests, verify they pass**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Notifications/RemindOnboardingTest.php`

Expected: PASS.

- [ ] **Step 10: Commit**

```bash
git add apps/studio/database/migrations apps/studio/app/Notifications/Studio/StudioSetupPending.php \
  apps/studio/app/Notifications/Actions/RemindOnboarding.php \
  apps/studio/app/Notifications/Console/Commands/RemindOnboardingCommand.php \
  apps/studio/app/Settings/Support/StudioSettingsResolver.php \
  apps/studio/routes/console.php apps/studio/tests/Feature/Notifications/RemindOnboardingTest.php
git commit -m "feat(mail): remind an unfinished studio after two days"
```

---

### Task 8: StaffInvited — new copy through the shared blocks view

**Files:**
- Modify: `apps/studio/app/Notifications/StaffInvited.php`
- Modify: `apps/studio/tests/Feature/Staff/InvitationTest.php` (only if it asserts old copy — check first)

**Interfaces:**
- Consumes: `mail.blocks-message` view from Task 2 (studio path — no `brand` passed, falls through to the layout composer).

This is the spec's own note: "die eine Bestandsmail, die mit überarbeitet
wurde" — replacing the 2FA line (`InvitationController` already made MFA
opt-in; see spec §"Beobachtet, nicht im Umfang") with two feature blocks.

- [ ] **Step 1: Read the current test to know what it asserts**

```bash
grep -n "richtest\|Kalender\|Antworten" tests/Feature/Staff/InvitationTest.php app/Notifications/*/MailIdentity*.php 2>/dev/null
```

If either file asserts the literal string "Danach richtest du die
Zwei-Faktor-Bestätigung ein.", note the line — it needs to change in Step 3.
Per the spec, `MailIdentityNotificationTest` and `InvitationTest` check
sender and footer, not this line, so this is expected to find nothing to
change beyond what Step 3 touches.

- [ ] **Step 2: Write the failing test**

Append to `apps/studio/tests/Feature/Staff/InvitationTest.php`:

```php
use App\Enums\StaffRole;
use App\Models\StaffInvitation;
use App\Notifications\StaffInvited;
use App\Settings\Support\StudioSettingsResolver;

it('renders the calendar and reply blocks, and drops the 2FA line', function () {
    $owner = User::factory()->create(['role' => StaffRole::Owner]);
    $invitation = StaffInvitation::factory()->create(['invited_by' => $owner->id]);

    $html = (string) (new StaffInvited($invitation, 'https://example.test/einladung/abc', app(StudioSettingsResolver::class)))
        ->toMail($owner)
        ->render();

    expect($html)
        ->toContain('Kalender abonnieren')
        ->toContain('Antworten am Auftrag')
        ->not->toContain('Zwei-Faktor');
});
```

- [ ] **Step 3: Run it, confirm it fails**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Staff/InvitationTest.php --filter="calendar and reply"`

Expected: FAIL — current `StaffInvited` still renders the 2FA line and has no blocks.

- [ ] **Step 4: Rewrite `toMail()`**

```php
<?php

// app/Notifications/StaffInvited.php — toMail() replaced, rest unchanged

public function toMail(object $notifiable): MailMessage
{
    $studio = $this->settings->displayName();

    $message = (new MailMessage)
        ->subject($studio === null ? 'Dein Zugang zum Studio' : "Dein Zugang zu {$studio}")
        ->view('mail.blocks-message', [
            'blocks' => [
                [
                    'heading' => 'Deine Termine im eigenen Kalender',
                    'body' => 'Die Shootings, für die du eingeteilt bist, kannst du in deiner Kalender-App abonnieren.',
                    'buttonLabel' => 'Kalender abonnieren',
                    'buttonUrl' => '/einstellungen/kalender',
                ],
                [
                    'heading' => 'Antworten am Auftrag',
                    'body' => 'Kundenantworten landen am Auftrag, auch wenn der Kunde aus seinem eigenen Mailprogramm schreibt.',
                    'buttonLabel' => 'Zum Studio',
                    'buttonUrl' => '/',
                ],
            ],
        ])
        ->greeting("Hallo {$this->invitation->name},")
        ->line("{$this->invitation->invitedBy->name} hat dich als {$this->invitation->role->label()} eingeladen. Schön, dass du dabei bist.")
        ->action('Zugang einrichten', $this->url)
        ->line('Der Link gilt '.StaffInvitation::VALID_FOR_DAYS.' Tage.');

    return $this->appendStudioMailIdentity($message, $this->settings);
}
```

No other change to the class — `use AppliesStudioMailIdentity;`, the
constructor and `via()` stay exactly as they are.

- [ ] **Step 5: Run the test, verify it passes**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Staff/InvitationTest.php`

Expected: PASS (all tests in the file).

- [ ] **Step 6: Run the sender/footer test named in the spec**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest --filter=MailIdentity`

Expected: PASS — `appendStudioMailIdentity()` is still the last thing called
on the message, unchanged.

- [ ] **Step 7: Commit**

```bash
git add apps/studio/app/Notifications/StaffInvited.php apps/studio/tests/Feature/Staff/InvitationTest.php
git commit -m "fix(mail): replace StaffInvited's stale 2FA line with real blocks"
```

---

### Task 9: CI — fail on stale compiled mail templates

**Files:**
- Modify: `.github/workflows/ci.yml`

**Interfaces:** none (CI-only).

`mail:build` is a PHP artisan command now (Task 1), so this check belongs in
the `studio` job (PHP, Postgres, Redis already set up there) rather than the
Node-only `web` job — it just also needs Node, which that job doesn't have
yet either.

- [ ] **Step 1: Give the `studio` job a `node` + `mjml`**

In `.github/workflows/ci.yml`, inside the `studio` job, right after the
existing `- uses: shivammathur/setup-php@v2` step (before "Install
postgresql-client matching the service container"):

```yaml
      - uses: pnpm/action-setup@v6
      - uses: actions/setup-node@v7
        with:
          node-version: 24
          cache: pnpm
      - run: pnpm install --frozen-lockfile --filter @studio/app
```

- [ ] **Step 2: Add the diff check**

Right after the existing "Assert the OpenAPI contract is current" step, at
the end of the job — same "generate, diff, fail with instructions" shape as
that step and the TypeScript-types one above it:

```yaml
      - name: Assert compiled mail templates are current
        working-directory: apps/studio
        run: |
          php artisan mail:build
          git diff --exit-code -- resources/views/vendor/mail \
            || { echo "Compiled mail templates are stale. Run 'php artisan mail:build' in apps/studio and commit the result."; exit 1; }
```

- [ ] **Step 3: Extend the `php` path filter**

The `changes` job's `php` filter (which gates whether the `studio` job runs
at all) lists specific subpaths rather than all of `apps/studio/resources/**`
— that broader pattern is the separate `web` filter's. An MJML-only edit
later (nobody touching `app/**`) would otherwise never re-trigger this check.
In `.github/workflows/ci.yml`, inside the `php: &php` filter list, add one
line after `'apps/studio/resources/js/types/generated.d.ts'`:

```yaml
              - 'apps/studio/resources/mail/**'
```

- [ ] **Step 4: Verify the check catches drift, locally**

```bash
cd apps/studio
echo "<!-- stray -->" >> resources/views/vendor/mail/html/layout.blade.php
docker compose run --rm php php artisan mail:build
git diff --exit-code -- resources/views/vendor/mail; echo "exit: $?"
```

Expected: `mail:build` overwrites the stray edit with the real compiled
output, and the `git diff --exit-code` reports no difference (exit 0) since
the file is back to its committed state — confirming the check would have
failed (non-zero) had `mail:build` **not** been run before the diff, which is
exactly the failure mode it exists to catch.

```bash
git checkout -- resources/views/vendor/mail
```

- [ ] **Step 5: Commit**

```bash
git add .github/workflows/ci.yml
git commit -m "ci: fail when compiled mail templates are stale"
```

---

### Task 10: Full verification

**Files:** none — this task only runs the suite.

- [ ] **Step 1: Run the full Pest suite**

```bash
docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest
```

Expected: PASS, no failures, no skipped tests introduced by this plan.

- [ ] **Step 2: Run the TypeScript/lint checks**

```bash
pnpm --filter @studio/app lint
```

Expected: PASS — no `.vue`/`.ts` type errors from Task 4's new component.

- [ ] **Step 3: Confirm the compiled mail templates are checked in and current**

```bash
docker compose run --rm php php artisan mail:build
git status --porcelain -- apps/studio/resources/views/vendor/mail
```

Expected: empty output (nothing to commit — the working tree already matches
the compiled output).

- [ ] **Step 4: Re-read the spec's test list (§5) and confirm each is covered**

Cross-check against the tests written in Tasks 3, 5, 6, 7, 8 — every bullet
in the spec's "## 5 Tests" section should have a corresponding `it(...)` by
name or by direct consequence (e.g. "eine Produkt-Mail trägt die
Produktmarke, StaffInvited die des Studios" is Task 2's
`TwoBrandMailTest.php`). No further code change expected here — this step is
a read-through, not an edit.

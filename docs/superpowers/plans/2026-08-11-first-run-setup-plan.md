# First-run setup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a fresh install create its first Owner account and configure its studio through the browser, with MFA opt-in instead of mandatory.

**Architecture:** Two new self-closing gates on the existing staff route group — `EnsureNoStaffExists` (404s once any user exists) fronts a new `/einrichtung` signup, and `RequireStudioOnboarding` (redirects GET requests until `studio_settings.onboarding_completed_at` is set) takes the route-group slot the removed mandatory-MFA middleware vacates. The onboarding wizard at `/einrichtung/studio` is a thin stepper over the settings forms that already exist — extracted into standalone components so both the ordinary settings screens and the wizard drive the same code.

**Tech Stack:** Laravel 11 (Pest tests), Inertia + Vue 3 `<script setup>`, PostgreSQL.

## Global Constraints

- Public-facing paths stay German (`/einrichtung`, matching `/anmelden`, `/einladung`, `/passwort`); PHP identifiers and route *names* stay English.
- Every non-public admin route must declare `audience:staff` — enforced by `tests/Feature/AudienceMatrixTest.php`. A route reachable without a session goes in `PUBLIC_ROUTES['admin']` with a comment explaining what authorises it instead.
- `AuthorizesStaffSettings::authorizeSettings()` (404, not 403) gates every settings write — reuse it, never reinvent a check.
- Every settings write goes through `StudioSettingsResolver::set()` so it is audited and the Redis cache (`Cache::store('redis')`) is invalidated — never `StudioSettings::query()->update()` directly outside that method.
- No new npm/composer dependency. Every UI piece below reuses classes already defined in `resources/css/admin.css` (`.authwrap`, `.authcard`, `.dsec`, `.dlab`, `.grp`, `.fld`, `.two`, `.wide`, `.inp`, `.err`, `.hint`, `.btn`, `.chip`, `.txtbtn`) and the Inertia `useForm` pattern already used throughout `resources/js/admin/pages/Settings/`.
- Password rules: `Illuminate\Validation\Rules\Password::defaults()`, same as `PasswordResetController` and `InvitationController`.
- Commit order matters for reviewability: pane extraction (Tasks 2–3) lands before the owner-setup/MFA changes (Tasks 4–5), which land before the onboarding wizard (Task 6) — each is a coherent, independently revertible commit.

---

### Task 1: `onboarding_completed_at` column

**Files:**
- Create: `apps/studio/database/migrations/2026_08_11_000100_add_onboarding_completed_at_to_studio_settings_table.php`
- Modify: `apps/studio/app/Settings/Models/StudioSettings.php:33-49` (fillable), `:76-91` (casts)
- Test: `apps/studio/tests/Feature/Settings/StudioSettingsOnboardingColumnTest.php`

**Interfaces:**
- Produces: `StudioSettings::$onboarding_completed_at` — nullable `Carbon` (cast `datetime`), fillable, read by Task 6's `RequireStudioOnboarding` and `StudioSettingsResolver::onboardingCompleted()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Settings\Models\StudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('starts every studio unonboarded and lets the column be written', function () {
    $row = StudioSettings::query()->sole();

    expect($row->onboarding_completed_at)->toBeNull();

    $row->update(['onboarding_completed_at' => now()]);

    expect($row->fresh()->onboarding_completed_at)->not->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php php artisan test --filter=StudioSettingsOnboardingColumnTest`
Expected: FAIL — `onboarding_completed_at` is not a mass-assignable attribute (or the column does not exist yet).

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Distinguishes "the wizard was never opened" from "display_name is
     * still blank" — display_name alone cannot tell those apart, and every
     * other studio_settings column already has a fallback or a default. See
     * App\Http\Middleware\RequireStudioOnboarding, the only reader.
     */
    public function up(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->timestampTz('onboarding_completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn('onboarding_completed_at');
        });
    }
};
```

- [ ] **Step 4: Add the column to the model**

In `apps/studio/app/Settings/Models/StudioSettings.php`, add to `$fillable` (after `'quiet_hours_enabled', 'quiet_hours_start', 'quiet_hours_end',`):

```php
        'onboarding_completed_at',
```

Add to `casts()` (after `'quiet_hours_enabled' => 'boolean',`):

```php
            'onboarding_completed_at' => 'datetime',
```

- [ ] **Step 5: Run migration and test**

Run: `docker compose exec php php artisan migrate && docker compose exec php php artisan test --filter=StudioSettingsOnboardingColumnTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add apps/studio/database/migrations/2026_08_11_000100_add_onboarding_completed_at_to_studio_settings_table.php \
  apps/studio/app/Settings/Models/StudioSettings.php \
  apps/studio/tests/Feature/Settings/StudioSettingsOnboardingColumnTest.php
git commit -m "feat(settings): add onboarding_completed_at column"
```

---

### Task 2: Extract Profil/USt/Dokumente/E-Mail panes out of `Settings/Index.vue`

**Files:**
- Create: `apps/studio/resources/js/admin/pages/Settings/panes/ProfileForm.vue`
- Create: `apps/studio/resources/js/admin/pages/Settings/panes/VatForm.vue`
- Create: `apps/studio/resources/js/admin/pages/Settings/panes/DocumentsForm.vue`
- Create: `apps/studio/resources/js/admin/pages/Settings/panes/EmailForm.vue`
- Modify: `apps/studio/resources/js/admin/pages/Settings/Index.vue` (rewritten below)
- Test: existing `apps/studio/tests/Feature/NoInternalIdsTest.php:147-153`, `apps/studio/tests/Feature/InertiaRenderingTest.php` (no new test file — this task must not change any assertion in either)

**Interfaces:**
- Each pane component: `props: { settings: App.Settings.Data.StudioSettingsData }`, `emits: { saved: [] }` (fired from the form's `onSuccess`), `defineExpose({ form, submit })` — `form` is the component's own `useForm()` return value (read by the parent for `isDirty`/`processing`/`errors`), `submit` is the component's own submit function (called by the parent's top-bar save button via the native `form="…"` attribute, and by Task 6's wizard directly through the template ref).
- Consumes (Task 6): the same four components, `settings` prop, `saved` emit, exposed `submit()`.

This is a pure extraction — no field, endpoint, or validation changes. Every `v-model`, `id`, class and error binding below is copied verbatim from the current `Settings/Index.vue`; only the wrapping `<script setup>`/`<template>` boundary and the save-button plumbing change (from one shared top button driven by a `computed` over four inline `useForm()` calls, to four self-contained components each exposing their own form).

- [ ] **Step 1: Create `panes/ProfileForm.vue`**

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

const props = defineProps<{ settings: App.Settings.Data.StudioSettingsData }>()
const emit = defineEmits<{ saved: [] }>()

const form = useForm({
  display_name: props.settings.displayName ?? '',
  legal_name: props.settings.legalName ?? '',
  legal_form: props.settings.legalForm ?? 'GmbH',
  street: props.settings.street ?? '',
  postcode: props.settings.postcode ?? '',
  city: props.settings.city ?? '',
  country: props.settings.country ?? 'Deutschland',
  tax_number: props.settings.taxNumber ?? '',
  vat_id: props.settings.vatId ?? '',
  register_number: props.settings.registerNumber ?? '',
  register_court: props.settings.registerCourt ?? '',
  representative: props.settings.representative ?? '',
  website: props.settings.website ?? '',
  accent_color: props.settings.accentColor ?? '',
  logo: null as File | null,
  bank_account_holder: props.settings.bankAccountHolder ?? '',
  bank_iban: props.settings.bankIban ?? '',
  bank_bic: props.settings.bankBic ?? '',
})

function onLogoChange(event: Event) {
  form.logo = (event.target as HTMLInputElement).files?.[0] ?? null
}

function submit() {
  // Inertia's own documented workaround: a browser cannot send a real PUT
  // or PATCH as multipart/form-data, so a file upload goes over POST with
  // `_method` spoofing the verb Laravel's router actually matches on.
  form
    .transform((data) => ({ ...data, _method: 'patch' }))
    .post('/einstellungen', { forceFormData: true, preserveScroll: true, onSuccess: () => emit('saved') })
}

defineExpose({ form, submit })
</script>

<template>
  <form id="settings-form" @submit.prevent="submit">
    <div class="dsec" style="margin-top: 0">
      <div class="dlab">{{ $t('settings.legalEntity') }}</div>
      <div class="grp">
        <div class="fld wide">
          <label for="p-firma">{{ $t('settings.legalName') }}</label>
          <input id="p-firma" v-model="form.legal_name" class="inp" type="text" />
          <p v-if="form.errors.legal_name" class="err">{{ form.errors.legal_name }}</p>
        </div>
      </div>
      <div class="grp">
        <div class="two">
          <div class="fld">
            <label for="p-rechtsform">{{ $t('settings.legalForm') }}</label>
            <select id="p-rechtsform" v-model="form.legal_form" class="inp">
              <option>Einzelunternehmen</option>
              <option>GmbH</option>
              <option>UG (haftungsbeschränkt)</option>
              <option>GbR</option>
            </select>
            <p v-if="form.errors.legal_form" class="err">{{ form.errors.legal_form }}</p>
          </div>
          <div class="fld">
            <label for="p-vertretung">{{ $t('settings.representative') }}</label>
            <input id="p-vertretung" v-model="form.representative" class="inp" type="text" />
            <p v-if="form.errors.representative" class="err">{{ form.errors.representative }}</p>
          </div>
        </div>
      </div>
      <div class="grp">
        <div class="fld wide">
          <label for="p-strasse">{{ $t('settings.street') }}</label>
          <input id="p-strasse" v-model="form.street" class="inp" type="text" />
          <p v-if="form.errors.street" class="err">{{ form.errors.street }}</p>
        </div>
      </div>
      <div class="grp">
        <div class="two">
          <div class="fld">
            <label for="p-plz">{{ $t('settings.postcode') }}</label>
            <input id="p-plz" v-model="form.postcode" class="inp num" type="text" />
            <p v-if="form.errors.postcode" class="err">{{ form.errors.postcode }}</p>
          </div>
          <div class="fld">
            <label for="p-stadt">{{ $t('settings.city') }}</label>
            <input id="p-stadt" v-model="form.city" class="inp" type="text" />
            <p v-if="form.errors.city" class="err">{{ form.errors.city }}</p>
          </div>
        </div>
      </div>
      <div class="grp">
        <div class="fld" style="max-width: 260px">
          <label for="p-land">{{ $t('settings.country') }}</label>
          <select id="p-land" v-model="form.country" class="inp">
            <option>Deutschland</option>
            <option>Österreich</option>
            <option>Schweiz</option>
          </select>
          <p v-if="form.errors.country" class="err">{{ form.errors.country }}</p>
        </div>
      </div>
      <div class="grp">
        <div class="two">
          <div class="fld">
            <label for="p-steuernr">{{ $t('settings.taxNumber') }}</label>
            <input id="p-steuernr" v-model="form.tax_number" class="inp num" type="text" />
            <p v-if="form.errors.tax_number" class="err">{{ form.errors.tax_number }}</p>
          </div>
          <div class="fld">
            <label for="p-ustid">{{ $t('settings.vatId') }} <em>{{ $t('settings.optional') }}</em></label>
            <input id="p-ustid" v-model="form.vat_id" class="inp num" type="text" />
            <p v-if="form.errors.vat_id" class="err">{{ form.errors.vat_id }}</p>
          </div>
        </div>
        <span class="hint">{{ $t('settings.taxHint') }}</span>
      </div>
      <div class="grp">
        <div class="two">
          <div class="fld">
            <label for="p-hrb">{{ $t('settings.registerNumber') }}</label>
            <input id="p-hrb" v-model="form.register_number" class="inp num" type="text" />
            <p v-if="form.errors.register_number" class="err">{{ form.errors.register_number }}</p>
          </div>
          <div class="fld">
            <label for="p-gericht">{{ $t('settings.registerCourt') }}</label>
            <input id="p-gericht" v-model="form.register_court" class="inp" type="text" />
            <p v-if="form.errors.register_court" class="err">{{ form.errors.register_court }}</p>
          </div>
        </div>
      </div>
    </div>

    <div class="dsec">
      <div class="dlab">{{ $t('settings.brand') }}</div>
      <div class="grp">
        <div class="two">
          <div class="fld">
            <label for="p-anzeigename">{{ $t('settings.displayName') }}</label>
            <input id="p-anzeigename" v-model="form.display_name" class="inp" type="text" />
            <p v-if="form.errors.display_name" class="err">{{ form.errors.display_name }}</p>
          </div>
          <div class="fld">
            <label for="p-web">{{ $t('settings.website') }}</label>
            <input id="p-web" v-model="form.website" class="inp" type="text" />
            <p v-if="form.errors.website" class="err">{{ form.errors.website }}</p>
          </div>
        </div>
      </div>
      <div class="grp">
        <div class="two">
          <div class="fld">
            <label for="p-logo">{{ $t('settings.logo') }}</label>
            <div style="display: flex; align-items: center; gap: var(--s3)">
              <div v-if="settings.logoUrl" class="galcover" style="width: 40px; height: 40px">
                <img :src="settings.logoUrl" :alt="$t('settings.logo')" style="width: 100%; height: 100%; object-fit: contain" />
              </div>
              <div v-else class="galcover empty" style="width: 40px; height: 40px">{{ $t('settings.logo') }}</div>
              <input id="p-logo" class="inp" type="file" accept="image/png" style="flex: 1" @change="onLogoChange" />
            </div>
            <span class="hint">{{ $t('settings.logoHint') }}</span>
            <p v-if="form.errors.logo" class="err">{{ form.errors.logo }}</p>
          </div>
          <div class="fld">
            <label for="p-akzent">{{ $t('settings.accentColor') }}</label>
            <div style="display: flex; align-items: center; gap: 10px">
              <span
                style="display: inline-block; width: 32px; height: 32px; border-radius: var(--r-sm); border: 1px solid var(--line); flex: none"
                :style="{ background: form.accent_color || 'transparent' }"
              ></span>
              <input id="p-akzent" v-model="form.accent_color" class="inp num" type="text" placeholder="#B44E22" />
            </div>
            <span class="hint">{{ $t('settings.accentColorHint') }}</span>
            <p v-if="form.errors.accent_color" class="err">{{ form.errors.accent_color }}</p>
          </div>
        </div>
      </div>
    </div>

    <div class="dsec">
      <div class="dlab">{{ $t('settings.bank') }}</div>
      <div class="grp">
        <div class="fld wide">
          <label for="p-kontoinhaber">{{ $t('settings.bankAccountHolder') }}</label>
          <input id="p-kontoinhaber" v-model="form.bank_account_holder" class="inp" type="text" />
          <p v-if="form.errors.bank_account_holder" class="err">{{ form.errors.bank_account_holder }}</p>
        </div>
      </div>
      <div class="grp">
        <div class="two">
          <div class="fld">
            <label for="p-iban">{{ $t('settings.bankIban') }}</label>
            <input id="p-iban" v-model="form.bank_iban" class="inp num" type="text" />
            <p v-if="form.errors.bank_iban" class="err">{{ form.errors.bank_iban }}</p>
          </div>
          <div class="fld">
            <label for="p-bic">{{ $t('settings.bankBic') }}</label>
            <input id="p-bic" v-model="form.bank_bic" class="inp num" type="text" />
            <p v-if="form.errors.bank_bic" class="err">{{ form.errors.bank_bic }}</p>
          </div>
        </div>
      </div>
    </div>
  </form>
</template>
```

- [ ] **Step 2: Create `panes/VatForm.vue`**

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { basisPointsToPercent, percentToBasisPoints } from '@/admin/lib/money'

const props = defineProps<{ settings: App.Settings.Data.StudioSettingsData }>()
const emit = defineEmits<{ saved: [] }>()

const form = useForm({
  vat_mode: props.settings.vatMode,
  vat_rate: basisPointsToPercent(props.settings.vatRate),
  vat_note: props.settings.vatNote ?? '',
})

function submit() {
  form
    .transform((data) => ({
      vat_mode: data.vat_mode,
      vat_rate: percentToBasisPoints(data.vat_rate),
      vat_note: data.vat_note || null,
    }))
    .patch('/einstellungen/ust', { preserveScroll: true, onSuccess: () => emit('saved') })
}

defineExpose({ form, submit })
</script>

<template>
  <form id="vat-form" @submit.prevent="submit">
    <div class="dsec" style="margin-top: 0">
      <div class="dlab">{{ $t('settings.vatModeLabel') }}</div>
      <div class="grp">
        <div style="display: flex; flex-direction: column; gap: var(--s3)">
          <label style="display: flex; align-items: center; gap: 10px; font: 400 13px/1.4 var(--font)">
            <input v-model="form.vat_mode" type="radio" name="vatmode" value="standard" />
            {{ $t('settings.vatModeStandard') }}
          </label>
          <label style="display: flex; align-items: center; gap: 10px; font: 400 13px/1.4 var(--font)">
            <input v-model="form.vat_mode" type="radio" name="vatmode" value="small_business" />
            {{ $t('settings.vatModeSmallBusiness') }}
          </label>
        </div>
        <p class="hint" style="margin-top: var(--s3); color: var(--accent)">{{ $t('settings.vatModeWarning') }}</p>
      </div>
      <div class="grp">
        <div class="fld" style="max-width: 160px">
          <label for="v-satz">{{ $t('settings.vatRateLabel') }}</label>
          <input id="v-satz" v-model="form.vat_rate" class="inp num" type="text" inputmode="decimal" />
          <span class="hint">{{ $t('settings.vatRateHint') }}</span>
          <p v-if="form.errors.vat_rate" class="err">{{ form.errors.vat_rate }}</p>
        </div>
      </div>
    </div>

    <div class="dsec">
      <div class="dlab">{{ $t('settings.vatNoteLabel') }}</div>
      <div class="grp">
        <div class="fld">
          <label for="v-hinweis">{{ $t('settings.vatNoteFieldLabel') }} <em>{{ $t('settings.optional') }}</em></label>
          <textarea id="v-hinweis" v-model="form.vat_note" class="inp" rows="3"></textarea>
          <span class="hint">{{ $t('settings.vatNoteHint') }}</span>
          <p v-if="form.errors.vat_note" class="err">{{ form.errors.vat_note }}</p>
        </div>
      </div>
    </div>

    <div class="dsec">
      <div class="dlab">{{ $t('settings.vatPreviewLabel') }}</div>
      <div class="card static">
        <div class="cardhead"><span class="kick">{{ $t('settings.vatPreviewKicker') }}</span></div>
        <p v-if="form.vat_note" style="margin: 0; font: 400 13px/1.5 var(--font); color: var(--ink)">
          {{ form.vat_note }}
        </p>
        <p v-else class="hint" style="margin: 0">{{ $t('settings.vatPreviewEmpty') }}</p>
      </div>
      <span class="hint" style="display: block; margin-top: var(--s3)">{{ $t('settings.vatPreviewHint') }}</span>
    </div>
  </form>
</template>
```

- [ ] **Step 3: Create `panes/DocumentsForm.vue`**

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { basisPointsToPercent, percentToBasisPoints } from '@/admin/lib/money'

const props = defineProps<{ settings: App.Settings.Data.StudioSettingsData }>()
const emit = defineEmits<{ saved: [] }>()

const form = useForm({
  quote_validity_days: props.settings.quoteValidityDays,
  payment_term_days: props.settings.paymentTermDays,
  deposit_percentage: basisPointsToPercent(props.settings.depositPercentage),
  widerruf_included: props.settings.widerrufIncluded,
  widerruf_text: props.settings.widerrufText ?? '',
})

function submit() {
  form
    .transform((data) => ({
      ...data,
      deposit_percentage: percentToBasisPoints(data.deposit_percentage),
      widerruf_text: data.widerruf_text || null,
    }))
    .patch('/einstellungen/dokumente', { preserveScroll: true, onSuccess: () => emit('saved') })
}

defineExpose({ form, submit })
</script>

<template>
  <form id="documents-form" @submit.prevent="submit">
    <div class="dsec" style="margin-top: 0">
      <div class="dlab">{{ $t('settings.documentsQuoteLabel') }}</div>
      <div class="grp">
        <div class="fld" style="max-width: 160px">
          <label for="d-gueltigkeit">{{ $t('settings.quoteValidityLabel') }}</label>
          <input id="d-gueltigkeit" v-model.number="form.quote_validity_days" class="inp num" type="number" min="0" />
          <span class="hint">{{ $t('settings.quoteValidityHint') }}</span>
          <p v-if="form.errors.quote_validity_days" class="err">{{ form.errors.quote_validity_days }}</p>
        </div>
      </div>
    </div>

    <div class="dsec">
      <div class="dlab">{{ $t('settings.documentsPaymentLabel') }}</div>
      <div class="grp">
        <div class="two">
          <div class="fld">
            <label for="d-zahlungsziel">{{ $t('settings.paymentTermLabel') }}</label>
            <input id="d-zahlungsziel" v-model.number="form.payment_term_days" class="inp num" type="number" min="0" />
            <span class="hint">{{ $t('settings.paymentTermHint') }}</span>
            <p v-if="form.errors.payment_term_days" class="err">{{ form.errors.payment_term_days }}</p>
          </div>
          <div class="fld">
            <label for="d-anzahlung">{{ $t('settings.depositPercentageLabel') }}</label>
            <input id="d-anzahlung" v-model="form.deposit_percentage" class="inp num" type="text" inputmode="decimal" />
            <span class="hint">{{ $t('settings.depositPercentageHint') }}</span>
            <p v-if="form.errors.deposit_percentage" class="err">{{ form.errors.deposit_percentage }}</p>
          </div>
        </div>
      </div>
    </div>

    <div class="dsec">
      <div class="dlab">{{ $t('settings.widerrufLabel') }}</div>
      <div class="grp">
        <label style="display: flex; align-items: center; gap: 10px; font: 400 13px/1.4 var(--font)">
          <input v-model="form.widerruf_included" type="checkbox" />
          {{ $t('settings.widerrufIncludedLabel') }}
        </label>
        <span class="hint">{{ $t('settings.widerrufIncludedHint') }}</span>
      </div>
      <div class="grp">
        <div class="fld">
          <label for="d-widerruf-text">{{ $t('settings.widerrufTextLabel') }}</label>
          <textarea
            id="d-widerruf-text"
            v-model="form.widerruf_text"
            class="inp"
            rows="5"
            :disabled="!form.widerruf_included"
          ></textarea>
          <span class="hint">{{ $t('settings.widerrufTextHint') }}</span>
          <p v-if="form.errors.widerruf_text" class="err">{{ form.errors.widerruf_text }}</p>
        </div>
      </div>
    </div>
  </form>
</template>
```

- [ ] **Step 4: Create `panes/EmailForm.vue`**

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

const props = defineProps<{ settings: App.Settings.Data.StudioSettingsData }>()
const emit = defineEmits<{ saved: [] }>()

const form = useForm({
  mail_from_name: props.settings.mailFromName,
  mail_from_address: props.settings.mailFromAddress,
  mail_reply_to: props.settings.mailReplyTo ?? '',
  mail_bcc: props.settings.mailBcc ?? '',
  mail_signature: props.settings.mailSignature ?? '',
})

function submit() {
  form
    .transform((data) => ({
      mail_from_name: data.mail_from_name || null,
      mail_from_address: data.mail_from_address || null,
      mail_reply_to: data.mail_reply_to || null,
      mail_bcc: data.mail_bcc || null,
      mail_signature: data.mail_signature || null,
    }))
    .patch('/einstellungen/email', { preserveScroll: true, onSuccess: () => emit('saved') })
}

defineExpose({ form, submit })
</script>

<template>
  <form id="mail-form" @submit.prevent="submit">
    <div class="dsec" style="margin-top: 0">
      <div class="dlab">{{ $t('settings.mailSenderLabel') }}</div>
      <div class="grp">
        <div class="two">
          <div class="fld">
            <label for="m-absendername">{{ $t('settings.mailFromNameLabel') }}</label>
            <input id="m-absendername" v-model="form.mail_from_name" class="inp" type="text" />
            <p v-if="form.errors.mail_from_name" class="err">{{ form.errors.mail_from_name }}</p>
          </div>
          <div class="fld">
            <label for="m-absenderadresse">{{ $t('settings.mailFromAddressLabel') }}</label>
            <input id="m-absenderadresse" v-model="form.mail_from_address" class="inp" type="email" />
            <p v-if="form.errors.mail_from_address" class="err">{{ form.errors.mail_from_address }}</p>
          </div>
        </div>
      </div>
    </div>

    <div class="dsec">
      <div class="dlab">{{ $t('settings.mailReplyLabel') }}</div>
      <div class="grp">
        <div class="two">
          <div class="fld">
            <label for="m-antwortan">{{ $t('settings.mailReplyToLabel') }} <em>{{ $t('settings.optional') }}</em></label>
            <input id="m-antwortan" v-model="form.mail_reply_to" class="inp" type="email" />
            <span class="hint">{{ $t('settings.mailReplyToHint') }}</span>
            <p v-if="form.errors.mail_reply_to" class="err">{{ form.errors.mail_reply_to }}</p>
          </div>
          <div class="fld">
            <label for="m-bcc">{{ $t('settings.mailBccLabel') }} <em>{{ $t('settings.optional') }}</em></label>
            <input id="m-bcc" v-model="form.mail_bcc" class="inp" type="email" />
            <span class="hint">{{ $t('settings.mailBccHint') }}</span>
            <p v-if="form.errors.mail_bcc" class="err">{{ form.errors.mail_bcc }}</p>
          </div>
        </div>
      </div>
    </div>

    <div class="dsec">
      <div class="dlab">{{ $t('settings.mailSignatureLabel') }}</div>
      <div class="grp">
        <div class="fld">
          <label for="m-signatur">{{ $t('settings.mailSignatureFieldLabel') }} <em>{{ $t('settings.optional') }}</em></label>
          <textarea id="m-signatur" v-model="form.mail_signature" class="inp" rows="4"></textarea>
          <span class="hint">{{ $t('settings.mailSignatureHint') }}</span>
          <p v-if="form.errors.mail_signature" class="err">{{ form.errors.mail_signature }}</p>
        </div>
      </div>
    </div>
  </form>
</template>
```

- [ ] **Step 5: Rewrite `Settings/Index.vue`**

The `general` pane is not part of the onboarding wizard (Task 6 covers five steps: Profil, USt, Dokumente, E-Mail, Portal), so it stays inline exactly as it was. Replace the whole file:

```vue
<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProfileForm from './panes/ProfileForm.vue'
import VatForm from './panes/VatForm.vue'
import DocumentsForm from './panes/DocumentsForm.vue'
import EmailForm from './panes/EmailForm.vue'

const props = defineProps<{ settings: App.Settings.Data.StudioSettingsData }>()

// Which pane of the section shell is showing — client-side only, the same
// second-level tab set the mockup draws as two static variants of one
// screen. Nothing here is routed: every form already saves through its own
// endpoint, so switching panes has no request to make.
const pane = ref<'profile' | 'vat' | 'documents' | 'email' | 'general'>('profile')

// Mo..So, ISO weekday order — matches the integers StudioSettingsController
// validates working_days.* against and TaskTemplate::dueFor() rolls over.
const weekdays = [
  { value: 1, key: 'settings.dayMon' },
  { value: 2, key: 'settings.dayTue' },
  { value: 3, key: 'settings.dayWed' },
  { value: 4, key: 'settings.dayThu' },
  { value: 5, key: 'settings.dayFri' },
  { value: 6, key: 'settings.daySat' },
  { value: 7, key: 'settings.daySun' },
] as const

// working_days travels the form as the array of ISO weekday integers
// StudioSettingsController validates directly — no bitmask, no translation
// at the wire boundary, same as every other field here.
const generalForm = useForm({
  timezone: props.settings.timezone,
  locale: props.settings.locale,
  working_days: [...props.settings.workingDays] as number[],
})

// Template refs onto the four extracted panes — each exposes { form, submit }
// (see panes/ProfileForm.vue and its siblings). Only one is ever mounted at a
// time (v-if/v-else-if below), so only one of these is non-null at once.
const profileRef = ref<InstanceType<typeof ProfileForm>>()
const vatRef = ref<InstanceType<typeof VatForm>>()
const documentsRef = ref<InstanceType<typeof DocumentsForm>>()
const emailRef = ref<InstanceType<typeof EmailForm>>()

// isDirty is Inertia's own useForm computed, comparing current values to the
// defaults the form was built with — not a bespoke computed re-implementing
// that comparison, the same thing Packages/Edit.vue and Projects/Quotes.vue
// would reach for too if either needed a dirty state.
const activeForm = computed(() => {
  if (pane.value === 'vat') return vatRef.value?.form
  if (pane.value === 'documents') return documentsRef.value?.form
  if (pane.value === 'email') return emailRef.value?.form
  if (pane.value === 'general') return generalForm
  return profileRef.value?.form
})

const formId = computed(() => {
  if (pane.value === 'vat') return 'vat-form'
  if (pane.value === 'documents') return 'documents-form'
  if (pane.value === 'email') return 'mail-form'
  if (pane.value === 'general') return 'general-form'
  return 'settings-form'
})

const paneTitle = computed(() => {
  if (pane.value === 'vat') return 'settings.vatTitle'
  if (pane.value === 'documents') return 'settings.documentsTitle'
  if (pane.value === 'email') return 'settings.mailTitle'
  if (pane.value === 'general') return 'settings.generalTitle'
  return 'settings.profileTitle'
})

// Every group named across this section's stories is live now that T-11's
// Benachrichtigungen has its own route — nothing left in this list.
const comingSoon: readonly string[] = []

function toggleWorkingDay(day: number) {
  const days = generalForm.working_days
  generalForm.working_days = days.includes(day) ? days.filter((d) => d !== day) : [...days, day].sort()
}

function submitGeneral() {
  generalForm
    .transform((data) => ({
      timezone: data.timezone || null,
      locale: data.locale || null,
      working_days: data.working_days,
    }))
    .patch('/einstellungen/allgemein', { preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="einstellungen">
    <div class="hrow">
      <div>
        <div class="kick">{{ $t('settings.kicker') }}</div>
        <h1>{{ $t(paneTitle) }}</h1>
      </div>
      <span v-if="activeForm?.isDirty" class="chip now">{{ $t('settings.unsaved') }}</span>
      <button
        class="btn"
        type="submit"
        :form="formId"
        :disabled="!activeForm?.isDirty || activeForm?.processing"
        :style="!activeForm?.isDirty ? 'opacity:.4' : undefined"
      >
        <svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" /></svg>
        {{ $t('common.save') }}
      </button>
    </div>

    <div style="display: flex; gap: var(--s6); margin-top: var(--s5); align-items: flex-start">
      <nav style="width: 196px; flex: none; display: flex; flex-direction: column; gap: 2px">
        <a class="nav" :class="{ on: pane === 'profile' }" href="#" @click.prevent="pane = 'profile'">
          {{ $t('settings.navProfile') }}
        </a>
        <a class="nav" :class="{ on: pane === 'vat' }" href="#" @click.prevent="pane = 'vat'">
          {{ $t('settings.navVat') }}
        </a>
        <a class="nav" href="/einstellungen/nummernkreise">{{ $t('settings.navNumbering') }}</a>
        <a class="nav" :class="{ on: pane === 'documents' }" href="#" @click.prevent="pane = 'documents'">
          {{ $t('settings.navDocuments') }}
        </a>
        <a class="nav" :class="{ on: pane === 'email' }" href="#" @click.prevent="pane = 'email'">
          {{ $t('settings.navMail') }}
        </a>
        <a class="nav" href="/einstellungen/verfuegbarkeit">{{ $t('settings.navAvailability') }}</a>
        <a class="nav" href="/einstellungen/zahlungen">{{ $t('settings.navPayments') }}</a>
        <a class="nav" href="/einstellungen/aufgabenvorlagen">{{ $t('settings.navTaskTemplates') }}</a>
        <a class="nav" href="/einstellungen/fragebogen-vorlagen">{{ $t('settings.navFormTemplates') }}</a>
        <a class="nav" href="/einstellungen/portal">{{ $t('settings.navPortal') }}</a>
        <a class="nav" href="/einstellungen/benachrichtigungen">{{ $t('settings.navNotifications') }}</a>
        <a
          v-for="key in comingSoon"
          :key="key"
          class="nav"
          href="#"
          style="pointer-events: none; opacity: 0.5"
        >
          {{ $t(`settings.${key}`) }}
          <span class="chip next" style="margin-left: auto">{{ $t('settings.soon') }}</span>
        </a>
        <a class="nav" :class="{ on: pane === 'general' }" href="#" @click.prevent="pane = 'general'">
          {{ $t('settings.navGeneral') }}
        </a>
      </nav>

      <div style="flex: 1; min-width: 0; max-width: 620px">
        <ProfileForm v-if="pane === 'profile'" ref="profileRef" :settings="settings" />
        <VatForm v-else-if="pane === 'vat'" ref="vatRef" :settings="settings" />
        <DocumentsForm v-else-if="pane === 'documents'" ref="documentsRef" :settings="settings" />
        <EmailForm v-else-if="pane === 'email'" ref="emailRef" :settings="settings" />

        <form v-else id="general-form" @submit.prevent="submitGeneral">
          <div class="dsec" style="margin-top: 0">
            <div class="dlab">{{ $t('settings.timezoneLocaleLabel') }}</div>
            <div class="grp">
              <div class="two">
                <div class="fld">
                  <label for="g-zeitzone">{{ $t('settings.timezoneLabel') }}</label>
                  <input id="g-zeitzone" v-model="generalForm.timezone" class="inp" type="text" placeholder="Europe/Berlin" />
                  <span class="hint">{{ $t('settings.timezoneHint') }}</span>
                  <p v-if="generalForm.errors.timezone" class="err">{{ generalForm.errors.timezone }}</p>
                </div>
                <div class="fld">
                  <label for="g-sprache">{{ $t('settings.localeLabel') }}</label>
                  <select id="g-sprache" v-model="generalForm.locale" class="inp">
                    <option value="de">{{ $t('settings.localeDe') }}</option>
                    <option value="de_DE">{{ $t('settings.localeDeDe') }}</option>
                    <option value="de_AT">{{ $t('settings.localeDeAt') }}</option>
                    <option value="de_CH">{{ $t('settings.localeDeCh') }}</option>
                  </select>
                  <p v-if="generalForm.errors.locale" class="err">{{ generalForm.errors.locale }}</p>
                </div>
              </div>
            </div>
          </div>

          <div class="dsec">
            <div class="dlab">{{ $t('settings.workingDaysLabel') }}</div>
            <div class="grp">
              <div style="display: flex; flex-wrap: wrap; gap: var(--s4)">
                <label
                  v-for="day in weekdays"
                  :key="day.value"
                  style="display: flex; align-items: center; gap: 8px; font: 400 13px/1.4 var(--font)"
                >
                  <input
                    type="checkbox"
                    :checked="generalForm.working_days.includes(day.value)"
                    @change="toggleWorkingDay(day.value)"
                  />
                  {{ $t(day.key) }}
                </label>
              </div>
              <span class="hint">{{ $t('settings.workingDaysHint') }}</span>
              <p v-if="generalForm.errors.working_days" class="err">{{ generalForm.errors.working_days }}</p>
            </div>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 6: Run the settings tests**

Run: `docker compose exec php php artisan test --filter=NoInternalIdsTest && docker compose exec php php artisan test tests/Feature/InertiaRenderingTest.php`
Expected: PASS, unchanged — the extraction changes no endpoint, field, or prop shape.

- [ ] **Step 7: Manual check**

Run: `pnpm --filter studio dev` (or the existing `./dev-server.sh`), open `/einstellungen`, switch between all five panes, confirm the top save button enables/disables with `isDirty` on each, and that a save on Profil (with a file) still works.

- [ ] **Step 8: Commit**

```bash
git add apps/studio/resources/js/admin/pages/Settings/panes/ apps/studio/resources/js/admin/pages/Settings/Index.vue
git commit -m "refactor(settings): extract Profil/USt/Dokumente/E-Mail panes into standalone components"
```

---

### Task 3: Extract the Portal pane

**Files:**
- Create: `apps/studio/resources/js/admin/pages/Settings/panes/PortalForm.vue`
- Modify: `apps/studio/resources/js/admin/pages/Settings/Portal.vue` (rewritten below)
- Test: existing `apps/studio/tests/Feature/Settings/PortalSettingsAdminTest.php` (must stay green, unmodified)

**Interfaces:**
- `PortalForm.vue`: `props: { settings: App.Settings.Data.PortalSettingsData }`, `emits: { saved: [] }`, `defineExpose({ form, submit })` — same shape as Task 2's four panes.
- Consumes (Task 6): `PortalForm`, `settings` prop typed `PortalSettingsData`, `saved` emit.

- [ ] **Step 1: Create `panes/PortalForm.vue`**

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

const props = defineProps<{ settings: App.Settings.Data.PortalSettingsData }>()
const emit = defineEmits<{ saved: [] }>()

const form = useForm({
  portal_maintenance_enabled: props.settings.maintenanceEnabled,
  portal_maintenance_message: props.settings.maintenanceMessage ?? '',
  portal_welcome_text: props.settings.welcomeText ?? '',
  portal_legal_datenschutz_url: props.settings.datenschutzUrl ?? '',
  portal_legal_agb_url: props.settings.agbUrl ?? '',
  portal_accept_quotes_enabled: props.settings.acceptQuotesEnabled,
  portal_sign_contracts_enabled: props.settings.signContractsEnabled,
  portal_payment_status_enabled: props.settings.paymentStatusEnabled,
  portal_messaging_enabled: props.settings.messagingEnabled,
})

function submit() {
  form
    .transform((data) => ({
      ...data,
      portal_maintenance_message: data.portal_maintenance_message || null,
      portal_welcome_text: data.portal_welcome_text || null,
      portal_legal_datenschutz_url: data.portal_legal_datenschutz_url || null,
      portal_legal_agb_url: data.portal_legal_agb_url || null,
    }))
    .patch('/einstellungen/portal', { preserveScroll: true, onSuccess: () => emit('saved') })
}

defineExpose({ form, submit })
</script>

<template>
  <form id="portal-settings-form" @submit.prevent="submit">
    <!-- T-08: a temporary closure, worded and switched separately from
         A-04's hide-project/revoke-contact forms — never a third
         catch-all access control. -->
    <div class="dsec" style="margin-top: 0">
      <div class="dlab">{{ $t('portalSettings.maintenanceSection') }}</div>
      <div class="grp">
        <label style="display: flex; align-items: center; gap: 10px; font: 400 13px/1.4 var(--font)">
          <input v-model="form.portal_maintenance_enabled" type="checkbox" />
          {{ $t('portalSettings.maintenanceEnabledLabel') }}
        </label>
        <span class="hint">{{ $t('portalSettings.maintenanceEnabledHint') }}</span>
      </div>
      <div class="grp">
        <div class="fld">
          <label for="po-wartungstext">{{ $t('portalSettings.maintenanceMessageLabel') }} <em>{{ $t('settings.optional') }}</em></label>
          <textarea id="po-wartungstext" v-model="form.portal_maintenance_message" class="inp" rows="3"></textarea>
          <span class="hint">{{ $t('portalSettings.maintenanceMessageHint') }}</span>
          <p v-if="form.errors.portal_maintenance_message" class="err">{{ form.errors.portal_maintenance_message }}</p>
        </div>
      </div>
    </div>

    <div class="dsec">
      <div class="dlab">{{ $t('portalSettings.homeSection') }}</div>
      <div class="grp">
        <div class="fld">
          <label for="po-willkommen">{{ $t('portalSettings.welcomeTextLabel') }} <em>{{ $t('settings.optional') }}</em></label>
          <textarea id="po-willkommen" v-model="form.portal_welcome_text" class="inp" rows="3"></textarea>
          <span class="hint">{{ $t('portalSettings.welcomeTextHint') }}</span>
          <p v-if="form.errors.portal_welcome_text" class="err">{{ form.errors.portal_welcome_text }}</p>
        </div>
      </div>
    </div>

    <div class="dsec">
      <div class="dlab">{{ $t('portalSettings.legalSection') }}</div>
      <div class="grp">
        <div class="two">
          <div class="fld">
            <label for="po-datenschutz">{{ $t('portalSettings.datenschutzUrlLabel') }} <em>{{ $t('settings.optional') }}</em></label>
            <input id="po-datenschutz" v-model="form.portal_legal_datenschutz_url" class="inp" type="text" />
            <p v-if="form.errors.portal_legal_datenschutz_url" class="err">{{ form.errors.portal_legal_datenschutz_url }}</p>
          </div>
          <div class="fld">
            <label for="po-agb">{{ $t('portalSettings.agbUrlLabel') }} <em>{{ $t('settings.optional') }}</em></label>
            <input id="po-agb" v-model="form.portal_legal_agb_url" class="inp" type="text" />
            <p v-if="form.errors.portal_legal_agb_url" class="err">{{ form.errors.portal_legal_agb_url }}</p>
          </div>
        </div>
        <span class="hint">{{ $t('portalSettings.legalUrlHint') }}</span>
      </div>
    </div>

    <!-- T-08: every switch below defaults to off — a studio opts the
         portal into each capability, whether or not the feature behind
         it already ships elsewhere. -->
    <div class="dsec">
      <div class="dlab">{{ $t('portalSettings.capabilitiesSection') }}</div>
      <div class="grp">
        <label style="display: flex; align-items: center; gap: 10px; font: 400 13px/1.4 var(--font)">
          <input v-model="form.portal_accept_quotes_enabled" type="checkbox" />
          {{ $t('portalSettings.acceptQuotesEnabledLabel') }}
        </label>
        <span class="hint">{{ $t('portalSettings.acceptQuotesEnabledHint') }}</span>
      </div>
      <div class="grp">
        <label style="display: flex; align-items: center; gap: 10px; font: 400 13px/1.4 var(--font)">
          <input v-model="form.portal_sign_contracts_enabled" type="checkbox" />
          {{ $t('portalSettings.signContractsEnabledLabel') }}
        </label>
        <span class="hint">{{ $t('portalSettings.signContractsEnabledHint') }}</span>
      </div>
      <div class="grp">
        <label style="display: flex; align-items: center; gap: 10px; font: 400 13px/1.4 var(--font)">
          <input v-model="form.portal_payment_status_enabled" type="checkbox" />
          {{ $t('portalSettings.paymentStatusEnabledLabel') }}
        </label>
        <span class="hint">{{ $t('portalSettings.paymentStatusEnabledHint') }}</span>
      </div>
      <div class="grp">
        <label style="display: flex; align-items: center; gap: 10px; font: 400 13px/1.4 var(--font)">
          <input v-model="form.portal_messaging_enabled" type="checkbox" />
          {{ $t('portalSettings.messagingEnabledLabel') }}
        </label>
        <span class="hint">{{ $t('portalSettings.messagingEnabledHint') }}</span>
      </div>
    </div>
  </form>
</template>
```

- [ ] **Step 2: Rewrite `Settings/Portal.vue`**

```vue
<script setup lang="ts">
import { ref } from 'vue'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PortalForm from './panes/PortalForm.vue'

defineProps<{ settings: App.Settings.Data.PortalSettingsData }>()

// Every other future group — the same disabled "bald" list every settings
// screen carries its own copy of, per Settings/Index.vue. Portal is this
// pane now, so it drops out of the list here same as Zahlungen did on
// Settings/Payments.vue; Benachrichtigungen is T-11's own route now too.
const comingSoon: readonly string[] = []

const formRef = ref<InstanceType<typeof PortalForm>>()
</script>

<template>
  <AdminLayout active="einstellungen">
    <div class="hrow">
      <div>
        <div class="kick">{{ $t('settings.kicker') }}</div>
        <h1>{{ $t('portalSettings.title') }}</h1>
      </div>
      <span v-if="formRef?.form.isDirty" class="chip now">{{ $t('settings.unsaved') }}</span>
      <button
        class="btn"
        type="submit"
        form="portal-settings-form"
        :disabled="!formRef?.form.isDirty || formRef?.form.processing"
        :style="!formRef?.form.isDirty ? 'opacity:.4' : undefined"
      >
        <svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" /></svg>
        {{ $t('common.save') }}
      </button>
    </div>

    <div style="display: flex; gap: var(--s6); margin-top: var(--s5); align-items: flex-start">
      <nav style="width: 196px; flex: none; display: flex; flex-direction: column; gap: 2px">
        <a class="nav" href="/einstellungen">{{ $t('settings.navProfile') }}</a>
        <a class="nav" href="/einstellungen/nummernkreise">{{ $t('settings.navNumbering') }}</a>
        <a class="nav" href="/einstellungen/verfuegbarkeit">{{ $t('settings.navAvailability') }}</a>
        <a class="nav" href="/einstellungen/zahlungen">{{ $t('settings.navPayments') }}</a>
        <a class="nav" href="/einstellungen/aufgabenvorlagen">{{ $t('settings.navTaskTemplates') }}</a>
        <a class="nav" href="/einstellungen/fragebogen-vorlagen">{{ $t('settings.navFormTemplates') }}</a>
        <a class="nav on" href="/einstellungen/portal">{{ $t('settings.navPortal') }}</a>
        <a class="nav" href="/einstellungen/benachrichtigungen">{{ $t('settings.navNotifications') }}</a>
        <a
          v-for="key in comingSoon"
          :key="key"
          class="nav"
          href="#"
          style="pointer-events: none; opacity: 0.5"
        >
          {{ $t(`settings.${key}`) }}
          <span class="chip next" style="margin-left: auto">{{ $t('settings.soon') }}</span>
        </a>
      </nav>

      <div style="flex: 1; min-width: 0; max-width: 620px">
        <PortalForm ref="formRef" :settings="settings" />
      </div>
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 3: Run the portal settings tests**

Run: `docker compose exec php php artisan test tests/Feature/Settings/PortalSettingsAdminTest.php`
Expected: PASS, unchanged.

- [ ] **Step 4: Manual check**

Open `/einstellungen/portal`, toggle a switch, confirm the top save button and "ungespeichert" chip react, save, confirm redirect back to the same page.

- [ ] **Step 5: Commit**

```bash
git add apps/studio/resources/js/admin/pages/Settings/panes/PortalForm.vue apps/studio/resources/js/admin/pages/Settings/Portal.vue
git commit -m "refactor(settings): extract Portal pane into a standalone component"
```

---

### Task 4: Owner setup at `/einrichtung`

**Files:**
- Create: `apps/studio/app/Http/Middleware/EnsureNoStaffExists.php`
- Create: `apps/studio/app/Http/Controllers/Staff/SetupController.php`
- Create: `apps/studio/resources/js/admin/pages/Auth/Setup.vue`
- Modify: `apps/studio/bootstrap/app.php:96` (middleware alias)
- Modify: `apps/studio/routes/admin.php:54-56` (add routes) and `:76-77` region (SessionController import already present)
- Modify: `apps/studio/app/Http/Controllers/Staff/SessionController.php:20-27` (`create()`)
- Modify: `apps/studio/tests/Feature/AudienceMatrixTest.php:14-24` (PUBLIC_ROUTES)
- Modify: `apps/studio/database/seeders/DatabaseSeeder.php`
- Modify: `apps/studio/resources/js/locales/de.json` (new `setup` key)
- Test: `apps/studio/tests/Feature/Staff/SetupTest.php`

**Interfaces:**
- Produces: route `setup` (`GET /einrichtung`), unnamed `POST /einrichtung`; middleware alias `setup` → `EnsureNoStaffExists`.
- Consumes: `StaffRole::Owner` (`app/Enums/StaffRole.php`), `AuditLogger::record()` (`app/Support/AuditLogger.php`).

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Enums\StaffRole;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

it('renders the setup screen on a database with no staff account', function () {
    $this->get('/einrichtung')->assertOk();
});

it('creates the first owner, signs them in, and sends them to the studio wizard', function () {
    $this->post('/einrichtung', [
        'name' => 'Christian Zepter',
        'email' => 'christian@nordlicht.test',
        'password' => 'ein-sehr-langes-passwort',
        'password_confirmation' => 'ein-sehr-langes-passwort',
    ])->assertRedirect('/einrichtung/studio');

    $user = User::where('email', 'christian@nordlicht.test')->sole();

    expect($user->role)->toBe(StaffRole::Owner);
    $this->assertAuthenticatedAs($user, 'staff');
});

it('404s both verbs once a staff account already exists', function () {
    User::factory()->create();

    $this->get('/einrichtung')->assertNotFound();
    $this->post('/einrichtung', [
        'name' => 'Zweite Person',
        'email' => 'zweite@nordlicht.test',
        'password' => 'ein-sehr-langes-passwort',
        'password_confirmation' => 'ein-sehr-langes-passwort',
    ])->assertNotFound();
});

it('rejects a weak or unconfirmed password', function () {
    $this->post('/einrichtung', [
        'name' => 'Christian Zepter',
        'email' => 'christian@nordlicht.test',
        'password' => 'short',
        'password_confirmation' => 'different',
    ])->assertSessionHasErrors('password');

    expect(User::query()->exists())->toBeFalse();
});

it('redirects the login form to setup while no staff account exists', function () {
    $this->get('/login')->assertRedirect('/einrichtung');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec php php artisan test --filter=SetupTest`
Expected: FAIL — route `/einrichtung` does not exist (404 from the router, not from the not-yet-built gate).

- [ ] **Step 3: Add the middleware**

```php
<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The first-run gate for /einrichtung: reachable only while the tenant has
 * no staff account at all. The moment one exists — created here or any
 * other way — this 404s forever, same as a route that was never
 * registered. See PUBLIC_ROUTES in AudienceMatrixTest for why that
 * emptiness check stands in for an audience guard on this one route.
 */
class EnsureNoStaffExists
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(User::query()->exists(), 404);

        return $next($request);
    }
}
```

- [ ] **Step 4: Add the controller**

```php
<?php

namespace App\Http\Controllers\Staff;

use App\Enums\StaffRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The one door into a database with no staff account yet. EnsureNoStaffExists
 * closes it the moment this succeeds — see that middleware's own doc
 * comment — so there is no "already set up" branch to handle here.
 */
class SetupController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Setup');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->where('tenant_id', config('tenancy.tenant_id')),
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // Re-checked inside the lock: two tabs submitting at once must not
        // both become Owner. EnsureNoStaffExists ran before either request
        // reached here and cannot see the other.
        $user = DB::transaction(function () use ($validated) {
            abort_if(User::query()->lockForUpdate()->exists(), 404);

            return User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => StaffRole::Owner,
            ]);
        });

        Auth::guard('staff')->login($user);
        $request->session()->regenerate();

        AuditLogger::record('staff.setup_completed', $user);

        return redirect('/einrichtung/studio');
    }
}
```

- [ ] **Step 5: Add the Vue page**

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

const form = useForm({ name: '', email: '', password: '', password_confirmation: '' })

function submit() {
  form.post('/einrichtung', { onFinish: () => form.reset('password', 'password_confirmation') })
}
</script>

<template>
  <div class="authwrap">
    <div class="authcard">
      <span class="brand">Nordlicht</span>
      <h1>{{ $t('setup.title') }}</h1>
      <p class="hint" style="margin-bottom: var(--s5)">{{ $t('setup.hint') }}</p>

      <form @submit.prevent="submit">
        <div class="fld grp">
          <label for="name">{{ $t('setup.name') }}</label>
          <input id="name" v-model="form.name" class="inp" type="text" autocomplete="name" required />
          <p v-if="form.errors.name" class="err">{{ form.errors.name }}</p>
        </div>

        <div class="fld grp">
          <label for="email">{{ $t('setup.email') }}</label>
          <input id="email" v-model="form.email" class="inp" type="email" autocomplete="email" required />
          <p v-if="form.errors.email" class="err">{{ form.errors.email }}</p>
        </div>

        <div class="fld grp">
          <label for="password">{{ $t('setup.password') }}</label>
          <input
            id="password"
            v-model="form.password"
            class="inp"
            type="password"
            autocomplete="new-password"
            required
          />
          <p v-if="form.errors.password" class="err">{{ form.errors.password }}</p>
        </div>

        <div class="fld grp">
          <label for="password_confirmation">{{ $t('setup.passwordConfirm') }}</label>
          <input
            id="password_confirmation"
            v-model="form.password_confirmation"
            class="inp"
            type="password"
            autocomplete="new-password"
            required
          />
        </div>

        <button class="btn" type="submit" style="width: 100%; justify-content: center" :disabled="form.processing">
          {{ $t('setup.submit') }}
        </button>
      </form>
    </div>
  </div>
</template>
```

- [ ] **Step 6: Add the `setup` locale block**

In `apps/studio/resources/js/locales/de.json`, add a new top-level key (alphabetically near `"settings"`):

```json
  "setup": {
    "title": "Studio einrichten",
    "hint": "Zuerst das Inhaber-Konto anlegen.",
    "name": "Name",
    "email": "E-Mail",
    "password": "Passwort",
    "passwordConfirm": "Passwort bestätigen",
    "submit": "Konto anlegen"
  },
```

- [ ] **Step 7: Register the middleware alias**

In `apps/studio/bootstrap/app.php`, add to the `$middleware->alias([...])` array (near `'mfa' => RequireConfirmedMfa::class,`):

```php
            'setup' => \App\Http\Middleware\EnsureNoStaffExists::class,
```

- [ ] **Step 8: Wire the routes**

In `apps/studio/routes/admin.php`, add the import (alongside the existing `use App\Http\Controllers\Staff\SessionController;`):

```php
use App\Http\Controllers\Staff\SetupController;
```

Add, immediately before the `Route::middleware('guest:staff')->group(...)` block that currently starts at line 75:

```php
Route::middleware(['guest:staff', 'setup'])->group(function () {
    Route::get('/einrichtung', [SetupController::class, 'create'])->name('setup');
    Route::post('/einrichtung', [SetupController::class, 'store']);
});

```

- [ ] **Step 9: Redirect `/login` when the table is empty**

In `apps/studio/app/Http/Controllers/Staff/SessionController.php`, add the import `use App\Models\User;` and change `create()`:

```php
    public function create(): Response
    {
        if (! User::query()->exists()) {
            return redirect('/einrichtung');
        }

        // The third of DevLoginController's three guards: false everywhere but
        // local, so the production bundle renders no path to the bypass.
        return Inertia::render('Auth/Login', [
            'devLogin' => app()->environment('local'),
        ]);
    }
```

- [ ] **Step 10: Declare the public route**

In `apps/studio/tests/Feature/AudienceMatrixTest.php`, add `'einrichtung',` to `PUBLIC_ROUTES['admin']` (after `'login',`), and extend the comment above the array:

```php
    // ...and einrichtung is the same shape one step earlier: EnsureNoStaffExists
    // is the credential — reachable only while the tenant has no staff account
    // at all, closed forever the moment one exists.
```

- [ ] **Step 11: Close the production seeding hole**

In `apps/studio/database/seeders/DatabaseSeeder.php`, move the `User::factory()->create([...])` call inside the existing `isProduction()` guard:

```php
    public function run(): void
    {
        // Sample data so local development and tests have something to look
        // at — the whole method is guarded, not only DemoCrmSeeder's call,
        // because db:seed against production is exactly the command someone
        // runs once against the wrong environment. /einrichtung is how
        // production gets its first account.
        if (! app()->isProduction()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

            $this->call(DemoCrmSeeder::class);
        }
    }
```

- [ ] **Step 12: Run tests to verify they pass**

Run: `docker compose exec php php artisan test --filter=SetupTest && docker compose exec php php artisan test --filter=AudienceMatrixTest`
Expected: PASS

- [ ] **Step 13: Commit**

```bash
git add apps/studio/app/Http/Middleware/EnsureNoStaffExists.php \
  apps/studio/app/Http/Controllers/Staff/SetupController.php \
  apps/studio/resources/js/admin/pages/Auth/Setup.vue \
  apps/studio/resources/js/locales/de.json \
  apps/studio/bootstrap/app.php \
  apps/studio/routes/admin.php \
  apps/studio/app/Http/Controllers/Staff/SessionController.php \
  apps/studio/tests/Feature/AudienceMatrixTest.php \
  apps/studio/database/seeders/DatabaseSeeder.php \
  apps/studio/tests/Feature/Staff/SetupTest.php
git commit -m "feat(staff): first-run owner signup at /einrichtung"
```

---

### Task 5: MFA becomes optional

**Files:**
- Delete: `apps/studio/app/Http/Middleware/RequireConfirmedMfa.php`
- Modify: `apps/studio/bootstrap/app.php:96` (remove alias + import)
- Modify: `apps/studio/routes/admin.php:105-110` (drop the `mfa` wrapper to a bare group)
- Modify: `apps/studio/app/Http/Controllers/Staff/InvitationController.php:150-153` (stale comment)
- Modify: `apps/studio/tests/Feature/Staff/MfaTest.php` (invert enforcement assertions)
- Modify: `apps/studio/tests/Feature/Staff/InvitationTest.php` (stale comment + assertion)
- Modify: `apps/studio/tests/e2e/login.spec.ts`, `apps/studio/tests/e2e/mfa.spec.ts`

**Interfaces:**
- Removes: middleware alias `mfa`. No route wrapper occupies that slot until Task 6 adds `onboarding` there — until then it is a bare `Route::group(function () {...})`, so this task is independently deployable.

- [ ] **Step 1: Write the failing test**

Add to `apps/studio/tests/Feature/Staff/MfaTest.php`, replacing the existing first test (`'sends an enrolled-but-unconfirmed user to enrollment instead of the dashboard'`):

```php
it('lets an un-enrolled user reach the dashboard — MFA is opt-in', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'staff')
        ->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Uebersicht'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php php artisan test --filter=MfaTest`
Expected: FAIL — current behaviour still redirects to `/auth/mfa/enroll`.

- [ ] **Step 3: Delete the middleware and its alias**

Delete `apps/studio/app/Http/Middleware/RequireConfirmedMfa.php`.

In `apps/studio/bootstrap/app.php`, remove the `use App\Http\Middleware\RequireConfirmedMfa;` import line and the `'mfa' => RequireConfirmedMfa::class,` line from the alias array.

- [ ] **Step 4: Unwrap the route group**

In `apps/studio/routes/admin.php`, change:

```php
    Route::middleware('mfa')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
```

to:

```php
    Route::group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
```

(Everything else inside the group is untouched — only the middleware call on this one line changes. Task 6 replaces `Route::group` with `Route::middleware('onboarding')->group` on this same line.)

Also update the comment two lines below, currently reading "gated behind confirmed MFA like every other sensitive staff screen below":

```php
        // N-04: the consent screen — approving it hands out a token scoped
        // to this person's own capabilities, same as every other sensitive
        // staff screen below.
```

- [ ] **Step 5: Rewrite the stale invitation comment**

In `apps/studio/app/Http/Controllers/Staff/InvitationController.php`, change:

```php
        // The account has no MFA yet, so RequireConfirmedMfa sends them
        // straight to enrollment — an invited user is not usable until TOTP is
        // set up, which is what F-08 requires and what this story inherits.
        return redirect('/');
```

to:

```php
        // MFA is opt-in (see RequireStudioOnboarding's own doc comment for
        // the one gate that still runs here) — an invited user lands on the
        // dashboard directly and enrolls later from /auth/mfa/enroll if they
        // choose to.
        return redirect('/');
```

- [ ] **Step 6: Update `InvitationTest`'s matching assertion**

In `apps/studio/tests/Feature/Staff/InvitationTest.php`, replace the test `'creates the account with the invited role and forces enrollment'`:

```php
it('creates the account with the invited role and signs them in directly — MFA is opt-in', function () {
    invite();

    $this->post('/einladung/raw-token', [
        'password' => 'ein-sehr-langes-passwort',
        'password_confirmation' => 'ein-sehr-langes-passwort',
    ])->assertRedirect('/');

    $user = User::where('email', 'nina@nordlicht.test')->sole();

    expect($user->role)->toBe(StaffRole::SecondShooter)
        ->and($user->hasConfirmedMfa())->toBeFalse();

    $this->get('/')->assertOk();
});
```

- [ ] **Step 7: Update the e2e specs**

Replace `apps/studio/tests/e2e/login.spec.ts`:

```ts
import { expect, test } from '@playwright/test'
import { ACCOUNTS, submitLogin } from './helpers'

test('a staff user without MFA signs in straight to the dashboard', async ({ page }) => {
  await submitLogin(page, ACCOUNTS.basic)

  await expect(page).toHaveURL('/')
  await expect(page.getByRole('heading', { name: 'Übersicht' })).toBeVisible()
})

test('a wrong password shows an error and stays on the login page', async ({ page }) => {
  await submitLogin(page, { ...ACCOUNTS.basic, password: 'definitely-wrong' })

  await expect(
    page.getByText('Diese Zugangsdaten stimmen nicht mit unseren Daten überein.'),
  ).toBeVisible()
  await expect(page).toHaveURL('/login')
})
```

Replace `apps/studio/tests/e2e/mfa.spec.ts`:

```ts
import { expect, test } from '@playwright/test'
import { signIn } from './helpers'

test('a staff user with MFA is challenged before reaching the dashboard', async ({ page }) => {
  await signIn(page)

  await expect(page.getByRole('heading', { name: 'Übersicht' })).toBeVisible()
})
```

Also update the doc comment on `ACCOUNTS.basic` in `apps/studio/tests/e2e/helpers.ts`:

```ts
  /** Not enrolled — signs straight in to the dashboard; MFA is opt-in. */
  basic: { email: 'e2e-basic@studio.test', password: 'correct-horse' },
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `docker compose exec php php artisan test --filter=MfaTest && docker compose exec php php artisan test --filter=InvitationTest`
Expected: PASS. (Playwright specs are exercised in Task 6's manual check, once `E2eSeeder` is updated so the accounts also clear the onboarding gate — see that task's Step 8.)

- [ ] **Step 9: Commit**

```bash
git add apps/studio/app/Http/Middleware/RequireConfirmedMfa.php \
  apps/studio/bootstrap/app.php \
  apps/studio/routes/admin.php \
  apps/studio/app/Http/Controllers/Staff/InvitationController.php \
  apps/studio/tests/Feature/Staff/MfaTest.php \
  apps/studio/tests/Feature/Staff/InvitationTest.php \
  apps/studio/tests/e2e/login.spec.ts \
  apps/studio/tests/e2e/mfa.spec.ts \
  apps/studio/tests/e2e/helpers.ts
git commit -m "feat(staff): MFA becomes opt-in instead of mandatory"
```

---

### Task 6: Studio onboarding wizard at `/einrichtung/studio`

**Files:**
- Create: `apps/studio/app/Http/Middleware/RequireStudioOnboarding.php`
- Create: `apps/studio/app/Settings/Http/Controllers/StudioOnboardingController.php`
- Create: `apps/studio/resources/js/admin/pages/Auth/Onboarding.vue`
- Modify: `apps/studio/app/Settings/Support/StudioSettingsResolver.php` (add `onboardingCompleted()`)
- Modify: `apps/studio/app/Settings/Data/StudioSettingsData.php` (add `fromResolver()`)
- Modify: `apps/studio/app/Settings/Http/Controllers/StudioSettingsController.php` (use the new static factory, delete `toData()`)
- Modify: `apps/studio/bootstrap/app.php` (add `onboarding` alias)
- Modify: `apps/studio/routes/admin.php:105` (wrap with `onboarding`) and after the `guest:staff`/staff-management block (new routes)
- Modify: `apps/studio/tests/Pest.php` (global default: every feature test's studio starts onboarded)
- Modify: `apps/studio/database/seeders/E2eSeeder.php`
- Modify: `apps/studio/resources/js/locales/de.json` (new `onboarding` key)
- Modify: `apps/studio/README.md` or `apps/studio/docs` (see Task 7 — not here)
- Test: `apps/studio/tests/Feature/Settings/OnboardingTest.php`

**Interfaces:**
- Consumes (Task 2/3): `ProfileForm`, `VatForm`, `DocumentsForm`, `EmailForm`, `PortalForm` — each `{ settings, saved }`/`{ settings, saved }`, `defineExpose({ form, submit })`.
- Produces: route `onboarding` (`GET /einrichtung/studio`), unnamed `POST /einrichtung/studio/abschluss`; middleware alias `onboarding` → `RequireStudioOnboarding`; `StudioSettingsResolver::onboardingCompleted(): bool`; `StudioSettingsData::fromResolver(StudioSettingsResolver $settings): self`.

**Why the global Pest default is needed:** `RequireStudioOnboarding` wraps the entire staff route group, and every migration-seeded `studio_settings` row starts with `onboarding_completed_at` null. Roughly 30 existing feature test files `actingAs()` a staff user and `GET` a route inside that group expecting `200`, not a redirect to the wizard — see e.g. `tests/Feature/NoInternalIdsTest.php:147-153`, which creates a bare `User::factory()->create(['role' => StaffRole::Owner])` and asserts `GET /einstellungen` is `assertOk()`. Without a suite-wide default, this task breaks dozens of unrelated, currently-green tests. `tests/Pest.php:35-41` already carries exactly this kind of cross-cutting default (`->beforeEach(fn () => $this->withoutVite())->in('Feature')`) — this task adds one more line to that same hook rather than editing 30 files individually.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Enums\StaffRole;
use App\Settings\Models\StudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => StudioSettings::query()->update(['onboarding_completed_at' => null]));

it('redirects a GET to the wizard while onboarding is unfinished', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/')
        ->assertRedirect('/einrichtung/studio');
});

it('does not redirect a PATCH to a settings endpoint — the wizard must be able to save itself', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->patch('/einstellungen', ['display_name' => 'Studio Zepter'])
        ->assertRedirect('/einstellungen');
});

it('does not redirect a role without ManageSettings', function () {
    // Photographer holds no Capability::ManageSettings (StaffRole::capabilities()),
    // so AuthorizesStaffSettings would 404 them on every settings screen anyway —
    // RequireStudioOnboarding must not catch them in its redirect either.
    $this->actingAs(staffMember(StaffRole::Photographer), 'staff')
        ->get('/')
        ->assertOk();
});

it('stamps completion and releases the dashboard', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post('/einrichtung/studio/abschluss')
        ->assertRedirect('/');

    expect(StudioSettings::query()->sole()->onboarding_completed_at)->not->toBeNull();

    $this->get('/')->assertOk();
});

it('opens the wizard screen itself without redirecting', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/einrichtung/studio')
        ->assertOk();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec php php artisan test --filter=OnboardingTest`
Expected: FAIL — `/einrichtung/studio` does not exist yet.

- [ ] **Step 3: Add `onboardingCompleted()` to the resolver**

In `apps/studio/app/Settings/Support/StudioSettingsResolver.php`, add near the other boolean accessors (e.g. beside `maintenanceEnabled()`):

```php
    /** Whether the first-run wizard (App\Http\Middleware\RequireStudioOnboarding) has been completed. */
    public function onboardingCompleted(): bool
    {
        return $this->row()->onboarding_completed_at !== null;
    }
```

- [ ] **Step 4: Add the middleware**

```php
<?php

namespace App\Http\Middleware;

use App\Enums\Capability;
use App\Settings\Support\StudioSettingsResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends whoever can finish the studio's first-run configuration to
 * /einrichtung/studio until they do. GET-only: the wizard's own steps save
 * through the ordinary settings endpoints this same route group holds
 * (settings.update, settings.vat.update, ...), and those must stay
 * reachable or the wizard could never save itself. Scoped to
 * Capability::ManageSettings so an invited role that cannot open the
 * wizard either (AuthorizesStaffSettings::authorizeSettings() 404s them on
 * every settings screen) is never caught behind this redirect.
 */
class RequireStudioOnboarding
{
    public function __construct(private StudioSettingsResolver $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('staff');

        if ($request->isMethod('GET')
            && ($user?->role->allows(Capability::ManageSettings) ?? false)
            && ! $this->settings->onboardingCompleted()
        ) {
            return redirect('/einrichtung/studio');
        }

        return $next($request);
    }
}
```

- [ ] **Step 5: Move `toData()` into `StudioSettingsData::fromResolver()`**

In `apps/studio/app/Settings/Data/StudioSettingsData.php`, add the import `use App\Settings\Support\StudioSettingsResolver;` and this static method inside the class (mirroring `PortalSettingsData::fromResolver()`):

```php
    public static function fromResolver(StudioSettingsResolver $settings): self
    {
        $attrs = $settings->attributes();

        return new self(
            id: $settings->publicId(),
            displayName: $attrs['display_name'],
            legalName: $attrs['legal_name'],
            legalForm: $attrs['legal_form'],
            street: $attrs['street'],
            postcode: $attrs['postcode'],
            city: $attrs['city'],
            country: $attrs['country'],
            taxNumber: $attrs['tax_number'],
            vatId: $attrs['vat_id'],
            registerNumber: $attrs['register_number'],
            registerCourt: $attrs['register_court'],
            representative: $attrs['representative'],
            website: $attrs['website'],
            accentColor: $attrs['accent_color'],
            logoUrl: $settings->logoPath() !== null ? route('settings.logo') : null,
            bankAccountHolder: $attrs['bank_account_holder'],
            bankIban: $attrs['bank_iban'],
            bankBic: $attrs['bank_bic'],
            vatMode: $settings->vatMode(),
            vatRate: $settings->vatRate(),
            vatNote: $settings->vatNote(),
            quoteValidityDays: $settings->quoteValidityDays(),
            paymentTermDays: $settings->paymentTermDays(),
            depositPercentage: $settings->depositPercentage(),
            widerrufIncluded: $settings->widerrufIncluded(),
            widerrufText: $settings->widerrufText(),
            documentLetterheadHtml: $settings->documentLetterheadHtml(),
            documentFooterHtml: $settings->documentFooterHtml(),
            timezone: $settings->timezone(),
            locale: $settings->locale(),
            workingDays: $settings->workingDays(),
            mailFromName: $settings->mailFromName(),
            mailFromAddress: $settings->mailFromAddress(),
            mailReplyTo: $settings->mailReplyTo(),
            mailSignature: $settings->mailSignature(),
            mailBcc: $settings->mailBcc(),
        );
    }
```

In `apps/studio/app/Settings/Http/Controllers/StudioSettingsController.php`, delete the private `toData()` method (lines 234-277) and change its one call site in `edit()`:

```php
        return Inertia::render('Settings/Index', [
            'settings' => StudioSettingsData::fromResolver($settings),
        ]);
```

- [ ] **Step 6: Add the controller**

```php
<?php

namespace App\Settings\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Http\Controllers\Controller;
use App\Settings\Data\PortalSettingsData;
use App\Settings\Data\StudioSettingsData;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The five-step first-run wizard. Each step posts to the settings endpoint
 * that already exists (StudioSettingsController, PortalSettingsController) —
 * this controller only shows the shell and stamps completion; no validation
 * or persistence is duplicated here. See RequireStudioOnboarding for the
 * gate this route sits behind (and outside of).
 */
class StudioOnboardingController extends Controller
{
    use AuthorizesStaffSettings;

    public function show(StudioSettingsResolver $settings): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Auth/Onboarding', [
            'settings' => StudioSettingsData::fromResolver($settings),
            'portalSettings' => PortalSettingsData::fromResolver($settings),
        ]);
    }

    public function complete(StudioSettingsResolver $settings): RedirectResponse
    {
        $this->authorizeSettings();

        $settings->set('onboarding_completed_at', now(), auth('staff')->user());

        return redirect('/');
    }
}
```

- [ ] **Step 7: Wire the middleware alias and routes**

In `apps/studio/bootstrap/app.php`, add to the alias array:

```php
            'onboarding' => \App\Http\Middleware\RequireStudioOnboarding::class,
```

In `apps/studio/routes/admin.php`:

Change the line Task 5 left as `Route::group(function () {` back to:

```php
    Route::middleware('onboarding')->group(function () {
```

Add the import `use App\Settings\Http\Controllers\StudioOnboardingController;` and, inside the `Route::middleware('audience:staff')->group(...)` block but *above* (outside) the `onboarding` sub-group — alongside the MFA enrollment routes at admin.php:101-103 — add:

```php
    Route::get('/einrichtung/studio', [StudioOnboardingController::class, 'show'])->name('onboarding');
    Route::post('/einrichtung/studio/abschluss', [StudioOnboardingController::class, 'complete']);
```

- [ ] **Step 8: Suite-wide test default and E2eSeeder**

In `apps/studio/tests/Pest.php`, add the imports `use App\Settings\Models\StudioSettings;` and `use Illuminate\Support\Facades\Schema;`, and change:

```php
pest()->extend(TestCase::class)
    ->beforeEach(fn () => $this->withoutVite())
    ->in('Feature');
```

to:

```php
pest()->extend(TestCase::class)
    ->beforeEach(function () {
        $this->withoutVite();

        // Every feature test exercises a studio that has already finished
        // onboarding — RequireStudioOnboarding would otherwise redirect any
        // authenticated GET in this directory to /einrichtung/studio.
        // tests/Feature/Settings/OnboardingTest.php resets this per test to
        // exercise the unfinished state on purpose.
        if (Schema::hasTable('studio_settings')) {
            StudioSettings::query()->update(['onboarding_completed_at' => now()]);
        }
    })
    ->in('Feature');
```

In `apps/studio/database/seeders/E2eSeeder.php`, add the import `use App\Settings\Models\StudioSettings;` and, at the top of `run()` before the two `User::updateOrCreate()` calls:

```php
        // The Playwright accounts below sign in past this seeder's own
        // wizard on purpose — E2eSeeder is idempotent fixture data, not a
        // first-run scenario, and no spec here exercises /einrichtung/studio.
        StudioSettings::query()->update(['onboarding_completed_at' => now()]);

```

- [ ] **Step 9: Add the wizard shell**

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import ProfileForm from '@/admin/pages/Settings/panes/ProfileForm.vue'
import VatForm from '@/admin/pages/Settings/panes/VatForm.vue'
import DocumentsForm from '@/admin/pages/Settings/panes/DocumentsForm.vue'
import EmailForm from '@/admin/pages/Settings/panes/EmailForm.vue'
import PortalForm from '@/admin/pages/Settings/panes/PortalForm.vue'

defineProps<{
  settings: App.Settings.Data.StudioSettingsData
  portalSettings: App.Settings.Data.PortalSettingsData
}>()

const steps = ['profile', 'vat', 'documents', 'email', 'portal'] as const
const titles: Record<(typeof steps)[number], string> = {
  profile: 'onboarding.stepProfile',
  vat: 'onboarding.stepVat',
  documents: 'onboarding.stepDocuments',
  email: 'onboarding.stepEmail',
  portal: 'onboarding.stepPortal',
}

const stepIndex = ref(0)
const step = () => steps[stepIndex.value]

// Only one of these is mounted at a time (v-if chain below), so only one
// ref is ever non-null. Each exposes { form, submit } — see
// Settings/panes/ProfileForm.vue and its siblings.
const paneRef = ref<{ form: { processing: boolean }; submit: () => void }>()

function advance() {
  if (stepIndex.value < steps.length - 1) {
    stepIndex.value += 1
  } else {
    finish()
  }
}

function saveAndContinue() {
  paneRef.value?.submit()
}

function finish() {
  router.post('/einrichtung/studio/abschluss')
}
</script>

<template>
  <div class="authwrap">
    <div class="authcard" style="max-width: 640px">
      <span class="brand">Nordlicht</span>
      <h1>{{ $t(titles[step()]) }}</h1>
      <p class="hint" style="margin-bottom: var(--s5)">
        {{ $t('onboarding.stepCount', { current: stepIndex + 1, total: steps.length }) }}
      </p>

      <ProfileForm v-if="step() === 'profile'" ref="paneRef" :settings="settings" @saved="advance" />
      <VatForm v-else-if="step() === 'vat'" ref="paneRef" :settings="settings" @saved="advance" />
      <DocumentsForm v-else-if="step() === 'documents'" ref="paneRef" :settings="settings" @saved="advance" />
      <EmailForm v-else-if="step() === 'email'" ref="paneRef" :settings="settings" @saved="advance" />
      <PortalForm v-else ref="paneRef" :settings="portalSettings" @saved="finish" />

      <div style="display: flex; gap: var(--s3); margin-top: var(--s5)">
        <button
          class="btn"
          type="button"
          style="flex: 1; justify-content: center"
          :disabled="paneRef?.form.processing"
          @click="saveAndContinue"
        >
          {{ stepIndex === steps.length - 1 ? $t('onboarding.finish') : $t('onboarding.next') }}
        </button>
        <button v-if="stepIndex > 0" class="txtbtn" type="button" @click="finish">
          {{ $t('onboarding.skip') }}
        </button>
      </div>
    </div>
  </div>
</template>
```

- [ ] **Step 10: Add the `onboarding` locale block**

In `apps/studio/resources/js/locales/de.json`, add (alphabetically near `"notifications"`/`"oauth"` if present, otherwise near `"onboarding"`'s natural place):

```json
  "onboarding": {
    "stepProfile": "Studio-Profil",
    "stepVat": "Umsatzsteuer",
    "stepDocuments": "Dokumente",
    "stepEmail": "E-Mail",
    "stepPortal": "Portal",
    "stepCount": "Schritt {current} von {total}",
    "next": "Weiter",
    "finish": "Fertigstellen",
    "skip": "Später abschließen"
  },
```

- [ ] **Step 11: Run the full backend suite**

Run: `docker compose exec php php artisan test`
Expected: PASS across the whole suite — this is the check that the global Pest default from Step 8 actually neutralises the ~30-file blast radius described above.

- [ ] **Step 12: Manual check**

Run `./dev-server.sh`, then:
1. `php artisan migrate:fresh` inside the php container (clears the seeded `test@example.com` so `/einrichtung` is reachable).
2. Visit `/einrichtung`, create an Owner, confirm the redirect to `/einrichtung/studio`.
3. Step through all five panes, confirm "Weiter" saves and advances, confirm "Später abschließen" (visible from step 2 onward) jumps straight to the dashboard.
4. Reload `/` — confirm no further redirect to the wizard.
5. `docker compose exec php php artisan db:seed --class=E2eSeeder && cd apps/studio && pnpm exec playwright test tests/e2e/login.spec.ts tests/e2e/mfa.spec.ts` — confirm both specs pass now that the seeded accounts clear the onboarding gate.

- [ ] **Step 13: Commit**

```bash
git add apps/studio/app/Http/Middleware/RequireStudioOnboarding.php \
  apps/studio/app/Settings/Http/Controllers/StudioOnboardingController.php \
  apps/studio/app/Settings/Support/StudioSettingsResolver.php \
  apps/studio/app/Settings/Data/StudioSettingsData.php \
  apps/studio/app/Settings/Http/Controllers/StudioSettingsController.php \
  apps/studio/resources/js/admin/pages/Auth/Onboarding.vue \
  apps/studio/resources/js/locales/de.json \
  apps/studio/bootstrap/app.php \
  apps/studio/routes/admin.php \
  apps/studio/tests/Pest.php \
  apps/studio/database/seeders/E2eSeeder.php \
  apps/studio/tests/Feature/Settings/OnboardingTest.php
git commit -m "feat(settings): studio onboarding wizard gates the dashboard until first-run configuration is saved"
```

---

### Task 7: Deploy-race note and local reach-the-wizard instructions

**Files:**
- Modify: `apps/studio/README.md` (or `docs/deployment.md`, whichever already documents the deploy sequence — check both, add to the one that covers "after deploy" steps)

**Interfaces:** none — documentation only.

- [ ] **Step 1: Add the deploy-race note**

Add a short section (near wherever the deploy sequence is already documented):

```markdown
### First-run setup

A freshly deployed instance has no staff account. The first person to reach
`/einrichtung` becomes the studio's Owner — there is no invite code and no
recovery path if the wrong person gets there first. Complete setup
immediately after deploying, before sharing the URL.

To see the wizard on a local machine that already has a seeded account, run
`php artisan migrate:fresh` first — `db:seed`'s `test@example.com` account
(created only outside production) otherwise makes `/einrichtung` 404 same as
it would once any real Owner exists.
```

- [ ] **Step 2: Commit**

```bash
git add apps/studio/README.md
git commit -m "docs: note the first-run setup deploy race and how to reach the wizard locally"
```

---

## Self-review notes

- **Spec coverage:** every numbered section of `docs/superpowers/specs/2026-08-11-first-run-setup-design.md` maps to a task — §1 owner signup → Task 4, MFA-optional → Task 5, §3 studio onboarding → Task 6 (incl. the migration from §3's "New column"), §4 seeder fix → Task 4 Step 11, Testing → each task's own Pest file, Risks → Task 7 (deploy race) and Task 6 Step 3/comment updates (MFA implicit step-up note is left as a documented, out-of-scope risk per the spec, not a task).
- **GET-only + escape hatch:** both devil's-advocate findings from brainstorming are implemented literally — `RequireStudioOnboarding::handle()` checks `$request->isMethod('GET')` (Task 6 Step 4) and `Onboarding.vue` renders "Später abschließen" from step 2 onward (Task 6 Step 9).
- **Commit order:** Tasks 2–3 (extraction) precede Tasks 4–5 (setup/MFA) precede Task 6 (wizard), matching the spec's stated commit order.
- **The blast-radius regression** (Task 6's own risk, not called out in the spec) is caught and fixed in the same task via the `tests/Pest.php` global default — flagged explicitly in Task 6's "Why the global Pest default is needed" so a reviewer sees the reasoning, not just the diff.

<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'

const props = defineProps<{ settings: App.Settings.Data.StudioSettingsData }>()

// isDirty is Inertia's own useForm computed, comparing current values to the
// defaults the form was built with — not a bespoke computed re-implementing
// that comparison, the same thing Packages/Edit.vue and Projects/Quotes.vue
// would reach for too if either needed a dirty state.
//
// Keys are snake_case, matching StudioSettingsController's validate() keys
// and the columns StudioSettingsResolver::set() writes — the same shape on
// both ends of the wire, nothing translated in between.
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

// Every other future group (Nummernkreise, Dokumente, ...) T-03 and later
// stories land — rendered disabled with the "bald" chip screen 4's Vertrag
// tab already uses for a superseded quote, per T-00's mockup. Zahlungsdaten
// & USt sits in that list too: T-03 hasn't landed a route behind it yet, so
// it stays disabled beside the rest until it does, rather than linking to a
// screen this story cannot build.
const comingSoon = [
  'navVat',
  'navNumbering',
  'navDocuments',
  'navPayments',
  'navPortal',
  'navNotifications',
  'navGeneral',
] as const

function onLogoChange(event: Event) {
  form.logo = (event.target as HTMLInputElement).files?.[0] ?? null
}

function submit() {
  // Inertia's own documented workaround: a browser cannot send a real PUT
  // or PATCH as multipart/form-data, so a file upload goes over POST with
  // `_method` spoofing the verb Laravel's router actually matches on.
  form
    .transform((data) => ({ ...data, _method: 'patch' }))
    .post('/einstellungen', { forceFormData: true, preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="einstellungen">
    <div class="hrow">
      <div>
        <div class="kick">{{ $t('settings.kicker') }}</div>
        <h1>{{ $t('settings.profileTitle') }}</h1>
      </div>
      <span v-if="form.isDirty" class="chip now">{{ $t('settings.unsaved') }}</span>
      <button
        class="btn"
        type="submit"
        form="settings-form"
        :disabled="!form.isDirty || form.processing"
        :style="!form.isDirty ? 'opacity:.4' : undefined"
      >
        <svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" /></svg>
        {{ $t('common.save') }}
      </button>
    </div>

    <div style="display: flex; gap: var(--s6); margin-top: var(--s5); align-items: flex-start">
      <nav style="width: 196px; flex: none; display: flex; flex-direction: column; gap: 2px">
        <a class="nav on" href="/einstellungen">{{ $t('settings.navProfile') }}</a>
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
                    <input id="p-logo" class="inp" type="file" accept="image/png,image/svg+xml" style="flex: 1" @change="onLogoChange" />
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
      </div>
    </div>
  </AdminLayout>
</template>

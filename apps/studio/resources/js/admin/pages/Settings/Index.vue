<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import { basisPointsToPercent, percentToBasisPoints } from '@/admin/lib/money'

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

// vat_rate travels the form as a percent string ("19.00") — the same euro/
// percent boundary Projects/Quotes.vue crosses with the same two functions
// — and only becomes basis points at submit time, in transform() below.
const vatForm = useForm({
  vat_mode: props.settings.vatMode,
  vat_rate: basisPointsToPercent(props.settings.vatRate),
  vat_note: props.settings.vatNote ?? '',
})

// T-05's own pane — the values every new quote, payment plan and contract
// render starts from. deposit_percentage travels the form as a percent
// string, same euro/percent boundary vat_rate crosses above.
const documentsForm = useForm({
  quote_validity_days: props.settings.quoteValidityDays,
  payment_term_days: props.settings.paymentTermDays,
  deposit_percentage: basisPointsToPercent(props.settings.depositPercentage),
  widerruf_included: props.settings.widerrufIncluded,
  widerruf_text: props.settings.widerrufText ?? '',
})

// working_days travels the form as the array of ISO weekday integers
// StudioSettingsController validates directly — no bitmask, no translation
// at the wire boundary, same as every other field here.
const generalForm = useForm({
  timezone: props.settings.timezone,
  locale: props.settings.locale,
  working_days: [...props.settings.workingDays] as number[],
})

// T-06's own pane. mail_from_name/mail_from_address arrive already resolved
// with the env fallback (StudioSettingsResolver::mailFromName()/
// mailFromAddress()), the same pre-filled shape timezone/locale use above —
// an unmodified save simply re-confirms the fallback. The footer itself is
// not a field here: it is T-02's legal particulars, composed at send time
// from the same StudioSettingsResolver::attributes() Studio-Profil already
// edits, never a second copy on this pane.
const mailForm = useForm({
  mail_from_name: props.settings.mailFromName,
  mail_from_address: props.settings.mailFromAddress,
  mail_reply_to: props.settings.mailReplyTo ?? '',
  mail_bcc: props.settings.mailBcc ?? '',
  mail_signature: props.settings.mailSignature ?? '',
})

const activeForm = computed(() => {
  if (pane.value === 'vat') return vatForm
  if (pane.value === 'documents') return documentsForm
  if (pane.value === 'email') return mailForm
  if (pane.value === 'general') return generalForm
  return form
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

// Every other future group (Portal, ...) later stories land — rendered
// disabled with the "bald" chip screen 4's Vertrag tab already uses for a
// superseded quote, per T-00's mockup. Zahlungsdaten & USt is T-03's own
// pane, Nummernkreise is T-04's own route, Verfügbarkeit is T-09's own
// route, Dokumente is T-05's own pane, E-Mail is T-06's own pane, Allgemein
// is T-13's own pane and Zahlungen is T-07's own route, so all seven drop
// out of this list.
const comingSoon = ['navPortal', 'navNotifications'] as const

function toggleWorkingDay(day: number) {
  const days = generalForm.working_days
  generalForm.working_days = days.includes(day) ? days.filter((d) => d !== day) : [...days, day].sort()
}

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

function submitVat() {
  vatForm
    .transform((data) => ({
      vat_mode: data.vat_mode,
      vat_rate: percentToBasisPoints(data.vat_rate),
      vat_note: data.vat_note || null,
    }))
    .patch('/einstellungen/ust', { preserveScroll: true })
}

function submitDocuments() {
  documentsForm
    .transform((data) => ({
      ...data,
      deposit_percentage: percentToBasisPoints(data.deposit_percentage),
      widerruf_text: data.widerruf_text || null,
    }))
    .patch('/einstellungen/dokumente', { preserveScroll: true })
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

function submitMail() {
  mailForm
    .transform((data) => ({
      mail_from_name: data.mail_from_name || null,
      mail_from_address: data.mail_from_address || null,
      mail_reply_to: data.mail_reply_to || null,
      mail_bcc: data.mail_bcc || null,
      mail_signature: data.mail_signature || null,
    }))
    .patch('/einstellungen/email', { preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="einstellungen">
    <div class="hrow">
      <div>
        <div class="kick">{{ $t('settings.kicker') }}</div>
        <h1>{{ $t(paneTitle) }}</h1>
      </div>
      <span v-if="activeForm.isDirty" class="chip now">{{ $t('settings.unsaved') }}</span>
      <button
        class="btn"
        type="submit"
        :form="formId"
        :disabled="!activeForm.isDirty || activeForm.processing"
        :style="!activeForm.isDirty ? 'opacity:.4' : undefined"
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

      <form v-if="pane === 'profile'" id="settings-form" style="flex: 1; min-width: 0; max-width: 620px" @submit.prevent="submit">
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

      <form v-else-if="pane === 'vat'" id="vat-form" style="flex: 1; min-width: 0; max-width: 460px" @submit.prevent="submitVat">
        <div class="dsec" style="margin-top: 0">
          <div class="dlab">{{ $t('settings.vatModeLabel') }}</div>
          <div class="grp">
            <div style="display: flex; flex-direction: column; gap: var(--s3)">
              <label style="display: flex; align-items: center; gap: 10px; font: 400 13px/1.4 var(--font)">
                <input v-model="vatForm.vat_mode" type="radio" name="vatmode" value="standard" />
                {{ $t('settings.vatModeStandard') }}
              </label>
              <label style="display: flex; align-items: center; gap: 10px; font: 400 13px/1.4 var(--font)">
                <input v-model="vatForm.vat_mode" type="radio" name="vatmode" value="small_business" />
                {{ $t('settings.vatModeSmallBusiness') }}
              </label>
            </div>
            <p class="hint" style="margin-top: var(--s3); color: var(--accent)">{{ $t('settings.vatModeWarning') }}</p>
          </div>
          <div class="grp">
            <div class="fld" style="max-width: 160px">
              <label for="v-satz">{{ $t('settings.vatRateLabel') }}</label>
              <input id="v-satz" v-model="vatForm.vat_rate" class="inp num" type="text" inputmode="decimal" />
              <span class="hint">{{ $t('settings.vatRateHint') }}</span>
              <p v-if="vatForm.errors.vat_rate" class="err">{{ vatForm.errors.vat_rate }}</p>
            </div>
          </div>
        </div>

        <div class="dsec">
          <div class="dlab">{{ $t('settings.vatNoteLabel') }}</div>
          <div class="grp">
            <div class="fld">
              <label for="v-hinweis">{{ $t('settings.vatNoteFieldLabel') }} <em>{{ $t('settings.optional') }}</em></label>
              <textarea id="v-hinweis" v-model="vatForm.vat_note" class="inp" rows="3"></textarea>
              <span class="hint">{{ $t('settings.vatNoteHint') }}</span>
              <p v-if="vatForm.errors.vat_note" class="err">{{ vatForm.errors.vat_note }}</p>
            </div>
          </div>
        </div>

        <div class="dsec">
          <div class="dlab">{{ $t('settings.vatPreviewLabel') }}</div>
          <div class="card static">
            <div class="cardhead"><span class="kick">{{ $t('settings.vatPreviewKicker') }}</span></div>
            <p v-if="vatForm.vat_note" style="margin: 0; font: 400 13px/1.5 var(--font); color: var(--ink)">
              {{ vatForm.vat_note }}
            </p>
            <p v-else class="hint" style="margin: 0">{{ $t('settings.vatPreviewEmpty') }}</p>
          </div>
          <span class="hint" style="display: block; margin-top: var(--s3)">{{ $t('settings.vatPreviewHint') }}</span>
        </div>
      </form>

      <form v-else-if="pane === 'documents'" id="documents-form" style="flex: 1; min-width: 0; max-width: 460px" @submit.prevent="submitDocuments">
        <div class="dsec" style="margin-top: 0">
          <div class="dlab">{{ $t('settings.documentsQuoteLabel') }}</div>
          <div class="grp">
            <div class="fld" style="max-width: 160px">
              <label for="d-gueltigkeit">{{ $t('settings.quoteValidityLabel') }}</label>
              <input id="d-gueltigkeit" v-model.number="documentsForm.quote_validity_days" class="inp num" type="number" min="0" />
              <span class="hint">{{ $t('settings.quoteValidityHint') }}</span>
              <p v-if="documentsForm.errors.quote_validity_days" class="err">{{ documentsForm.errors.quote_validity_days }}</p>
            </div>
          </div>
        </div>

        <div class="dsec">
          <div class="dlab">{{ $t('settings.documentsPaymentLabel') }}</div>
          <div class="grp">
            <div class="two">
              <div class="fld">
                <label for="d-zahlungsziel">{{ $t('settings.paymentTermLabel') }}</label>
                <input id="d-zahlungsziel" v-model.number="documentsForm.payment_term_days" class="inp num" type="number" min="0" />
                <span class="hint">{{ $t('settings.paymentTermHint') }}</span>
                <p v-if="documentsForm.errors.payment_term_days" class="err">{{ documentsForm.errors.payment_term_days }}</p>
              </div>
              <div class="fld">
                <label for="d-anzahlung">{{ $t('settings.depositPercentageLabel') }}</label>
                <input id="d-anzahlung" v-model="documentsForm.deposit_percentage" class="inp num" type="text" inputmode="decimal" />
                <span class="hint">{{ $t('settings.depositPercentageHint') }}</span>
                <p v-if="documentsForm.errors.deposit_percentage" class="err">{{ documentsForm.errors.deposit_percentage }}</p>
              </div>
            </div>
          </div>
        </div>

        <div class="dsec">
          <div class="dlab">{{ $t('settings.widerrufLabel') }}</div>
          <div class="grp">
            <label style="display: flex; align-items: center; gap: 10px; font: 400 13px/1.4 var(--font)">
              <input v-model="documentsForm.widerruf_included" type="checkbox" />
              {{ $t('settings.widerrufIncludedLabel') }}
            </label>
            <span class="hint">{{ $t('settings.widerrufIncludedHint') }}</span>
          </div>
          <div class="grp">
            <div class="fld">
              <label for="d-widerruf-text">{{ $t('settings.widerrufTextLabel') }}</label>
              <textarea
                id="d-widerruf-text"
                v-model="documentsForm.widerruf_text"
                class="inp"
                rows="5"
                :disabled="!documentsForm.widerruf_included"
              ></textarea>
              <span class="hint">{{ $t('settings.widerrufTextHint') }}</span>
              <p v-if="documentsForm.errors.widerruf_text" class="err">{{ documentsForm.errors.widerruf_text }}</p>
            </div>
          </div>
        </div>
      </form>

      <form v-else-if="pane === 'email'" id="mail-form" style="flex: 1; min-width: 0; max-width: 460px" @submit.prevent="submitMail">
        <div class="dsec" style="margin-top: 0">
          <div class="dlab">{{ $t('settings.mailSenderLabel') }}</div>
          <div class="grp">
            <div class="two">
              <div class="fld">
                <label for="m-absendername">{{ $t('settings.mailFromNameLabel') }}</label>
                <input id="m-absendername" v-model="mailForm.mail_from_name" class="inp" type="text" />
                <p v-if="mailForm.errors.mail_from_name" class="err">{{ mailForm.errors.mail_from_name }}</p>
              </div>
              <div class="fld">
                <label for="m-absenderadresse">{{ $t('settings.mailFromAddressLabel') }}</label>
                <input id="m-absenderadresse" v-model="mailForm.mail_from_address" class="inp" type="email" />
                <p v-if="mailForm.errors.mail_from_address" class="err">{{ mailForm.errors.mail_from_address }}</p>
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
                <input id="m-antwortan" v-model="mailForm.mail_reply_to" class="inp" type="email" />
                <span class="hint">{{ $t('settings.mailReplyToHint') }}</span>
                <p v-if="mailForm.errors.mail_reply_to" class="err">{{ mailForm.errors.mail_reply_to }}</p>
              </div>
              <div class="fld">
                <label for="m-bcc">{{ $t('settings.mailBccLabel') }} <em>{{ $t('settings.optional') }}</em></label>
                <input id="m-bcc" v-model="mailForm.mail_bcc" class="inp" type="email" />
                <span class="hint">{{ $t('settings.mailBccHint') }}</span>
                <p v-if="mailForm.errors.mail_bcc" class="err">{{ mailForm.errors.mail_bcc }}</p>
              </div>
            </div>
          </div>
        </div>

        <div class="dsec">
          <div class="dlab">{{ $t('settings.mailSignatureLabel') }}</div>
          <div class="grp">
            <div class="fld">
              <label for="m-signatur">{{ $t('settings.mailSignatureFieldLabel') }} <em>{{ $t('settings.optional') }}</em></label>
              <textarea id="m-signatur" v-model="mailForm.mail_signature" class="inp" rows="4"></textarea>
              <span class="hint">{{ $t('settings.mailSignatureHint') }}</span>
              <p v-if="mailForm.errors.mail_signature" class="err">{{ mailForm.errors.mail_signature }}</p>
            </div>
          </div>
        </div>
      </form>

      <form v-else id="general-form" style="flex: 1; min-width: 0; max-width: 460px" @submit.prevent="submitGeneral">
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
  </AdminLayout>
</template>

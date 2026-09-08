<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import ProfileForm from './panes/ProfileForm.vue'
import VatForm from './panes/VatForm.vue'
import DocumentsForm from './panes/DocumentsForm.vue'
import EmailForm from './panes/EmailForm.vue'
import BrandPreview from './previews/BrandPreview.vue'
import DocumentPreview from './previews/DocumentPreview.vue'
import MailPreview from './previews/MailPreview.vue'
import { basisPointsToPercent } from '@/admin/lib/money'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Checkbox } from '@/ui/checkbox'
import { Textarea } from '@/ui/textarea'
import { Label } from '@/ui/label'

const props = defineProps<{

  fontPairs: { value: string; label: string; description: string }[]
  settings: App.Settings.Data.StudioSettingsData
}>()

// Which pane of the section shell is showing. In the query rather than in a
// ref, because SettingsNav reaches these four from the sibling settings
// screens too — a pane only this component could switch to was a pane those
// screens had to render as "bald".
// The studio profile is one form split over three nav entries, so each of
// them is its own pane and ProfileForm renders only that section.
const PROFILE_SECTIONS = ['brand', 'legal', 'bank'] as const
const PANES = [...PROFILE_SECTIONS, 'vat', 'documents', 'email', 'capture', 'general'] as const

const page = usePage()

const pane = computed(() => {
  const asked = new URL(page.url, 'http://settings').searchParams.get('pane')

  return PANES.find((known) => known === asked) ?? 'brand'
})

const profileSection = computed(() => PROFILE_SECTIONS.find((section) => section === pane.value))

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

// The three suggestion lists the "Sonstige" fields at capture offer, one
// entry per line — the list is short and typed rarely, so a textarea beats a
// tag editor nobody asked for.
const captureForm = useForm({
  lead_type_presets: props.settings.leadTypePresets.join('\n'),
  lead_source_presets: props.settings.leadSourcePresets.join('\n'),
  lead_channel_presets: props.settings.leadChannelPresets.join('\n'),
})

function lines(value: string) {
  return value.split('\n').map((line) => line.trim()).filter(Boolean)
}

function submitCapture() {
  captureForm
    .transform((data) => ({
      lead_type_presets: lines(data.lead_type_presets),
      lead_source_presets: lines(data.lead_source_presets),
      lead_channel_presets: lines(data.lead_channel_presets),
    }))
    .patch('/einstellungen/erfassung', { preserveScroll: true })
}

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
  if (pane.value === 'capture') return captureForm
  if (pane.value === 'general') return generalForm
  return profileRef.value?.form
})

const formId = computed(() => {
  if (pane.value === 'vat') return 'vat-form'
  if (pane.value === 'documents') return 'documents-form'
  if (pane.value === 'email') return 'mail-form'
  if (pane.value === 'capture') return 'capture-form'
  if (pane.value === 'general') return 'general-form'
  return 'settings-form'
})

const paneTitle = computed(() => {
  if (pane.value === 'vat') return 'settings.vatTitle'
  if (pane.value === 'documents') return 'settings.documentsTitle'
  if (pane.value === 'email') return 'settings.mailTitle'
  if (pane.value === 'capture') return 'settings.captureTitle'
  if (pane.value === 'general') return 'settings.generalTitle'
  if (pane.value === 'legal') return 'settings.legalEntity'
  if (pane.value === 'bank') return 'settings.bank'
  return 'settings.brand'
})

// The sample sheet Rechtliches, Bankverbindung, Zahlungsdaten & USt and
// Dokumente share: saved settings underneath, whatever the mounted form holds
// on top, so the field being typed is the one moving in the preview. Only one
// pane is mounted at a time, so at most one of the three refs contributes.
const DOCUMENT_PANES = ['legal', 'bank', 'vat', 'documents']

const documentPreview = computed(() => {
  const profile = profileRef.value?.form
  const vat = vatRef.value?.form
  const documents = documentsRef.value?.form

  return {
    displayName: props.settings.displayName ?? '',
    logoUrl: props.settings.logoUrl,
    fontPair: props.settings.fontPair ?? 'hanken',
    accentColor: props.settings.accentColor,
    legalName: profile?.legal_name ?? props.settings.legalName ?? '',
    legalForm: profile?.legal_form ?? props.settings.legalForm ?? '',
    street: profile?.street ?? props.settings.street ?? '',
    postcode: profile?.postcode ?? props.settings.postcode ?? '',
    city: profile?.city ?? props.settings.city ?? '',
    representative: profile?.representative ?? props.settings.representative ?? '',
    taxNumber: profile?.tax_number ?? props.settings.taxNumber ?? '',
    vatId: profile?.vat_id ?? props.settings.vatId ?? '',
    registerNumber: profile?.register_number ?? props.settings.registerNumber ?? '',
    registerCourt: profile?.register_court ?? props.settings.registerCourt ?? '',
    bankAccountHolder: profile?.bank_account_holder ?? props.settings.bankAccountHolder ?? '',
    bankIban: profile?.bank_iban ?? props.settings.bankIban ?? '',
    bankBic: profile?.bank_bic ?? props.settings.bankBic ?? '',
    vatMode: vat?.vat_mode ?? props.settings.vatMode,
    vatRate: vat?.vat_rate ?? basisPointsToPercent(props.settings.vatRate),
    quoteValidityDays: documents?.quote_validity_days ?? props.settings.quoteValidityDays,
    paymentTermDays: documents?.payment_term_days ?? props.settings.paymentTermDays,
    depositPercentage: documents?.deposit_percentage ?? basisPointsToPercent(props.settings.depositPercentage),
    widerrufIncluded: documents?.widerruf_included ?? props.settings.widerrufIncluded,
  }
})

const mailPreview = computed(() => {
  const mail = emailRef.value?.form

  return {
    fromName: mail?.mail_from_name ?? props.settings.mailFromName,
    fromAddress: mail?.mail_from_address ?? props.settings.mailFromAddress,
    replyTo: mail?.mail_reply_to ?? props.settings.mailReplyTo ?? '',
    bcc: mail?.mail_bcc ?? props.settings.mailBcc ?? '',
    signature: mail?.mail_signature ?? props.settings.mailSignature ?? '',
  }
})

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
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :kicker="$t('settings.kicker')" :title="$t(paneTitle)">
          <span v-if="activeForm?.isDirty" class="flex-none rounded-[var(--r-sm)] bg-due-bg px-2 py-[3px] text-[10px] leading-[1.5] font-semibold tracking-[0.04em] uppercase text-due">{{ $t('settings.unsaved') }}</span>
          <Button
            class="ml-auto"
            variant="pill"
            type="submit"
            :form="formId"
            :disabled="!activeForm?.isDirty || activeForm?.processing"
          >
            {{ $t('common.save') }}
          </Button>
        </PageHead>

        <div class="px-8 pt-7 pb-8">
          <div class="flex items-start gap-8 max-[640px]:flex-col max-[640px]:gap-4">
            <SettingsNav :active="pane" />

            <div class="max-w-[620px] min-w-0 flex-1">
              <ProfileForm v-if="profileSection" ref="profileRef" :settings="settings" :font-pairs="fontPairs" :sections="[profileSection]" />
              <VatForm v-else-if="pane === 'vat'" ref="vatRef" :settings="settings" />
              <DocumentsForm v-else-if="pane === 'documents'" ref="documentsRef" :settings="settings" />
              <EmailForm v-else-if="pane === 'email'" ref="emailRef" :settings="settings" />

              <form v-else-if="pane === 'capture'" id="capture-form" @submit.prevent="submitCapture">
                <div>
                  <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('settings.capturePresetsLabel') }}</div>
                  <div class="mb-6">
                    <div class="flex min-w-0 flex-col gap-1.5">
                      <Label for="c-typ">{{ $t('leads.type') }}</Label>
                      <Textarea id="c-typ" v-model="captureForm.lead_type_presets" rows="4" />
                    </div>
                    <div class="mt-4 flex min-w-0 flex-col gap-1.5">
                      <Label for="c-quelle">{{ $t('leads.source') }}</Label>
                      <Textarea id="c-quelle" v-model="captureForm.lead_source_presets" rows="4" />
                    </div>
                    <div class="mt-4 flex min-w-0 flex-col gap-1.5">
                      <Label for="c-kanal">{{ $t('leads.capturedVia') }}</Label>
                      <Textarea id="c-kanal" v-model="captureForm.lead_channel_presets" rows="4" />
                    </div>
                    <span class="text-[11.5px] leading-[1.45] font-normal text-faint">{{ $t('settings.capturePresetsHint') }}</span>
                  </div>
                </div>
              </form>

              <form v-else id="general-form" @submit.prevent="submitGeneral">
                <div>
                  <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('settings.timezoneLocaleLabel') }}</div>
                  <div class="mb-6">
                    <div class="grid grid-cols-2 gap-4">
                      <div class="flex min-w-0 flex-col gap-1.5">
                        <Label for="g-zeitzone">{{ $t('settings.timezoneLabel') }}</Label>
                        <Input id="g-zeitzone" v-model="generalForm.timezone" type="text" placeholder="Europe/Berlin" />
                        <span class="text-[11.5px] leading-[1.45] font-normal text-faint">{{ $t('settings.timezoneHint') }}</span>
                        <p v-if="generalForm.errors.timezone" class="text-[11.5px] leading-[1.45] text-destructive">{{ generalForm.errors.timezone }}</p>
                      </div>
                      <div class="flex min-w-0 flex-col gap-1.5">
                        <Label for="g-sprache">{{ $t('settings.localeLabel') }}</Label>
                        <Select
                          :model-value="generalForm.locale"
                          @update:model-value="(value) => (generalForm.locale = String(value))"
                        >
                          <SelectTrigger id="g-sprache">
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="de">{{ $t('settings.localeDe') }}</SelectItem>
                            <SelectItem value="de_DE">{{ $t('settings.localeDeDe') }}</SelectItem>
                            <SelectItem value="de_AT">{{ $t('settings.localeDeAt') }}</SelectItem>
                            <SelectItem value="de_CH">{{ $t('settings.localeDeCh') }}</SelectItem>
                          </SelectContent>
                        </Select>
                        <p v-if="generalForm.errors.locale" class="text-[11.5px] leading-[1.45] text-destructive">{{ generalForm.errors.locale }}</p>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="mt-8 first-of-type:mt-6">
                  <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('settings.workingDaysLabel') }}</div>
                  <div class="mb-6">
                    <div class="flex flex-wrap gap-4">
                      <div v-for="day in weekdays" :key="day.value" class="flex items-center gap-2">
                        <Checkbox
                          :id="`wd-${day.value}`"
                          :model-value="generalForm.working_days.includes(day.value)"
                          @update:model-value="toggleWorkingDay(day.value)"
                        />
                        <Label :for="`wd-${day.value}`">{{ $t(day.key) }}</Label>
                      </div>
                    </div>
                    <span class="text-[11.5px] leading-[1.45] font-normal text-faint">{{ $t('settings.workingDaysHint') }}</span>
                    <p v-if="generalForm.errors.working_days" class="text-[11.5px] leading-[1.45] text-destructive">{{ generalForm.errors.working_days }}</p>
                  </div>
                </div>
              </form>
            </div>

            <BrandPreview
              v-if="pane === 'brand' && profileRef"
              :display-name="profileRef.form.display_name"
              :website="profileRef.form.website"
              :accent-color="profileRef.form.accent_color"
              :font-pair="profileRef.form.font_pair"
              :logo-file="profileRef.form.logo"
              :logo-url="settings.logoUrl"
            />
            <DocumentPreview v-else-if="DOCUMENT_PANES.includes(pane)" :doc="documentPreview" />
            <MailPreview v-else-if="pane === 'email'" :mail="mailPreview" />
          </div>
        </div>
      </div>
    </Page>
  </AdminLayout>
</template>

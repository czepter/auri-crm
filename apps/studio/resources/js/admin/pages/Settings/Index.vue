<script setup lang="ts">
import { computed, ref } from 'vue'
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

const props = defineProps<{
  pane: 'invoice' | 'documents' | 'email' | 'general'
  fontPairs: { value: string; label: string; description: string }[]
  mailSenderFromEnv: { name: boolean; address: boolean }
  settings: App.Settings.Data.StudioSettingsData
}>()

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
// Rechnungsdaten zeigt zwei Formulare — Bankverbindung schreibt nach
// /einstellungen, die Umsatzsteuer nach /einstellungen/ust, weil der
// Onboarding-Schritt dasselbe VatForm benutzt. Der Kopf speichert deshalb,
// was schmutzig ist, statt ein einzelnes form-Attribut zu tragen.
const activePanes = computed(() => {
  if (props.pane === 'invoice') return [profileRef.value, vatRef.value]
  if (props.pane === 'documents') return [documentsRef.value]
  if (props.pane === 'email') return [emailRef.value]
  return [profileRef.value]
})

const dirty = computed(() => activePanes.value.some((entry) => entry?.form.isDirty))
const processing = computed(() => activePanes.value.some((entry) => entry?.form.processing))

function save() {
  activePanes.value.filter((entry) => entry?.form.isDirty).forEach((entry) => entry!.submit())
}

const paneTitle = computed(() => {
  if (props.pane === 'invoice') return 'settings.navInvoiceData'
  if (props.pane === 'documents') return 'settings.documentsTitle'
  if (props.pane === 'email') return 'settings.mailTitle'
  return 'settings.generalTitle'
})

// The sample sheet Bankverbindung, Zahlungsdaten & USt and
// Dokumente share: saved settings underneath, whatever the mounted form holds
// on top, so the field being typed is the one moving in the preview. Only one
// pane is mounted at a time, so at most one of the three refs contributes.
const DOCUMENT_PANES = ['invoice', 'documents']

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
    quoteLayout: documents?.quote_layout ?? props.settings.quoteLayout,
  }
})

const mailPreview = computed(() => {
  const mail = emailRef.value?.form

  return {
    fromName: props.settings.mailFromName,
    fromAddress: props.settings.mailFromAddress,
    replyTo: mail?.mail_reply_to ?? props.settings.mailReplyTo ?? '',
    bcc: mail?.mail_bcc ?? props.settings.mailBcc ?? '',
    signature: mail?.mail_signature ?? props.settings.mailSignature ?? '',
  }
})
</script>

<template>
  <AdminLayout active="einstellungen">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :kicker="$t('settings.kicker')" :title="$t(paneTitle)">
          <span v-if="dirty" class="flex-none rounded-[var(--r-sm)] bg-due-bg px-2 py-[3px] text-[10px] leading-[1.5] font-semibold tracking-[0.04em] uppercase text-due">{{ $t('settings.unsaved') }}</span>
          <Button
            v-if="dirty"
            class="ml-auto"
            variant="pill"
            type="button"
            :disabled="processing"
            @click="save"
          >
            {{ $t('common.save') }}
          </Button>
        </PageHead>

        <div class="px-8 pt-7 pb-8">
          <div class="flex flex-wrap items-start gap-8 max-[640px]:flex-col max-[640px]:gap-4">
            <SettingsNav :active="pane" />

            <div class="max-w-[620px] min-w-0 flex-1">
              <ProfileForm v-if="pane === 'general'" ref="profileRef" :settings="settings" :font-pairs="fontPairs" :sections="['brand', 'legal', 'tax', 'general']" />

              <template v-else-if="pane === 'invoice'">
                <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('settings.bank') }}</div>
                <ProfileForm ref="profileRef" :settings="settings" :font-pairs="fontPairs" :sections="['bank']" />
                <div class="mt-8">
                  <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('settings.vatSection') }}</div>
                  <VatForm ref="vatRef" :settings="settings" />
                </div>
              </template>

              <DocumentsForm v-else-if="pane === 'documents'" ref="documentsRef" :settings="settings" />
              <EmailForm v-else-if="pane === 'email'" ref="emailRef" :settings="settings" :sender-from-env="mailSenderFromEnv" />

            </div>

            <BrandPreview
              v-if="pane === 'general' && profileRef"
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

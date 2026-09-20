<script setup lang="ts">
import { computed, ref } from 'vue'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import ProfileForm from './panes/ProfileForm.vue'
import VatForm from './panes/VatForm.vue'
import PaymentForm from './panes/PaymentForm.vue'
import ReceiptsForm from './panes/ReceiptsForm.vue'
import EmailForm from './panes/EmailForm.vue'
import BrandPreview from './previews/BrandPreview.vue'
import DocumentPreview from './previews/DocumentPreview.vue'
import MailPreview from './previews/MailPreview.vue'
import { Button } from '@/ui/button'

const props = defineProps<{
  pane: 'payment' | 'receipts' | 'email' | 'general'
  fontPairs: { value: string; label: string; description: string }[]
  mailSenderFromEnv: { name: boolean; address: boolean }
  settings: App.Settings.Data.StudioSettingsData
}>()

// Template refs onto the extracted panes — each exposes { form, submit }
// (see panes/ProfileForm.vue and its siblings). Only the panes of the current
// page are mounted (v-if/v-else-if below).
const profileRef = ref<InstanceType<typeof ProfileForm>>()
const vatRef = ref<InstanceType<typeof VatForm>>()
const paymentRef = ref<InstanceType<typeof PaymentForm>>()
const receiptsRef = ref<InstanceType<typeof ReceiptsForm>>()
const emailRef = ref<InstanceType<typeof EmailForm>>()

// isDirty is Inertia's own useForm computed, comparing current values to the
// defaults the form was built with — not a bespoke computed re-implementing
// that comparison, the same thing Packages/Edit.vue and Projects/Quotes.vue
// would reach for too if either needed a dirty state.
// Allgemein und Zahlung zeigen zwei Formulare — Stammdaten und Bankverbindung
// schreiben nach /einstellungen, die Umsatzsteuer nach /einstellungen/ust und
// die Fristen nach /einstellungen/zahlung. Der Kopf speichert deshalb, was
// schmutzig ist, statt ein einzelnes form-Attribut zu tragen.
const activePanes = computed(() => {
  if (props.pane === 'general') return [profileRef.value, vatRef.value]
  if (props.pane === 'payment') return [profileRef.value, paymentRef.value]
  if (props.pane === 'receipts') return [receiptsRef.value]
  return [emailRef.value]
})

const dirty = computed(() => activePanes.value.some((entry) => entry?.form.isDirty))
const processing = computed(() => activePanes.value.some((entry) => entry?.form.processing))

function save() {
  activePanes.value.filter((entry) => entry?.form.isDirty).forEach((entry) => entry!.submit())
}

const paneTitle = computed(() => {
  if (props.pane === 'payment') return 'settings.navPayment'
  if (props.pane === 'receipts') return 'settings.documentsTitle'
  if (props.pane === 'email') return 'settings.mailTitle'
  return 'settings.generalTitle'
})

// The sample sheet Zahlung and Belege share: the saved layout underneath,
// whatever the mounted form holds on top.
const DOCUMENT_PANES = ['payment', 'receipts']

const documentPreview = computed(() => ({
  documentLayout: receiptsRef.value?.form.document_layout ?? props.settings.documentLayout,
}))

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
              <template v-if="pane === 'general'">
                <ProfileForm ref="profileRef" :settings="settings" :font-pairs="fontPairs" :sections="['brand', 'legal', 'general', 'tax']" />
                <VatForm ref="vatRef" :settings="settings" />
              </template>

              <template v-else-if="pane === 'payment'">
                <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('settings.bank') }}</div>
                <ProfileForm ref="profileRef" :settings="settings" :font-pairs="fontPairs" :sections="['bank']" />
                <div class="mt-8">
                  <PaymentForm ref="paymentRef" :settings="settings" />
                </div>
              </template>

              <ReceiptsForm v-else-if="pane === 'receipts'" ref="receiptsRef" :settings="settings" />
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

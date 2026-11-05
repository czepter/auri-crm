<script setup lang="ts">
import { computed, ref } from 'vue'
import { useObjectUrl } from '@vueuse/core'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import SettingsNav from '@/admin/components/settings/SettingsNav.vue'
import ProfileForm from './panes/ProfileForm.vue'
import VatForm from './panes/VatForm.vue'
import PaymentForm from './panes/PaymentForm.vue'
import ReceiptsForm from './panes/ReceiptsForm.vue'
import EmailForm from './panes/EmailForm.vue'
import PortalFrame from './previews/PortalFrame.vue'
import DocumentPreview from './previews/DocumentPreview.vue'
import MailPreview from './previews/MailPreview.vue'
import { Button } from '@/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/ui/dialog'
import { useLiveProps } from '@/admin/lib/liveReload'

const props = defineProps<{
  pane: 'payment' | 'receipts' | 'email' | 'general'
  fontPairs: { value: string; label: string; description: string }[]
  mailSenderFromEnv: { name: boolean; address: boolean }
  settings: App.Settings.Data.StudioSettingsData
}>()
useLiveProps(['settings', 'mailSenderFromEnv'])

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

function saveDirty() {
  activePanes.value.filter((entry) => entry?.form.isDirty).forEach((entry) => entry!.submit())
}

// Eine andere Anrede fragt vor dem Speichern, ob Vorlagen und Automationen
// mitziehen. Gibt es nichts umzustellen, wird ohne Frage gespeichert.
const register = ref<{ adapted: number; skipped: number } | null>(null)

async function save() {
  const profile = profileRef.value?.form

  if (props.pane !== 'general' || !profile || profile.client_register === props.settings.clientRegister) return saveDirty()

  const response = await fetch(`/einstellungen/anrede-vorschau?ziel=${profile.client_register}`, { headers: { Accept: 'application/json' } })
  const counts = response.ok ? await response.json() : null

  if (counts?.adapted > 0) register.value = counts
  else saveDirty()
}

function saveWithRegister(adapt: boolean) {
  if (profileRef.value) profileRef.value.adaptExisting = adapt
  register.value = null
  saveDirty()
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

// Was das Portal an Marke zeigt, wie es im Formular steht.
const logoPicked = useObjectUrl(computed(() => profileRef.value?.form.logo ?? undefined))
const brandQuery = computed(() => {
  const form = profileRef.value?.form

  return Object.fromEntries(Object.entries({
    name: form?.display_name,
    akzent: form?.accent_color,
    schrift: form?.font_pair,
    logo: logoPicked.value,
    adresse: [form?.street, [form?.postcode, form?.city].filter(Boolean).join(' ')].filter(Boolean).join(', '),
  }).filter(([, value]) => value !== undefined && value !== null)) as Record<string, string>
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
          <span v-if="dirty" class="flex-none rounded-[var(--r-sm)] bg-due-bg px-2 py-[3px] text-small leading-[1.5] font-semibold text-due">{{ $t('settings.unsaved') }}</span>
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

        <div class="px-6 pt-7 pb-8">
          <div class="flex flex-wrap items-start gap-8 max-[640px]:flex-col max-[640px]:gap-4">
            <SettingsNav :active="pane" />

            <div class="max-w-[620px] min-w-0 flex-1">
              <template v-if="pane === 'general'">
                <ProfileForm ref="profileRef" :settings="settings" :font-pairs="fontPairs" :sections="['brand', 'legal', 'general', 'tax']" />
                <VatForm ref="vatRef" :settings="settings" />
              </template>

              <template v-else-if="pane === 'payment'">
                <div class="mb-2.5 text-small leading-none font-semibold text-faint">{{ $t('settings.bank') }}</div>
                <ProfileForm ref="profileRef" :settings="settings" :font-pairs="fontPairs" :sections="['bank']" />
                <div class="mt-8">
                  <PaymentForm ref="paymentRef" :settings="settings" />
                </div>
              </template>

              <ReceiptsForm v-else-if="pane === 'receipts'" ref="receiptsRef" :settings="settings" />
              <EmailForm v-else-if="pane === 'email'" ref="emailRef" :settings="settings" :sender-from-env="mailSenderFromEnv" />

            </div>

            <PortalFrame v-if="pane === 'general' && profileRef" :query="brandQuery" />
            <DocumentPreview v-else-if="DOCUMENT_PANES.includes(pane)" :doc="documentPreview" />
            <MailPreview v-else-if="pane === 'email'" :mail="mailPreview" />
          </div>
        </div>
      </div>
    </Page>

    <Dialog :open="register !== null" @update:open="(value) => value || (register = null)">
      <DialogContent class="max-w-[440px]">
        <DialogHeader>
          <DialogTitle>{{ $t('settings.registerAdaptTitle') }}</DialogTitle>
          <DialogDescription>
            {{ $t('settings.registerAdaptBody', { adapted: register?.adapted, target: $t(profileRef?.form.client_register === 'informal' ? 'settings.clientRegisterInformal' : 'settings.clientRegisterFormal') }) }}
            <template v-if="register?.skipped">{{ $t('settings.registerAdaptSkipped', { skipped: register.skipped }) }}</template>
          </DialogDescription>
        </DialogHeader>
        <DialogFooter class="justify-end">
          <Button variant="outline" type="button" @click="saveWithRegister(false)">{{ $t('settings.registerAdaptNo') }}</Button>
          <Button type="button" @click="saveWithRegister(true)">{{ $t('settings.registerAdaptYes') }}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AdminLayout>
</template>

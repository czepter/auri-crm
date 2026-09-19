<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Textarea } from '@/ui/textarea'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'
import { Field } from '@/ui/field'
import { RadioGroup, RadioGroupItem } from '@/ui/radio-group'
import { ChoiceGroup } from '@/ui/choice-group'
import { missingLegalTexts } from '@/admin/lib/legalTexts'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'

type Section = 'portal' | 'home' | 'privacy'

// Reihenfolge der Zeilen im Abschnitt Rechtliches; dieselben Schlüssel führt
// LegalTextController::TEXTS und die Portal-Route /rechtstexte/{text}.
const LEGAL_TEXTS = ['impressum', 'datenschutz', 'agb', 'widerruf'] as const

type LegalText = (typeof LEGAL_TEXTS)[number]

type LegalTemplate = { family: string; name: string; updatedAt: string | null }

const props = withDefaults(defineProps<{
  settings: App.Settings.Data.PortalSettingsData
  sections?: Section[]
  /** Rechtstexte aus dem Dokumenteneditor, als Quelle wählbar. */
  legalTemplates?: LegalTemplate[]
}>(), { sections: () => ['portal', 'home', 'privacy'], legalTemplates: () => [] })
const emit = defineEmits<{ saved: [] }>()
const { t } = useI18n()

// A pane sends only its own fields; the controller's `sometimes` leaves the
// rest of the row untouched.
const SECTION_FIELDS: Record<Section, string[]> = {
  portal: ['portal_maintenance_enabled', 'portal_maintenance_message', 'portal_accept_quotes_enabled', 'portal_payment_status_enabled', 'portal_messaging_mode', 'portal_offers_enabled', 'client_register'],
  home: ['portal_welcome_inquiry', 'portal_welcome_text', 'portal_welcome_many', 'portal_contact_phone', 'portal_contact_email', 'portal_contact_response_time'],
  privacy: [
    'dsgvo_request_recipient', 'dsgvo_request_deadline_days',
    ...LEGAL_TEXTS.flatMap((text) => [`portal_legal_${text}_url`, `portal_legal_${text}_family`]),
  ],
}

const shows = (section: Section) => props.sections.includes(section)

const form = useForm({
  portal_maintenance_enabled: props.settings.maintenanceEnabled,
  portal_maintenance_message: props.settings.maintenanceMessage ?? '',
  portal_welcome_text: props.settings.welcomeText ?? '',
  portal_legal_impressum_url: props.settings.impressumUrl ?? '',
  portal_legal_datenschutz_url: props.settings.datenschutzUrl ?? '',
  portal_legal_agb_url: props.settings.agbUrl ?? '',
  portal_legal_widerruf_url: props.settings.widerrufUrl ?? '',
  portal_legal_impressum_family: props.settings.legalFamilies.impressum ?? '',
  portal_legal_datenschutz_family: props.settings.legalFamilies.datenschutz ?? '',
  portal_legal_agb_family: props.settings.legalFamilies.agb ?? '',
  portal_legal_widerruf_family: props.settings.legalFamilies.widerruf ?? '',
  portal_accept_quotes_enabled: props.settings.acceptQuotesEnabled,
  portal_payment_status_enabled: props.settings.paymentStatusEnabled,
  portal_offers_enabled: props.settings.offersEnabled,
  portal_messaging_mode: props.settings.messagingMode,
  client_register: props.settings.clientRegister,
  portal_welcome_inquiry: props.settings.welcomeInquiry ?? '',
  portal_welcome_many: props.settings.welcomeMany ?? '',
  dsgvo_request_recipient: props.settings.dsgvoRecipient ?? '',
  dsgvo_request_deadline_days: props.settings.dsgvoDeadlineDays,
  portal_contact_phone: props.settings.contactPhone ?? '',
  portal_contact_email: props.settings.contactEmail ?? '',
  portal_contact_response_time: props.settings.contactResponseTime ?? '',
})

type Source = 'studio' | 'link' | 'document'

// Welche Quelle gilt, steht in den Werten selbst: eine Adresse schlägt eine
// Vorlage, und beim Impressum bleibt die Seite aus den Studio-Daten, solange
// keins von beiden gesetzt ist.
function sourceOf(text: LegalText): Source {
  if (form[`portal_legal_${text}_url`]) return 'link'
  if (form[`portal_legal_${text}_family`]) return 'document'

  return text === 'impressum' ? 'studio' : 'link'
}

function sourceOptions(text: LegalText) {
  return [
    ...(text === 'impressum' ? [{ value: 'studio', label: t('portalSettings.legalSourceStudio') }] : []),
    { value: 'link', label: t('portalSettings.legalSourceLink') },
    { value: 'document', label: t('portalSettings.legalSourceDocument') },
  ]
}

// Ohne Widerrufsbelehrung darf ein Angebot entstehen, aber nicht hinausgehen.
const missingBeforeSend = computed(() => missingLegalTexts(Object.fromEntries(
  LEGAL_TEXTS.map((text) => [text, { url: form[`portal_legal_${text}_url`], family: form[`portal_legal_${text}_family`] }]),
)))

function chooseSource(text: LegalText, source: Source) {
  form[`portal_legal_${text}_url`] = ''
  form[`portal_legal_${text}_family`] = source === 'document' ? (props.settings.legalFamilies[text] ?? '') : ''
}

function submit() {
  const sent = new Set<string>(props.sections.flatMap((section) => SECTION_FIELDS[section]))

  form
    .transform((data) => Object.fromEntries(Object.entries({
      ...data,
      portal_maintenance_message: data.portal_maintenance_message || null,
      portal_welcome_text: data.portal_welcome_text || null,
      ...Object.fromEntries(LEGAL_TEXTS.flatMap((text) => [
        [`portal_legal_${text}_url`, data[`portal_legal_${text}_url`] || null],
        [`portal_legal_${text}_family`, data[`portal_legal_${text}_family`] || null],
      ])),
      portal_welcome_inquiry: data.portal_welcome_inquiry || null,
      portal_welcome_many: data.portal_welcome_many || null,
      dsgvo_request_recipient: data.dsgvo_request_recipient || null,
      portal_contact_phone: data.portal_contact_phone || null,
      portal_contact_email: data.portal_contact_email || null,
      portal_contact_response_time: data.portal_contact_response_time || null,
    }).filter(([field]) => sent.has(field))))
    .patch('/einstellungen/portal', { preserveScroll: true, onSuccess: () => emit('saved') })
}

defineExpose({ form, submit })
</script>

<template>
  <form id="portal-settings-form" @submit.prevent="submit">
    <!-- T-08: a temporary closure, worded and switched separately from
         A-04's hide-project/revoke-contact forms — never a third
         catch-all access control. -->
    <div v-if="shows('portal')" class="mt-0">
      <div v-if="sections.length > 1" class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('portalSettings.maintenanceSection') }}</div>
      <div class="mb-6">
        <div class="flex items-center gap-2.5">
          <Checkbox id="cb-portal-maintenance-enabled" v-model="form.portal_maintenance_enabled" />
          <Label variant="inline" for="cb-portal-maintenance-enabled">{{ $t('portalSettings.maintenanceEnabledLabel') }}</Label>
        </div>
        <span class="text-[11.5px] leading-[1.45] text-faint">{{ $t('portalSettings.maintenanceEnabledHint') }}</span>
      </div>
      <div class="mb-6">
        <Field :error="form.errors.portal_maintenance_message" v-slot="{ id }">
          <Label :for="id">{{ $t('portalSettings.maintenanceMessageLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
          <Textarea :id v-model="form.portal_maintenance_message" rows="3" />
        </Field>
      </div>
    </div>

    <div v-if="shows('home')" class="mt-8 first-of-type:mt-6">
      <div v-if="sections.length > 1" class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('portalSettings.homeSection') }}</div>
      <!-- P-09 (#228): three rooms, three texts. One sentence cannot greet
           somebody who has not booked anything, a couple with one wedding
           and a venue with forty dates a year. -->
      <div class="mb-6">
        <Field v-slot="{ id }">
          <Label :for="id">{{ $t('portalSettings.welcomeInquiryLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
          <Textarea :id v-model="form.portal_welcome_inquiry" rows="2" />
        </Field>
      </div>
      <div class="mb-6">
        <Field :error="form.errors.portal_welcome_text" v-slot="{ id }">
          <Label :for="id">{{ $t('portalSettings.welcomeTextLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
          <Textarea :id v-model="form.portal_welcome_text" rows="2" />
        </Field>
      </div>
      <div class="mb-6">
        <Field v-slot="{ id }">
          <Label :for="id">{{ $t('portalSettings.welcomeManyLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
          <Textarea :id v-model="form.portal_welcome_many" rows="2" />
        </Field>
      </div>
    </div>

    <!-- P-05 (#224) put subject access and erasure in the portal; both are
         requests with a legal deadline, so both need somewhere to land. -->
    <div v-if="shows('privacy')" class="mt-8 first-of-type:mt-6">
      <div v-if="sections.length > 1" class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('portalSettings.dsgvoSection') }}</div>
      <div class="mb-6">
        <div class="grid grid-cols-2 gap-4">
          <Field :error="form.errors.dsgvo_request_recipient" v-slot="{ id }">
            <Label :for="id">{{ $t('portalSettings.dsgvoRecipientLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
            <Input :id v-model="form.dsgvo_request_recipient" type="email" />
          </Field>
          <Field :label="$t('portalSettings.dsgvoDeadlineLabel')" id="po-dsgvo-frist">
            <InputGroup>
              <InputGroupInput id="po-dsgvo-frist" v-model="form.dsgvo_request_deadline_days" class="tabular-nums" type="number" min="1" />
              <InputGroupAddon align="inline-end">{{ $t('common.unitDays') }}</InputGroupAddon>
            </InputGroup>
          </Field>
        </div>
      </div>
    </div>

    <!-- The portal footer's contact line had no source: portalBranding
         carries only the logo, the accent colour and the display name. -->
    <div v-if="shows('home')" class="mt-8 first-of-type:mt-6">
      <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('portalSettings.contactSection') }}</div>
      <div class="mb-6">
        <div class="grid grid-cols-2 gap-4">
          <Field v-slot="{ id }">
            <Label :for="id">{{ $t('portalSettings.contactPhoneLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
            <Input :id v-model="form.portal_contact_phone" type="tel" />
          </Field>
          <Field :error="form.errors.portal_contact_email" v-slot="{ id }">
            <Label :for="id">{{ $t('portalSettings.contactEmailLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
            <Input :id v-model="form.portal_contact_email" type="email" />
          </Field>
        </div>
      </div>
      <div class="mb-6">
        <Field v-slot="{ id }">
          <Label :for="id">{{ $t('portalSettings.contactResponseLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
          <Input :id v-model="form.portal_contact_response_time" type="text" />
        </Field>
      </div>
    </div>

    <div v-if="shows('privacy')" class="mt-8 first-of-type:mt-6">
      <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('portalSettings.legalSection') }}</div>
      <div class="mb-6 flex flex-col gap-3">
        <div v-for="text in LEGAL_TEXTS" :key="text" class="flex flex-wrap items-center gap-x-5 gap-y-3 rounded-[12px] border border-border p-4">
          <div class="w-[200px] flex-none">
            <span class="block text-[14px] leading-none font-semibold tracking-[-0.01em] text-foreground">{{ $t(`portalSettings.legal_${text}`) }}</span>
            <span class="mt-1.5 block text-[12px] text-muted-foreground">{{ $t(`portalSettings.legalNote_${text}`) }}</span>
          </div>

          <ChoiceGroup
            class="flex-none"
            variant="segmented"
            :model-value="sourceOf(text)"
            :options="sourceOptions(text)"
            @update:model-value="(value) => chooseSource(text, value as Source)"
          />

          <div class="min-w-[220px] flex-grow">
            <Field v-if="sourceOf(text) === 'link'" :error="form.errors[`portal_legal_${text}_url`]" v-slot="{ id }">
              <Label :for="id">{{ $t('portalSettings.legalUrlLabel') }}</Label>
              <Input :id v-model="form[`portal_legal_${text}_url`]" type="text" placeholder="https://" />
            </Field>

            <Field v-else-if="sourceOf(text) === 'document'" :error="form.errors[`portal_legal_${text}_family`]" v-slot="{ id }">
              <Label :for="id">{{ $t('portalSettings.legalTemplateLabel') }}</Label>
              <Select
                :model-value="form[`portal_legal_${text}_family`]"
                @update:model-value="(value) => (form[`portal_legal_${text}_family`] = String(value))"
              >
                <SelectTrigger :id><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="template in legalTemplates" :key="template.family" :value="template.family">
                    {{ template.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </Field>

            <a
              v-else
              class="text-[13px] font-medium text-action no-underline"
              href="/impressum"
              target="_blank"
              rel="noopener"
            >{{ $t('portalSettings.legalOpenImpressum') }}</a>
          </div>
        </div>

        <div v-if="missingBeforeSend.length" class="flex flex-wrap items-center gap-2.5 rounded-[12px] bg-due-bg px-4 py-3 text-[12.5px] text-due">
          <span class="font-semibold">{{ $t('portalSettings.legalMissing') }}</span>
          <span>{{ missingBeforeSend.map((text) => $t(`portalSettings.legal_${text}`)).join(', ') }}</span>
        </div>

        <a class="text-[13px] font-medium text-action no-underline" href="/dokumentvorlagen/neu">
          {{ $t('portalSettings.legalNewTemplate') }}
        </a>
      </div>
    </div>

    <!-- T-08: every switch below defaults to off — a studio opts the
         portal into each capability, whether or not the feature behind
         it already ships elsewhere. -->
    <div v-if="shows('portal')" class="mt-8 first-of-type:mt-6">
      <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('portalSettings.capabilitiesSection') }}</div>
      <div class="mb-6">
        <div class="flex items-center gap-2.5">
          <Checkbox id="cb-portal-accept-quotes-enabled" v-model="form.portal_accept_quotes_enabled" />
          <Label variant="inline" for="cb-portal-accept-quotes-enabled">{{ $t('portalSettings.acceptQuotesEnabledLabel') }}</Label>
        </div>
      </div>
      <div class="mb-6">
        <div class="flex items-center gap-2.5">
          <Checkbox id="cb-portal-payment-status-enabled" v-model="form.portal_payment_status_enabled" />
          <Label variant="inline" for="cb-portal-payment-status-enabled">{{ $t('portalSettings.paymentStatusEnabledLabel') }}</Label>
        </div>
        <div class="flex items-center gap-2.5">
          <!-- Eigener Schalter und nicht an „Angebote annehmen" gehängt: ein
               Kunde mitten in einer Reklamation soll nichts angeboten
               bekommen, darf aber weiter annehmen, was er schon hat. -->
          <Checkbox id="cb-portal-offers-enabled" v-model="form.portal_offers_enabled" />
          <Label variant="inline" for="cb-portal-offers-enabled">{{ $t('portalSettings.offersEnabledLabel') }}</Label>
        </div>
      </div>
      <!-- P-04 (#223) made a thread without a booking possible, which is a
           third state rather than a second position of one switch. -->
      <div class="mb-6">
        <Label>{{ $t('portalSettings.messagingLabel') }}</Label>
        <RadioGroup v-model="form.portal_messaging_mode">
          <div v-for="mode in ['off', 'booking', 'any']" :key="mode" class="flex items-center gap-2.5">
            <RadioGroupItem :id="`rb-messaging-${mode}`" :value="mode" />
            <Label variant="inline" :for="`rb-messaging-${mode}`">
              {{ $t(`portalSettings.messagingMode.${mode}`) }}
            </Label>
          </div>
        </RadioGroup>
      </div>
      <ChoiceGroup
        v-model="form.client_register"
        class="mb-6"
        :label="$t('settings.clientRegister')"
        :options="[
          { value: 'formal', label: $t('settings.clientRegisterFormal') },
          { value: 'informal', label: $t('settings.clientRegisterInformal') },
        ]"
      />
    </div>
  </form>
</template>

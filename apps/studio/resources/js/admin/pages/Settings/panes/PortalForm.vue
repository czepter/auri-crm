<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Textarea } from '@/ui/textarea'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'
import { Field } from '@/ui/field'
import { RadioGroup, RadioGroupItem } from '@/ui/radio-group'

type Section = 'portal' | 'home' | 'privacy'

const props = withDefaults(defineProps<{
  settings: App.Settings.Data.PortalSettingsData
  sections?: Section[]
}>(), { sections: () => ['portal', 'home', 'privacy'] })
const emit = defineEmits<{ saved: [] }>()

// A pane sends only its own fields; the controller's `sometimes` leaves the
// rest of the row untouched.
const SECTION_FIELDS: Record<Section, string[]> = {
  portal: ['portal_maintenance_enabled', 'portal_maintenance_message', 'portal_accept_quotes_enabled', 'portal_sign_contracts_enabled', 'portal_payment_status_enabled', 'portal_messaging_mode', 'portal_offers_enabled'],
  home: ['portal_welcome_inquiry', 'portal_welcome_text', 'portal_welcome_many', 'portal_contact_phone', 'portal_contact_email', 'portal_contact_response_time'],
  privacy: ['dsgvo_request_recipient', 'dsgvo_request_deadline_days', 'portal_legal_datenschutz_url', 'portal_legal_agb_url'],
}

const shows = (section: Section) => props.sections.includes(section)

const form = useForm({
  portal_maintenance_enabled: props.settings.maintenanceEnabled,
  portal_maintenance_message: props.settings.maintenanceMessage ?? '',
  portal_welcome_text: props.settings.welcomeText ?? '',
  portal_legal_datenschutz_url: props.settings.datenschutzUrl ?? '',
  portal_legal_agb_url: props.settings.agbUrl ?? '',
  portal_accept_quotes_enabled: props.settings.acceptQuotesEnabled,
  portal_sign_contracts_enabled: props.settings.signContractsEnabled,
  portal_payment_status_enabled: props.settings.paymentStatusEnabled,
  portal_offers_enabled: props.settings.offersEnabled,
  portal_messaging_mode: props.settings.messagingMode,
  portal_welcome_inquiry: props.settings.welcomeInquiry ?? '',
  portal_welcome_many: props.settings.welcomeMany ?? '',
  dsgvo_request_recipient: props.settings.dsgvoRecipient ?? '',
  dsgvo_request_deadline_days: props.settings.dsgvoDeadlineDays,
  portal_contact_phone: props.settings.contactPhone ?? '',
  portal_contact_email: props.settings.contactEmail ?? '',
  portal_contact_response_time: props.settings.contactResponseTime ?? '',
})

function submit() {
  const sent = new Set<string>(props.sections.flatMap((section) => SECTION_FIELDS[section]))

  form
    .transform((data) => Object.fromEntries(Object.entries({
      ...data,
      portal_maintenance_message: data.portal_maintenance_message || null,
      portal_welcome_text: data.portal_welcome_text || null,
      portal_legal_datenschutz_url: data.portal_legal_datenschutz_url || null,
      portal_legal_agb_url: data.portal_legal_agb_url || null,
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
      <div class="mb-6">
        <div class="grid grid-cols-2 gap-4">
          <Field :error="form.errors.portal_legal_datenschutz_url" v-slot="{ id }">
            <Label :for="id">{{ $t('portalSettings.datenschutzUrlLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
            <Input :id v-model="form.portal_legal_datenschutz_url" type="text" />
          </Field>
          <Field :error="form.errors.portal_legal_agb_url" v-slot="{ id }">
            <Label :for="id">{{ $t('portalSettings.agbUrlLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
            <Input :id v-model="form.portal_legal_agb_url" type="text" />
          </Field>
        </div>
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
          <Checkbox id="cb-portal-sign-contracts-enabled" v-model="form.portal_sign_contracts_enabled" />
          <Label variant="inline" for="cb-portal-sign-contracts-enabled">{{ $t('portalSettings.signContractsEnabledLabel') }}</Label>
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
    </div>
  </form>
</template>

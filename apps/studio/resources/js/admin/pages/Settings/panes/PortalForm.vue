<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Textarea } from '@/ui/textarea'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'
import { Field } from '@/ui/field'
import { RadioGroup, RadioGroupItem } from '@/ui/radio-group'

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
  portal_messaging_mode: props.settings.messagingMode,
  portal_welcome_inquiry: props.settings.welcomeInquiry ?? '',
  portal_welcome_many: props.settings.welcomeMany ?? '',
  gallery_default_expiry_days: props.settings.galleryExpiryDays ?? '',
  gallery_default_requires_settled_balance: props.settings.galleryRequiresSettledBalance,
  gallery_default_downloads_enabled: props.settings.galleryDownloadsEnabled,
  gallery_default_lists_enabled: props.settings.galleryListsEnabled,
  gallery_default_comments_enabled: props.settings.galleryCommentsEnabled,
  dsgvo_request_recipient: props.settings.dsgvoRecipient ?? '',
  dsgvo_request_deadline_days: props.settings.dsgvoDeadlineDays,
  portal_contact_phone: props.settings.contactPhone ?? '',
  portal_contact_email: props.settings.contactEmail ?? '',
  portal_contact_response_time: props.settings.contactResponseTime ?? '',
})

function submit() {
  form
    .transform((data) => ({
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
      gallery_default_expiry_days: data.gallery_default_expiry_days === '' ? null : data.gallery_default_expiry_days,
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
    <div class="mt-0">
      <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('portalSettings.maintenanceSection') }}</div>
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

    <div class="mt-8 first-of-type:mt-6">
      <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('portalSettings.homeSection') }}</div>
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

    <!-- P-09: what a new gallery starts as. These live per gallery today and
         nowhere as a default, so a studio re-enters them on every delivery. -->
    <div class="mt-8 first-of-type:mt-6">
      <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('portalSettings.gallerySection') }}</div>
      <div class="mb-6">
        <Field :error="form.errors.gallery_default_expiry_days" v-slot="{ id }">
          <Label :for="id">{{ $t('portalSettings.galleryExpiryLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
          <InputGroup>
            <InputGroupInput :id v-model="form.gallery_default_expiry_days" class="tabular-nums" type="number" min="1" />
            <InputGroupAddon align="inline-end">{{ $t('common.unitDays') }}</InputGroupAddon>
          </InputGroup>
        </Field>
      </div>
      <div class="mb-6">
        <div class="flex items-center gap-2.5">
          <Checkbox id="cb-gallery-balance" v-model="form.gallery_default_requires_settled_balance" />
          <Label variant="inline" for="cb-gallery-balance">{{ $t('portalSettings.galleryBalanceLabel') }}</Label>
        </div>
      </div>
      <div class="mb-6">
        <div class="flex items-center gap-2.5">
          <Checkbox id="cb-gallery-downloads" v-model="form.gallery_default_downloads_enabled" />
          <Label variant="inline" for="cb-gallery-downloads">{{ $t('portalSettings.galleryDownloadsLabel') }}</Label>
        </div>
      </div>
      <div class="mb-6">
        <div class="flex items-center gap-2.5">
          <Checkbox id="cb-gallery-lists" v-model="form.gallery_default_lists_enabled" />
          <Label variant="inline" for="cb-gallery-lists">{{ $t('portalSettings.galleryListsLabel') }}</Label>
        </div>
      </div>
      <div class="mb-6">
        <div class="flex items-center gap-2.5">
          <Checkbox id="cb-gallery-comments" v-model="form.gallery_default_comments_enabled" />
          <Label variant="inline" for="cb-gallery-comments">{{ $t('portalSettings.galleryCommentsLabel') }}</Label>
        </div>
      </div>
    </div>

    <!-- P-05 (#224) put subject access and erasure in the portal; both are
         requests with a legal deadline, so both need somewhere to land. -->
    <div class="mt-8 first-of-type:mt-6">
      <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('portalSettings.dsgvoSection') }}</div>
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
    <div class="mt-8 first-of-type:mt-6">
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

    <div class="mt-8 first-of-type:mt-6">
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
    <div class="mt-8 first-of-type:mt-6">
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

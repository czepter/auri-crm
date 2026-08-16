<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Input } from '@/ui/input'
import { Textarea } from '@/ui/textarea'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'
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
    <div class="dsec" style="margin-top: 0">
      <div class="dlab">{{ $t('portalSettings.maintenanceSection') }}</div>
      <div class="grp">
        <div class="cbrow">
          <Checkbox id="cb-portal-maintenance-enabled" v-model="form.portal_maintenance_enabled" />
          <Label variant="inline" for="cb-portal-maintenance-enabled">{{ $t('portalSettings.maintenanceEnabledLabel') }}</Label>
        </div>
        <span class="hint">{{ $t('portalSettings.maintenanceEnabledHint') }}</span>
      </div>
      <div class="grp">
        <div class="fld">
          <Label for="po-wartungstext">{{ $t('portalSettings.maintenanceMessageLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
          <Textarea id="po-wartungstext" v-model="form.portal_maintenance_message" rows="3" />
          <p v-if="form.errors.portal_maintenance_message" class="err">{{ form.errors.portal_maintenance_message }}</p>
        </div>
      </div>
    </div>

    <div class="dsec">
      <div class="dlab">{{ $t('portalSettings.homeSection') }}</div>
      <!-- P-09 (#228): three rooms, three texts. One sentence cannot greet
           somebody who has not booked anything, a couple with one wedding
           and a venue with forty dates a year. -->
      <div class="grp">
        <div class="fld">
          <Label for="po-willkommen-anfrage">{{ $t('portalSettings.welcomeInquiryLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
          <Textarea id="po-willkommen-anfrage" v-model="form.portal_welcome_inquiry" rows="2" />
        </div>
      </div>
      <div class="grp">
        <div class="fld">
          <Label for="po-willkommen">{{ $t('portalSettings.welcomeTextLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
          <Textarea id="po-willkommen" v-model="form.portal_welcome_text" rows="2" />
          <p v-if="form.errors.portal_welcome_text" class="err">{{ form.errors.portal_welcome_text }}</p>
        </div>
      </div>
      <div class="grp">
        <div class="fld">
          <Label for="po-willkommen-viele">{{ $t('portalSettings.welcomeManyLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
          <Textarea id="po-willkommen-viele" v-model="form.portal_welcome_many" rows="2" />
        </div>
      </div>
    </div>

    <!-- P-09: what a new gallery starts as. These live per gallery today and
         nowhere as a default, so a studio re-enters them on every delivery. -->
    <div class="dsec">
      <div class="dlab">{{ $t('portalSettings.gallerySection') }}</div>
      <div class="grp">
        <div class="fld">
          <Label for="po-galerie-tage">{{ $t('portalSettings.galleryExpiryLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
          <Input id="po-galerie-tage" v-model="form.gallery_default_expiry_days" type="number" min="1" />
          <p v-if="form.errors.gallery_default_expiry_days" class="err">{{ form.errors.gallery_default_expiry_days }}</p>
        </div>
      </div>
      <div class="grp">
        <div class="cbrow">
          <Checkbox id="cb-gallery-balance" v-model="form.gallery_default_requires_settled_balance" />
          <Label variant="inline" for="cb-gallery-balance">{{ $t('portalSettings.galleryBalanceLabel') }}</Label>
        </div>
      </div>
      <div class="grp">
        <div class="cbrow">
          <Checkbox id="cb-gallery-downloads" v-model="form.gallery_default_downloads_enabled" />
          <Label variant="inline" for="cb-gallery-downloads">{{ $t('portalSettings.galleryDownloadsLabel') }}</Label>
        </div>
      </div>
      <div class="grp">
        <div class="cbrow">
          <Checkbox id="cb-gallery-lists" v-model="form.gallery_default_lists_enabled" />
          <Label variant="inline" for="cb-gallery-lists">{{ $t('portalSettings.galleryListsLabel') }}</Label>
        </div>
      </div>
      <div class="grp">
        <div class="cbrow">
          <Checkbox id="cb-gallery-comments" v-model="form.gallery_default_comments_enabled" />
          <Label variant="inline" for="cb-gallery-comments">{{ $t('portalSettings.galleryCommentsLabel') }}</Label>
        </div>
      </div>
    </div>

    <!-- P-05 (#224) put subject access and erasure in the portal; both are
         requests with a legal deadline, so both need somewhere to land. -->
    <div class="dsec">
      <div class="dlab">{{ $t('portalSettings.dsgvoSection') }}</div>
      <div class="grp">
        <div class="two">
          <div class="fld">
            <Label for="po-dsgvo-mail">{{ $t('portalSettings.dsgvoRecipientLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
            <Input id="po-dsgvo-mail" v-model="form.dsgvo_request_recipient" type="email" />
            <p v-if="form.errors.dsgvo_request_recipient" class="err">{{ form.errors.dsgvo_request_recipient }}</p>
          </div>
          <div class="fld">
            <Label for="po-dsgvo-frist">{{ $t('portalSettings.dsgvoDeadlineLabel') }}</Label>
            <Input id="po-dsgvo-frist" v-model="form.dsgvo_request_deadline_days" type="number" min="1" />
          </div>
        </div>
      </div>
    </div>

    <!-- The portal footer's contact line had no source: portalBranding
         carries only the logo, the accent colour and the display name. -->
    <div class="dsec">
      <div class="dlab">{{ $t('portalSettings.contactSection') }}</div>
      <div class="grp">
        <div class="two">
          <div class="fld">
            <Label for="po-kontakt-tel">{{ $t('portalSettings.contactPhoneLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
            <Input id="po-kontakt-tel" v-model="form.portal_contact_phone" type="tel" />
          </div>
          <div class="fld">
            <Label for="po-kontakt-mail">{{ $t('portalSettings.contactEmailLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
            <Input id="po-kontakt-mail" v-model="form.portal_contact_email" type="email" />
            <p v-if="form.errors.portal_contact_email" class="err">{{ form.errors.portal_contact_email }}</p>
          </div>
        </div>
      </div>
      <div class="grp">
        <div class="fld">
          <Label for="po-kontakt-zeit">{{ $t('portalSettings.contactResponseLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
          <Input id="po-kontakt-zeit" v-model="form.portal_contact_response_time" type="text" />
        </div>
      </div>
    </div>

    <div class="dsec">
      <div class="dlab">{{ $t('portalSettings.legalSection') }}</div>
      <div class="grp">
        <div class="two">
          <div class="fld">
            <Label for="po-datenschutz">{{ $t('portalSettings.datenschutzUrlLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
            <Input id="po-datenschutz" v-model="form.portal_legal_datenschutz_url" type="text" />
            <p v-if="form.errors.portal_legal_datenschutz_url" class="err">{{ form.errors.portal_legal_datenschutz_url }}</p>
          </div>
          <div class="fld">
            <Label for="po-agb">{{ $t('portalSettings.agbUrlLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
            <Input id="po-agb" v-model="form.portal_legal_agb_url" type="text" />
            <p v-if="form.errors.portal_legal_agb_url" class="err">{{ form.errors.portal_legal_agb_url }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- T-08: every switch below defaults to off — a studio opts the
         portal into each capability, whether or not the feature behind
         it already ships elsewhere. -->
    <div class="dsec">
      <div class="dlab">{{ $t('portalSettings.capabilitiesSection') }}</div>
      <div class="grp">
        <div class="cbrow">
          <Checkbox id="cb-portal-accept-quotes-enabled" v-model="form.portal_accept_quotes_enabled" />
          <Label variant="inline" for="cb-portal-accept-quotes-enabled">{{ $t('portalSettings.acceptQuotesEnabledLabel') }}</Label>
        </div>
      </div>
      <div class="grp">
        <div class="cbrow">
          <Checkbox id="cb-portal-sign-contracts-enabled" v-model="form.portal_sign_contracts_enabled" />
          <Label variant="inline" for="cb-portal-sign-contracts-enabled">{{ $t('portalSettings.signContractsEnabledLabel') }}</Label>
        </div>
      </div>
      <div class="grp">
        <div class="cbrow">
          <Checkbox id="cb-portal-payment-status-enabled" v-model="form.portal_payment_status_enabled" />
          <Label variant="inline" for="cb-portal-payment-status-enabled">{{ $t('portalSettings.paymentStatusEnabledLabel') }}</Label>
        </div>
      </div>
      <!-- P-04 (#223) made a thread without a booking possible, which is a
           third state rather than a second position of one switch. -->
      <div class="grp">
        <Label>{{ $t('portalSettings.messagingLabel') }}</Label>
        <RadioGroup v-model="form.portal_messaging_mode">
          <div v-for="mode in ['off', 'booking', 'any']" :key="mode" class="cbrow">
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

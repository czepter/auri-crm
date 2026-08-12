<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Input } from '@/ui/input'
import { Textarea } from '@/ui/textarea'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'

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
  portal_messaging_enabled: props.settings.messagingEnabled,
})

function submit() {
  form
    .transform((data) => ({
      ...data,
      portal_maintenance_message: data.portal_maintenance_message || null,
      portal_welcome_text: data.portal_welcome_text || null,
      portal_legal_datenschutz_url: data.portal_legal_datenschutz_url || null,
      portal_legal_agb_url: data.portal_legal_agb_url || null,
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
      <div class="grp">
        <div class="fld">
          <Label for="po-willkommen">{{ $t('portalSettings.welcomeTextLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
          <Textarea id="po-willkommen" v-model="form.portal_welcome_text" rows="3" />
          <p v-if="form.errors.portal_welcome_text" class="err">{{ form.errors.portal_welcome_text }}</p>
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
      <div class="grp">
        <div class="cbrow">
          <Checkbox id="cb-portal-messaging-enabled" v-model="form.portal_messaging_enabled" />
          <Label variant="inline" for="cb-portal-messaging-enabled">{{ $t('portalSettings.messagingEnabledLabel') }}</Label>
        </div>
      </div>
    </div>
  </form>
</template>

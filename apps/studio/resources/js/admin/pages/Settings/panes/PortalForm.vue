<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

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
        <label style="display: flex; align-items: center; gap: 10px; font: 400 13px/1.4 var(--font)">
          <input v-model="form.portal_maintenance_enabled" type="checkbox" />
          {{ $t('portalSettings.maintenanceEnabledLabel') }}
        </label>
        <span class="hint">{{ $t('portalSettings.maintenanceEnabledHint') }}</span>
      </div>
      <div class="grp">
        <div class="fld">
          <label for="po-wartungstext">{{ $t('portalSettings.maintenanceMessageLabel') }} <em>{{ $t('settings.optional') }}</em></label>
          <textarea id="po-wartungstext" v-model="form.portal_maintenance_message" class="inp" rows="3"></textarea>
          <span class="hint">{{ $t('portalSettings.maintenanceMessageHint') }}</span>
          <p v-if="form.errors.portal_maintenance_message" class="err">{{ form.errors.portal_maintenance_message }}</p>
        </div>
      </div>
    </div>

    <div class="dsec">
      <div class="dlab">{{ $t('portalSettings.homeSection') }}</div>
      <div class="grp">
        <div class="fld">
          <label for="po-willkommen">{{ $t('portalSettings.welcomeTextLabel') }} <em>{{ $t('settings.optional') }}</em></label>
          <textarea id="po-willkommen" v-model="form.portal_welcome_text" class="inp" rows="3"></textarea>
          <span class="hint">{{ $t('portalSettings.welcomeTextHint') }}</span>
          <p v-if="form.errors.portal_welcome_text" class="err">{{ form.errors.portal_welcome_text }}</p>
        </div>
      </div>
    </div>

    <div class="dsec">
      <div class="dlab">{{ $t('portalSettings.legalSection') }}</div>
      <div class="grp">
        <div class="two">
          <div class="fld">
            <label for="po-datenschutz">{{ $t('portalSettings.datenschutzUrlLabel') }} <em>{{ $t('settings.optional') }}</em></label>
            <input id="po-datenschutz" v-model="form.portal_legal_datenschutz_url" class="inp" type="text" />
            <p v-if="form.errors.portal_legal_datenschutz_url" class="err">{{ form.errors.portal_legal_datenschutz_url }}</p>
          </div>
          <div class="fld">
            <label for="po-agb">{{ $t('portalSettings.agbUrlLabel') }} <em>{{ $t('settings.optional') }}</em></label>
            <input id="po-agb" v-model="form.portal_legal_agb_url" class="inp" type="text" />
            <p v-if="form.errors.portal_legal_agb_url" class="err">{{ form.errors.portal_legal_agb_url }}</p>
          </div>
        </div>
        <span class="hint">{{ $t('portalSettings.legalUrlHint') }}</span>
      </div>
    </div>

    <!-- T-08: every switch below defaults to off — a studio opts the
         portal into each capability, whether or not the feature behind
         it already ships elsewhere. -->
    <div class="dsec">
      <div class="dlab">{{ $t('portalSettings.capabilitiesSection') }}</div>
      <div class="grp">
        <label style="display: flex; align-items: center; gap: 10px; font: 400 13px/1.4 var(--font)">
          <input v-model="form.portal_accept_quotes_enabled" type="checkbox" />
          {{ $t('portalSettings.acceptQuotesEnabledLabel') }}
        </label>
        <span class="hint">{{ $t('portalSettings.acceptQuotesEnabledHint') }}</span>
      </div>
      <div class="grp">
        <label style="display: flex; align-items: center; gap: 10px; font: 400 13px/1.4 var(--font)">
          <input v-model="form.portal_sign_contracts_enabled" type="checkbox" />
          {{ $t('portalSettings.signContractsEnabledLabel') }}
        </label>
        <span class="hint">{{ $t('portalSettings.signContractsEnabledHint') }}</span>
      </div>
      <div class="grp">
        <label style="display: flex; align-items: center; gap: 10px; font: 400 13px/1.4 var(--font)">
          <input v-model="form.portal_payment_status_enabled" type="checkbox" />
          {{ $t('portalSettings.paymentStatusEnabledLabel') }}
        </label>
        <span class="hint">{{ $t('portalSettings.paymentStatusEnabledHint') }}</span>
      </div>
      <div class="grp">
        <label style="display: flex; align-items: center; gap: 10px; font: 400 13px/1.4 var(--font)">
          <input v-model="form.portal_messaging_enabled" type="checkbox" />
          {{ $t('portalSettings.messagingEnabledLabel') }}
        </label>
        <span class="hint">{{ $t('portalSettings.messagingEnabledHint') }}</span>
      </div>
    </div>
  </form>
</template>

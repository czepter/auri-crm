<script setup lang="ts">
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import { basisPointsToPercent } from '@/admin/lib/money'

const props = defineProps<{ settings: App.Settings.Data.PaymentSettingsData }>()

// Every other future group — the same disabled "bald" list Settings/Index.vue
// and Numbering.vue both render. Zahlungsdaten & USt is T-03's own pane,
// Nummernkreise is T-04's, Dokumente is T-05's, Zahlungen is this pane and
// Portal is T-08's own route, so all five drop out of this list.
const comingSoon = ['navNotifications'] as const

// Keys are write-only (T-07): the server never echoes a stored value back,
// so this form always starts with them blank. Submitting blank leaves the
// stored pair untouched — see StudioSettingsResolver::setPaymentSettings().
const form = useForm({
  payments_stripe_enabled: props.settings.stripeEnabled,
  payments_stripe_mode: props.settings.stripeMode,
  payments_reminders_enabled: props.settings.remindersEnabled,
  payments_stripe_test_publishable_key: '',
  payments_stripe_test_secret_key: '',
  payments_stripe_live_publishable_key: '',
  payments_stripe_live_secret_key: '',
})

const hasActiveKeys = computed(() =>
  form.payments_stripe_mode === 'live' ? props.settings.hasStripeLiveKeys : props.settings.hasStripeTestKeys,
)

function submit() {
  form.patch('/einstellungen/zahlungen', { preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="einstellungen">
    <div class="hrow">
      <div>
        <div class="kick">{{ $t('settings.kicker') }}</div>
        <h1>{{ $t('paymentSettings.title') }}</h1>
      </div>
      <span v-if="form.isDirty" class="chip now">{{ $t('settings.unsaved') }}</span>
      <button
        class="btn"
        type="submit"
        form="payment-settings-form"
        :disabled="!form.isDirty || form.processing"
        :style="!form.isDirty ? 'opacity:.4' : undefined"
      >
        <svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" /></svg>
        {{ $t('common.save') }}
      </button>
    </div>

    <div style="display: flex; gap: var(--s6); margin-top: var(--s5); align-items: flex-start">
      <nav style="width: 196px; flex: none; display: flex; flex-direction: column; gap: 2px">
        <a class="nav" href="/einstellungen">{{ $t('settings.navProfile') }}</a>
        <a class="nav" href="/einstellungen/nummernkreise">{{ $t('settings.navNumbering') }}</a>
        <a class="nav" href="/einstellungen/verfuegbarkeit">{{ $t('settings.navAvailability') }}</a>
        <a class="nav on" href="/einstellungen/zahlungen">{{ $t('settings.navPayments') }}</a>
        <a class="nav" href="/einstellungen/aufgabenvorlagen">{{ $t('settings.navTaskTemplates') }}</a>
        <a class="nav" href="/einstellungen/portal">{{ $t('settings.navPortal') }}</a>
        <a
          v-for="key in comingSoon"
          :key="key"
          class="nav"
          href="#"
          style="pointer-events: none; opacity: 0.5"
        >
          {{ $t(`settings.${key}`) }}
          <span class="chip next" style="margin-left: auto">{{ $t('settings.soon') }}</span>
        </a>
      </nav>

      <form id="payment-settings-form" style="flex: 1; min-width: 0; max-width: 620px" @submit.prevent="submit">
        <div class="dsec" style="margin-top: 0">
          <div class="dlab">{{ $t('paymentSettings.stripeSection') }}</div>

          <div class="grp">
            <label style="display: flex; align-items: center; gap: 10px; font: 400 13px/1.4 var(--font)">
              <input v-model="form.payments_stripe_enabled" type="checkbox" />
              {{ $t('paymentSettings.stripeEnabled') }}
            </label>
            <span class="hint">{{ $t('paymentSettings.stripeEnabledHint') }}</span>
          </div>

          <div class="grp">
            <div class="fld" style="max-width: 220px">
              <label for="ps-mode">{{ $t('paymentSettings.stripeMode') }}</label>
              <select id="ps-mode" v-model="form.payments_stripe_mode" class="inp">
                <option value="test">{{ $t('paymentSettings.stripeModeTest') }}</option>
                <option value="live">{{ $t('paymentSettings.stripeModeLive') }}</option>
              </select>
            </div>
            <span class="hint">
              {{ hasActiveKeys ? $t('paymentSettings.stripeKeyConfigured') : $t('paymentSettings.stripeKeyMissing') }}
            </span>
          </div>

          <div class="grp">
            <div class="dlab" style="font-size: 12px">{{ $t('paymentSettings.stripeTestKeys') }}</div>
            <div class="two">
              <div class="fld">
                <label for="ps-test-pk">{{ $t('paymentSettings.stripePublishableKey') }}</label>
                <input
                  id="ps-test-pk"
                  v-model="form.payments_stripe_test_publishable_key"
                  class="inp num"
                  type="text"
                  autocomplete="off"
                  :placeholder="settings.hasStripeTestKeys ? '••••••••••••' : ''"
                />
                <p v-if="form.errors.payments_stripe_test_publishable_key" class="err">
                  {{ form.errors.payments_stripe_test_publishable_key }}
                </p>
              </div>
              <div class="fld">
                <label for="ps-test-sk">{{ $t('paymentSettings.stripeSecretKey') }}</label>
                <input
                  id="ps-test-sk"
                  v-model="form.payments_stripe_test_secret_key"
                  class="inp num"
                  type="password"
                  autocomplete="off"
                  :placeholder="settings.hasStripeTestKeys ? '••••••••••••' : ''"
                />
                <p v-if="form.errors.payments_stripe_test_secret_key" class="err">
                  {{ form.errors.payments_stripe_test_secret_key }}
                </p>
              </div>
            </div>
            <span class="hint">{{ $t('paymentSettings.stripeKeyHint') }}</span>
          </div>

          <div class="grp">
            <div class="dlab" style="font-size: 12px">{{ $t('paymentSettings.stripeLiveKeys') }}</div>
            <div class="two">
              <div class="fld">
                <label for="ps-live-pk">{{ $t('paymentSettings.stripePublishableKey') }}</label>
                <input
                  id="ps-live-pk"
                  v-model="form.payments_stripe_live_publishable_key"
                  class="inp num"
                  type="text"
                  autocomplete="off"
                  :placeholder="settings.hasStripeLiveKeys ? '••••••••••••' : ''"
                />
                <p v-if="form.errors.payments_stripe_live_publishable_key" class="err">
                  {{ form.errors.payments_stripe_live_publishable_key }}
                </p>
              </div>
              <div class="fld">
                <label for="ps-live-sk">{{ $t('paymentSettings.stripeSecretKey') }}</label>
                <input
                  id="ps-live-sk"
                  v-model="form.payments_stripe_live_secret_key"
                  class="inp num"
                  type="password"
                  autocomplete="off"
                  :placeholder="settings.hasStripeLiveKeys ? '••••••••••••' : ''"
                />
                <p v-if="form.errors.payments_stripe_live_secret_key" class="err">
                  {{ form.errors.payments_stripe_live_secret_key }}
                </p>
              </div>
            </div>
            <span class="hint">{{ $t('paymentSettings.stripeKeyHint') }}</span>
          </div>
        </div>

        <div class="dsec">
          <div class="dlab">{{ $t('paymentSettings.defaultsSection') }}</div>
          <div class="grp">
            <label style="display: flex; align-items: center; gap: 10px; font: 400 13px/1.4 var(--font)">
              <input v-model="form.payments_reminders_enabled" type="checkbox" />
              {{ $t('paymentSettings.remindersEnabled') }}
            </label>
          </div>

          <!-- Deposit percentage and Zahlungsziel are T-05's own Dokumente
               fields (studio_settings.deposit_percentage / payment_term_days)
               — read-only here for context, edited there, never a second
               writable copy. -->
          <div class="grp">
            <div class="two">
              <div class="fld">
                <label>{{ $t('paymentSettings.depositPercent') }}</label>
                <div class="inp num" style="background: var(--hover); pointer-events: none">
                  {{ basisPointsToPercent(settings.depositPercent) }} %
                </div>
              </div>
              <div class="fld">
                <label>{{ $t('paymentSettings.zahlungszielDays') }}</label>
                <div class="inp num" style="background: var(--hover); pointer-events: none">
                  {{ settings.zahlungszielDays }}
                </div>
              </div>
            </div>
            <span class="hint">
              {{ $t('paymentSettings.defaultsReadOnlyHint') }}
              <a href="/einstellungen">{{ $t('settings.navDocuments') }}</a>
            </span>
          </div>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

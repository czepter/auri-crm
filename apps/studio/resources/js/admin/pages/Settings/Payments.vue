<script setup lang="ts">
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'

const props = defineProps<{ settings: App.Settings.Data.PaymentSettingsData }>()

// Every other future group (Dokumente, Portal, ...) — the same disabled
// "bald" list Settings/Index.vue and Numbering.vue both render. Zahlungen
// drops out of this list here, the same way Nummernkreise dropped out once
// T-04 gave it a real route.
const comingSoon = ['navDocuments', 'navPortal', 'navNotifications', 'navGeneral'] as const

// Keys are write-only (T-07): the server never echoes a stored value back,
// so this form always starts with them blank. Submitting blank leaves the
// stored pair untouched — see StudioSettingsResolver::setPaymentSettings().
const form = useForm({
  payments_stripe_enabled: props.settings.stripeEnabled,
  payments_stripe_mode: props.settings.stripeMode,
  payments_deposit_percent: (props.settings.depositPercent / 100).toFixed(2),
  payments_zahlungsziel_days: props.settings.zahlungszielDays,
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
  form
    .transform((data) => ({
      ...data,
      payments_deposit_percent: Math.round(parseFloat(data.payments_deposit_percent || '0') * 100),
    }))
    .patch('/einstellungen/zahlungen', { preserveScroll: true })
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
        <a class="nav on" href="/einstellungen/zahlungen">{{ $t('settings.navPayments') }}</a>
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
            <div class="two">
              <div class="fld">
                <label for="ps-deposit">{{ $t('paymentSettings.depositPercent') }}</label>
                <input
                  id="ps-deposit"
                  v-model="form.payments_deposit_percent"
                  class="inp num"
                  type="text"
                  inputmode="decimal"
                />
                <p v-if="form.errors.payments_deposit_percent" class="err">{{ form.errors.payments_deposit_percent }}</p>
              </div>
              <div class="fld">
                <label for="ps-zahlungsziel">{{ $t('paymentSettings.zahlungszielDays') }}</label>
                <input
                  id="ps-zahlungsziel"
                  v-model.number="form.payments_zahlungsziel_days"
                  class="inp num"
                  type="number"
                  min="0"
                />
                <p v-if="form.errors.payments_zahlungsziel_days" class="err">{{ form.errors.payments_zahlungsziel_days }}</p>
              </div>
            </div>
          </div>
          <div class="grp">
            <label style="display: flex; align-items: center; gap: 10px; font: 400 13px/1.4 var(--font)">
              <input v-model="form.payments_reminders_enabled" type="checkbox" />
              {{ $t('paymentSettings.remindersEnabled') }}
            </label>
          </div>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import { basisPointsToPercent } from '@/admin/lib/money'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'

const props = defineProps<{ settings: App.Settings.Data.PaymentSettingsData }>()

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
      <Button
        type="submit"
        form="payment-settings-form"
        :disabled="!form.isDirty || form.processing"
        :style="!form.isDirty ? 'opacity:.4' : undefined"
      >
        <svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" /></svg>
        {{ $t('common.save') }}
      </Button>
    </div>

    <div style="display: flex; gap: var(--s6); margin-top: var(--s5); align-items: flex-start">
      <SettingsNav active="payments" />

      <form id="payment-settings-form" style="flex: 1; min-width: 0; max-width: 620px" @submit.prevent="submit">
        <div class="dsec" style="margin-top: 0">
          <div class="dlab">{{ $t('paymentSettings.stripeSection') }}</div>

          <div class="grp">
            <div class="cbrow">
              <Checkbox id="cb-payments-stripe-enabled" v-model="form.payments_stripe_enabled" />
              <Label variant="inline" for="cb-payments-stripe-enabled">{{ $t('paymentSettings.stripeEnabled') }}</Label>
            </div>
            <span class="hint">{{ $t('paymentSettings.stripeEnabledHint') }}</span>
          </div>

          <div class="grp">
            <div class="fld" style="max-width: 220px">
              <Label for="ps-mode">{{ $t('paymentSettings.stripeMode') }}</Label>
              <Select
                :model-value="form.payments_stripe_mode"
                @update:model-value="(value) => (form.payments_stripe_mode = String(value))"
              >
                <SelectTrigger id="ps-mode">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="test">{{ $t('paymentSettings.stripeModeTest') }}</SelectItem>
                  <SelectItem value="live">{{ $t('paymentSettings.stripeModeLive') }}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <span class="hint">
              {{ hasActiveKeys ? $t('paymentSettings.stripeKeyConfigured') : $t('paymentSettings.stripeKeyMissing') }}
            </span>
          </div>

          <div class="grp">
            <div class="dlab" style="font-size: 12px">{{ $t('paymentSettings.stripeTestKeys') }}</div>
            <div class="two">
              <div class="fld">
                <Label for="ps-test-pk">{{ $t('paymentSettings.stripePublishableKey') }}</Label>
                <Input
                  id="ps-test-pk"
                  v-model="form.payments_stripe_test_publishable_key" class="tabular-nums"
                  type="text"
                  autocomplete="off"
                  :placeholder="settings.hasStripeTestKeys ? '••••••••••••' : ''" />
                <p v-if="form.errors.payments_stripe_test_publishable_key" class="err">
                  {{ form.errors.payments_stripe_test_publishable_key }}
                </p>
              </div>
              <div class="fld">
                <Label for="ps-test-sk">{{ $t('paymentSettings.stripeSecretKey') }}</Label>
                <Input
                  id="ps-test-sk"
                  v-model="form.payments_stripe_test_secret_key" class="tabular-nums"
                  type="password"
                  autocomplete="off"
                  :placeholder="settings.hasStripeTestKeys ? '••••••••••••' : ''" />
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
                <Label for="ps-live-pk">{{ $t('paymentSettings.stripePublishableKey') }}</Label>
                <Input
                  id="ps-live-pk"
                  v-model="form.payments_stripe_live_publishable_key" class="tabular-nums"
                  type="text"
                  autocomplete="off"
                  :placeholder="settings.hasStripeLiveKeys ? '••••••••••••' : ''" />
                <p v-if="form.errors.payments_stripe_live_publishable_key" class="err">
                  {{ form.errors.payments_stripe_live_publishable_key }}
                </p>
              </div>
              <div class="fld">
                <Label for="ps-live-sk">{{ $t('paymentSettings.stripeSecretKey') }}</Label>
                <Input
                  id="ps-live-sk"
                  v-model="form.payments_stripe_live_secret_key" class="tabular-nums"
                  type="password"
                  autocomplete="off"
                  :placeholder="settings.hasStripeLiveKeys ? '••••••••••••' : ''" />
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
            <div class="cbrow">
              <Checkbox id="cb-payments-reminders-enabled" v-model="form.payments_reminders_enabled" />
              <Label variant="inline" for="cb-payments-reminders-enabled">{{ $t('paymentSettings.remindersEnabled') }}</Label>
            </div>
          </div>

          <!-- Deposit percentage and Zahlungsziel are T-05's own Dokumente
               fields (studio_settings.deposit_percentage / payment_term_days)
               — read-only here for context, edited there, never a second
               writable copy. -->
          <div class="grp">
            <div class="two">
              <div class="fld">
                <Label>{{ $t('paymentSettings.depositPercent') }}</Label>
                <Input
                  :model-value="`${basisPointsToPercent(settings.depositPercent)} %`"
                  class="pointer-events-none bg-muted tabular-nums"
                  readonly
                />
              </div>
              <div class="fld">
                <Label>{{ $t('paymentSettings.zahlungszielDays') }}</Label>
                <Input
                  :model-value="settings.zahlungszielDays"
                  class="pointer-events-none bg-muted tabular-nums"
                  readonly
                />
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

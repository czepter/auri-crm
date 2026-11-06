<script setup lang="ts">
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import FormShell from '@/ui/page/FormShell.vue'
import SettingsNav from '@/admin/components/settings/SettingsNav.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'
import { saveState } from '@/admin/lib/autosave'
import { useLiveProps } from '@/admin/lib/liveReload'

const props = defineProps<{
  settings: App.Settings.Data.PaymentSettingsData
  status: { lastWebhookAt: string | null; lastPaidOn: string | null }
}>()
useLiveProps(['settings', 'status'])

const day = (value: string | null) => (value ? new Date(value.replace(' ', 'T')).toLocaleDateString('de-DE') : '–')

// Keys are write-only (T-07): the server never echoes a stored value back,
// so this form always starts with them blank. Submitting blank leaves the
// stored pair untouched — see App\Settings\Actions\UpdatePaymentSettings.
const form = useForm({
  payments_stripe_enabled: props.settings.stripeEnabled,
  payments_stripe_mode: props.settings.stripeMode,
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
    <FormShell
      :kicker="$t('settings.kicker')" :title="$t('paymentSettings.title')"
      :state="saveState([form])"
    >
      <template #head>
        <Button
          v-if="form.isDirty"
          variant="pill"
          type="submit"
          form="payment-settings-form"
          :disabled="form.processing"
        >
          {{ $t('common.save') }}
        </Button>
      </template>

      <template #nav><SettingsNav active="payments" /></template>

      <form id="payment-settings-form" style="flex: 1; min-width: 0; max-width: 620px" @submit.prevent="submit">
        <div>
          <div class="mb-2.5 text-small leading-none font-semibold text-faint">{{ $t('paymentSettings.stripeSection') }}</div>

          <div class="mb-6">
            <div class="flex items-center gap-2.5">
              <Checkbox id="cb-payments-stripe-enabled" v-model="form.payments_stripe_enabled" />
              <Label variant="inline" for="cb-payments-stripe-enabled">{{ $t('paymentSettings.stripeEnabled') }}</Label>
            </div>
            <span class="text-small leading-[1.45] font-normal text-faint">{{ $t('paymentSettings.stripeEnabledHint') }}</span>
          </div>

          <div class="mb-6">
            <div class="flex min-w-0 flex-col gap-1.5" style="max-width: 220px">
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
            <span v-if="!hasActiveKeys" class="text-small leading-[1.45] font-normal text-faint">
              {{ $t('paymentSettings.stripeKeyMissing', { keys: $t(form.payments_stripe_mode === 'live' ? 'paymentSettings.stripeLiveKeys' : 'paymentSettings.stripeTestKeys') }) }}
            </span>
          </div>

          <div class="mb-6">
            <div class="mb-2.5 text-small leading-none font-semibold text-faint">{{ $t('paymentSettings.stripeTestKeys') }}</div>
            <div class="grid grid-cols-2 gap-4">
              <div class="flex min-w-0 flex-col gap-1.5">
                <Label for="ps-test-pk">{{ $t('paymentSettings.stripePublishableKey') }}</Label>
                <Input
                  id="ps-test-pk"
                  v-model="form.payments_stripe_test_publishable_key" class="tabular-nums"
                  type="text"
                  autocomplete="off"
                  :placeholder="settings.hasStripeTestKeys ? '••••••••••••' : ''" />
                <p v-if="form.errors.payments_stripe_test_publishable_key" class="text-small leading-[1.45] text-destructive">
                  {{ form.errors.payments_stripe_test_publishable_key }}
                </p>
              </div>
              <div class="flex min-w-0 flex-col gap-1.5">
                <Label for="ps-test-sk">{{ $t('paymentSettings.stripeSecretKey') }}</Label>
                <Input
                  id="ps-test-sk"
                  v-model="form.payments_stripe_test_secret_key" class="tabular-nums"
                  type="password"
                  autocomplete="off"
                  :placeholder="settings.hasStripeTestKeys ? '••••••••••••' : ''" />
                <p v-if="form.errors.payments_stripe_test_secret_key" class="text-small leading-[1.45] text-destructive">
                  {{ form.errors.payments_stripe_test_secret_key }}
                </p>
              </div>
            </div>
            <span class="text-small leading-[1.45] font-normal text-faint">{{ $t('paymentSettings.stripeKeyHint') }}</span>
          </div>

          <div class="mb-6">
            <div class="mb-2.5 text-small leading-none font-semibold text-faint">{{ $t('paymentSettings.stripeLiveKeys') }}</div>
            <div class="grid grid-cols-2 gap-4">
              <div class="flex min-w-0 flex-col gap-1.5">
                <Label for="ps-live-pk">{{ $t('paymentSettings.stripePublishableKey') }}</Label>
                <Input
                  id="ps-live-pk"
                  v-model="form.payments_stripe_live_publishable_key" class="tabular-nums"
                  type="text"
                  autocomplete="off"
                  :placeholder="settings.hasStripeLiveKeys ? '••••••••••••' : ''" />
                <p v-if="form.errors.payments_stripe_live_publishable_key" class="text-small leading-[1.45] text-destructive">
                  {{ form.errors.payments_stripe_live_publishable_key }}
                </p>
              </div>
              <div class="flex min-w-0 flex-col gap-1.5">
                <Label for="ps-live-sk">{{ $t('paymentSettings.stripeSecretKey') }}</Label>
                <Input
                  id="ps-live-sk"
                  v-model="form.payments_stripe_live_secret_key" class="tabular-nums"
                  type="password"
                  autocomplete="off"
                  :placeholder="settings.hasStripeLiveKeys ? '••••••••••••' : ''" />
                <p v-if="form.errors.payments_stripe_live_secret_key" class="text-small leading-[1.45] text-destructive">
                  {{ form.errors.payments_stripe_live_secret_key }}
                </p>
              </div>
            </div>
            <span class="text-small leading-[1.45] font-normal text-faint">{{ $t('paymentSettings.stripeKeyHint') }}</span>
          </div>
        </div>

      </form>

      <template #aside>
        <dl class="m-0 grid gap-3 rounded-[var(--r)] bg-paper p-5 shadow-card text-ui">
          <div class="flex justify-between gap-4">
            <dt class="text-ink-2">{{ $t('paymentSettings.stripeMode') }}</dt>
            <dd class="m-0 font-semibold">{{ $t(form.payments_stripe_mode === 'live' ? 'paymentSettings.stripeModeLive' : 'paymentSettings.stripeModeTest') }}</dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-ink-2">{{ $t('paymentSettings.statusWebhook') }}</dt>
            <dd class="m-0 tabular-nums">{{ day(status.lastWebhookAt) }}</dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-ink-2">{{ $t('paymentSettings.statusLastPayment') }}</dt>
            <dd class="m-0 tabular-nums">{{ day(status.lastPaidOn) }}</dd>
          </div>
        </dl>
      </template>
    </FormShell>
  </AdminLayout>
</template>

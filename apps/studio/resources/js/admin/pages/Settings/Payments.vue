<script setup lang="ts">
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PageHead from '@/ui/page/PageHead.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import { basisPointsToPercent } from '@/admin/lib/money'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
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
    <div data-tokens>
      <PageHead :kicker="$t('settings.kicker')" :title="$t('paymentSettings.title')">
        <span v-if="form.isDirty" class="flex-none rounded-[var(--r-sm)] px-2 py-[3px] text-[10px] leading-[1.5] font-semibold tracking-[0.04em] uppercase bg-due-bg text-due">{{ $t('settings.unsaved') }}</span>
        <Button
          class="ml-auto"
          variant="pill"
          type="submit"
          form="payment-settings-form"
          :disabled="!form.isDirty || form.processing"
        >
          {{ $t('common.save') }}
        </Button>
      </PageHead>

      <div class="px-8 pt-7 pb-8">
        <div class="flex items-start gap-8">
          <SettingsNav active="payments" />

          <form id="payment-settings-form" style="flex: 1; min-width: 0; max-width: 620px" @submit.prevent="submit">
            <div>
              <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('paymentSettings.stripeSection') }}</div>

              <div class="mb-6">
                <div class="flex items-center gap-2.5">
                  <Checkbox id="cb-payments-stripe-enabled" v-model="form.payments_stripe_enabled" />
                  <Label variant="inline" for="cb-payments-stripe-enabled">{{ $t('paymentSettings.stripeEnabled') }}</Label>
                </div>
                <span class="text-[11.5px] leading-[1.45] font-normal text-faint">{{ $t('paymentSettings.stripeEnabledHint') }}</span>
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
                <span class="text-[11.5px] leading-[1.45] font-normal text-faint">
                  {{ hasActiveKeys ? $t('paymentSettings.stripeKeyConfigured') : $t('paymentSettings.stripeKeyMissing') }}
                </span>
              </div>

              <div class="mb-6">
                <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint" style="font-size: 12px">{{ $t('paymentSettings.stripeTestKeys') }}</div>
                <div class="grid grid-cols-2 gap-4">
                  <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for="ps-test-pk">{{ $t('paymentSettings.stripePublishableKey') }}</Label>
                    <Input
                      id="ps-test-pk"
                      v-model="form.payments_stripe_test_publishable_key" class="tabular-nums"
                      type="text"
                      autocomplete="off"
                      :placeholder="settings.hasStripeTestKeys ? '••••••••••••' : ''" />
                    <p v-if="form.errors.payments_stripe_test_publishable_key" class="text-[11.5px] leading-[1.45] text-destructive">
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
                    <p v-if="form.errors.payments_stripe_test_secret_key" class="text-[11.5px] leading-[1.45] text-destructive">
                      {{ form.errors.payments_stripe_test_secret_key }}
                    </p>
                  </div>
                </div>
                <span class="text-[11.5px] leading-[1.45] font-normal text-faint">{{ $t('paymentSettings.stripeKeyHint') }}</span>
              </div>

              <div class="mb-6">
                <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint" style="font-size: 12px">{{ $t('paymentSettings.stripeLiveKeys') }}</div>
                <div class="grid grid-cols-2 gap-4">
                  <div class="flex min-w-0 flex-col gap-1.5">
                    <Label for="ps-live-pk">{{ $t('paymentSettings.stripePublishableKey') }}</Label>
                    <Input
                      id="ps-live-pk"
                      v-model="form.payments_stripe_live_publishable_key" class="tabular-nums"
                      type="text"
                      autocomplete="off"
                      :placeholder="settings.hasStripeLiveKeys ? '••••••••••••' : ''" />
                    <p v-if="form.errors.payments_stripe_live_publishable_key" class="text-[11.5px] leading-[1.45] text-destructive">
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
                    <p v-if="form.errors.payments_stripe_live_secret_key" class="text-[11.5px] leading-[1.45] text-destructive">
                      {{ form.errors.payments_stripe_live_secret_key }}
                    </p>
                  </div>
                </div>
                <span class="text-[11.5px] leading-[1.45] font-normal text-faint">{{ $t('paymentSettings.stripeKeyHint') }}</span>
              </div>
            </div>

            <div class="mt-8 first-of-type:mt-6">
              <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('paymentSettings.defaultsSection') }}</div>
              <div class="mb-6">
                <div class="flex items-center gap-2.5">
                  <Checkbox id="cb-payments-reminders-enabled" v-model="form.payments_reminders_enabled" />
                  <Label variant="inline" for="cb-payments-reminders-enabled">{{ $t('paymentSettings.remindersEnabled') }}</Label>
                </div>
              </div>

              <!-- Deposit percentage and Zahlungsziel are T-05's own Dokumente
                   fields (studio_settings.deposit_percentage / payment_term_days)
                   — read-only here for context, edited there, never a second
                   writable copy. -->
              <div class="mb-6">
                <div class="grid grid-cols-2 gap-4">
                  <div class="flex min-w-0 flex-col gap-1.5">
                    <Label>{{ $t('paymentSettings.depositPercent') }}</Label>
                    <InputGroup class="pointer-events-none bg-muted">
                      <InputGroupInput
                        :model-value="basisPointsToPercent(settings.depositPercent)"
                        class="tabular-nums"
                        readonly
                      />
                      <InputGroupAddon align="inline-end">%</InputGroupAddon>
                    </InputGroup>
                  </div>
                  <div class="flex min-w-0 flex-col gap-1.5">
                    <Label>{{ $t('paymentSettings.zahlungszielDays') }}</Label>
                    <InputGroup class="pointer-events-none bg-muted">
                      <InputGroupInput
                        :model-value="settings.zahlungszielDays"
                        class="tabular-nums"
                        readonly
                      />
                      <InputGroupAddon align="inline-end">{{ $t('common.unitDays') }}</InputGroupAddon>
                    </InputGroup>
                  </div>
                </div>
                <span class="text-[11.5px] leading-[1.45] font-normal text-faint">
                  {{ $t('paymentSettings.defaultsReadOnlyHint') }}
                  <a href="/einstellungen">{{ $t('settings.navDocuments') }}</a>
                </span>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

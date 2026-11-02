<script setup lang="ts">
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'
import { useLiveProps } from '@/admin/lib/liveReload'

const props = defineProps<{ settings: App.Settings.Data.PaymentSettingsData }>()
useLiveProps(['settings'])

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
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :kicker="$t('settings.kicker')" :title="$t('paymentSettings.title')">
          <span v-if="form.isDirty" class="flex-none rounded-[var(--r-sm)] px-2 py-[3px] text-small leading-[1.5] font-semibold bg-due-bg text-due">{{ $t('settings.unsaved') }}</span>
          <Button
            v-if="form.isDirty"
            class="ml-auto"
            variant="pill"
            type="submit"
            form="payment-settings-form"
            :disabled="form.processing"
          >
            {{ $t('common.save') }}
          </Button>
        </PageHead>

        <div class="px-6 pt-7 pb-8">
          <div class="flex items-start gap-8 max-[640px]:flex-col max-[640px]:gap-4">
            <SettingsNav active="payments" />

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
                  <span class="text-small leading-[1.45] font-normal text-faint">
                    {{ hasActiveKeys ? $t('paymentSettings.stripeKeyConfigured') : $t('paymentSettings.stripeKeyMissing') }}
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
          </div>
        </div>
      </div>
    </Page>
  </AdminLayout>
</template>

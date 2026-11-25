<script setup lang="ts">
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import FormSection from '@/ui/page/FormSection.vue'
import { Input } from '@/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'
import { Field } from '@/ui/field'

const props = defineProps<{
  stripe: App.Settings.Data.PaymentSettingsData
  status: { lastWebhookAt: string | null; lastPaidOn: string | null }
}>()
const emit = defineEmits<{ saved: [] }>()

const day = (value: string | null) => (value ? new Date(value.replace(' ', 'T')).toLocaleDateString('de-DE') : '–')

// Keys are write-only (T-07): the server never echoes a stored value back,
// so this form always starts with them blank. Submitting blank leaves the
// stored pair untouched — see App\Settings\Actions\UpdatePaymentSettings.
const form = useForm({
  payments_stripe_enabled: props.stripe.stripeEnabled,
  payments_stripe_mode: props.stripe.stripeMode,
  payments_stripe_test_publishable_key: '',
  payments_stripe_test_secret_key: '',
  payments_stripe_live_publishable_key: '',
  payments_stripe_live_secret_key: '',
})

const hasActiveKeys = computed(() =>
  form.payments_stripe_mode === 'live' ? props.stripe.hasStripeLiveKeys : props.stripe.hasStripeTestKeys,
)

function submit() {
  form.patch('/einstellungen/zahlung/stripe', { preserveScroll: true, onSuccess: () => emit('saved') })
}

defineExpose({ form, submit })
</script>

<template>
  <form id="stripe-form" @submit.prevent="submit">
    <FormSection :title="$t('paymentSettings.stripeSection')">
      <div class="grid gap-6">
        <div>
          <div class="flex items-center gap-2.5">
            <Checkbox id="cb-payments-stripe-enabled" v-model="form.payments_stripe_enabled" />
            <Label variant="inline" for="cb-payments-stripe-enabled">{{ $t('paymentSettings.stripeEnabled') }}</Label>
          </div>
          <span class="text-small leading-[1.45] font-normal text-faint">{{ $t('paymentSettings.stripeEnabledHint') }}</span>
        </div>

        <div>
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

        <div v-for="mode in (['test', 'live'] as const)" :key="mode">
          <div class="mb-2.5 text-small leading-none font-semibold text-faint">{{ $t(`paymentSettings.stripe${mode === 'live' ? 'Live' : 'Test'}Keys`) }}</div>
          <div class="grid grid-cols-2 gap-4">
            <Field :label="$t('paymentSettings.stripePublishableKey')" :id="`ps-${mode}-pk`" :error="form.errors[`payments_stripe_${mode}_publishable_key`]">
              <Input
                :id="`ps-${mode}-pk`"
                v-model="form[`payments_stripe_${mode}_publishable_key`]" class="tabular-nums"
                type="text"
                autocomplete="off"
                :placeholder="stripe[mode === 'live' ? 'hasStripeLiveKeys' : 'hasStripeTestKeys'] ? '••••••••••••' : ''" />
            </Field>
            <Field :label="$t('paymentSettings.stripeSecretKey')" :id="`ps-${mode}-sk`" :error="form.errors[`payments_stripe_${mode}_secret_key`]">
              <Input
                :id="`ps-${mode}-sk`"
                v-model="form[`payments_stripe_${mode}_secret_key`]" class="tabular-nums"
                type="password"
                autocomplete="off"
                :placeholder="stripe[mode === 'live' ? 'hasStripeLiveKeys' : 'hasStripeTestKeys'] ? '••••••••••••' : ''" />
            </Field>
          </div>
          <span class="text-small leading-[1.45] font-normal text-faint">{{ $t('paymentSettings.stripeKeyHint') }}</span>
        </div>

        <dl class="m-0 grid gap-2 text-ui">
          <div class="flex justify-between gap-4">
            <dt class="text-ink-2">{{ $t('paymentSettings.statusWebhook') }}</dt>
            <dd class="m-0 tabular-nums">{{ day(status.lastWebhookAt) }}</dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-ink-2">{{ $t('paymentSettings.statusLastPayment') }}</dt>
            <dd class="m-0 tabular-nums">{{ day(status.lastPaidOn) }}</dd>
          </div>
        </dl>
      </div>
    </FormSection>
  </form>
</template>

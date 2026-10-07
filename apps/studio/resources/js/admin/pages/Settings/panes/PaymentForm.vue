<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { basisPointsToPercent, percentToBasisPoints } from '@/admin/lib/money'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'
import { Field } from '@/ui/field'

const props = defineProps<{ settings: App.Settings.Data.StudioSettingsData }>()
const emit = defineEmits<{ saved: [] }>()

const form = useForm({
  payment_term_days: props.settings.paymentTermDays,
  deposit_percentage: basisPointsToPercent(props.settings.depositPercentage),
  payments_reminders_enabled: props.settings.remindersEnabled,
})

function submit() {
  form
    .transform((data) => ({
      ...data,
      deposit_percentage: percentToBasisPoints(data.deposit_percentage),
    }))
    .patch('/einstellungen/zahlung', { preserveScroll: true, onSuccess: () => emit('saved') })
}

defineExpose({ form, submit })
</script>

<template>
  <form id="payment-form" @submit.prevent="submit">
    <div class="mt-0">
      <div class="mb-2.5 text-small leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('settings.documentsPaymentLabel') }}</div>
      <div class="mb-6">
        <div class="grid grid-cols-2 gap-4">
          <Field :label="$t('settings.paymentTermLabel')" id="d-zahlungsziel" :error="form.errors.payment_term_days" :hint="$t('settings.paymentTermHint')">
            <InputGroup>
              <InputGroupInput id="d-zahlungsziel" v-model.number="form.payment_term_days" class="tabular-nums" type="number" min="0" />
              <InputGroupAddon align="inline-end">{{ $t('common.unitDays') }}</InputGroupAddon>
            </InputGroup>
          </Field>
          <Field :label="$t('settings.depositPercentageLabel')" id="d-anzahlung" :error="form.errors.deposit_percentage">
            <InputGroup>
              <InputGroupInput id="d-anzahlung" v-model="form.deposit_percentage" class="tabular-nums" type="text" inputmode="decimal" />
              <InputGroupAddon align="inline-end">%</InputGroupAddon>
            </InputGroup>
          </Field>
        </div>
      </div>
    </div>

    <!-- Die Mahnstufe stand bei Stripe, weil das Geld dort eingeht. Fällig
         wird eine Rate aber nach der Frist daneben. -->
    <div class="mt-8">
      <div class="mb-2.5 text-small leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('paymentSettings.remindersSection') }}</div>
      <div class="mb-6">
        <div class="flex items-center gap-2.5">
          <Checkbox id="cb-payments-reminders-enabled" v-model="form.payments_reminders_enabled" />
          <Label variant="inline" for="cb-payments-reminders-enabled">{{ $t('paymentSettings.remindersEnabled') }}</Label>
        </div>
        <span class="text-small leading-[1.45] text-faint">{{ $t('paymentSettings.remindersCadence') }}</span>
      </div>
    </div>
  </form>
</template>

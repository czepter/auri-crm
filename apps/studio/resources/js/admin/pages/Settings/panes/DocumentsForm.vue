<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { basisPointsToPercent, percentToBasisPoints } from '@/admin/lib/money'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Textarea } from '@/ui/textarea'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'
import { Field } from '@/ui/field'

const props = defineProps<{ settings: App.Settings.Data.StudioSettingsData }>()
const emit = defineEmits<{ saved: [] }>()

const form = useForm({
  quote_validity_days: props.settings.quoteValidityDays,
  payment_term_days: props.settings.paymentTermDays,
  deposit_percentage: basisPointsToPercent(props.settings.depositPercentage),
  widerruf_included: props.settings.widerrufIncluded,
  widerruf_text: props.settings.widerrufText ?? '',
})

function submit() {
  form
    .transform((data) => ({
      ...data,
      deposit_percentage: percentToBasisPoints(data.deposit_percentage),
      widerruf_text: data.widerruf_text || null,
    }))
    .patch('/einstellungen/dokumente', { preserveScroll: true, onSuccess: () => emit('saved') })
}

defineExpose({ form, submit })
</script>

<template>
  <form id="documents-form" @submit.prevent="submit">
    <div class="mt-0">
      <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('settings.documentsQuoteLabel') }}</div>
      <div class="mb-6">
        <Field
          id="d-gueltigkeit"
          class="max-w-[160px]"
          :label="$t('settings.quoteValidityLabel')"
          :hint="$t('settings.quoteValidityHint')"
          :error="form.errors.quote_validity_days"
        >
          <InputGroup>
            <InputGroupInput id="d-gueltigkeit" v-model.number="form.quote_validity_days" class="tabular-nums" type="number" min="0" />
            <InputGroupAddon align="inline-end">{{ $t('common.unitDays') }}</InputGroupAddon>
          </InputGroup>
        </Field>
      </div>
    </div>

    <div class="mt-8 first-of-type:mt-6">
      <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('settings.documentsPaymentLabel') }}</div>
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

    <div class="mt-8 first-of-type:mt-6">
      <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('settings.widerrufLabel') }}</div>
      <div class="mb-6">
        <div class="flex items-center gap-2.5">
          <Checkbox id="cb-widerruf-included" v-model="form.widerruf_included" />
          <Label variant="inline" for="cb-widerruf-included">{{ $t('settings.widerrufIncludedLabel') }}</Label>
        </div>
      </div>
      <div class="mb-6">
        <Field :label="$t('settings.widerrufTextLabel')" id="d-widerruf-text" :error="form.errors.widerruf_text">
          <Textarea
            id="d-widerruf-text"
            v-model="form.widerruf_text"
            rows="5"
            :disabled="!form.widerruf_included" />
        </Field>
      </div>
    </div>
  </form>
</template>

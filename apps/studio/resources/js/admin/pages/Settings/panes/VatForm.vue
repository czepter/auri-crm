<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { basisPointsToPercent, percentToBasisPoints } from '@/lib/money'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Textarea } from '@/ui/textarea'
import { RadioGroup, RadioGroupItem } from '@/ui/radio-group'
import { Label } from '@/ui/label'
import { Field } from '@/ui/field'

const props = defineProps<{ settings: App.Settings.Data.StudioSettingsData }>()
const emit = defineEmits<{ saved: [] }>()

const form = useForm({
  vat_mode: props.settings.vatMode,
  vat_rate: basisPointsToPercent(props.settings.vatRate),
  vat_note: props.settings.vatNote ?? '',
})

function submit() {
  form
    .transform((data) => ({
      vat_mode: data.vat_mode,
      vat_rate: percentToBasisPoints(data.vat_rate),
      vat_note: data.vat_note || null,
    }))
    .patch('/einstellungen/ust', { preserveScroll: true, onSuccess: () => emit('saved') })
}

defineExpose({ form, submit })
</script>

<template>
  <form id="vat-form" @submit.prevent="submit">
    <div class="mt-0">
      <div class="mb-2.5 text-small leading-none font-semibold text-faint">{{ $t('settings.vatModeLabel') }}</div>
      <div class="mb-6">
        <RadioGroup
          name="vatmode"
          :model-value="form.vat_mode"
          @update:model-value="(value) => (form.vat_mode = String(value))"
        >
          <div class="flex items-center gap-2.5">
            <RadioGroupItem id="vat-standard" value="standard" />
            <Label variant="inline" for="vat-standard">{{ $t('settings.vatModeStandard') }}</Label>
          </div>
          <div class="flex items-center gap-2.5">
            <RadioGroupItem id="vat-small-business" value="small_business" />
            <Label variant="inline" for="vat-small-business">{{ $t('settings.vatModeSmallBusiness') }}</Label>
          </div>
        </RadioGroup>
        <p class="mt-2.5 text-small leading-[1.45] text-destructive">{{ $t('settings.vatModeWarning') }}</p>
      </div>
      <div v-if="form.vat_mode === 'standard'" class="mb-6">
        <Field
          id="v-satz"
          class="max-w-[160px]"
          :label="$t('settings.vatRateLabel')"
          :hint="$t('settings.vatRateHint')"
          :error="form.errors.vat_rate"
        >
          <InputGroup>
            <InputGroupInput id="v-satz" v-model="form.vat_rate" class="tabular-nums" type="text" inputmode="decimal" />
            <InputGroupAddon align="inline-end">%</InputGroupAddon>
          </InputGroup>
        </Field>
      </div>
    </div>

    <template v-if="form.vat_mode === 'small_business'">
    <div class="mt-8 first-of-type:mt-6">
      <div class="mb-2.5 text-small leading-none font-semibold text-faint">{{ $t('settings.vatNoteLabel') }}</div>
      <div class="mb-6">
        <Field :error="form.errors.vat_note" v-slot="{ id }">
          <Label :for="id">{{ $t('settings.vatNoteFieldLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
          <Textarea :id v-model="form.vat_note" rows="3" />
        </Field>
      </div>
    </div>

    <div class="mt-8 first-of-type:mt-6">
      <div class="mb-2.5 text-small leading-none font-semibold text-faint">{{ $t('settings.vatPreviewLabel') }}</div>
      <div class="relative block cursor-default rounded-lg border border-border p-2.5">
        <div class="mb-4 flex items-center justify-between"><span class="mb-[5px] text-small leading-none font-semibold text-faint">{{ $t('settings.vatPreviewKicker') }}</span></div>
        <p v-if="form.vat_note" style="margin: 0; font: 400 13px/1.5 var(--font); color: var(--ink)">
          {{ form.vat_note }}
        </p>
        <p v-else class="m-0 text-small leading-[1.45] text-faint">{{ $t('settings.vatPreviewEmpty') }}</p>
      </div>
    </div>
    </template>
  </form>
</template>

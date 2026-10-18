<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { RadioGroup, RadioGroupItem } from '@/ui/radio-group'
import { Field } from '@/ui/field'

const props = defineProps<{ settings: App.Settings.Data.StudioSettingsData }>()
const emit = defineEmits<{ saved: [] }>()

const DOCUMENT_LAYOUTS = [
  { value: 'classic', labelKey: 'settings.documentLayoutClassic', descKey: 'settings.documentLayoutClassicDesc' },
  { value: 'minimal', labelKey: 'settings.documentLayoutMinimal', descKey: 'settings.documentLayoutMinimalDesc' },
  { value: 'letter', labelKey: 'settings.documentLayoutLetter', descKey: 'settings.documentLayoutLetterDesc' },
  { value: 'header_band', labelKey: 'settings.documentLayoutHeaderBand', descKey: 'settings.documentLayoutHeaderBandDesc' },
  { value: 'structured', labelKey: 'settings.documentLayoutStructured', descKey: 'settings.documentLayoutStructuredDesc' },
] as const

const form = useForm({
  quote_validity_days: props.settings.quoteValidityDays,
  document_layout: props.settings.documentLayout,
})

function submit() {
  form.patch('/einstellungen/belege', { preserveScroll: true, onSuccess: () => emit('saved') })
}

defineExpose({ form, submit })
</script>

<template>
  <form id="receipts-form" @submit.prevent="submit">
    <div class="mt-0">
      <div class="mb-2.5 text-small leading-none font-semibold text-faint">{{ $t('settings.documentsLayoutLabel') }}</div>
      <div class="mb-6">
        <RadioGroup
          name="documentlayout"
          class="grid grid-cols-2 gap-2.5"
          :model-value="form.document_layout"
          @update:model-value="(value) => (form.document_layout = String(value))"
        >
          <!-- The card is the radio: a <label> forwards its click to the
               button reka renders, so the whole card is the hit area and the
               dot itself has nothing to draw — same pattern as the Schriftpaar
               picker in ProfileForm.vue. -->
          <label
            v-for="option in DOCUMENT_LAYOUTS"
            :key="option.value"
            class="block cursor-pointer rounded-md bg-muted px-4 pt-4 pb-6 shadow-[inset_0_0_0_1px_transparent] has-[[data-state=checked]]:shadow-[inset_0_0_0_2px_var(--accent)]"
          >
            <RadioGroupItem :value="option.value" class="sr-only" />
            <b class="block text-title leading-tight font-semibold text-foreground">{{ $t(option.labelKey) }}</b>
            <em class="mt-2 block text-small leading-[1.5] font-normal not-italic text-muted-foreground">{{ $t(option.descKey) }}</em>
          </label>
        </RadioGroup>
      </div>
    </div>

    <div class="mt-8 first-of-type:mt-6">
      <div class="mb-2.5 text-small leading-none font-semibold text-faint">{{ $t('settings.documentsQuoteLabel') }}</div>
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
  </form>
</template>

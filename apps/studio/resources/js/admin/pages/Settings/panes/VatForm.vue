<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { basisPointsToPercent, percentToBasisPoints } from '@/admin/lib/money'
import { Input } from '@/ui/input'
import { Textarea } from '@/ui/textarea'
import { RadioGroup, RadioGroupItem } from '@/ui/radio-group'
import { Label } from '@/ui/label'

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
    <div class="dsec" style="margin-top: 0">
      <div class="dlab">{{ $t('settings.vatModeLabel') }}</div>
      <div class="grp">
        <RadioGroup
          name="vatmode"
          :model-value="form.vat_mode"
          @update:model-value="(value) => (form.vat_mode = String(value))"
        >
          <div class="cbrow">
            <RadioGroupItem id="vat-standard" value="standard" />
            <Label variant="inline" for="vat-standard">{{ $t('settings.vatModeStandard') }}</Label>
          </div>
          <div class="cbrow">
            <RadioGroupItem id="vat-small-business" value="small_business" />
            <Label variant="inline" for="vat-small-business">{{ $t('settings.vatModeSmallBusiness') }}</Label>
          </div>
        </RadioGroup>
        <p class="hint" style="margin-top: var(--s3); color: var(--late)">{{ $t('settings.vatModeWarning') }}</p>
      </div>
      <div class="grp">
        <div class="fld" style="max-width: 160px">
          <Label for="v-satz">{{ $t('settings.vatRateLabel') }}</Label>
          <Input id="v-satz" v-model="form.vat_rate" class="tabular-nums" type="text" inputmode="decimal" />
          <span class="hint">{{ $t('settings.vatRateHint') }}</span>
          <p v-if="form.errors.vat_rate" class="err">{{ form.errors.vat_rate }}</p>
        </div>
      </div>
    </div>

    <div class="dsec">
      <div class="dlab">{{ $t('settings.vatNoteLabel') }}</div>
      <div class="grp">
        <div class="fld">
          <Label for="v-hinweis">{{ $t('settings.vatNoteFieldLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
          <Textarea id="v-hinweis" v-model="form.vat_note" rows="3" />
          <p v-if="form.errors.vat_note" class="err">{{ form.errors.vat_note }}</p>
        </div>
      </div>
    </div>

    <div class="dsec">
      <div class="dlab">{{ $t('settings.vatPreviewLabel') }}</div>
      <div class="card static">
        <div class="cardhead"><span class="kick">{{ $t('settings.vatPreviewKicker') }}</span></div>
        <p v-if="form.vat_note" style="margin: 0; font: 400 13px/1.5 var(--font); color: var(--ink)">
          {{ form.vat_note }}
        </p>
        <p v-else class="hint" style="margin: 0">{{ $t('settings.vatPreviewEmpty') }}</p>
      </div>
    </div>
  </form>
</template>

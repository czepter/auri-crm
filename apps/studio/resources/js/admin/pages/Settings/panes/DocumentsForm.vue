<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { basisPointsToPercent, percentToBasisPoints } from '@/admin/lib/money'

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
    <div class="dsec" style="margin-top: 0">
      <div class="dlab">{{ $t('settings.documentsQuoteLabel') }}</div>
      <div class="grp">
        <div class="fld" style="max-width: 160px">
          <label for="d-gueltigkeit">{{ $t('settings.quoteValidityLabel') }}</label>
          <input id="d-gueltigkeit" v-model.number="form.quote_validity_days" class="inp num" type="number" min="0" />
          <span class="hint">{{ $t('settings.quoteValidityHint') }}</span>
          <p v-if="form.errors.quote_validity_days" class="err">{{ form.errors.quote_validity_days }}</p>
        </div>
      </div>
    </div>

    <div class="dsec">
      <div class="dlab">{{ $t('settings.documentsPaymentLabel') }}</div>
      <div class="grp">
        <div class="two">
          <div class="fld">
            <label for="d-zahlungsziel">{{ $t('settings.paymentTermLabel') }}</label>
            <input id="d-zahlungsziel" v-model.number="form.payment_term_days" class="inp num" type="number" min="0" />
            <span class="hint">{{ $t('settings.paymentTermHint') }}</span>
            <p v-if="form.errors.payment_term_days" class="err">{{ form.errors.payment_term_days }}</p>
          </div>
          <div class="fld">
            <label for="d-anzahlung">{{ $t('settings.depositPercentageLabel') }}</label>
            <input id="d-anzahlung" v-model="form.deposit_percentage" class="inp num" type="text" inputmode="decimal" />
            <span class="hint">{{ $t('settings.depositPercentageHint') }}</span>
            <p v-if="form.errors.deposit_percentage" class="err">{{ form.errors.deposit_percentage }}</p>
          </div>
        </div>
      </div>
    </div>

    <div class="dsec">
      <div class="dlab">{{ $t('settings.widerrufLabel') }}</div>
      <div class="grp">
        <label style="display: flex; align-items: center; gap: 10px; font: 400 13px/1.4 var(--font)">
          <input v-model="form.widerruf_included" type="checkbox" />
          {{ $t('settings.widerrufIncludedLabel') }}
        </label>
      </div>
      <div class="grp">
        <div class="fld">
          <label for="d-widerruf-text">{{ $t('settings.widerrufTextLabel') }}</label>
          <textarea
            id="d-widerruf-text"
            v-model="form.widerruf_text"
            class="inp"
            rows="5"
            :disabled="!form.widerruf_included"
          ></textarea>
          <p v-if="form.errors.widerruf_text" class="err">{{ form.errors.widerruf_text }}</p>
        </div>
      </div>
    </div>
  </form>
</template>

<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Input } from '@/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Label } from '@/ui/label'

const props = withDefaults(defineProps<{
  settings: App.Settings.Data.StudioSettingsData
  // Absent during first-run setup: the pair is fine-tuning, not a thing a
  // studio has to decide before it can work.
  fontPairs?: { value: string; label: string; description: string }[]
}>(), { fontPairs: () => [] })
const emit = defineEmits<{ saved: [] }>()

const form = useForm({
  display_name: props.settings.displayName ?? '',
  legal_name: props.settings.legalName ?? '',
  legal_form: props.settings.legalForm ?? 'GmbH',
  street: props.settings.street ?? '',
  postcode: props.settings.postcode ?? '',
  city: props.settings.city ?? '',
  country: props.settings.country ?? 'Deutschland',
  tax_number: props.settings.taxNumber ?? '',
  vat_id: props.settings.vatId ?? '',
  register_number: props.settings.registerNumber ?? '',
  register_court: props.settings.registerCourt ?? '',
  representative: props.settings.representative ?? '',
  website: props.settings.website ?? '',
  accent_color: props.settings.accentColor ?? '',
  font_pair: props.settings.fontPair ?? 'instrument',
  logo: null as File | null,
  bank_account_holder: props.settings.bankAccountHolder ?? '',
  bank_iban: props.settings.bankIban ?? '',
  bank_bic: props.settings.bankBic ?? '',
})

function onLogoChange(event: Event) {
  form.logo = (event.target as HTMLInputElement).files?.[0] ?? null
}

function submit() {
  // Inertia's own documented workaround: a browser cannot send a real PUT
  // or PATCH as multipart/form-data, so a file upload goes over POST with
  // `_method` spoofing the verb Laravel's router actually matches on.
  form
    .transform((data) => ({ ...data, _method: 'patch' }))
    .post('/einstellungen', { forceFormData: true, preserveScroll: true, onSuccess: () => emit('saved') })
}

defineExpose({ form, submit })
</script>

<template>
  <form id="settings-form" @submit.prevent="submit">
    <div class="dsec" style="margin-top: 0">
      <div class="dlab">{{ $t('settings.legalEntity') }}</div>
      <div class="grp">
        <div class="fld wide">
          <Label for="p-firma">{{ $t('settings.legalName') }}</Label>
          <Input id="p-firma" v-model="form.legal_name" type="text" />
          <p v-if="form.errors.legal_name" class="err">{{ form.errors.legal_name }}</p>
        </div>
      </div>
      <div class="grp">
        <div class="two">
          <div class="fld">
            <Label for="p-rechtsform">{{ $t('settings.legalForm') }}</Label>
            <Select
              :model-value="form.legal_form"
              @update:model-value="(value) => (form.legal_form = String(value))"
            >
              <SelectTrigger id="p-rechtsform">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="Einzelunternehmen">Einzelunternehmen</SelectItem>
                <SelectItem value="GmbH">GmbH</SelectItem>
                <SelectItem value="UG (haftungsbeschränkt)">UG (haftungsbeschränkt)</SelectItem>
                <SelectItem value="GbR">GbR</SelectItem>
              </SelectContent>
            </Select>
            <p v-if="form.errors.legal_form" class="err">{{ form.errors.legal_form }}</p>
          </div>
          <div class="fld">
            <Label for="p-vertretung">{{ $t('settings.representative') }}</Label>
            <Input id="p-vertretung" v-model="form.representative" type="text" />
            <p v-if="form.errors.representative" class="err">{{ form.errors.representative }}</p>
          </div>
        </div>
      </div>
      <div class="grp">
        <div class="fld wide">
          <Label for="p-strasse">{{ $t('settings.street') }}</Label>
          <Input id="p-strasse" v-model="form.street" type="text" />
          <p v-if="form.errors.street" class="err">{{ form.errors.street }}</p>
        </div>
      </div>
      <div class="grp">
        <div class="two">
          <div class="fld">
            <Label for="p-plz">{{ $t('settings.postcode') }}</Label>
            <Input id="p-plz" v-model="form.postcode" class="tabular-nums" type="text" />
            <p v-if="form.errors.postcode" class="err">{{ form.errors.postcode }}</p>
          </div>
          <div class="fld">
            <Label for="p-stadt">{{ $t('settings.city') }}</Label>
            <Input id="p-stadt" v-model="form.city" type="text" />
            <p v-if="form.errors.city" class="err">{{ form.errors.city }}</p>
          </div>
        </div>
      </div>
      <div class="grp">
        <div class="fld" style="max-width: 260px">
          <Label for="p-land">{{ $t('settings.country') }}</Label>
          <Select :model-value="form.country" @update:model-value="(value) => (form.country = String(value))">
            <SelectTrigger id="p-land">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="Deutschland">Deutschland</SelectItem>
              <SelectItem value="Österreich">Österreich</SelectItem>
              <SelectItem value="Schweiz">Schweiz</SelectItem>
            </SelectContent>
          </Select>
          <p v-if="form.errors.country" class="err">{{ form.errors.country }}</p>
        </div>
      </div>
      <div class="grp">
        <div class="two">
          <div class="fld">
            <Label for="p-steuernr">{{ $t('settings.taxNumber') }}</Label>
            <Input id="p-steuernr" v-model="form.tax_number" class="tabular-nums" type="text" />
            <p v-if="form.errors.tax_number" class="err">{{ form.errors.tax_number }}</p>
          </div>
          <div class="fld">
            <Label for="p-ustid">{{ $t('settings.vatId') }} <em>{{ $t('settings.optional') }}</em></Label>
            <Input id="p-ustid" v-model="form.vat_id" class="tabular-nums" type="text" />
            <p v-if="form.errors.vat_id" class="err">{{ form.errors.vat_id }}</p>
          </div>
        </div>
        <span class="hint">{{ $t('settings.taxHint') }}</span>
      </div>
      <div class="grp">
        <div class="two">
          <div class="fld">
            <Label for="p-hrb">{{ $t('settings.registerNumber') }}</Label>
            <Input id="p-hrb" v-model="form.register_number" class="tabular-nums" type="text" />
            <p v-if="form.errors.register_number" class="err">{{ form.errors.register_number }}</p>
          </div>
          <div class="fld">
            <Label for="p-gericht">{{ $t('settings.registerCourt') }}</Label>
            <Input id="p-gericht" v-model="form.register_court" type="text" />
            <p v-if="form.errors.register_court" class="err">{{ form.errors.register_court }}</p>
          </div>
        </div>
      </div>
    </div>

    <div class="dsec">
      <div class="dlab">{{ $t('settings.brand') }}</div>
      <div class="grp">
        <div class="two">
          <div class="fld">
            <Label for="p-anzeigename">{{ $t('settings.displayName') }}</Label>
            <Input id="p-anzeigename" v-model="form.display_name" type="text" />
            <p v-if="form.errors.display_name" class="err">{{ form.errors.display_name }}</p>
          </div>
          <div class="fld">
            <Label for="p-web">{{ $t('settings.website') }}</Label>
            <Input id="p-web" v-model="form.website" type="text" />
            <p v-if="form.errors.website" class="err">{{ form.errors.website }}</p>
          </div>
        </div>
      </div>
      <div class="grp">
        <div class="two">
          <div class="fld">
            <Label for="p-logo">{{ $t('settings.logo') }}</Label>
            <div style="display: flex; align-items: center; gap: var(--s3)">
              <div v-if="settings.logoUrl" class="galcover" style="width: 40px; height: 40px">
                <img :src="settings.logoUrl" :alt="$t('settings.logo')" style="width: 100%; height: 100%; object-fit: contain" />
              </div>
              <div v-else class="galcover empty" style="width: 40px; height: 40px">{{ $t('settings.logo') }}</div>
              <Input id="p-logo" type="file" accept="image/png" style="flex: 1" @change="onLogoChange" />
            </div>
            <span class="hint">{{ $t('settings.logoHint') }}</span>
            <p v-if="form.errors.logo" class="err">{{ form.errors.logo }}</p>
          </div>
          <div class="fld">
            <Label for="p-akzent">{{ $t('settings.accentColor') }}</Label>
            <div style="display: flex; align-items: center; gap: 10px">
              <span
                style="display: inline-block; width: 32px; height: 32px; border-radius: var(--r-sm); border: 1px solid var(--line); flex: none"
                :style="{ background: form.accent_color || 'transparent' }"
              ></span>
              <Input id="p-akzent" v-model="form.accent_color" class="tabular-nums" type="text" placeholder="#B44E22" />
            </div>
            <p v-if="form.errors.accent_color" class="err">{{ form.errors.accent_color }}</p>
          </div>
        </div>

        <!-- #234: the pair is shown in itself, not named in a sample line —
             a typeface is judged by reading it. Only the display pair is
             selectable; minimum sizes and contrast stay fixed. -->
        <div v-if="fontPairs.length" class="fld grp">
          <Label>{{ $t('settings.fontPair') }}</Label>
          <div class="fontpairs">
            <label v-for="pair in fontPairs" :key="pair.value" :data-font="pair.value">
              <input v-model="form.font_pair" type="radio" :value="pair.value" />
              <span class="pv">
                <b>{{ settings.displayName || pair.label }}</b>
                <em>{{ pair.description }}</em>
              </span>
            </label>
          </div>
        </div>
      </div>
    </div>

    <div class="dsec">
      <div class="dlab">{{ $t('settings.bank') }}</div>
      <div class="grp">
        <div class="fld wide">
          <Label for="p-kontoinhaber">{{ $t('settings.bankAccountHolder') }}</Label>
          <Input id="p-kontoinhaber" v-model="form.bank_account_holder" type="text" />
          <p v-if="form.errors.bank_account_holder" class="err">{{ form.errors.bank_account_holder }}</p>
        </div>
      </div>
      <div class="grp">
        <div class="two">
          <div class="fld">
            <Label for="p-iban">{{ $t('settings.bankIban') }}</Label>
            <Input id="p-iban" v-model="form.bank_iban" class="tabular-nums" type="text" />
            <p v-if="form.errors.bank_iban" class="err">{{ form.errors.bank_iban }}</p>
          </div>
          <div class="fld">
            <Label for="p-bic">{{ $t('settings.bankBic') }}</Label>
            <Input id="p-bic" v-model="form.bank_bic" class="tabular-nums" type="text" />
            <p v-if="form.errors.bank_bic" class="err">{{ form.errors.bank_bic }}</p>
          </div>
        </div>
      </div>
    </div>
  </form>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { useObjectUrl } from '@vueuse/core'
import { Input } from '@/ui/input'
import { DropArea } from '@/ui/drop-area'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Label } from '@/ui/label'
import { Field } from '@/ui/field'
import { RadioGroup, RadioGroupItem } from '@/ui/radio-group'
import { ChoiceGroup } from '@/ui/choice-group'
import { Combobox, ComboboxAnchor, ComboboxContent, ComboboxInput, ComboboxItem } from '@/ui/combobox'
import { browserTimezone, searchTimezones, timezoneLabel } from '@/admin/lib/timezones'
import { requiresRegister } from '@/admin/lib/legalForm'

type Section = 'legal' | 'tax' | 'brand' | 'bank' | 'general'

const props = withDefaults(defineProps<{
  settings: App.Settings.Data.StudioSettingsData
  // Absent during first-run setup: the pair is fine-tuning, not a thing a
  // studio has to decide before it can work.
  fontPairs?: { value: string; label: string; description: string }[]
  // The settings screen shows one fieldset per section.
  sections?: Section[]
  // First run asks for the brand and stops there: the display name, the logo
  // and the accent are what the portal header shows the client, and the
  // portal goes live with the first quote. Firmierung and Anschrift ride
  // along with that quote (they carry the Impressum), Steuernummer and the
  // register pair with the first invoice.
  firstRun?: boolean
}>(), { fontPairs: () => [], sections: () => ['brand', 'legal', 'tax', 'general', 'bank'], firstRun: false })
const emit = defineEmits<{ saved: [] }>()

// A pane sends only its own fields. tax_number/vat_id are required_without
// each other, so a brand-only save that carried them along would fail on a
// field the screen is not showing — the controller's `sometimes` skips the
// pair when neither key arrives.
const SECTION_FIELDS: Record<Section, string[]> = {
  legal: ['legal_name', 'legal_form', 'representative', 'street', 'postcode', 'city', 'country'],
  tax: ['tax_number', 'vat_id', 'register_number', 'register_court'],
  brand: ['display_name', 'website', 'logo', 'accent_color', 'font_pair', 'client_register'],
  bank: ['bank_account_holder', 'bank_iban', 'bank_bic'],
  general: ['timezone'],
}

const FIRST_RUN_SECTIONS: Section[] = ['brand']

const shownSections = computed(() => (props.firstRun ? FIRST_RUN_SECTIONS : props.sections))
const shows = (section: Section) => shownSections.value.includes(section)

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
  font_pair: props.settings.fontPair ?? 'hanken',
  client_register: props.settings.clientRegister,
  timezone: props.settings.timezone,
  logo: null as File | null,
  bank_account_holder: props.settings.bankAccountHolder ?? '',
  bank_iban: props.settings.bankIban ?? '',
  bank_bic: props.settings.bankBic ?? '',
})

// The scheme is drawn by the field rather than typed into it, but what is
// stored stays the whole URL the controller validates `url` against. The
// setter strips a pasted scheme too — otherwise pasting the address out of a
// browser bar files it as https://https://….
const websiteHost = computed({
  get: () => form.website.replace(/^https?:\/\//, ''),
  set: (value) => {
    const host = value.trim().replace(/^https?:\/\//, '')
    form.website = host === '' ? '' : `https://${host}`
  },
})

// Der Browser kennt die Zone des Geräts; die Zeile darunter bietet sie an,
// solange sie von der gespeicherten abweicht.
const zoneTerm = ref<string>()
const zoneChoices = computed(() => searchTimezones(zoneTerm.value ?? '', form.timezone))
const browserZone = computed(() => {
  const zone = browserTimezone()

  return zone === form.timezone ? null : zone
})

function chooseZone(zone: string) {
  form.timezone = zone
  zoneTerm.value = undefined
}

const pickedLogo = useObjectUrl(computed(() => form.logo ?? undefined))
const logoPreview = computed(() => pickedLogo.value ?? props.settings.logoUrl)

// Gesetzt vom Dialog auf Allgemein, wenn die Anrede wechselt.
const adaptExisting = ref(false)

function submit() {
  const sent = new Set<string>(shownSections.value.flatMap((section) => SECTION_FIELDS[section]))

  // Inertia's own documented workaround: a browser cannot send a real PUT
  // or PATCH as multipart/form-data, so a file upload goes over POST with
  // `_method` spoofing the verb Laravel's router actually matches on.
  form
    .transform((data) => ({
      ...Object.fromEntries(Object.entries({ ...data, timezone: data.timezone || null }).filter(([field]) => sent.has(field))),
      ...(adaptExisting.value ? { adapt_existing: true } : {}),
      _method: 'patch',
    }))
    .post('/einstellungen', {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => emit('saved'),
      onFinish: () => (adaptExisting.value = false),
    })
}

defineExpose({ form, submit, adaptExisting })
</script>

<template>
  <form id="settings-form" @submit.prevent="submit">
    <div v-if="shows('brand')" class="mt-8 first:mt-0">
      <div v-if="shownSections.length > 1" class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('settings.brand') }}</div>
      <div class="mb-6">
        <div class="grid grid-cols-2 gap-4">
          <Field :label="$t('settings.displayName')" id="p-anzeigename" :error="form.errors.display_name">
            <Input id="p-anzeigename" v-model="form.display_name" type="text" />
          </Field>
          <Field :label="$t('settings.website')" id="p-web" :error="form.errors.website">
            <InputGroup>
              <InputGroupAddon>https://</InputGroupAddon>
              <InputGroupInput id="p-web" v-model="websiteHost" type="text" />
            </InputGroup>
          </Field>
        </div>
      </div>
      <div class="mb-6">
        <ChoiceGroup
          v-model="form.client_register"
          class="mb-6"
          :label="$t('settings.clientRegister')"
          :options="[
            { value: 'formal', label: $t('settings.clientRegisterFormal') },
            { value: 'informal', label: $t('settings.clientRegisterInformal') },
          ]"
        />
        <div :class="firstRun ? 'flex flex-col gap-6' : 'grid grid-cols-2 gap-4'">
          <Field :label="$t('settings.logo')" id="p-logo" :error="form.errors.logo" :hint="$t('settings.logoHint')">
            <DropArea id="p-logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" @files="(files) => (form.logo = files[0])">
              <img v-if="logoPreview" :src="logoPreview" :alt="$t('settings.logo')" class="size-20 flex-none rounded-[6px] bg-muted object-contain p-1.5" />
              <div v-else class="size-20 flex-none rounded-[6px] bg-muted" />
              <span class="min-w-0 truncate text-[13px] text-foreground">{{ form.logo?.name ?? $t('common.drop') }}</span>
            </DropArea>
          </Field>

          <Field :label="$t('settings.accentColor')" id="p-akzent" :error="form.errors.accent_color">
            <InputGroup>
              <InputGroupAddon>
                <span
                  class="size-4 flex-none rounded-[3px] border border-border"
                  :style="{ background: form.accent_color || 'transparent' }"
                />
              </InputGroupAddon>
              <InputGroupInput id="p-akzent" v-model="form.accent_color" class="tabular-nums" type="text" placeholder="#1C6870" />
            </InputGroup>
          </Field>
        </div>

        <!-- #234: the pair is shown in itself, not named in a sample line —
             a typeface is judged by reading it. Only the display pair is
             selectable; minimum sizes and contrast stay fixed. -->
        <Field v-if="fontPairs.length" class="mt-6">
          <Label>{{ $t('settings.fontPair') }}</Label>
          <RadioGroup v-model="form.font_pair" class="grid grid-cols-2 gap-2.5">
            <!-- The card is the radio: a <label> forwards its click to the
                 button reka renders, so the whole sample is the hit area and
                 the dot itself has nothing to draw. -->
            <label
              v-for="pair in fontPairs"
              :key="pair.value"
              class="block cursor-pointer rounded-md bg-muted px-4 pt-4 pb-6 shadow-[inset_0_0_0_1px_transparent] has-[[data-state=checked]]:shadow-[inset_0_0_0_2px_var(--accent)]"
              :data-font="pair.value"
            >
              <RadioGroupItem :value="pair.value" class="sr-only" />
              <b class="block font-normal text-[26px] leading-[var(--display-leading)] font-[family-name:var(--font-display)] tracking-[var(--display-tracking)] text-foreground">{{ settings.displayName || pair.label }}</b>
              <em class="mt-2 block font-sans text-[12.5px] leading-[1.5] font-normal not-italic text-muted-foreground">{{ pair.description }}</em>
            </label>
          </RadioGroup>
        </Field>
      </div>
    </div>

    <div v-if="shows('legal')" class="mt-8 first:mt-0">
      <div v-if="shownSections.length > 1" class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('settings.legalEntity') }}</div>
      <div class="mb-6">
        <Field :label="$t('settings.legalName')" id="p-firma" wide :error="form.errors.legal_name">
          <Input id="p-firma" v-model="form.legal_name" type="text" />
        </Field>
      </div>
      <div class="mb-6">
        <div class="grid grid-cols-2 gap-4">
          <Field :label="$t('settings.legalForm')" id="p-rechtsform" :error="form.errors.legal_form">
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
          </Field>
          <Field :label="$t('settings.representative')" id="p-vertretung" :error="form.errors.representative">
            <Input id="p-vertretung" v-model="form.representative" type="text" />
          </Field>
        </div>
      </div>
      <div class="mb-6">
        <Field :label="$t('settings.street')" id="p-strasse" wide :error="form.errors.street">
          <Input id="p-strasse" v-model="form.street" type="text" />
        </Field>
      </div>
      <div class="mb-6">
        <div class="grid grid-cols-2 gap-4">
          <Field :label="$t('settings.postcode')" id="p-plz" :error="form.errors.postcode">
            <Input id="p-plz" v-model="form.postcode" class="tabular-nums" type="text" />
          </Field>
          <Field :label="$t('settings.city')" id="p-stadt" :error="form.errors.city">
            <Input id="p-stadt" v-model="form.city" type="text" />
          </Field>
        </div>
      </div>
      <div class="mb-6">
        <Field :label="$t('settings.country')" id="p-land" class="max-w-[260px]" :error="form.errors.country">
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
        </Field>
      </div>
    </div>

    <div v-if="shows('general')" class="mt-8 first:mt-0">
      <div v-if="shownSections.length > 1" class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('settings.timezoneLabel') }}</div>
      <div class="mb-6">
        <div class="grid grid-cols-2 gap-4">
          <Field :error="form.errors.timezone" v-slot="{ id }">
            <Label :for="id" class="sr-only">{{ $t('settings.timezoneLabel') }}</Label>
            <Combobox
              :model-value="form.timezone"
              ignore-filter
              open-on-focus
              open-on-click
              @update:model-value="chooseZone(String($event))"
              @update:open="zoneTerm = undefined"
            >
              <ComboboxAnchor>
                <ComboboxInput
                  :id
                  :display-value="() => timezoneLabel(form.timezone)"
                  @input="zoneTerm = ($event.target as HTMLInputElement).value"
                />
              </ComboboxAnchor>
              <ComboboxContent>
                <ComboboxItem v-for="zone in zoneChoices" :key="zone" :value="zone">
                  {{ timezoneLabel(zone) }}
                </ComboboxItem>
              </ComboboxContent>
            </Combobox>
            <button
              v-if="browserZone"
              type="button"
              class="self-start text-[11.5px] leading-[1.45] text-ink-2 underline"
              @click="chooseZone(browserZone)"
            >
              {{ $t('settings.timezoneFromBrowser', { zone: browserZone }) }}
            </button>
          </Field>
        </div>
      </div>
    </div>

    <div v-if="shows('tax')" class="mt-8 first:mt-0">
      <div v-if="shownSections.length > 1" class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('settings.taxSection') }}</div>
      <div class="mb-6">
        <div class="grid grid-cols-2 gap-4">
          <Field :label="$t('settings.taxNumber')" id="p-steuernr" :error="form.errors.tax_number">
            <Input id="p-steuernr" v-model="form.tax_number" class="tabular-nums" type="text" />
          </Field>
          <Field :error="form.errors.vat_id" v-slot="{ id }">
            <Label :for="id">{{ $t('settings.vatId') }} <em>{{ $t('settings.optional') }}</em></Label>
            <Input :id v-model="form.vat_id" class="tabular-nums" type="text" />
          </Field>
        </div>
        <span class="text-[11.5px] leading-[1.45] text-faint">{{ $t('settings.taxHint') }}</span>
      </div>
      <!-- Einzelunternehmen und GbR stehen in keinem Register; dasselbe
           Paar führt Impressum.vue. -->
      <div v-if="requiresRegister(form.legal_form)" class="mb-6">
        <div class="grid grid-cols-2 gap-4">
          <Field :label="$t('settings.registerNumber')" id="p-hrb" :error="form.errors.register_number">
            <Input id="p-hrb" v-model="form.register_number" class="tabular-nums" type="text" />
          </Field>
          <Field :label="$t('settings.registerCourt')" id="p-gericht" :error="form.errors.register_court">
            <Input id="p-gericht" v-model="form.register_court" type="text" />
          </Field>
        </div>
      </div>
    </div>

    <div v-if="shows('bank')" class="mt-8 first:mt-0">
      <div v-if="shownSections.length > 1" class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('settings.bank') }}</div>
      <div class="mb-6">
        <Field :label="$t('settings.bankAccountHolder')" id="p-kontoinhaber" wide :error="form.errors.bank_account_holder">
          <Input id="p-kontoinhaber" v-model="form.bank_account_holder" type="text" />
        </Field>
      </div>
      <div class="mb-6">
        <div class="grid grid-cols-2 gap-4">
          <Field :label="$t('settings.bankIban')" id="p-iban" :error="form.errors.bank_iban">
            <Input id="p-iban" v-model="form.bank_iban" class="tabular-nums" type="text" />
          </Field>
          <Field :label="$t('settings.bankBic')" id="p-bic" :error="form.errors.bank_bic">
            <Input id="p-bic" v-model="form.bank_bic" class="tabular-nums" type="text" />
          </Field>
        </div>
      </div>
    </div>
  </form>
</template>

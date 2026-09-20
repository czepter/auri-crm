<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Label } from '@/ui/label'
import { Field } from '@/ui/field'
import { ChoiceGroup } from '@/ui/choice-group'
import { missingLegalTexts } from '@/admin/lib/legalTexts'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'

/*
 * Impressum, Datenschutz, AGB und Widerruf — und wohin eine
 * Datenschutz-Anfrage geht. Beides gehört dem Studio, nicht dem Portal, und
 * steht deshalb im Formular von Allgemein statt in einem eigenen.
 *
 * Die Felder gehören dem Formular der Seite: sie werden mit ihr gespeichert,
 * und ein eigenes useForm hier wäre ein zweiter Speicherknopf auf einem Blatt.
 */
// Reihenfolge der Zeilen; dieselben Schlüssel führt LegalTextController::TEXTS
// und die Portal-Route /rechtstexte/{text}.
const LEGAL_TEXTS = ['impressum', 'datenschutz', 'agb', 'widerruf'] as const

type LegalText = (typeof LEGAL_TEXTS)[number]

type LegalTemplate = { family: string; name: string; updatedAt: string | null }

const props = withDefaults(defineProps<{
  /*
   * Das Formular der Seite, dessen Felder diese Zeilen bedienen. Die
   * Schlüssel werden zusammengesetzt (`portal_legal_${text}_url`), was
   * Inertias useForm-Typ nicht hergibt — deshalb die lose Signatur.
   */
  form: Record<string, any>
  families: Record<string, string | null>
  legalTemplates?: LegalTemplate[]
}>(), { legalTemplates: () => [] })

const { t } = useI18n()

const form = computed(() => props.form)

type Source = 'studio' | 'link' | 'document'

// Welche Quelle gilt, steht in den Werten selbst: eine Adresse schlägt eine
// Vorlage, und beim Impressum bleibt die Seite aus den Studio-Daten, solange
// keins von beiden gesetzt ist.
function sourceOf(text: LegalText): Source {
  if (form.value[`portal_legal_${text}_url`]) return 'link'
  if (form.value[`portal_legal_${text}_family`]) return 'document'

  return text === 'impressum' ? 'studio' : 'link'
}

function sourceOptions(text: LegalText) {
  return [
    ...(text === 'impressum' ? [{ value: 'studio', label: t('portalSettings.legalSourceStudio') }] : []),
    { value: 'link', label: t('portalSettings.legalSourceLink') },
    { value: 'document', label: t('portalSettings.legalSourceDocument') },
  ]
}

// Ohne Widerrufsbelehrung darf ein Angebot entstehen, aber nicht hinausgehen.
const missingBeforeSend = computed(() => missingLegalTexts(Object.fromEntries(
  LEGAL_TEXTS.map((text) => [text, {
    url: String(form.value[`portal_legal_${text}_url`] ?? ''),
    family: String(form.value[`portal_legal_${text}_family`] ?? ''),
  }]),
)))

function chooseSource(text: LegalText, source: Source) {
  form.value[`portal_legal_${text}_url`] = ''
  form.value[`portal_legal_${text}_family`] = source === 'document' ? (props.families[text] ?? '') : ''
}

</script>

<template>
  <div>
    <div class="mt-0 mb-6 flex flex-col gap-3">
      <div v-for="text in LEGAL_TEXTS" :key="text" class="flex flex-wrap items-center gap-x-5 gap-y-3 rounded-[12px] border border-border p-4" :class="missingBeforeSend.includes(text) && 'bg-due-bg'">
        <div class="w-[200px] flex-none">
          <span class="block text-[14px] leading-none font-semibold tracking-[-0.01em] text-foreground">{{ $t(`portalSettings.legal_${text}`) }}</span>
          <span class="mt-1.5 block text-[12px] text-muted-foreground">{{ $t(`portalSettings.legalNote_${text}`) }}</span>
        </div>

        <ChoiceGroup
          class="flex-none"
          variant="segmented"
          :model-value="sourceOf(text)"
          :options="sourceOptions(text)"
          @update:model-value="(value) => chooseSource(text, value as Source)"
        />

        <div class="min-w-[220px] flex-grow">
          <Field v-if="sourceOf(text) === 'link'" :error="form.errors[`portal_legal_${text}_url`]" v-slot="{ id }">
            <Label :for="id">{{ $t('portalSettings.legalUrlLabel') }}</Label>
            <Input :id v-model="form[`portal_legal_${text}_url`]" type="text" placeholder="https://" />
          </Field>

          <Field v-else-if="sourceOf(text) === 'document'" :error="form.errors[`portal_legal_${text}_family`]" v-slot="{ id }">
            <Label :for="id">{{ $t('portalSettings.legalTemplateLabel') }}</Label>
            <Select
              :model-value="form[`portal_legal_${text}_family`]"
              @update:model-value="(value) => (form[`portal_legal_${text}_family`] = String(value))"
            >
              <SelectTrigger :id><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="template in legalTemplates" :key="template.family" :value="template.family">
                  {{ template.name }}
                </SelectItem>
              </SelectContent>
            </Select>
          </Field>

          <a
            v-else
            class="text-[13px] font-medium text-action no-underline"
            href="/impressum"
            target="_blank"
            rel="noopener"
          >{{ $t('portalSettings.legalOpenImpressum') }}</a>
        </div>

        <!-- Die Zeile trägt ihren eigenen Stand; ein Balken unter der Liste
             wiederholte, was hier schon steht. -->
        <span v-if="missingBeforeSend.includes(text)" class="flex-none text-[12.5px] font-semibold text-due">{{ $t('portalSettings.legalMissing') }}</span>
      </div>

      <a class="text-[13px] font-medium text-action no-underline" href="/dokumentvorlagen/neu">
        {{ $t('portalSettings.legalNewTemplate') }}
      </a>
    </div>

    <!-- P-05 (#224) put subject access and erasure in the portal; both are
         requests with a legal deadline, so both need somewhere to land. -->
    <div class="mt-8">
      <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('portalSettings.dsgvoSection') }}</div>
      <div class="mb-6">
        <div class="grid grid-cols-2 gap-4">
          <Field :error="form.errors.dsgvo_request_recipient" v-slot="{ id }">
            <Label :for="id">{{ $t('portalSettings.dsgvoRecipientLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
            <Input :id v-model="form.dsgvo_request_recipient" type="email" />
          </Field>
          <Field :label="$t('portalSettings.dsgvoDeadlineLabel')" id="po-dsgvo-frist">
            <InputGroup>
              <InputGroupInput id="po-dsgvo-frist" v-model="form.dsgvo_request_deadline_days" class="tabular-nums" type="number" min="1" />
              <InputGroupAddon align="inline-end">{{ $t('common.unitDays') }}</InputGroupAddon>
            </InputGroup>
          </Field>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
/*
 * Eine Regel: Auslöser, Bedingung, Nachricht. Rechts die Vorschau im
 * Mail-Layout des Studios und die letzten Läufe — „Übersprungen“ ist dort
 * eine Antwort und kein Ausfall.
 *
 * Keine Einstellungs-Spalte: eine Regel ist ein Datensatz mit eigenem Kopf,
 * kein Feld auf einer Fläche.
 */
import { computed } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import { Button } from '@/ui/button'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Switch } from '@/ui/switch'
import { Textarea } from '@/ui/textarea'

type Option = { value: string; label: string }

const props = defineProps<{
  rule: App.Automation.Data.AutomationRuleData
  anchors: Option[]
  recipients: Option[]
  conditions: Option[]
  projectTypes: Option[]
  actions: Option[]
  mergeFields: { path: string; sample: string }[]
  runs: App.Automation.Data.AutomationRunData[]
}>()

const NO_CONDITION = 'none'

const dated = computed(() => props.rule.triggerType === 'date_offset')
const brand = computed(() => String(usePage().props.brand ?? ''))

const form = useForm({
  name: props.rule.name,
  anchor: props.rule.anchor,
  offset_days: props.rule.offsetDays ?? 0,
  direction: props.rule.direction ?? 'before',
  recipient_type: props.rule.recipient,
  action: props.rule.action,
  condition: props.rule.condition,
  project_type: props.rule.projectType,
  notification_subject: props.rule.subject,
  notification_body: props.rule.body,
  is_active: props.rule.isActive,
})

// Dieselben Beispielwerte, gegen die der Server rendert; ein unbekanntes Feld
// bleibt stehen, wie es geschrieben wurde.
const samples = computed(() => Object.fromEntries(props.mergeFields.map((f) => [f.path, f.sample])))

function render(text: string) {
  return text.replace(/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/g, (match, path) => samples.value[path] ?? match)
}

function save() {
  form.patch(`/einstellungen/automationen/${props.rule.id}`, { preserveScroll: true })
}

function remove() {
  router.delete(`/einstellungen/automationen/${props.rule.id}`)
}

function insert(path: string) {
  form.notification_body = `${form.notification_body}{{ ${path} }}`
}

function chipClass(status: string): string {
  return ({ sent: 'done', failed: 'fail', skipped: 'off' } as Record<string, string>)[status] ?? 'now'
}
</script>

<template>
  <AdminLayout active="einstellungen">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :kicker="$t('automations.title')" kicker-href="/einstellungen/automationen" :title="rule.name">
          <span
            v-if="form.isDirty"
            class="ml-auto flex-none rounded-[var(--r-sm)] bg-due-bg px-2 py-[3px] text-small leading-[1.5] font-semibold text-due"
          >{{ $t('settings.unsaved') }}</span>
          <Button :class="form.isDirty ? '' : 'ml-auto'" type="button" :disabled="form.processing" @click="save">
            {{ $t('common.save') }}
          </Button>
        </PageHead>

        <div class="flex items-start gap-10 px-8 pt-7 pb-8 max-[900px]:flex-col">
          <form class="max-w-[620px] min-w-0 flex-1" @submit.prevent="save">
            <div class="mb-2.5 text-small leading-none font-semibold text-faint">
              {{ $t('automations.triggerSection') }}
            </div>

            <div class="grid grid-cols-[repeat(2,minmax(0,1fr))] gap-x-6 gap-y-4">
              <Field v-slot="{ id }" wide :label="$t('automations.name')" :error="form.errors.name">
                <Input :id="id" v-model="form.name" type="text" />
              </Field>

              <Field :label="$t('automations.kind')">
                <Input :model-value="dated ? $t('automations.kindDate') : $t('automations.kindEvent')" type="text" disabled />
              </Field>

              <Field v-slot="{ id }" :label="$t('automations.anchor')">
                <Select
                  v-if="dated"
                  :model-value="form.anchor ?? undefined"
                  @update:model-value="(v) => (form.anchor = String(v))"
                >
                  <SelectTrigger :id="id"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="option in anchors" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <Input v-else :id="id" :model-value="rule.eventLabel ?? ''" type="text" disabled />
              </Field>

              <Field v-if="dated" :label="$t('automations.offset')" :error="form.errors.offset_days">
                <div class="flex items-center gap-2">
                  <Input v-model="form.offset_days" class="w-16" type="number" min="0" />
                  <Select :model-value="form.direction" @update:model-value="(v) => (form.direction = String(v))">
                    <SelectTrigger class="w-32"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="before">{{ $t('automations.daysBefore') }}</SelectItem>
                      <SelectItem value="after">{{ $t('automations.daysAfter') }}</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </Field>

              <Field
                v-slot="{ id }"
                :label="$t('automations.recipient')"
                :hint="$t('automations.recipientHint')"
              >
                <Select
                  :model-value="form.recipient_type"
                  @update:model-value="(v) => (form.recipient_type = String(v))"
                >
                  <SelectTrigger :id="id"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="option in recipients" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </Field>

              <Field v-if="form.recipient_type !== 'client'" v-slot="{ id }" :label="$t('automations.action')">
                <Select :model-value="form.action" @update:model-value="(v) => (form.action = String(v))">
                  <SelectTrigger :id="id"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="option in actions" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </Field>
            </div>

            <div class="mt-8 mb-2.5 text-small leading-none font-semibold text-faint">
              {{ $t('automations.condition') }}
            </div>

            <div class="flex gap-6 max-[640px]:flex-col">
              <Select
                :model-value="form.condition ?? NO_CONDITION"
                @update:model-value="(v) => (form.condition = v === NO_CONDITION ? null : String(v))"
              >
                <SelectTrigger class="max-w-[300px]"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem :value="NO_CONDITION">{{ $t('automations.conditionNone') }}</SelectItem>
                  <SelectItem v-for="option in conditions" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>

              <Select
                :model-value="form.project_type ?? NO_CONDITION"
                @update:model-value="(v) => (form.project_type = v === NO_CONDITION ? null : String(v))"
              >
                <SelectTrigger class="max-w-[300px]" :aria-label="$t('automations.projectType')"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem :value="NO_CONDITION">{{ $t('automations.projectTypeAll') }}</SelectItem>
                  <SelectItem v-for="option in projectTypes" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="mt-8 mb-2.5 text-small leading-none font-semibold text-faint">
              {{ $t('automations.messageSection') }}
            </div>

            <div class="grid grid-cols-[repeat(2,minmax(0,1fr))] gap-x-6 gap-y-4">
              <Field v-slot="{ id }" wide :label="$t('automations.subject')" :error="form.errors.notification_subject">
                <Input :id="id" v-model="form.notification_subject" type="text" />
              </Field>

              <Field v-slot="{ id }" wide :label="$t('automations.body')" :error="form.errors.notification_body">
                <Textarea :id="id" v-model="form.notification_body" rows="6" />
                <div class="mt-2 flex flex-wrap gap-1.5">
                  <button
                    v-for="field in mergeFields"
                    :key="field.path"
                    class="cursor-pointer rounded-[var(--r-sm)] bg-sunk-2 px-2 py-1 text-small leading-none"
                    type="button"
                    @click="insert(field.path)"
                  >
                    {{ field.path }}
                  </button>
                </div>
              </Field>
            </div>

            <div class="mt-6 flex items-center gap-3">
              <Switch id="sw-rule-active" v-model="form.is_active" />
              <label class="text-small" for="sw-rule-active">{{ $t('automations.active') }}</label>
              <Button class="ml-auto" variant="outline" type="button" @click="remove">
                {{ $t('common.remove') }}
              </Button>
            </div>
          </form>

          <aside class="w-[320px] flex-none max-[900px]:w-full">
            <div class="text-small leading-none font-semibold text-faint">
              {{ $t('automations.preview') }}
            </div>

            <!-- Im Mail-Layout, nicht als Rohtext: die Regel wird für einen
                 Posteingang geschrieben und dort gelesen. -->
            <div class="mt-2 rounded-[10px] bg-sunk-2 p-3">
              <div class="rounded-[7px] bg-paper p-5">
                <div class="text-small font-semibold">{{ brand }}</div>
                <div class="mt-3 text-small font-medium">{{ render(form.notification_subject) }}</div>
                <p class="mt-1.5 text-small leading-[1.55] whitespace-pre-line">
                  {{ render(form.notification_body) }}
                </p>
              </div>
            </div>

            <div class="mt-7 text-small leading-none font-semibold text-faint">
              {{ $t('automations.runs') }}
            </div>

            <div class="mt-2 overflow-hidden rounded-[10px] border border-rule">
              <div
                v-for="run in runs"
                :key="run.id"
                class="flex items-center gap-3 border-b border-rule px-3 py-2.5 text-small last:border-b-0"
              >
                <span class="w-24 flex-none text-ink-2 tabular-nums">{{ run.firedAt }}</span>
                <span class="min-w-0 flex-1 truncate">{{ run.anchorLabel }}</span>
                <span :class="['chip', chipClass(run.status)]">{{ run.statusLabel }}</span>
              </div>
            </div>
          </aside>
        </div>
      </div>
    </Page>
  </AdminLayout>
</template>

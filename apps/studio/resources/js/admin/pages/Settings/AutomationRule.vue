<script setup lang="ts">
/*
 * Eine Regel: Anker, Abstand, Empfänger, Bedingung und der Text. Rechts die
 * Vorschau im Wortlaut, mit dem sie zugestellt würde, und die letzten Läufe
 * — „Übersprungen“ ist dort eine Antwort und kein Ausfall.
 */
import { computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PageHead from '@/ui/page/PageHead.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Textarea } from '@/ui/textarea'

type Option = { value: string; label: string }

const props = defineProps<{
  rule: App.Automation.Data.AutomationRuleData
  anchors: Option[]
  recipients: Option[]
  conditions: Option[]
  mergeFields: { path: string; sample: string }[]
  runs: App.Automation.Data.AutomationRunData[]
}>()

const NO_CONDITION = 'none'

const form = useForm({
  name: props.rule.name,
  anchor: props.rule.anchor ?? 'per_shoot',
  offset_days: props.rule.offsetDays ?? 0,
  direction: props.rule.direction ?? 'before',
  recipient_type: props.rule.recipient,
  condition: props.rule.condition,
  notification_subject: props.rule.subject,
  notification_body: props.rule.body,
  is_active: props.rule.isActive,
})

// Die Vorschau rechnet mit denselben Beispielwerten wie der Server; ein
// unbekanntes Feld bleibt stehen, wie es geschrieben wurde.
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
</script>

<template>
  <AdminLayout active="einstellungen">
    <div data-tokens>
      <PageHead :kicker="$t('automations.title')" :title="rule.name" />

      <div class="px-8 pt-7 pb-8">
        <div class="flex items-start gap-8 max-[640px]:flex-col max-[640px]:gap-4">
          <SettingsNav active="automations" />

          <form class="max-w-[520px] min-w-0 flex-1" @submit.prevent="save">
            <div class="grid grid-cols-[repeat(2,minmax(0,1fr))] gap-x-6 gap-y-4">
              <Field v-slot="{ id }" wide :label="$t('automations.name')" :error="form.errors.name">
                <Input :id="id" v-model="form.name" type="text" />
              </Field>

              <Field v-slot="{ id }" :label="$t('automations.anchor')">
                <Select :model-value="form.anchor" @update:model-value="(v) => (form.anchor = String(v))">
                  <SelectTrigger :id="id"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="option in anchors" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </Field>

              <Field :label="$t('automations.offset')" :error="form.errors.offset_days">
                <div class="flex items-center gap-2">
                  <Input v-model="form.offset_days" class="w-16" type="number" min="0" />
                  <Select :model-value="form.direction" @update:model-value="(v) => (form.direction = String(v))">
                    <SelectTrigger class="w-24"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="before">{{ $t('project.taskOffsetBefore') }}</SelectItem>
                      <SelectItem value="after">{{ $t('project.taskOffsetAfter') }}</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </Field>

              <Field v-slot="{ id }" :label="$t('automations.recipient')">
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

              <Field v-slot="{ id }" :label="$t('automations.condition')">
                <Select
                  :model-value="form.condition ?? NO_CONDITION"
                  @update:model-value="(v) => (form.condition = v === NO_CONDITION ? null : String(v))"
                >
                  <SelectTrigger :id="id"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem :value="NO_CONDITION">{{ $t('automations.conditionNone') }}</SelectItem>
                    <SelectItem v-for="option in conditions" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </Field>

              <Field v-slot="{ id }" wide :label="$t('automations.subject')" :error="form.errors.notification_subject">
                <Input :id="id" v-model="form.notification_subject" type="text" />
              </Field>

              <Field v-slot="{ id }" wide :label="$t('automations.body')" :error="form.errors.notification_body">
                <Textarea :id="id" v-model="form.notification_body" rows="6" />
                <div class="mt-2 flex flex-wrap gap-1.5">
                  <button
                    v-for="field in mergeFields"
                    :key="field.path"
                    class="cursor-pointer rounded-[var(--r-pill)] bg-sunk-2 px-2 py-1 text-[11px] leading-none"
                    type="button"
                    @click="insert(field.path)"
                  >
                    {{ field.path }}
                  </button>
                </div>
              </Field>
            </div>

            <div class="mt-4 flex items-center gap-2">
              <Checkbox id="cb-rule-active" v-model="form.is_active" />
              <Label variant="inline" for="cb-rule-active">{{ $t('automations.active') }}</Label>
            </div>

            <div class="mt-6 flex gap-2">
              <Button type="submit" :disabled="form.processing">{{ $t('common.save') }}</Button>
              <Button variant="ghost" type="button" @click="remove">{{ $t('common.remove') }}</Button>
            </div>
          </form>

          <aside class="w-[260px] flex-none max-[900px]:w-full">
            <div class="text-[10.5px] font-semibold tracking-[0.07em] uppercase text-ink-2">
              {{ $t('automations.preview') }}
            </div>
            <div class="mt-2 rounded-[7px] bg-sunk-2 p-3">
              <div class="text-[12.5px] font-medium">{{ render(form.notification_subject) }}</div>
              <p class="mt-1.5 text-[12.5px] leading-[1.5] whitespace-pre-line">{{ render(form.notification_body) }}</p>
            </div>

            <div class="mt-6 text-[10.5px] font-semibold tracking-[0.07em] uppercase text-ink-2">
              {{ $t('automations.runs') }}
            </div>
            <div v-for="run in runs" :key="run.id" class="flex items-baseline gap-2 py-1.5 text-[11.5px]">
              <span class="text-ink-2 tabular-nums">{{ run.firedAt }}</span>
              <span>{{ run.statusLabel }}</span>
            </div>
          </aside>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
/*
 * Schnellbau: der Vertrag entsteht aus abgehakten Klauseln, nicht aus einer
 * leeren Seite. Was hier herauskommt, ist ein Entwurf im Dokumenteditor —
 * jede Klausel bleibt dort Text wie jeder andere.
 */
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import {
  Dialog,
  DialogBody,
  DialogClose,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/ui/dialog'
import { Label } from '@/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Switch } from '@/ui/switch'
import { Textarea } from '@/ui/textarea'

type Clause = { key: string; group: string; title: string; text: string; on: boolean }

const props = defineProps<{
  projectId: string
  templates: { value: string; label: string }[]
  clauses: Clause[]
}>()

const GROUPS = ['auftrag', 'nutzung', 'lieferung', 'ausfall', 'recht'] as const

const open = ref(false)
const picked = ref(new Map(props.clauses.map((clause) => [clause.key, { ...clause }])))

const form = useForm({ template: props.templates[0]?.value ?? '' })

const grouped = computed(() =>
  GROUPS.map((group) => ({
    group,
    clauses: props.clauses.filter((clause) => clause.group === group),
  })).filter((section) => section.clauses.length > 0),
)

function state(key: string) {
  return picked.value.get(key)!
}

function countOf(group: string): string {
  const clauses = props.clauses.filter((clause) => clause.group === group)

  return `${clauses.filter((clause) => state(clause.key).on).length}/${clauses.length}`
}

function submit() {
  form
    .transform((data) => ({
      ...data,
      clauses: props.clauses
        .filter((clause) => state(clause.key).on)
        .map((clause) => ({ title: clause.title, text: state(clause.key).text })),
    }))
    .post(`/auftraege/${props.projectId}/vertraege`, {
      preserveScroll: true,
      onSuccess: () => (open.value = false),
    })
}
</script>

<template>
  <Dialog v-model:open="open">
    <DialogTrigger as-child>
      <Button type="button">{{ $t('contractWizard.open') }}</Button>
    </DialogTrigger>

    <DialogContent class="max-w-[640px]">
      <DialogHeader>
        <DialogTitle>{{ $t('contractWizard.title') }}</DialogTitle>
      </DialogHeader>

      <DialogBody>
        <form id="contract-wizard" class="grid gap-6" @submit.prevent="submit">
          <div class="flex min-w-0 max-w-[280px] flex-col gap-1.5">
            <Label for="wizard-template">{{ $t('project.contractTemplate') }}</Label>
            <Select :model-value="form.template" @update:model-value="(value) => (form.template = String(value))">
              <SelectTrigger id="wizard-template">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="option in templates" :key="option.value" :value="option.value">
                  {{ option.label }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <section v-for="section in grouped" :key="section.group" class="grid gap-3">
            <div class="flex items-baseline gap-2">
              <h3 class="text-[12.5px] font-medium">{{ $t(`contractWizard.group.${section.group}`) }}</h3>
              <span class="text-[11.5px] text-ink-2">{{ countOf(section.group) }}</span>
            </div>

            <div v-for="clause in section.clauses" :key="clause.key" class="grid gap-1.5">
              <div class="flex items-center gap-2.5">
                <Switch :id="`clause-${clause.key}`" v-model="state(clause.key).on" />
                <Label :for="`clause-${clause.key}`">{{ clause.title }}</Label>
              </div>
              <Textarea v-if="state(clause.key).on" v-model="state(clause.key).text" rows="3" />
            </div>
          </section>
        </form>
      </DialogBody>

      <DialogFooter>
        <DialogClose as-child>
          <Button type="button" variant="outline">{{ $t('common.cancel') }}</Button>
        </DialogClose>
        <Button type="submit" form="contract-wizard" :disabled="form.processing">
          {{ $t('contractWizard.submit') }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

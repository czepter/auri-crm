<script setup lang="ts">
/*
 * Aufgabe anlegen. Eine Komponente, weil dasselbe Formular auf der Übersicht
 * und auf der Aufgabenseite steht — zweimal geschrieben liefen die beiden
 * beim ersten neuen Feld auseinander.
 *
 * Fällig hat zwei Formen und nie beide zugleich: ein Kalendertag, oder ein
 * Ereignis mit Versatz. Der Server rechnet das Ereignis in einen Tag um; hier
 * steht nur, woran es hängt.
 */
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import { DatePicker } from '@/ui/date-picker'
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
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/ui/select'
import { dayWithWeekday } from '@/admin/lib/dates'

type Anchor = { value: string; label: string; group: string; on: string | null }

const props = defineProps<{
  projectId: string
  shootOptions: { value: string; label: string }[]
  staffOptions: { value: string; label: string }[]
  anchors: Anchor[]
}>()

const open = ref(false)
const mode = ref<'anchor' | 'fixed'>('fixed')
const direction = ref('after')
const days = ref('0')

const form = useForm({
  title: '',
  audience: 'internal',
  due_on: '',
  anchor: '',
  shoot: 'none',
  assignee: 'none',
})

const groupLabels: Record<string, string> = {
  shoot: 'project.taskAnchorShoot',
  reached: 'project.taskAnchorReached',
  pending: 'project.taskAnchorPending',
}

const groups = computed(() =>
  (['shoot', 'reached', 'pending'] as const)
    .map((group) => ({ group, items: props.anchors.filter((anchor) => anchor.group === group) }))
    .filter((entry) => entry.items.length > 0)
)

function submit() {
  form
    .transform((data) => ({
      ...data,
      due_on: mode.value === 'fixed' && data.due_on !== '' ? data.due_on : null,
      anchor: mode.value === 'anchor' && data.anchor !== '' ? data.anchor : null,
      offset_days:
        mode.value === 'anchor' ? (direction.value === 'before' ? -Number(days.value) : Number(days.value)) : null,
      shoot: data.shoot === 'none' ? null : data.shoot,
      assignee: data.assignee === 'none' ? null : data.assignee,
    }))
    .post(`/auftraege/${props.projectId}/aufgaben`, {
      preserveScroll: true,
      onSuccess: () => {
        form.reset()
        open.value = false
      },
    })
}
</script>

<template>
  <!-- Eine Aufgabe wird angelegt, während man die Liste liest: inline schöbe
       das Formular die Liste bei jedem Öffnen weg. -->
  <Dialog v-model:open="open">
    <DialogTrigger as-child>
      <Button class="ml-auto" variant="pill-muted" type="button">
        <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
        {{ $t('project.taskAdd') }}
      </Button>
    </DialogTrigger>

    <DialogContent>
      <DialogHeader>
        <DialogTitle>{{ $t('project.taskAdd') }}</DialogTitle>
      </DialogHeader>

      <DialogBody>
        <form
          id="task-create-form"
          class="grid grid-cols-[repeat(2,minmax(0,1fr))] gap-x-6 gap-y-4"
          @submit.prevent="submit"
        >
          <Field v-slot="{ id }" wide :label="$t('project.taskTitle')" :error="form.errors.title">
            <Input :id="id" v-model="form.title" type="text" />
          </Field>

          <Field v-slot="{ id }" :label="$t('project.taskAudience')">
            <Select v-model="form.audience">
              <SelectTrigger :id="id"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="internal">{{ $t('lead.audienceInternal') }}</SelectItem>
                <SelectItem value="client">{{ $t('lead.audienceClient') }}</SelectItem>
              </SelectContent>
            </Select>
          </Field>

          <Field v-slot="{ id }" :label="$t('project.taskShoot')">
            <Select v-model="form.shoot">
              <SelectTrigger :id="id"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="none">{{ $t('project.taskNoShoot') }}</SelectItem>
                <SelectItem v-for="option in shootOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </SelectItem>
              </SelectContent>
            </Select>
          </Field>

          <Field v-if="!$page.props.soloStudio" v-slot="{ id }" :label="$t('project.taskAssignee')">
            <Select v-model="form.assignee">
              <SelectTrigger :id="id"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="none">{{ $t('project.taskUnassigned') }}</SelectItem>
                <SelectItem v-for="option in staffOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </SelectItem>
              </SelectContent>
            </Select>
          </Field>

          <Field wide :label="$t('project.taskDue')" :error="form.errors.anchor ?? form.errors.due_on">
            <span class="inline-flex w-fit gap-0.5 rounded-lg bg-sunk p-[3px]">
              <button
                v-for="option in (['fixed', 'anchor'] as const)"
                :key="option"
                class="cursor-pointer rounded-[var(--r-sm)] px-[11px] py-1 text-small leading-none"
                :class="mode === option ? 'bg-paper font-semibold' : 'text-ink-2'"
                type="button"
                @click="mode = option"
              >
                {{ option === 'fixed' ? $t('project.taskDueFixed') : $t('project.taskDueAnchored') }}
              </button>
            </span>

            <DatePicker v-if="mode === 'fixed'" v-model="form.due_on" class="mt-2" />

            <div v-else class="mt-2 flex flex-wrap items-center gap-2">
              <Input v-model="days" class="w-16" type="number" min="0" />
              <span class="text-small text-ink-2">{{ $t('common.unitDays') }}</span>

              <Select v-model="direction">
                <SelectTrigger class="w-24"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="before">{{ $t('project.taskOffsetBefore') }}</SelectItem>
                  <SelectItem value="after">{{ $t('project.taskOffsetAfter') }}</SelectItem>
                </SelectContent>
              </Select>

              <Select v-model="form.anchor">
                <SelectTrigger class="min-w-52 flex-1"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectGroup v-for="entry in groups" :key="entry.group">
                    <SelectLabel>{{ $t(groupLabels[entry.group]) }}</SelectLabel>
                    <SelectItem v-for="anchor in entry.items" :key="anchor.value" :value="anchor.value">
                      {{ anchor.label }}
                      <span class="ml-2 text-faint">{{ dayWithWeekday(anchor.on, '') }}</span>
                    </SelectItem>
                  </SelectGroup>
                </SelectContent>
              </Select>
            </div>
          </Field>
        </form>
      </DialogBody>

      <DialogFooter>
        <Button
          type="submit"
          form="task-create-form"
          :disabled="!form.title || form.processing || (mode === 'anchor' && !form.anchor)"
        >
          {{ $t('common.save') }}
        </Button>
        <DialogClose as-child>
          <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
        </DialogClose>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

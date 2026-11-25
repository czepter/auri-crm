<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import ContactTypeahead from '@/admin/components/crm/ContactTypeahead.vue'
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
import { parseQuickAdd } from '@/admin/lib/quickAdd'
import { dayWithWeekday } from '@/admin/lib/dates'
import Chip from '@/ui/page/Chip.vue'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'

defineProps<{ staffOptions: { value: string; label: string }[]; label?: string }>()

const open = ref(false)
const contact = ref<App.Crm.Data.ContactSuggestionData | null>(null)

const form = useForm({ title: '', due_on: '', assignee: 'none' })

const quick = computed(() => parseQuickAdd(form.title))
const parsedDue = computed(() => (form.due_on === '' ? quick.value.dueOn : null))

function submit() {
  form
    .transform((data) => ({
      ...data,
      title: parsedDue.value ? quick.value.title : data.title,
      due_on: parsedDue.value ?? (data.due_on === '' ? null : data.due_on),
      assignee: data.assignee === 'none' ? null : data.assignee,
      contact: contact.value?.id ?? null,
    }))
    .post('/aufgaben', {
      preserveScroll: true,
      onSuccess: () => {
        form.reset()
        contact.value = null
        open.value = false
      },
    })
}
</script>

<template>
  <Dialog v-model:open="open">
    <DialogTrigger as-child>
      <Button class="ml-auto" variant="default" type="button">
        <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
        {{ label ?? $t('project.taskAdd') }}
      </Button>
    </DialogTrigger>

    <DialogContent>
      <DialogHeader>
        <DialogTitle>{{ $t('project.taskAdd') }}</DialogTitle>
      </DialogHeader>

      <DialogBody>
        <form
          id="studio-task-create-form"
          class="grid grid-cols-[repeat(2,minmax(0,1fr))] gap-x-6 gap-y-4"
          @submit.prevent="submit"
        >
          <Field v-slot="{ id }" wide :label="$t('project.taskTitle')" :error="form.errors.title">
            <Input :id="id" v-model="form.title" type="text" />
            <Chip v-if="parsedDue" class="mt-1.5">{{ dayWithWeekday(parsedDue) }}</Chip>
          </Field>

          <Field wide :label="$t('project.taskDue')" :error="form.errors.due_on">
            <DatePicker v-model="form.due_on" />
          </Field>

          <Field v-if="!$page.props.soloStudio" v-slot="{ id }" wide :label="$t('project.taskAssignee')">
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

          <Field wide :label="$t('leads.contact')">
            <ContactTypeahead v-model="contact" :creatable="false" />
          </Field>
        </form>
      </DialogBody>

      <DialogFooter>
        <Button class="ml-auto" type="submit" form="studio-task-create-form" :disabled="!form.title || form.processing">
          {{ $t('common.save') }}
        </Button>
        <DialogClose as-child>
          <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
        </DialogClose>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

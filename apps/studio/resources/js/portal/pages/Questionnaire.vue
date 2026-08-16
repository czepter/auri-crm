<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { Textarea } from '@/ui/textarea'

type Field = App.Crm.Data.FormInstanceFieldData

const props = defineProps<{
  contact: { name: string }
  project: { id: string; name: string }
  questionnaire: { id: string; name: string; fields: Field[] }
}>()

// One submit for the whole thing: a client answers a questionnaire, they do
// not save eight of them. Prefilled with whatever was already answered —
// by them or by the studio off a phone call — so revising is editing rather
// than starting over.
const form = useForm<{ answers: Record<string, string> }>({
  answers: Object.fromEntries(props.questionnaire.fields.map((f) => [f.id, f.currentAnswer ?? ''])),
})

function submit() {
  form.patch(`/projekte/${props.project.id}/fragebogen/${props.questionnaire.id}`, { preserveScroll: true })
}

// FormFieldType's own cases (App\Crm\Enums\FormFieldType) — the builder's
// palette, rendered here as the controls a client actually types into.
// `boolean` rides as the string "ja"/"" because a FormAnswer's value is one
// text column and always has been.
function isChecked(id: string) {
  return form.answers[id] === 'ja'
}

// Errors come back keyed "answers.<publicId>" — one per unanswered required
// question, which is not a shape useForm's own typed errors can describe.
function errorFor(id: string) {
  return (form.errors as Record<string, string | undefined>)[`answers.${id}`]
}
</script>

<template>
  <PortalHeader :contact="contact" />

  <main class="letter">
    <div class="kick">{{ project.name }}</div>
    <h1>{{ questionnaire.name }}</h1>

    <form style="margin-top: var(--s6)" @submit.prevent="submit">
      <template v-for="field in questionnaire.fields" :key="field.id">
        <div v-if="field.type === 'boolean'" class="grp">
          <label class="consent">
            <Checkbox
              :model-value="isChecked(field.id)"
              @update:model-value="form.answers[field.id] = isChecked(field.id) ? '' : 'ja'"
            />
            <span>{{ field.label }}</span>
          </label>
        </div>

        <Label v-else class="field">
          <span>{{ field.label }}<em v-if="!field.required"> · {{ $t('settings.optional') }}</em></span>

          <select v-if="field.type === 'select'" v-model="form.answers[field.id]">
            <option value="" />
            <option v-for="option in field.options ?? []" :key="option" :value="option">{{ option }}</option>
          </select>

          <!-- name_list is a list of people typed one per line; nothing in
               the schema splits it, so nothing here pretends to. -->
          <Textarea
            v-else-if="field.type === 'text' || field.type === 'name_list'"
            v-model="form.answers[field.id]"
            rows="3"
          />

          <Input
            v-else
            v-model="form.answers[field.id]"
            :type="field.type === 'date' ? 'date' : field.type === 'time' ? 'time' : 'text'"
          />
        </Label>

        <p v-if="errorFor(field.id)" class="err">{{ errorFor(field.id) }}</p>
      </template>

      <div class="formfoot">
        <Button type="submit" :disabled="form.processing">{{ $t('common.save') }}</Button>
      </div>
    </form>
  </main>

  <PortalFooter />
</template>

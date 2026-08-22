<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import PortalField from '@/portal/components/PortalField.vue'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalShell from '@/portal/components/PortalShell.vue'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { Input } from '@/ui/input'
import { NativeSelect } from '@/ui/native-select'
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
  <PortalShell>
    <PortalHeader :switcher="{ label: project.name, toAll: false }" />

    <!-- The letter measure: this is a page somebody reads and answers, not a
         screen they scan. -->
    <main class="mx-auto w-full max-w-[668px] grow px-6 pt-[var(--band)] pb-28">
      <p class="animate-rise motion-reduce:animate-none mb-4 text-[11px] leading-none font-semibold tracking-[0.17em] uppercase text-muted-foreground">{{ project.name }}</p>
      <h1 class="animate-rise [animation-delay:140ms] motion-reduce:animate-none font-serif text-[clamp(36px,5vw,52px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">
        {{ questionnaire.name }}
      </h1>

      <form class="animate-rise [animation-delay:230ms] motion-reduce:animate-none mt-12 grid gap-6" @submit.prevent="submit">
        <template v-for="field in questionnaire.fields" :key="field.id">
          <label
            v-if="field.type === 'boolean'"
            class="flex cursor-pointer items-start gap-3.5 text-[14.5px] leading-[1.5] [&_[data-slot=checkbox]]:mt-0.5 [&_[data-slot=checkbox]]:size-[17px] [&_[data-slot=checkbox]]:flex-none [&_[data-slot=checkbox][data-state=checked]]:border-action [&_[data-slot=checkbox][data-state=checked]]:bg-action"
          >
            <Checkbox
              :model-value="isChecked(field.id)"
              @update:model-value="form.answers[field.id] = isChecked(field.id) ? '' : 'ja'"
            />
            <span>{{ field.label }}</span>
          </label>

          <PortalField
            v-else
            :label="field.label"
            :optional="field.required ? undefined : $t('settings.optional')"
          >
            <NativeSelect v-if="field.type === 'select'" v-model="form.answers[field.id]">
              <option value="" />
              <option v-for="option in field.options ?? []" :key="option" :value="option">{{ option }}</option>
            </NativeSelect>

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

            <span v-if="errorFor(field.id)" class="mt-[7px] block text-[12.5px] leading-[1.45] text-destructive">{{ errorFor(field.id) }}</span>
          </PortalField>
        </template>

        <Button class="mt-2 justify-self-start" variant="portal" type="submit" :disabled="form.processing">
          {{ $t('common.save') }}
        </Button>
      </form>
    </main>

    <PortalFooter />
  </PortalShell>
</template>

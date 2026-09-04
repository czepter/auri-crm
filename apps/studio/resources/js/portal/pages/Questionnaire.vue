<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import GroupPhotoField from '@/portal/components/GroupPhotoField.vue'
import PortalField from '@/portal/components/PortalField.vue'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalShell from '@/portal/components/PortalShell.vue'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { Input } from '@/ui/input'
import { NONE, fromOption, toOption } from '@/lib/select'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
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
  answers: Object.fromEntries(
    props.questionnaire.fields.filter((f) => f.type !== 'file').map((f) => [f.id, f.currentAnswer ?? '']),
  ),
})

// A file goes up on its own rather than with the form's one submit: bytes are
// not something anybody retypes when the rest of the page fails validation.
function upload(fieldId: string, event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  router.post(
    `/projekte/${props.project.id}/fragebogen/${props.questionnaire.id}/datei`,
    { field: fieldId, file },
    { preserveScroll: true, onFinish: () => (input.value = '') },
  )
}

function removeFile(fileId: string) {
  router.delete(`/projekte/${props.project.id}/fragebogen/${props.questionnaire.id}/datei/${fileId}`, {
    preserveScroll: true,
  })
}

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
            :hint="field.groupPhotos?.help ?? undefined"
          >
            <template v-if="field.type === 'file'">
              <div v-for="file in field.files" :key="file.id" class="flex items-center gap-3 text-[14.5px] leading-[1.5]">
                <span>{{ file.name }}</span>
                <button class="text-[12.5px] underline underline-offset-2" type="button" @click="removeFile(file.id)">
                  {{ $t('common.remove') }}
                </button>
              </div>
              <input
                class="block text-[13.5px] leading-[1.5]"
                type="file"
                :accept="(field.fileFormats ?? []).map((f) => `.${f}`).join(',')"
                @change="upload(field.id, $event)"
              />
            </template>

            <Select
              v-else-if="field.type === 'select'"
              :model-value="toOption(form.answers[field.id])"
              @update:model-value="(value) => form.answers[field.id] = fromOption(value)"
            >
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem :value="NONE">{{ $t('portal.none') }}</SelectItem>
                <SelectItem v-for="option in field.options ?? []" :key="option" :value="option">{{ option }}</SelectItem>
              </SelectContent>
            </Select>

            <!-- Gruppenfotos bauen ihre Antwort selbst und legen sie als JSON
                 in dieselbe Textspalte wie jede andere. -->
            <GroupPhotoField
              v-else-if="field.type === 'group_photos'"
              v-model="form.answers[field.id]"
              :suggestions="field.groupPhotos?.suggestions ?? []"
              :minutes-per-group="field.groupPhotos?.minutesPerGroup ?? 2"
            />

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

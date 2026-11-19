<script setup lang="ts">
import { Fragment, computed, reactive, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import GroupPhotoField from '@/portal/components/GroupPhotoField.vue'
import PortalChip from '@/portal/components/PortalChip.vue'
import PortalField from '@/portal/components/PortalField.vue'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalShell from '@/portal/components/PortalShell.vue'
import { steps, unanswered } from '@/portal/lib/questionnaire'
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
  questionnaire: { id: string; name: string; closed: boolean; fields: Field[] }
}>()

// One submit for the whole thing: a client answers a questionnaire, they do
// not save eight of them. Prefilled with whatever was already answered —
// by them or by the studio off a phone call — so revising is editing rather
// than starting over.
const { t } = useI18n()

const form = useForm<{ answers: Record<string, string> }>({
  answers: Object.fromEntries(
    props.questionnaire.fields.filter((f) => f.type !== 'file' && f.type !== 'section').map((f) => [f.id, f.currentAnswer ?? '']),
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

function shotNames(field: Field): string {
  try {
    return JSON.parse(field.currentAnswer ?? '').groups.map((g: { name: string }) => g.name).join(', ')
  } catch {
    return ''
  }
}

const pages = computed(() => steps(props.questionnaire.fields))
const step = ref(0)
const direction = ref(1)
const last = computed(() => step.value === pages.value.length - 1)
const touched = reactive(new Set<string>())

function goTo(index: number) {
  if (index === step.value) return

  direction.value = index > step.value ? 1 : -1
  step.value = index
}

function blocked(index: number) {
  const missing = pages.value[index].filter((f) => unanswered(f, form.answers))
  missing.forEach((f) => touched.add(f.id))

  return missing.length > 0
}

function next() {
  if (!blocked(step.value)) goTo(step.value + 1)
}

function submit() {
  if (!last.value) return next()

  const open = pages.value.findIndex((_, index) => blocked(index))
  if (open !== -1) return goTo(open)

  form.patch(`/projekte/${props.project.id}/fragebogen/${props.questionnaire.id}`, {
    preserveScroll: true,
    onError: () => goTo(Math.max(0, pages.value.findIndex((page) => page.some((f) => serverError(f.id))))),
  })
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
function serverError(id: string) {
  return (form.errors as Record<string, string | undefined>)[`answers.${id}`]
}

function ids(describedBy: string | undefined, controlId: string, field: Field) {
  return [describedBy, errorFor(field.id) ? `${controlId}-error` : undefined].filter(Boolean).join(' ') || undefined
}

function errorFor(id: string) {
  const field = props.questionnaire.fields.find((f) => f.id === id)!

  return touched.has(id) ? (unanswered(field, form.answers) ? t('common.invalid.valueMissing') : undefined) : serverError(id)
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
      <PortalChip v-if="questionnaire.closed" class="mt-4">{{ $t('formInstances.closed') }}</PortalChip>

      <dl v-if="questionnaire.closed" class="mt-12 grid gap-6">
        <template v-for="field in questionnaire.fields" :key="field.id">
        <div v-if="field.type === 'section'" class="pt-4">
          <h2 class="font-serif text-[22px] leading-[1.2]">{{ field.label }}</h2>
          <p v-if="field.help" class="mt-1 whitespace-pre-line text-[12.5px] leading-[1.45] text-muted-foreground">{{ field.help }}</p>
        </div>
        <div v-else>
          <dt class="text-[14.5px] leading-[1.35] font-medium">{{ field.label }}</dt>
          <dd v-if="field.type === 'file'" class="mt-1 grid gap-1 text-[14.5px] leading-[1.5]">
            <a
              v-for="file in field.files"
              :key="file.id"
              class="underline underline-offset-2"
              :href="`/projekte/${project.id}/fragebogen/${questionnaire.id}/datei/${file.id}`"
            >{{ file.name }}</a>
          </dd>
          <dd v-else class="mt-1 whitespace-pre-line text-[14.5px] leading-[1.5]">{{ field.type === 'group_photos' ? shotNames(field) : field.currentAnswer || $t('portal.none') }}</dd>
        </div>
        </template>
      </dl>

      <form v-else class="animate-rise [animation-delay:230ms] motion-reduce:animate-none mt-12 grid gap-8" :data-direction="direction" @submit.prevent="submit">
        <Transition name="step" mode="out-in">
        <div :key="step" class="grid gap-8">
        <template v-for="field in pages[step]" :key="field.id">
          <div v-if="field.type === 'section'">
            <h2 class="font-serif text-[22px] leading-[1.2]">{{ field.label }}</h2>
            <p v-if="field.help" class="mt-1 whitespace-pre-line text-[12.5px] leading-[1.45] text-muted-foreground">{{ field.help }}</p>
          </div>
          <component :is="field.help ? 'div' : Fragment" v-else-if="field.type === 'boolean'">
          <label
            class="flex cursor-pointer items-start gap-3.5 text-[14.5px] leading-[1.5] [&_[data-slot=checkbox]]:mt-0.5 [&_[data-slot=checkbox]]:size-[17px] [&_[data-slot=checkbox]]:flex-none [&_[data-slot=checkbox][data-state=checked]]:border-action [&_[data-slot=checkbox][data-state=checked]]:bg-action"
          >
            <Checkbox
              :aria-describedby="field.help ? `${field.id}-help` : undefined"
              :model-value="isChecked(field.id)"
              @update:model-value="form.answers[field.id] = isChecked(field.id) ? '' : 'ja'"
            />
            <span>{{ field.label }}</span>
          </label>
          <p v-if="field.help" :id="`${field.id}-help`" class="mt-1 pl-[31px] whitespace-pre-line text-[12.5px] leading-[1.45] text-muted-foreground">{{ field.help }}</p>
          </component>

          <PortalField
            v-else
            class="relative [&_[data-slot=caption]]:text-[14.5px] [&_[data-slot=caption]]:leading-[1.35] [&_[data-slot=caption]]:font-medium [&_[data-slot=caption]]:text-foreground"
            :label="field.label"
            :optional="field.required ? undefined : $t('settings.optional')"
            :help="field.help ?? undefined"
            :group="field.type === 'group_photos'"
            @focusout="touched.add(field.id)"
            v-slot="{ controlId, captionId, describedBy }"
          >
            <template v-if="field.type === 'file'">
              <div v-for="file in field.files" :key="file.id" class="flex items-center gap-3 text-[14.5px] leading-[1.5]">
                <span>{{ file.name }}</span>
                <button class="text-[12.5px] underline underline-offset-2" type="button" @click="removeFile(file.id)">
                  {{ $t('common.remove') }}
                </button>
              </div>
              <input
                :id="controlId"
                :aria-describedby="ids(describedBy, controlId, field)"
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
              <SelectTrigger :id="controlId" :aria-describedby="ids(describedBy, controlId, field)"><SelectValue /></SelectTrigger>
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
              :aria-labelledby="captionId"
              :aria-describedby="ids(describedBy, controlId, field)"
              :suggestions="field.groupPhotos?.suggestions ?? []"
              :minutes-per-group="field.groupPhotos?.minutesPerGroup ?? 2"
            />

            <!-- name_list is a list of people typed one per line; nothing in
                 the schema splits it, so nothing here pretends to. -->
            <Textarea
              v-else-if="field.type === 'text' || field.type === 'name_list'"
              v-model="form.answers[field.id]"
              :id="controlId"
              :aria-describedby="ids(describedBy, controlId, field)"
              rows="3"
            />

            <Input
              v-else
              v-model="form.answers[field.id]"
              :id="controlId"
              :aria-describedby="ids(describedBy, controlId, field)"
              :type="field.type === 'date' ? 'date' : field.type === 'time' ? 'time' : 'text'"
            />

            <Transition name="error">
              <span v-if="errorFor(field.id)" :id="`${controlId}-error`" role="status" class="absolute top-full left-0 pt-1.5 text-[12.5px] leading-[1.45] text-destructive">{{ errorFor(field.id) }}</span>
            </Transition>
          </PortalField>
        </template>
        </div>
        </Transition>

        <div class="mt-2 flex items-center gap-3">
          <Button v-if="step > 0" variant="portal-ghost" type="button" @click="goTo(step - 1)">{{ $t('common.back') }}</Button>
          <Button v-if="!last" variant="portal" type="submit">{{ $t('portal.next') }}</Button>
          <Button v-else variant="portal" type="submit" :state="form.processing ? 'loading' : 'idle'" :disabled="form.processing">
            {{ $t('common.save') }}
          </Button>
          <span v-if="pages.length > 1" class="ml-auto text-[12.5px] tabular-nums text-muted-foreground">{{ step + 1 }} / {{ pages.length }}</span>
        </div>
      </form>
    </main>

    <PortalFooter />
  </PortalShell>
</template>

<style scoped>
.step-enter-active {
  transition: opacity 280ms cubic-bezier(0.22, 0.61, 0.36, 1), transform 280ms cubic-bezier(0.22, 0.61, 0.36, 1);
}
.step-leave-active {
  transition: opacity 140ms cubic-bezier(0.4, 0, 1, 1), transform 140ms cubic-bezier(0.4, 0, 1, 1);
}
.step-enter-from,
.step-leave-to {
  opacity: 0;
}
[data-direction='1'] .step-enter-from,
[data-direction='-1'] .step-leave-to {
  transform: translateX(22px);
}
[data-direction='-1'] .step-enter-from,
[data-direction='1'] .step-leave-to {
  transform: translateX(-22px);
}

.error-enter-active,
.error-leave-active {
  transition: opacity 150ms ease-out, transform 150ms ease-out;
}
.error-enter-from,
.error-leave-to {
  opacity: 0;
  transform: translateY(-3px);
}

@media (prefers-reduced-motion: reduce) {
  .step-enter-active,
  .step-leave-active,
  .error-enter-active,
  .error-leave-active {
    transition-property: opacity;
  }
  .step-enter-from,
  .step-leave-to,
  .error-enter-from,
  .error-leave-to {
    transform: none !important;
  }
}
</style>

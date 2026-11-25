<script setup lang="ts">
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import BackButton from '@/ui/page/BackButton.vue'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'
import { Dialog, DialogBody, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/ui/dialog'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import DocumentEditor from '@/admin/components/templates/DocumentEditor.vue'
import { Toaster } from '@/ui/sonner'
import { toPlainText } from '@/admin/documents/blocks/serialize'
import type { EditorDocument, PaletteEntry } from '@/admin/documents/blocks/types'

// Full-screen compose view for every "per E-Mail senden": recipient, subject
// and message on the left, the mail on the right — the split pixieset's own
// "Share Collection" view uses. The right side is the notification's own
// server render (App\Notifications\Support\MailPreview) with its message
// paragraph swapped for what is being typed, so every line, button and
// footer the mail class adds shows exactly as it is sent. The message goes
// out as plain text (toPlainText), which is therefore what it shows too.
const MESSAGE = '%%NACHRICHT%%'

const props = withDefaults(
  defineProps<{
    recipients: { name: string; email: string }[]
    subject: string
    message: EditorDocument
    mailHtml: string
    palette?: PaletteEntry[]
    mergeFields?: { path: string; sample: string }[]
    templates?: { id: string; name: string; subject: string | null; body: string }[]
    shootKinds?: string[]
    saveAs?: { occasion: string; quote?: string; invoice?: string }
    processing?: boolean
  }>(),
  { palette: () => [], mergeFields: () => [], templates: () => [], shootKinds: () => [] },
)

const emit = defineEmits<{ cancel: []; send: [{ subject: string; message: EditorDocument }] }>()

const subject = ref(props.subject)
const message = ref<EditorDocument>(props.message)
const editorKey = ref(0)

// The template body arrives resolved against this case, as plain paragraphs.
function useTemplate(id: string) {
  const template = props.templates.find((entry) => entry.id === id)
  if (!template) return

  message.value = { blocks: template.body.split('\n\n').map((text) => ({ type: 'paragraph', data: { text } })) }
  if (template.subject) subject.value = template.subject
  editorKey.value++
}

const saving = ref(false)
const save = useForm({ name: '', shoot_kind: '', with_subject: true, as_default: false })

function saveTemplate() {
  save
    .transform((data) => ({
      name: data.name,
      shoot_kind: data.shoot_kind || null,
      subject: data.with_subject ? subject.value : null,
      message: toPlainText(message.value),
      occasion: data.as_default ? props.saveAs?.occasion : null,
      quote: props.saveAs?.quote,
      invoice: props.saveAs?.invoice,
    }))
    .post('/dokumentvorlagen/email', {
      preserveScroll: true,
      onSuccess: () => {
        saving.value = false
        save.reset()
        router.reload({ only: ['mailTemplates'] })
      },
    })
}

function escapeHtml(text: string): string {
  return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}

const previewHtml = computed(() =>
  props.mailHtml.replace(new RegExp(`(<p[^>]*>)${MESSAGE}</p>`), (_, open: string) =>
    toPlainText(message.value)
      .split('\n\n')
      .map((paragraph) => `${open}${escapeHtml(paragraph)}</p>`)
      .join(''),
  ),
)

function send() {
  emit('send', { subject: subject.value, message: message.value })
}
</script>

<template>
  <div class="flex h-screen flex-col">
    <header class="flex h-16 flex-none items-center gap-3.5 px-7">
      <BackButton @click="emit('cancel')" />

      <h1 class="m-0 text-title leading-7 font-semibold tracking-[-0.02em]">{{ $t('sendMail.title') }}</h1>
    </header>

    <div class="flex min-h-0 flex-1">
      <div class="flex w-1/2 flex-none flex-col gap-5 overflow-y-auto p-6">
        <slot name="document" />

        <Field :label="$t('sendMail.recipient')" dense>
          <div class="flex flex-col gap-2">
            <div v-for="recipient in recipients" :key="recipient.email" class="flex items-center gap-2.5 rounded-md border border-border px-3 py-2.5">
              <div class="flex size-[26px] flex-none items-center justify-center rounded-full bg-sunk text-small font-semibold text-ink-2">
                {{ recipient.name.slice(0, 1) }}
              </div>
              <div class="min-w-0 flex-1">
                <div class="text-small font-semibold">{{ recipient.name }}</div>
                <div class="text-small text-ink-2">{{ recipient.email }}</div>
              </div>
            </div>
          </div>
        </Field>

        <Field v-slot="{ id }" :label="$t('sendMail.subject')" dense>
          <Input :id="id" v-model="subject" type="text" />
        </Field>

        <Field v-if="templates.length || saveAs" v-slot="{ id }" :label="$t('sendMail.template')" dense>
          <div class="flex items-center gap-3">
            <Select class="flex-1" @update:model-value="(value) => useTemplate(String(value))">
              <SelectTrigger :id="id" class="flex-1"><SelectValue placeholder="—" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="template in templates" :key="template.id" :value="template.id">{{ template.name }}</SelectItem>
              </SelectContent>
            </Select>
            <Button v-if="saveAs" variant="outline" type="button" class="flex-none" @click="saving = true">{{ $t('sendMail.saveTemplate') }}</Button>
          </div>
        </Field>

        <Field :label="$t('sendMail.message')" dense class="min-h-[160px] flex-1">
          <div class="flex-1 overflow-y-auto rounded-md border border-border px-3 py-2.5">
            <DocumentEditor :key="editorKey" v-model="message" :palette="palette" :merge-fields="mergeFields" />
          </div>
        </Field>

        <div class="flex justify-end border-t border-border pt-4">
          <Button variant="default" type="button" :state="processing ? 'loading' : 'idle'" @click="send">{{ $t('sendMail.submit') }}</Button>
        </div>
      </div>

      <div class="flex min-w-0 flex-1 flex-col p-6">
        <iframe :srcdoc="previewHtml" sandbox="" :title="$t('sendMail.preview')" class="min-h-0 flex-1 rounded-md border-0 bg-sunk" />
      </div>
    </div>
    <Dialog v-model:open="saving">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{{ $t('sendMail.saveTemplate') }}</DialogTitle>
        </DialogHeader>
        <DialogBody>
          <form id="save-template-form" class="flex flex-col gap-4" @submit.prevent="saveTemplate">
            <Field v-slot="{ id }" :label="$t('documentTemplates.name')" :error="save.errors.name" dense>
              <Input :id="id" v-model="save.name" type="text" required />
            </Field>
            <Field v-slot="{ id }" :label="$t('sendMail.shootKind')" dense>
              <Input :id="id" v-model="save.shoot_kind" type="text" list="save-shoot-kinds" :placeholder="$t('documentTemplates.shootKindAny')" />
              <datalist id="save-shoot-kinds">
                <option v-for="kind in shootKinds" :key="kind" :value="kind" />
              </datalist>
            </Field>
            <div class="flex items-center gap-2.5">
              <Checkbox id="save-with-subject" v-model="save.with_subject" />
              <Label variant="inline" for="save-with-subject">{{ $t('sendMail.withSubject') }}</Label>
            </div>
            <div class="flex items-center gap-2.5">
              <Checkbox id="save-as-default" v-model="save.as_default" />
              <Label variant="inline" for="save-as-default">{{ $t('sendMail.asDefault') }}</Label>
            </div>
          </form>
        </DialogBody>
        <DialogFooter>
          <Button variant="default" type="submit" form="save-template-form" :disabled="save.processing">{{ $t('common.save') }}</Button>
          <Button variant="outline" type="button" @click="saving = false">{{ $t('common.cancel') }}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
    <Toaster position="bottom-right" rich-colors />
  </div>
</template>

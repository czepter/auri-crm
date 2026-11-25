<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { ChoiceGroup } from '@/ui/choice-group'
import { CopyButton } from '@/ui/copy-button'
import { Dialog, DialogBody, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/ui/dialog'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupInput } from '@/ui/input-group'
import { Label } from '@/ui/label'
import { Textarea } from '@/ui/textarea'
import { generatePassword, mailText, shareText } from '@/admin/lib/galleryShare'

const props = defineProps<{
  gallery: { id: string; title: string | null; url: string; viewPassword: string | null; downloadPin: string | null; mailMessage?: string | null }
  recipient: { name: string; email: string } | null
  publishable: boolean
}>()
const open = defineModel<boolean>('open', { required: true })
const tab = defineModel<'mail' | 'link'>('tab', { required: true })

const subject = ref('')
const message = ref('')
const includePassword = ref(true)
const processing = ref(false)

watch(open, (isOpen) => {
  if (!isOpen) return
  subject.value = props.gallery.title ?? ''
  message.value = props.gallery.mailMessage ?? mailText(props.recipient?.name ?? null)
  includePassword.value = true
})

const pasteText = computed(() => shareText({ name: props.recipient?.name ?? null, url: props.gallery.url, password: props.gallery.viewPassword }))
const link = computed(() => [
  { id: 'link', label: 'galleryRecord.link', value: props.gallery.url },
  { id: 'pin', label: 'gallery.pinLabel', value: props.gallery.downloadPin },
].filter((field): field is { id: string; label: string; value: string } => field.value !== null))

function post(data: Record<string, string | boolean>) {
  router.post(`/galerien/${props.gallery.id}/veroeffentlichen`, data, {
    preserveScroll: true,
    onStart: () => (processing.value = true),
    onFinish: () => (processing.value = false),
    onSuccess: () => (open.value = false),
  })
}

function sendMail() {
  post({ subject: subject.value, message: message.value, include_password: props.gallery.viewPassword !== null && includePassword.value })
}

function rotatePassword() {
  router.patch(`/galerien/${props.gallery.id}`, { view_password: generatePassword() }, { preserveScroll: true, only: ['gallery'] })
}
</script>

<template>
  <Dialog v-model:open="open">
    <DialogContent>
      <DialogHeader>
        <DialogTitle>{{ $t('galleryRecord.send') }}</DialogTitle>
      </DialogHeader>

      <DialogBody class="flex flex-col gap-4">
        <ChoiceGroup
          v-if="recipient"
          v-model="tab"
          :options="[{ value: 'mail', label: $t('galleryRecord.tabMail') }, { value: 'link', label: $t('galleryRecord.tabLink') }]"
        />

        <template v-if="recipient && tab === 'mail'">
          <Field :label="$t('sendMail.recipient')" dense>
            <Input :model-value="`${recipient.name} <${recipient.email}>`" readonly />
          </Field>
          <Field v-slot="{ id }" :label="$t('sendMail.subject')" dense>
            <Input :id="id" v-model="subject" type="text" />
          </Field>
          <Field v-slot="{ id }" :label="$t('sendMail.message')" dense>
            <Textarea :id="id" v-model="message" rows="5" />
          </Field>
          <div v-if="gallery.viewPassword" class="flex items-center gap-2">
            <Checkbox id="send-password" v-model="includePassword" />
            <Label for="send-password">{{ $t('galleryRecord.passwordInMail') }}</Label>
          </div>
        </template>

        <template v-else>
          <div v-for="field in link" :key="field.id" class="flex flex-col gap-1.5">
            <Label :for="`send-${field.id}`">{{ $t(field.label) }}</Label>
            <InputGroup class="bg-sunk">
              <InputGroupInput :id="`send-${field.id}`" :model-value="field.value" readonly @focus="($event.target as HTMLInputElement).select()" />
              <CopyButton :value="field.value" variant="ghost" class="flex-none px-3" />
            </InputGroup>
          </div>

          <div class="flex flex-col gap-1.5">
            <Label for="send-password">{{ $t('galleries.passwordField') }}</Label>
            <InputGroup class="bg-sunk">
              <InputGroupInput id="send-password" :model-value="gallery.viewPassword ?? ''" readonly @focus="($event.target as HTMLInputElement).select()" />
              <Button type="button" variant="ghost" class="flex-none px-3" @click="rotatePassword">{{ $t('galleryRecord.generate') }}</Button>
              <CopyButton v-if="gallery.viewPassword" :value="gallery.viewPassword" variant="ghost" class="flex-none px-3" />
            </InputGroup>
          </div>

          <Field v-slot="{ id }" :label="$t('galleryRecord.pasteField')" dense>
            <Textarea :id="id" :model-value="pasteText" readonly rows="5" />
          </Field>
        </template>
      </DialogBody>

      <DialogFooter>
        <template v-if="recipient && tab === 'mail'">
          <Button class="ml-auto" type="button" :state="processing ? 'loading' : 'idle'" @click="sendMail">
            {{ publishable ? $t('galleryRecord.sendAndPublish') : $t('sendMail.submit') }}
          </Button>
          <Button v-if="publishable" type="button" variant="secondary" :disabled="processing" @click="post({})">{{ $t('galleryRecord.publishSilently') }}</Button>
        </template>
        <template v-else>
          <CopyButton :value="pasteText" :label="$t('galleryRecord.copyAll')" variant="default" />
          <Button v-if="publishable" type="button" variant="secondary" :disabled="processing" @click="post({})">{{ $t('galleryRecord.publish') }}</Button>
        </template>
        <DialogClose as-child>
          <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
        </DialogClose>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

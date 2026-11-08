<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import SendByMailView from '@/admin/components/templates/SendByMailView.vue'
import { toPlainText } from '@/admin/documents/blocks/serialize'
import type { EditorDocument } from '@/admin/documents/blocks/types'
import { useLiveProps } from '@/admin/lib/liveReload'

const props = defineProps<{
  gallery: { id: string; title: string }
  recipients: { name: string; email: string }[]
  mailHtml: string
}>()

useLiveProps([])

const message: EditorDocument = {
  blocks: [
    { type: 'paragraph', data: { text: `Hallo ${props.recipients[0]?.name ?? ''},` } },
    { type: 'paragraph', data: { text: 'eure Bilder sind da.' } },
  ],
}

function cancel() {
  router.visit(`/galerien/${props.gallery.id}`)
}

const processing = ref(false)

function send({ subject, message }: { subject: string; message: EditorDocument }) {
  router.post(`/galerien/${props.gallery.id}/veroeffentlichen`, { subject, message: toPlainText(message) }, {
    onStart: () => (processing.value = true),
    onFinish: () => (processing.value = false),
  })
}
</script>

<template>
  <SendByMailView
    :recipients="recipients"
    :subject="gallery.title"
    :message="message"
    :mail-html="mailHtml"
    :processing="processing"
    @cancel="cancel"
    @send="send"
  />
</template>

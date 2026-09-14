<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import SendByMailView from '@/admin/components/SendByMailView.vue'
import { toPlainText } from '@/admin/documents/blocks/serialize'
import type { EditorDocument } from '@/admin/documents/blocks/types'

const props = defineProps<{
  gallery: { id: string; title: string; assetCount: number }
  studioName: string
  recipients: { name: string; email: string }[]
}>()

const message: EditorDocument = {
  blocks: [
    { type: 'paragraph', data: { text: `Hallo ${props.recipients[0]?.name ?? ''},` } },
    { type: 'paragraph', data: { text: 'eure Bilder sind da.' } },
  ],
}

function cancel() {
  router.visit(`/galerien/${props.gallery.id}`)
}

function send({ subject, message }: { subject: string; message: EditorDocument }) {
  router.post(`/galerien/${props.gallery.id}/veroeffentlichen`, { subject, message: toPlainText(message) })
}
</script>

<template>
  <SendByMailView
    :studio-name="studioName"
    :recipients="recipients"
    :subject="gallery.title"
    :message="message"
    :cta="$t('sendMail.galleryCta')"
    @cancel="cancel"
    @send="send"
  >
    <template #card>
      <div class="flex items-baseline justify-between px-[18px] py-3.5">
        <span class="text-[13px] font-semibold">{{ gallery.title }}</span>
        <span class="text-[11.5px] text-faint">{{ $t('sendMail.imageCount', gallery.assetCount) }}</span>
      </div>
    </template>
  </SendByMailView>
</template>

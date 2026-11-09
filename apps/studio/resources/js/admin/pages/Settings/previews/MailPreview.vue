<script setup lang="ts">
import { computed } from 'vue'
import { refDebounced } from '@vueuse/core'
import PreviewFrame from './PreviewFrame.vue'

const props = defineProps<{
  mail: {
    fromName: string
    fromAddress: string
    replyTo: string
    bcc: string
    signature: string
    headerName: boolean
    footerLegal: boolean
    footerExtra: string
  }
}>()

const source = computed(() => `/einstellungen/email/vorschau?${new URLSearchParams({ signatur: props.mail.signature, kopf: props.mail.headerName ? '1' : '0', recht: props.mail.footerLegal ? '1' : '0', zeile: props.mail.footerExtra })}`)
const src = refDebounced(source, 400)
</script>

<template>
  <PreviewFrame :label="$t('settings.previewMail')" wide>
    <div class="overflow-hidden rounded-md bg-background text-small leading-[1.5] text-foreground">
      <div class="space-y-1 border-b border-border px-4 py-3">
        <div class="flex gap-2">
          <span class="w-16 flex-none text-faint">{{ $t('settings.previewMailFrom') }}</span>
          <span class="min-w-0 truncate">{{ mail.fromName }} &lt;{{ mail.fromAddress }}&gt;</span>
        </div>
        <div v-if="mail.replyTo" class="flex gap-2">
          <span class="w-16 flex-none text-faint">{{ $t('settings.previewMailReplyTo') }}</span>
          <span class="min-w-0 truncate">{{ mail.replyTo }}</span>
        </div>
        <div v-if="mail.bcc" class="flex gap-2">
          <span class="w-16 flex-none text-faint">{{ $t('settings.mailBccLabel') }}</span>
          <span class="min-w-0 truncate">{{ mail.bcc }}</span>
        </div>
      </div>

      <iframe :src class="block h-[560px] w-full border-0 bg-background" title="" />
    </div>
  </PreviewFrame>
</template>

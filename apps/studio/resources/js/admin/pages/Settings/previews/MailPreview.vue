<script setup lang="ts">
import PreviewFrame from './PreviewFrame.vue'

defineProps<{
  mail: {
    fromName: string
    fromAddress: string
    replyTo: string
    bcc: string
    signature: string
  }
}>()
</script>

<template>
  <PreviewFrame :label="$t('settings.previewMail')">
    <div class="rounded-md bg-background p-4 text-[11px] leading-[1.5] text-foreground">
      <div class="space-y-1 border-b border-border pb-3">
        <div class="flex gap-2">
          <span class="w-14 flex-none text-faint">{{ $t('settings.previewMailFrom') }}</span>
          <span class="min-w-0 truncate">{{ mail.fromName }} &lt;{{ mail.fromAddress }}&gt;</span>
        </div>
        <div v-if="mail.replyTo" class="flex gap-2">
          <span class="w-14 flex-none text-faint">{{ $t('settings.previewMailReplyTo') }}</span>
          <span class="min-w-0 truncate">{{ mail.replyTo }}</span>
        </div>
        <div v-if="mail.bcc" class="flex gap-2">
          <span class="w-14 flex-none text-faint">{{ $t('settings.mailBccLabel') }}</span>
          <span class="min-w-0 truncate">{{ mail.bcc }}</span>
        </div>
      </div>

      <div class="space-y-1.5 py-3">
        <div class="h-1.5 w-full rounded-full bg-muted" />
        <div class="h-1.5 w-11/12 rounded-full bg-muted" />
        <div class="h-1.5 w-2/3 rounded-full bg-muted" />
      </div>

      <div
        v-if="mail.signature"
        class="border-t border-border pt-3 text-[10.5px] whitespace-pre-line text-muted-foreground"
      >{{ mail.signature }}</div>
    </div>
  </PreviewFrame>
</template>

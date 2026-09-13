<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Button } from '@/ui/button'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import DocumentEditor from '@/admin/components/DocumentEditor.vue'
import { serialize } from '@/admin/documents/blocks/serialize'
import { formatEuros } from '@/admin/lib/money'
import { monthDate } from '@/admin/lib/dates'
import type { EditorDocument, PaletteEntry } from '@/admin/documents/blocks/types'

// Full-screen compose view for Q-send: recipient, subject and message on the
// left, the email as it will actually render on the right — the split
// pixieset's own "Share Collection" view uses for a gallery reveal. The
// message field is Editor.js rather than a plain textarea (an empty palette:
// no block inserter, just the paragraph tool it ships with by default), so
// its content already serializes through the same blocks/serialize.ts every
// other mail body in the app goes through.
const props = withDefaults(
  defineProps<{
    quote: App.Quotes.Data.QuoteData
    studioName: string
    recipientName: string
    recipientEmail: string
    subject?: string
    message?: EditorDocument
    palette?: PaletteEntry[]
    mergeFields?: { path: string; sample: string }[]
  }>(),
  { palette: () => [], mergeFields: () => [] },
)

const emit = defineEmits<{ cancel: []; send: [{ subject: string; message: EditorDocument }] }>()

const { t } = useI18n()

const subject = ref(props.subject ?? t('quotes.title'))
const message = ref<EditorDocument>(
  props.message ?? {
    blocks: [
      { type: 'paragraph', data: { text: `Hallo ${props.recipientName},` } },
      { type: 'paragraph', data: { text: 'vielen Dank für euer Vertrauen — hier euer Angebot.' } },
    ],
  },
)

const messageHtml = computed(() => serialize(message.value, 'mail'))

function send() {
  emit('send', { subject: subject.value, message: message.value })
}
</script>

<template>
  <div class="flex h-screen flex-col">
    <header class="flex h-16 flex-none items-center gap-3.5 border-b border-border px-7">
      <Button variant="ghost" class="size-8 p-0" type="button" @click="emit('cancel')">
        <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6" /></svg>
      </Button>

      <h1 class="m-0 text-[17px] leading-[1.2] font-bold tracking-[-0.02em]">{{ $t('quotes.sendByMail') }}</h1>

      <div class="ml-auto flex items-center gap-2">
        <Button variant="ghost" type="button" @click="emit('cancel')">{{ $t('quotes.sendCancel') }}</Button>
        <Button variant="pill" type="button" @click="send">{{ $t('quotes.sendSubmit') }}</Button>
      </div>
    </header>

    <div class="flex min-h-0 flex-1">
      <div class="flex w-[420px] flex-none flex-col gap-5 overflow-y-auto border-r border-border p-6">
        <Field :label="$t('quotes.recipient')" dense>
          <div class="flex items-center gap-2.5 rounded-md border border-border px-3 py-2.5">
            <div class="flex size-[26px] flex-none items-center justify-center rounded-full bg-sunk text-[11px] font-semibold text-ink-2">
              {{ recipientName.slice(0, 1) }}
            </div>
            <div class="min-w-0 flex-1">
              <div class="text-[13px] font-semibold">{{ recipientName }}</div>
              <div class="text-[12.5px] text-ink-2">{{ recipientEmail }}</div>
            </div>
          </div>
        </Field>

        <Field v-slot="{ id }" :label="$t('quotes.sendSubject')" dense>
          <Input :id="id" v-model="subject" type="text" />
        </Field>

        <Field :label="$t('quotes.sendMessage')" dense class="min-h-[160px] flex-1">
          <div class="flex-1 overflow-y-auto rounded-md border border-border px-3 py-2.5">
            <DocumentEditor v-model="message" :palette="palette" :merge-fields="mergeFields" />
          </div>
        </Field>

        <div class="flex items-center gap-2 border-t border-border pt-3 text-[12px] text-faint">
          <svg class="size-[15px] flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="M3 7l9 6 9-6" /></svg>
          {{ $t('quotes.sendPdfNote') }}
        </div>
      </div>

      <div class="flex min-w-0 flex-1 flex-col p-6">
        <div class="flex flex-none items-center gap-2.5 border-b border-border pb-2.5">
          <span class="text-[10px] leading-none font-semibold tracking-[0.09em] text-faint uppercase">{{ $t('quotes.preview') }}</span>
          <span class="text-[12px] text-faint">{{ $t('quotes.sendPreviewHint') }}</span>
        </div>

        <div class="mt-4 flex flex-1 items-start justify-center overflow-auto rounded-md bg-sunk p-6">
          <div class="w-[520px] flex-none bg-background shadow-[0_18px_40px_-24px_rgba(20,16,14,.28)]">
            <div class="px-10 pt-8 text-center text-[19px] font-bold tracking-[-0.03em]">{{ studioName }}</div>

            <div class="px-10 pt-7 pb-9">
              <div class="prose-mail text-[14px] leading-[1.6]" v-html="messageHtml" />

              <div class="mt-6 overflow-hidden rounded-[10px] border border-border">
                <div class="flex items-baseline justify-between border-b border-border px-[18px] py-3.5">
                  <span class="text-[13px] font-semibold">{{ $t('quotes.title') }}</span>
                  <span v-if="quote.validUntil" class="text-[11.5px] text-faint">{{ $t('quotes.validUntilInline', { date: monthDate(quote.validUntil) }) }}</span>
                </div>
                <div class="px-[18px]">
                  <div
                    v-for="item in quote.items"
                    :key="item.id"
                    class="flex gap-2.5 border-b border-border py-[11px] text-[12.5px] last:border-b-0"
                  >
                    <span class="flex-1">{{ item.title }}</span>
                    <span class="font-semibold tabular-nums">{{ item.isIncluded ? $t('quotes.includedPrice') : formatEuros(item.netTotal) }}</span>
                  </div>
                  <div class="flex gap-2.5 py-3.5 text-[14px] font-semibold">
                    <span class="flex-1">{{ $t('quotes.total') }}</span>
                    <span class="tabular-nums">{{ formatEuros(quote.total) }}</span>
                  </div>
                </div>
              </div>

              <a href="#" class="mt-6 inline-block rounded-full bg-action px-[30px] py-3.5 text-[13px] font-semibold text-action-foreground">{{ $t('quotes.sendCta') }}</a>
            </div>

            <div class="border-t border-border px-10 pt-5.5 pb-7.5 text-center">
              <div class="text-[12px] font-semibold text-ink-2">{{ studioName }}</div>
              <div class="mt-1.5 text-[11.5px] text-faint">{{ $t('quotes.sendQuestions') }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.prose-mail :deep(p) {
  margin: 0 0 0.7em;
}
.prose-mail :deep(p:last-child) {
  margin-bottom: 0;
}
</style>

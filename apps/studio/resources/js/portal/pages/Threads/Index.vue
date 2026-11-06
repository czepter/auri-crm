<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalShell from '@/portal/components/PortalShell.vue'
import PortalSubhead from '@/portal/components/PortalSubhead.vue'
import PortalWrap from '@/portal/components/PortalWrap.vue'
import { dayDate } from '@/portal/lib/dates'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Textarea } from '@/ui/textarea'
import { NONE, fromOption, toOption } from '@/lib/select'

type Message = { id: string; author: string; fromStaff: boolean; body: string; sentAt: string }
type Thread = { id: string; subject: string; messages: Message[] }
type Row = { id: string; subject: string; reference: string | null; snippet: string | null; lastAt: string; waiting: boolean }

const props = defineProps<{
  contact: { name: string }
  studioName: string | null
  showSwitcher: boolean
  threads: Row[]
  openThread: Thread | null
  openReference: string | null
  bookings: { id: string; label: string }[]
  preselected: string | null
  // P-09: with messaging limited to bookings, "Allgemein" is not on offer.
  generalAllowed: boolean
}>()

// The compose form doubles as "new conversation" — the mailbox is empty on a
// contact's first visit, so it opens in that state rather than behind a
// button nobody would find.
const composing = ref(props.openThread === null)

/*
 * Below 980px the two panes are two screens: the list, then the one
 * conversation. Which of them is showing cannot be read off openThread —
 * MailboxController opens the newest one when nothing is named, so arriving
 * at /nachrichten would drop a phone straight into a conversation it never
 * asked for. The URL is what distinguishes the two: ?unterhaltung= is there
 * only when a row was tapped.
 */
const chosen = computed(() => usePage().url.includes('unterhaltung='))
const subscreen = computed(() => chosen.value || composing.value)

const reply = useForm({ body: '' })
const started = useForm({ subject: '', body: '', project: props.preselected ?? '' })

// resources/js/lib/select.ts — "Allgemein" is a re-selectable choice the
// server reads as the empty string, which reka-ui keeps for "nothing
// selected". Without generalAllowed there is no such item to stand in for, so
// the empty string stays and the trigger shows its placeholder.
const projectOption = computed({
  get: () => (props.generalAllowed ? toOption(started.project) : started.project),
  set: (value) => (started.project = fromOption(value)),
})

function send() {
  if (props.openThread === null || composing.value) {
    started.post('/nachrichten', { onSuccess: () => started.reset() })

    return
  }

  reply.post(`/nachrichten/${props.openThread.id}`, {
    preserveScroll: true,
    onSuccess: () => reply.reset(),
  })
}

/* The conversation scrolls inside its pane, so it does not land where a
   document would — at the top, on the oldest line. Both openings need it:
   switching threads, and a reply arriving in the one already open. */
const stream = ref<HTMLElement | null>(null)

watch(
  () => `${props.openThread?.id}:${props.openThread?.messages.length}`,
  async () => {
    await nextTick()

    if (stream.value) {
      stream.value.scrollTop = stream.value.scrollHeight
    }
  },
  { immediate: true },
)

function longDateTime(iso: string) {
  return new Date(iso).toLocaleString('de-DE', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}

const form = computed(() => (props.openThread === null || composing.value ? started : reply))

/* The line that names the booking a thread is about. Twice on the screen,
   once per list row and once over the conversation, and both read
   "Allgemein" when there is no booking to name — a valid state, not a
   missing value. */
const REF = 'mt-2 inline-flex items-center rounded-[var(--r-pill)] border border-border bg-sunk px-2.5 py-[3px] text-[11.5px] leading-none font-medium text-muted-foreground'
</script>

<template>
  <!-- A mailbox is not a document: the rail and the conversation scroll
       against each other and the composer stays where it was put. Below
       980px they become two screens rather than two panes — the list, then
       the one conversation — so the lock holds there too. -->
  <PortalShell fill :class="subscreen && 'max-[979px]:h-dvh max-[979px]:overflow-hidden'">
    <PortalHeader :subhead="subscreen" />

    <PortalSubhead
      v-if="subscreen"
      :back="'/nachrichten'"
      :back-label="$t('portal.backMessages')"
      :title="openThread && !composing ? openThread.subject : $t('portal.messagesNew')"
      :note="openThread && !composing ? (openReference ?? $t('portal.messagesNoBooking')) : null"
    />

    <PortalWrap class="flex min-h-0 flex-col pb-12 min-[980px]:pb-8" :class="subscreen && 'max-[979px]:pb-0'">
      <section class="flex-none pt-10 pb-8" :class="subscreen && 'max-[979px]:hidden'">
        <h1 class="animate-rise [animation-delay:140ms] motion-reduce:animate-none font-serif text-[clamp(30px,3.4vw,38px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">
          {{ $t('portal.messagesTitle') }}
        </h1>
      </section>

      <!-- The rail stands whether or not it has rows. An empty mailbox is a
           mailbox — the column that will hold the conversations, and the
           button that starts the first one. -->
      <div
        class="animate-rise [animation-delay:230ms] motion-reduce:animate-none grid min-h-0 items-start gap-12 min-[980px]:flex-1 min-[980px]:grid-cols-[340px_minmax(0,1fr)] min-[980px]:items-stretch min-[980px]:gap-8"
        :class="subscreen && 'max-[979px]:flex-1 max-[979px]:items-stretch'"
      >
        <aside class="flex min-h-0 flex-col" :class="subscreen && 'max-[979px]:hidden'">
          <Button class="mb-5 w-full flex-none justify-center" variant="portal-ghost" type="button" @click="composing = true">
            <svg class="fill-none stroke-current" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
            {{ $t('portal.messagesNew') }}
          </Button>

          <!-- -mx-3/px-3 gives each row its 12px of hover bleed back inside a
               scroller, which would otherwise turn it into sideways overflow. -->
          <div class="-mx-3 min-h-0 flex-1 overflow-y-auto border-t border-border px-3 max-[979px]:overflow-visible">
            <Link
              v-for="row in threads"
              :key="row.id"
              class="-mx-3 block rounded-[var(--r-sm)] border-b border-border px-3 py-4 text-inherit no-underline transition-colors duration-200 hover:bg-sunk"
              :class="openThread?.id === row.id && !composing && (chosen ? 'bg-sunk' : 'min-[980px]:bg-sunk')"
              :href="`/nachrichten?unterhaltung=${row.id}`"
              preserve-scroll
              @click="composing = false"
            >
              <span class="flex items-baseline gap-3">
                <i v-if="row.waiting" class="size-[7px] flex-none translate-y-[-1px] self-center rounded-full bg-action" />
                <b
                  class="flex-1 text-[14.5px] leading-[1.35] tracking-[-0.008em]"
                  :class="row.waiting ? 'font-semibold' : 'font-medium text-muted-foreground'"
                >{{ row.subject }}</b>
                <span class="text-[12px] whitespace-nowrap tabular-nums text-muted-foreground">{{ dayDate(row.lastAt) }}</span>
              </span>
              <span v-if="row.snippet" class="mt-[5px] line-clamp-1 block text-[13px] text-muted-foreground">{{ row.snippet }}</span>
              <span :class="[REF, openThread?.id === row.id && !composing && 'bg-background']">
                {{ row.reference ?? $t('portal.messagesNoBooking') }}
              </span>
            </Link>
          </div>
        </aside>

        <!-- One of two columns at 980px, the whole screen below it, where a
             frame around the screen encloses nothing and costs two gutters.
             PortalSubhead has said the subject and the booking by then, so
             the pane's own head steps aside with the frame. -->
        <div
          class="flex min-h-0 flex-col overflow-hidden rounded-md border border-border max-[979px]:rounded-none max-[979px]:border-0"
          :class="!subscreen && 'max-[979px]:hidden'"
        >
          <template v-if="openThread && !composing">
            <div class="flex-none border-b border-border px-6 py-5 max-[979px]:hidden">
              <h2 class="m-0 font-serif text-[26px] leading-[1.2] font-normal tracking-[-0.012em]">{{ openThread.subject }}</h2>
              <span :class="REF">{{ openReference ?? $t('portal.messagesNoBooking') }}</span>
            </div>

            <!-- One voice answering the other. Which is which is the side and
                 the ground, not a label on every line. -->
            <div ref="stream" class="flex min-h-0 flex-1 flex-col overflow-y-auto">
              <!-- mt-auto, not justify-end: a short exchange sits down by the
                   composer where it was written, and a long one still scrolls
                   from its first line instead of being clipped at the top. -->
              <div class="mt-auto grid gap-6 p-6 max-[979px]:px-0">
                <div
                  v-for="message in openThread.messages"
                  :key="message.id"
                  class="max-w-[78%] max-[979px]:max-w-[85%]"
                  :class="!message.fromStaff && 'justify-self-end text-right'"
                >
                  <div class="mb-1.5 text-[12px] tabular-nums text-muted-foreground">{{ message.fromStaff ? (studioName ?? message.author) : message.author }} · {{ longDateTime(message.sentAt) }}</div>
                  <div
                    class="rounded-md px-6 py-4 text-left text-[14.5px] leading-[1.6] whitespace-pre-wrap"
                    :class="message.fromStaff ? 'rounded-tl-[4px] bg-sunk' : 'rounded-tr-[4px] bg-foreground text-background'"
                  >
                    {{ message.body }}
                  </div>
                </div>
              </div>
            </div>
          </template>

          <!-- Subject and booking stand above the field, never below it: the
               arrow is the last thing on the screen, and anything under it
               reads as a step still to come. -->
          <form
            class="mt-auto flex-none border-t border-border bg-background first:border-t-0"
            :class="[openThread && !composing ? 'px-6 py-5' : 'p-6 max-[979px]:mt-0', 'max-[979px]:px-0']"
            @submit.prevent="send"
          >
            <template v-if="!openThread || composing">
              <Input
                v-model="started.subject"
                class="mb-3.5 rounded-[var(--r-sm)] border-input px-[13px] py-[11px] text-[14.5px] leading-[1.5]"
                type="text"
                :placeholder="$t('portal.messagesSubjectPlaceholder')"
              />
              <p v-if="started.errors.subject" class="mb-3.5 text-[12.5px] leading-[1.45] text-destructive">{{ started.errors.subject }}</p>

              <!-- Optional, never required. A client writing out of a booking
                   arrives with it preselected; everyone else sends "Allgemein"
                   and the studio files it later. -->
              <label v-if="bookings.length" class="mb-3.5 flex items-center gap-2.5 text-[13px] text-muted-foreground">
                {{ $t('portal.messagesAbout') }}
                <Select v-model="projectOption">
                  <SelectTrigger><SelectValue :placeholder="$t('portal.messagesNoBooking')" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-if="generalAllowed" :value="NONE">{{ $t('portal.messagesNoBooking') }}</SelectItem>
                    <SelectItem v-for="booking in bookings" :key="booking.id" :value="booking.id">
                      {{ booking.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </label>
            </template>

            <div class="relative">
              <Textarea
                v-model="form.body"
                class="max-h-[40vh] min-h-12 resize-none rounded-[20px] border-input py-[13px] pr-14 pl-4 text-[14.5px] leading-[1.5] field-sizing-content"
                :placeholder="$t('portal.messagesBodyPlaceholder')"
              />
              <!-- The mark carries the whole button, so it carries the name
                   too — nothing else in here says "send" out loud. -->
              <Button
                class="absolute right-1.5 bottom-1.5 size-9 justify-center p-0"
                variant="portal"
                type="submit"
                :disabled="form.processing || form.body.trim() === ''"
                :aria-label="$t('portal.messagesSend')"
              >
                <svg class="fill-none stroke-current" viewBox="0 0 24 24"><path d="M12 19V5M5 12l7-7 7 7" /></svg>
              </Button>
            </div>
            <p v-if="form.errors.body" class="mt-2 text-[12.5px] leading-[1.45] text-destructive">{{ form.errors.body }}</p>
          </form>
        </div>
      </div>
    </PortalWrap>

    <!-- A conversation holds the viewport below 980px; the studio's address
         under a pinned composer would be a second bottom edge. -->
    <PortalFooter class="min-[980px]:pt-5 min-[980px]:pb-5" :class="subscreen && 'max-[979px]:hidden'" />
  </PortalShell>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalWrap from '@/portal/components/PortalWrap.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Textarea } from '@/ui/textarea'

type Message = { id: string; author: string; fromStaff: boolean; body: string; sentAt: string }
type Thread = { id: string; subject: string; messages: Message[] }
type Row = { id: string; subject: string; reference: string | null; snippet: string | null; lastAt: string }

const props = defineProps<{
  contact: { name: string }
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

const reply = useForm({ body: '' })
const started = useForm({ subject: '', body: '', project: props.preselected ?? '' })

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

function longDateTime(iso: string) {
  return new Date(iso).toLocaleString('de-DE', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}

function shortDate(iso: string) {
  return new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' })
}

const form = computed(() => (props.openThread === null || composing.value ? started : reply))

/* portal.css `.mbox .ref` — the line that names the booking a thread is
   about. Twice on the screen, once per list row and once over the
   conversation, and both take .ref.none when there is no booking to name. */
</script>

<template>
  <PortalHeader :contact="contact" :show-switcher="showSwitcher" />

  <PortalWrap wide>
    <h1>{{ $t('portal.messagesTitle') }}</h1>

    <!-- No list until there is something to list: an empty 300px rail beside
         a lone compose box is a hole, not a layout. -->
    <div
      class="mt-8 grid items-start gap-11"
      :class="threads.length ? 'min-[980px]:grid-cols-[300px_minmax(0,1fr)]' : 'max-w-[620px]'"
    >
      <aside v-if="threads.length">
        <div class="border-t border-border">
          <Link
            v-for="row in threads"
            :key="row.id"
            class="block border-b border-border py-5 text-foreground no-underline hover:bg-muted"
            :class="
              openThread?.id === row.id && !composing && 'pl-3.5 shadow-[inset_2px_0_0_var(--accent)]'
            "
            :href="`/nachrichten?faden=${row.id}`"
            preserve-scroll
            @click="composing = false"
          >
            <span class="flex items-baseline gap-3.5">
              <b class="flex-1 text-[14px] leading-[1.35] font-semibold">{{ row.subject }}</b>
              <span class="text-[11.5px] leading-none font-normal whitespace-nowrap tabular-nums text-faint">{{ shortDate(row.lastAt) }}</span>
            </span>
            <div v-if="row.snippet" class="mt-[3px] line-clamp-2 text-[13px] leading-[1.5] font-normal text-muted-foreground">{{ row.snippet }}</div>
            <span class="mt-1.5 inline-block text-[11px] leading-none font-medium tracking-[0.04em] text-muted-foreground" :class="!row.reference && 'text-faint'">
              {{ row.reference ?? $t('portal.messagesNoBooking') }}
            </span>
          </Link>
        </div>

        <Button variant="ghost" type="button" class="mt-5" @click="composing = true">
          {{ $t('portal.messagesNew') }}
        </Button>
      </aside>

      <div>
        <template v-if="openThread && !composing">
          <div class="border-b border-border pb-5">
            <h2 class="m-0 text-[20px] leading-[1.3] font-bold tracking-[-0.02em]">{{ openThread.subject }}</h2>
            <span class="mt-1.5 inline-block text-[11px] leading-none font-medium tracking-[0.04em] text-muted-foreground" :class="!openReference && 'text-faint'">
              {{ openReference ?? $t('portal.messagesNoBooking') }}
            </span>
          </div>

          <div class="flex flex-col gap-8 py-8">
            <div v-for="message in openThread.messages" :key="message.id">
              <div class="my-1.5 flex flex-col gap-[3px] text-[11.5px] leading-none font-medium tabular-nums text-faint">{{ message.author }} · {{ longDateTime(message.sentAt) }}</div>
              <div
                class="text-[14.5px] leading-[1.65] font-normal whitespace-pre-wrap"
                :class="!message.fromStaff && 'border-l-2 border-border pl-5'"
              >
                {{ message.body }}
              </div>
            </div>
          </div>
        </template>

        <form class="rounded-md border border-border p-5 [&_textarea]:min-h-24" @submit.prevent="send">
          <template v-if="!openThread || composing">
            <Input
              v-model="started.subject"
              type="text"
              class="mb-3.5"
              :placeholder="$t('portal.messagesSubjectPlaceholder')"
            />
            <p v-if="started.errors.subject" class="text-[11.5px] leading-[1.45] text-destructive">{{ started.errors.subject }}</p>
          </template>

          <Textarea v-model="form.body" :placeholder="$t('portal.messagesBodyPlaceholder')" />
          <p v-if="form.errors.body" class="text-[11.5px] leading-[1.45] text-destructive">{{ form.errors.body }}</p>

          <div class="mt-3.5 flex flex-wrap items-center gap-5 [&_[data-slot=button]]:ml-auto">
            <!-- Optional, never required. A client writing out of a booking
                 arrives with it preselected; everyone else sends "Allgemein"
                 and the studio files it later. -->
            <label v-if="(!openThread || composing) && bookings.length" class="flex items-center gap-3.5 text-[12px] leading-none font-medium text-muted-foreground">
              {{ $t('portal.messagesAbout') }}
              <Select v-model="started.project">
                <SelectTrigger><SelectValue :placeholder="$t('portal.messagesNoBooking')" /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-if="generalAllowed" value="">{{ $t('portal.messagesNoBooking') }}</SelectItem>
                  <SelectItem v-for="booking in bookings" :key="booking.id" :value="booking.id">
                    {{ booking.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </label>

            <Button type="submit" :disabled="form.processing">{{ $t('portal.messagesSend') }}</Button>
          </div>
        </form>
      </div>
    </div>
  </PortalWrap>

  <PortalFooter />
</template>

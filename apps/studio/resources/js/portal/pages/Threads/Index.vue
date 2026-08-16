<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
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
</script>

<template>
  <PortalHeader :contact="contact" :show-switcher="showSwitcher" />

  <main class="pwrap wide">
    <h1>{{ $t('portal.messagesTitle') }}</h1>

    <!-- No list until there is something to list: an empty 300px rail beside
         a lone compose box is a hole, not a layout. -->
    <div class="mbox" :class="{ single: !threads.length }" style="margin-top: var(--s5)">
      <aside v-if="threads.length">
        <div class="threads">
          <Link
            v-for="row in threads"
            :key="row.id"
            :class="{ on: openThread?.id === row.id && !composing }"
            :href="`/nachrichten?faden=${row.id}`"
            preserve-scroll
            @click="composing = false"
          >
            <span class="line">
              <b>{{ row.subject }}</b>
              <span class="ago">{{ shortDate(row.lastAt) }}</span>
            </span>
            <div v-if="row.snippet" class="snippet">{{ row.snippet }}</div>
            <span class="ref" :class="{ none: !row.reference }">
              {{ row.reference ?? $t('portal.messagesNoBooking') }}
            </span>
          </Link>
        </div>

        <Button variant="ghost" type="button" style="margin-top: var(--s4)" @click="composing = true">
          {{ $t('portal.messagesNew') }}
        </Button>
      </aside>

      <div>
        <template v-if="openThread && !composing">
          <div class="convohead">
            <h2>{{ openThread.subject }}</h2>
            <span class="ref" :class="{ none: !openReference }">
              {{ openReference ?? $t('portal.messagesNoBooking') }}
            </span>
          </div>

          <div class="msgs">
            <div
              v-for="message in openThread.messages"
              :key="message.id"
              class="mailmsg"
              :class="{ mine: !message.fromStaff }"
            >
              <div class="meta">{{ message.author }} · {{ longDateTime(message.sentAt) }}</div>
              <div class="text">{{ message.body }}</div>
            </div>
          </div>
        </template>

        <form class="compose" @submit.prevent="send">
          <template v-if="!openThread || composing">
            <Input
              v-model="started.subject"
              type="text"
              style="margin-bottom: var(--s3)"
              :placeholder="$t('portal.messagesSubjectPlaceholder')"
            />
            <p v-if="started.errors.subject" class="err">{{ started.errors.subject }}</p>
          </template>

          <Textarea v-model="form.body" :placeholder="$t('portal.messagesBodyPlaceholder')" />
          <p v-if="form.errors.body" class="err">{{ form.errors.body }}</p>

          <div class="composefoot">
            <!-- Optional, never required. A client writing out of a booking
                 arrives with it preselected; everyone else sends "Allgemein"
                 and the studio files it later. -->
            <label v-if="(!openThread || composing) && bookings.length" class="about">
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
  </main>

  <PortalFooter />
</template>

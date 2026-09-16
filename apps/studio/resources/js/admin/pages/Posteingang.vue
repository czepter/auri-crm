<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import { Button } from '@/ui/button'
import { Field } from '@/ui/field'
import Rail from '@/ui/page/Rail.vue'
import RailItem from '@/ui/page/RailItem.vue'
import RailSection from '@/ui/page/RailSection.vue'
import { Queue, QueueGroup, QueueItem } from '@/ui/queue'
import { Textarea } from '@/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/ui/alert-dialog'

type Row = {
  id: string
  who: string
  project: string | null
  subject: string
  snippet: string | null
  waitingDays: number | null
  origin: string
}

const props = defineProps<{
  groups: { key: string; rows: Row[] }[]
  open: {
    id: string
    subject: string
    state: string
    projectId: string | null
    projectName: string | null
    shootDate: string | null
    origin: string
    people: string[]
    messages: { id: string; who: string; own: boolean; body: string; at: string }[]
  } | null
  configured: boolean
  catchAll: string | null
  canRotate: boolean
  pending: {
    id: string
    from: string
    to: string
    subject: string | null
    body: string
    state: string
    stateLabel: string
    reason: string | null
    receivedAt: string
    projectName: string | null
  }[]
  threads: { id: string; label: string }[]
  unfiled: { id: string; subject: string; snippet: string | null; startedAt: string }[]
  drafts: { id: string; subject: string; body: string; projectName: string | null; to: string }[]
  projects: { id: string; label: string }[]
}>()

// One open picker at a time, keyed by the row — the same shape the payments
// card uses for marking an installment paid.
const attaching = ref<string | null>(null)
const chosenThread = ref<string | null>(null)

function attach(id: string) {
  if (!chosenThread.value) return

  router.post(`/posteingang/${id}/zuordnen`, { thread: chosenThread.value }, {
    preserveScroll: true,
    onSuccess: () => {
      attaching.value = null
      chosenThread.value = null
    },
  })
}

// P-04 (#223): the same one-open-picker shape, for a client thread that
// names no booking yet.
const filing = ref<string | null>(null)
const chosenProject = ref<string | null>(null)

function file(id: string) {
  if (!chosenProject.value) return

  router.post(`/posteingang/unterhaltung/${id}/zuordnen`, { project: chosenProject.value }, {
    preserveScroll: true,
    onSuccess: () => {
      filing.value = null
      chosenProject.value = null
    },
  })
}

// Long enough to notice. Below that the wait is the normal course of a
// conversation and colouring it would make every thread look late.
const NOTICEABLE_DAYS = 3

const reply = useForm({ body: '' })

function send() {
  if (!props.open) return

  reply.post(`/posteingang/unterhaltung/${props.open.id}/antwort`, {
    preserveScroll: true,
    onSuccess: () => reply.reset(),
  })
}

function close(id: string) {
  router.post(`/posteingang/unterhaltung/${id}/erledigt`, {}, { preserveScroll: true })
}

function show(id: string) {
  router.get('/posteingang', { unterhaltung: id }, { preserveScroll: true, preserveState: true })
}

function discard(id: string) {
  router.post(`/posteingang/entwuerfe/${id}/verwerfen`, {}, { preserveScroll: true })
}

function rotate() {
  router.post('/posteingang/adresse-erneuern', {}, { preserveScroll: true })
}

const threaded = computed(() => props.groups.some((group) => group.rows.length > 0))

function receivedAt(iso: string) {
  return new Date(iso).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <AdminLayout active="posteingang">
    <!-- C-14 (#250): conversations, grouped by working state. A queue on the
         left, the thread beside it, its trace on the right — the three columns
         of mockups/redesign/crm-posteingang.html, each filling the height. -->
    <Page>
      <template v-if="threaded" #left>
        <Queue>
          <template v-for="group in groups" :key="group.key">
            <QueueGroup :title="$t(`inbox.group.${group.key}`)" :count="group.rows.length" />

            <!-- The project name under the sender, not the mail address: which
                 booking a message is about is what one triages on. -->
            <QueueItem
              v-for="row in group.rows"
              :key="row.id"
              :name="row.who"
              :meta="row.waitingDays !== null && row.waitingDays >= NOTICEABLE_DAYS
                ? $t('inbox.waiting', row.waitingDays)
                : null"
              :alert="true"
              :title="row.project ?? $t('inbox.general')"
              :snippet="row.snippet"
              :active="open?.id === row.id"
              @select="show(row.id)"
            />
          </template>
        </Queue>
      </template>

      <main data-tokens class="min-w-0 flex-1 overflow-y-auto px-[26px] pt-4 pb-5">
        <template v-if="open">
          <div class="mt-[5px] flex items-start gap-3.5">
            <div>
              <div class="text-[11.5px] leading-[1.47] text-ink-2">{{ $t(`inbox.origin.${open.origin}`) }}</div>
              <h1 class="m-0 mt-0.5 text-[25px] leading-[1.08] font-semibold tracking-[-0.032em]">{{ open.subject }}</h1>
            </div>
            <Button class="ml-auto" variant="pill-muted" type="button" @click="close(open.id)">
              {{ open.state === 'erledigt' ? $t('inbox.reopen') : $t('inbox.done') }}
            </Button>
          </div>

          <div v-for="message in open.messages" :key="message.id" class="py-3 shadow-[inset_0_1px_0_var(--rule)]" :class="
              message.own &&
                'pl-3.5 shadow-[inset_0_1px_0_var(--rule),inset_2px_0_0_var(--sunk-2)]'
            ">
            <div class="flex items-baseline gap-[9px]">
              <span class="text-[13px] leading-[1.47] font-semibold tracking-[-0.012em]">{{ message.who }}</span>
              <span class="ml-auto text-[11.5px] leading-[1.47] tabular-nums text-ink-2">{{ receivedAt(message.at) }}</span>
            </div>
            <div class="mt-[5px] max-w-[68ch] text-[13.5px] leading-[1.55]">{{ message.body }}</div>
          </div>

          <form class="mt-4 pt-1 pb-0.5" @submit.prevent="send">
            <Field v-slot="{ id }" dense :label="$t('inbox.reply')">
              <Textarea :id="id" v-model="reply.body" rows="3" class="mb-3" />
            </Field>
            <div class="mt-2.5 flex items-center gap-3">
              <Button variant="pill" class="ml-auto" type="submit" :disabled="reply.processing || !reply.body">
                {{ $t('inbox.send') }}
              </Button>
            </div>
          </form>
        </template>

        <section :class="open && 'mt-8'">
          <h2 class="m-0 mb-[9px] text-[10.5px] leading-none font-semibold tracking-[0.07em] uppercase text-ink-2">{{ $t('inbox.addressTitle') }}</h2>

          <p v-if="!configured" class="m-0 text-[12.5px] leading-[1.47] text-ink-2">{{ $t('inbox.notConfigured') }}</p>
          <template v-else>
            <p v-if="catchAll" class="m-0 text-[13.5px] leading-[1.47] tabular-nums"><code>{{ catchAll }}</code></p>
            <p v-else class="m-0 text-[12.5px] leading-[1.47] text-ink-2">{{ $t('inbox.noSecret') }}</p>

            <!-- Rotation is the Inhaber's alone, and the dialog says what it
                 costs: every address already handed out stops working, including
                 the Reply-To on mail sitting in clients' inboxes. -->
            <AlertDialog v-if="canRotate">
              <AlertDialogTrigger as-child>
                <Button class="mt-2.5" type="button" variant="pill-muted">{{ $t('inbox.rotate') }}</Button>
              </AlertDialogTrigger>
              <AlertDialogContent>
                <AlertDialogHeader>
                  <AlertDialogTitle>{{ $t('inbox.rotateConfirm') }}</AlertDialogTitle>
                </AlertDialogHeader>
                <p>{{ $t('inbox.rotateConsequence') }}</p>
                <AlertDialogFooter>
                  <AlertDialogAction tone="destructive" @click="rotate">{{ $t('inbox.rotate') }}</AlertDialogAction>
                  <AlertDialogCancel>{{ $t('common.cancel') }}</AlertDialogCancel>
                </AlertDialogFooter>
              </AlertDialogContent>
            </AlertDialog>
          </template>
        </section>

        <section v-if="drafts.length" class="mt-8">
          <h2 class="m-0 mb-[9px] text-[10.5px] leading-none font-semibold tracking-[0.07em] uppercase text-ink-2">{{ $t('inbox.draftsTitle') }}</h2>

          <div v-for="draft in drafts" :key="draft.id" class="py-3 shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none">
            <div class="flex items-baseline gap-[9px]">
              <span class="text-[13px] leading-[1.47] font-semibold tracking-[-0.012em]">{{ draft.subject }}</span>
              <span class="ml-auto text-[11.5px] leading-[1.47] text-ink-2">{{ [draft.projectName, draft.to].filter(Boolean).join(' · ') }}</span>
            </div>
            <div class="mt-[5px] line-clamp-3 max-w-[68ch] text-[13.5px] leading-[1.55] whitespace-pre-line text-ink-2">{{ draft.body }}</div>

            <div class="mt-2.5 flex items-center gap-2">
              <Button :as="Link" variant="pill" :href="`/posteingang/entwuerfe/${draft.id}/senden`">{{ $t('inbox.send') }}</Button>
              <Button variant="ghost" type="button" @click="discard(draft.id)">{{ $t('inbox.discard') }}</Button>
            </div>
          </div>
        </section>

        <!-- P-04 (#223): a conversation the client started without naming a
             booking. Nothing else in the admin lists it — every other thread is
             reachable through its project screen. -->
        <section v-if="unfiled.length" class="mt-8">
          <h2 class="m-0 mb-[9px] text-[10.5px] leading-none font-semibold tracking-[0.07em] uppercase text-ink-2">{{ $t('inbox.unfiledTitle') }}</h2>

          <div v-for="row in unfiled" :key="row.id" class="py-3 shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none">
            <div class="flex items-baseline gap-[9px]">
              <span class="text-[13px] leading-[1.47] font-semibold tracking-[-0.012em]">{{ row.subject }}</span>
              <span class="ml-auto text-[11.5px] leading-[1.47] tabular-nums text-ink-2">{{ receivedAt(row.startedAt) }}</span>
            </div>
            <div v-if="row.snippet" class="mt-[5px] max-w-[68ch] text-[13.5px] leading-[1.55]">{{ row.snippet }}</div>

            <div class="mt-2.5 flex items-center gap-2">
              <template v-if="filing === row.id">
                <Select v-model="chosenProject">
                  <SelectTrigger size="sm" class="w-64"><SelectValue :placeholder="$t('inbox.chooseProject')" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="project in projects" :key="project.id" :value="project.id">
                      {{ project.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <Button variant="pill" type="button" :disabled="!chosenProject" @click="file(row.id)">
                  {{ $t('inbox.file') }}
                </Button>
                <Button variant="ghost" type="button" @click="filing = null">{{ $t('common.cancel') }}</Button>
              </template>
              <Button v-else variant="pill-muted" type="button" @click="filing = row.id">
                {{ $t('inbox.file') }}
              </Button>
            </div>
          </div>
        </section>

        <section v-if="pending.length" class="mt-8">
          <h2 class="m-0 mb-[9px] text-[10.5px] leading-none font-semibold tracking-[0.07em] uppercase text-ink-2">{{ $t('inbox.triageTitle') }}</h2>

          <div v-for="row in pending" :key="row.id" class="py-3 shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none">
            <div class="flex items-baseline gap-[9px]">
              <span class="text-[13px] leading-[1.47] font-semibold tracking-[-0.012em]">{{ row.from }}</span>
              <span class="text-[11.5px] leading-[1.47] text-ink-2">
                {{ row.stateLabel }}<template v-if="row.reason"> · {{ $t(`inbox.reason.${row.reason}`) }}</template>
              </span>
              <span class="ml-auto text-[11.5px] leading-[1.47] tabular-nums text-ink-2">{{ receivedAt(row.receivedAt) }}</span>
            </div>
            <div v-if="row.subject" class="mt-[5px] text-[13px] leading-[1.47]">{{ row.subject }}</div>
            <div class="mt-[5px] max-w-[68ch] text-[13.5px] leading-[1.55] text-ink-2">{{ row.body }}</div>

            <!-- Quarantined mail is shown and never attachable: it failed
                 DMARC, so the sender is not established, and attaching it
                 would put an unverified stranger into a client conversation
                 by hand instead of automatically. -->
            <div v-if="row.state !== 'quarantined'" class="mt-2.5 flex items-center gap-2">
              <template v-if="attaching === row.id">
                <Select v-model="chosenThread">
                  <SelectTrigger size="sm" class="w-64"><SelectValue :placeholder="$t('inbox.chooseThread')" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="thread in threads" :key="thread.id" :value="thread.id">
                      {{ thread.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <Button variant="pill" type="button" :disabled="!chosenThread" @click="attach(row.id)">
                  {{ $t('inbox.attach') }}
                </Button>
                <Button variant="ghost" type="button" @click="attaching = null">{{ $t('common.cancel') }}</Button>
              </template>
              <Button v-else variant="pill-muted" type="button" @click="attaching = row.id">
                {{ $t('inbox.attach') }}
              </Button>
            </div>
          </div>
        </section>
      </main>

      <template v-if="open" #right>
        <Rail data-tokens>
          <RailSection :title="$t('inbox.people')">
            <div
              v-for="person in open.people"
              :key="person"
              class="flex items-center gap-2 py-[5px] text-[12.5px] leading-[1.47]"
            >{{ person }}</div>
          </RailSection>

          <RailSection v-if="open.projectName" :title="$t('inbox.project')">
            <RailItem
              :title="open.projectName"
              :href="`/auftraege/${open.projectId}`"
              :meta="open.shootDate ? receivedAt(open.shootDate) : null"
            />
          </RailSection>
        </Rail>
      </template>
    </Page>
  </AdminLayout>
</template>

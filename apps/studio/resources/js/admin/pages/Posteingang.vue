<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import { Button } from '@/ui/button'
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

  router.post(`/posteingang/faden/${id}/zuordnen`, { project: chosenProject.value }, {
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

  reply.post(`/posteingang/faden/${props.open.id}/antwort`, {
    preserveScroll: true,
    onSuccess: () => reply.reset(),
  })
}

function close(id: string) {
  router.post(`/posteingang/faden/${id}/erledigt`, {}, { preserveScroll: true })
}

function show(id: string) {
  router.get('/posteingang', { faden: id }, { preserveScroll: true, preserveState: true })
}

function rotate() {
  router.post('/posteingang/adresse-erneuern', {}, { preserveScroll: true })
}

const threaded = computed(() => props.groups.some((group) => group.rows.length > 0))

/* crm.css .queue — the conversations, grouped by working state. */
const QUEUE = 'w-58 flex-none overflow-y-auto bg-sunk pt-3.5 max-[1080px]:w-[200px]'
const QUEUE_H2 = 'm-0 flex items-baseline px-3.5 pb-2 [a+&]:pt-5'
const QUEUE_B = 'text-[12.5px] leading-none font-semibold tracking-[-0.012em]'
const QUEUE_N = 'ml-auto text-[11px] leading-[1.47] tabular-nums text-ink-2'
const QUEUE_A = 'block px-3.5 py-[9px] text-inherit no-underline shadow-[inset_0_1px_0_#ECECEE]'
const QUEUE_NM = 'block text-[12.5px] leading-[1.47] font-semibold tracking-[-0.012em]'
const QUEUE_MT = 'mt-px block text-[11.5px] leading-[1.47]'

/* crm.css .detail — the thread itself. */
const DETAIL = 'min-w-0 flex-1 overflow-y-auto px-[26px] pt-4 pb-5'
const CRUMB = 'text-[11.5px] leading-[1.47] text-ink-2'
const TITLE = 'mt-[5px] flex items-start gap-3.5'
const H1 = 'm-0 mt-0.5 text-[25px] leading-[1.08] font-semibold tracking-[-0.032em]'
const ACTS = 'ml-auto flex items-center gap-2'

/* crm.css .msg — one message inside the conversation. */
const MSG = 'py-3 shadow-[inset_0_1px_0_var(--rule)]'
const MSG_OWN = 'pl-3.5 shadow-[inset_0_1px_0_var(--rule),inset_2px_0_0_var(--sunk-2)]'
const MSG_HEAD = 'flex items-baseline gap-[9px]'
const MSG_N = 'text-[13px] leading-[1.47] font-semibold tracking-[-0.012em]'
const MSG_T = 'ml-auto text-[11.5px] leading-[1.47] tabular-nums text-ink-2'
const MSG_BODY = 'mt-[5px] max-w-[68ch] text-[13.5px] leading-[1.55]'

/* crm.css .composer / .field / .btn / .btn-2 — the reply, written in place. */
const COMPOSER = 'mt-4 pt-1 pb-0.5'
const FIELD = 'flex flex-col gap-1 [&>span]:pl-0.5 [&>span]:text-[10.5px] [&>span]:leading-none [&>span]:font-normal [&>span]:tracking-[0.05em] [&>span]:uppercase [&>span]:text-ink-2'
const FOOT = 'mt-2.5 flex items-center gap-3'
const BTN_BASE = 'inline-flex cursor-pointer items-center gap-1.5 rounded-[var(--r-pill)] border-0 px-3.5 py-1.5 text-[12.5px] leading-[1.47] font-medium tracking-[-0.01em] whitespace-nowrap no-underline transition-[filter,background-color] duration-[.12s] ease-out'
const BTN = `${BTN_BASE} bg-action text-white hover:brightness-[1.08]`
const BTN_2 = `${BTN_BASE} bg-sunk-2 text-foreground hover:bg-[#E9E9EC]`

/* crm.css .rail — who is on the thread, and what it belongs to. */
const RAIL = 'w-70 flex-none overflow-y-auto bg-sunk px-5 py-4 max-[1080px]:hidden'
const RAIL_H2 = 'm-0 mb-[9px] text-[10.5px] leading-none font-semibold tracking-[0.07em] uppercase text-ink-2'
const RAIL_P = 'flex items-center gap-2 py-[5px] text-[12.5px] leading-[1.47]'
const RAIL_ITEM = 'py-2 shadow-[inset_0_1px_0_#ECECEE]'
const RAIL_N = 'text-[12.5px] leading-[1.47] font-semibold tracking-[-0.012em]'
const RAIL_M = 'mt-px text-[11.5px] leading-[1.47] text-ink-2'

function receivedAt(iso: string) {
  return new Date(iso).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <AdminLayout active="posteingang">
    <div class="hrow">
      <div>
        <div class="kick">{{ $t('inbox.kicker') }}</div>
        <h1>{{ $t('inbox.title') }}</h1>
      </div>
    </div>

    <!-- C-14 (#250): conversations, grouped by working state. A queue on the
         left, the thread on the right, its trace beside it. -->
    <div v-if="threaded" data-tokens="crm" style="display: flex; min-height: 60vh; margin-top: var(--s5)">
      <div :class="QUEUE">
        <template v-for="group in groups" :key="group.key">
          <h2 v-if="group.rows.length" :class="QUEUE_H2">
            <b :class="QUEUE_B">{{ $t(`inbox.group.${group.key}`) }}</b>
            <span :class="QUEUE_N">{{ group.rows.length }}</span>
          </h2>

          <a
            v-for="row in group.rows"
            :key="row.id"
            href="#"
            :class="[QUEUE_A, open?.id === row.id && 'bg-paper']"
            @click.prevent="show(row.id)"
          >
            <span :class="QUEUE_NM">{{ row.who }}</span>
            <!-- The project name under the sender, not the mail address:
                 which booking a message is about is what one triages on. -->
            <span :class="[QUEUE_MT, 'text-ink-2']">{{ row.project ?? $t('inbox.general') }}</span>
            <span
              v-if="row.waitingDays !== null && row.waitingDays >= NOTICEABLE_DAYS"
              :class="[QUEUE_MT, 'text-action']"
            >{{ $t('inbox.waiting', row.waitingDays) }}</span>
          </a>
        </template>
      </div>

      <div :class="DETAIL">
        <template v-if="open">
          <div :class="TITLE">
            <div>
              <div :class="CRUMB">{{ $t(`inbox.origin.${open.origin}`) }}</div>
              <h1 :class="H1">{{ open.subject }}</h1>
            </div>
            <div :class="ACTS">
              <button :class="BTN_2" type="button" @click="close(open.id)">
                {{ open.state === 'erledigt' ? $t('inbox.reopen') : $t('inbox.done') }}
              </button>
            </div>
          </div>

          <div v-for="message in open.messages" :key="message.id" :class="[MSG, message.own && MSG_OWN]">
            <div :class="MSG_HEAD">
              <span :class="MSG_N">{{ message.who }}</span>
              <span :class="MSG_T">{{ receivedAt(message.at) }}</span>
            </div>
            <div :class="MSG_BODY">{{ message.body }}</div>
          </div>

          <form :class="COMPOSER" @submit.prevent="send">
            <div :class="FIELD">
              <span>{{ $t('inbox.reply') }}</span>
              <textarea v-model="reply.body" rows="3" class="mb-3" />
            </div>
            <div :class="FOOT">
              <button :class="[BTN, 'ml-auto']" type="submit" :disabled="reply.processing || !reply.body">
                {{ $t('inbox.send') }}
              </button>
            </div>
          </form>
        </template>
      </div>

      <aside v-if="open" :class="RAIL">
        <h2 :class="RAIL_H2">{{ $t('inbox.people') }}</h2>
        <div v-for="person in open.people" :key="person" :class="RAIL_P">{{ person }}</div>

        <template v-if="open.projectName">
          <h2 :class="RAIL_H2">{{ $t('inbox.project') }}</h2>
          <div :class="RAIL_ITEM">
            <Link :class="RAIL_N" :href="`/auftraege/${open.projectId}`">{{ open.projectName }}</Link>
            <div v-if="open.shootDate" :class="RAIL_M">{{ receivedAt(open.shootDate) }}</div>
          </div>
        </template>
      </aside>
    </div>

    <div class="card static" style="margin-top: var(--s5)">
      <div class="cardhead"><span class="kick">{{ $t('inbox.addressTitle') }}</span></div>

      <p v-if="!configured" class="hint">{{ $t('inbox.notConfigured') }}</p>
      <template v-else>
        <p v-if="catchAll"><code>{{ catchAll }}</code></p>
        <p v-else class="hint">{{ $t('inbox.noSecret') }}</p>

        <!-- Rotation is the Inhaber's alone, and the dialog says what it
             costs: every address already handed out stops working, including
             the Reply-To on mail sitting in clients' inboxes. -->
        <AlertDialog v-if="canRotate">
          <AlertDialogTrigger as-child>
            <Button type="button" variant="outline">{{ $t('inbox.rotate') }}</Button>
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
    </div>

    <!-- P-04 (#223): a conversation the client started without naming a
         booking. Nothing else in the admin lists it — every other thread is
         reachable through its project screen. -->
    <div v-if="unfiled.length" class="card static" style="margin-top: var(--s4)">
      <div class="cardhead"><span class="kick">{{ $t('inbox.unfiledTitle') }}</span></div>

      <div class="msg" style="gap: var(--s4); margin-top: var(--s3)">
        <div v-for="row in unfiled" :key="row.id" >
          <div>
            <div>
              {{ row.subject }}
              <em>{{ receivedAt(row.startedAt) }}</em>
            </div>
            <div v-if="row.snippet" class="msgbody" style="-webkit-line-clamp: unset">{{ row.snippet }}</div>

            <div class="mt-2.5">
              <template v-if="filing === row.id">
                <Select v-model="chosenProject">
                  <SelectTrigger><SelectValue :placeholder="$t('inbox.chooseProject')" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="project in projects" :key="project.id" :value="project.id">
                      {{ project.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <div class="formfoot" style="margin-top: var(--s3)">
                  <Button type="button" :disabled="!chosenProject" @click="file(row.id)">
                    {{ $t('inbox.file') }}
                  </Button>
                  <Button type="button" variant="ghost" @click="filing = null">{{ $t('common.cancel') }}</Button>
                </div>
              </template>
              <Button v-else type="button" variant="ghost" @click="filing = row.id">
                {{ $t('inbox.file') }}
              </Button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="pending.length" class="card static" style="margin-top: var(--s4)">
      <div class="cardhead"><span class="kick">{{ $t('inbox.triageTitle') }}</span></div>

      <div class="mt-2.5">
        <div v-for="row in pending" :key="row.id" >
          <div>
            <div>
              {{ row.from }}
              <em>{{ receivedAt(row.receivedAt) }}</em>
              <em> · {{ row.stateLabel }}</em>
              <em v-if="row.reason"> · {{ $t(`inbox.reason.${row.reason}`) }}</em>
            </div>
            <div v-if="row.subject">{{ row.subject }}</div>
            <div class="msgbody" style="-webkit-line-clamp: unset">{{ row.body }}</div>

            <!-- Quarantined mail is shown and never attachable: it failed
                 DMARC, so the sender is not established, and attaching it
                 would put an unverified stranger into a client conversation
                 by hand instead of automatically. -->
            <div v-if="row.state !== 'quarantined'" class="mt-2.5">
              <template v-if="attaching === row.id">
                <Select v-model="chosenThread">
                  <SelectTrigger><SelectValue :placeholder="$t('inbox.chooseThread')" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="thread in threads" :key="thread.id" :value="thread.id">
                      {{ thread.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <div class="formfoot" style="margin-top: var(--s3)">
                  <Button type="button" :disabled="!chosenThread" @click="attach(row.id)">
                    {{ $t('inbox.attach') }}
                  </Button>
                  <Button type="button" variant="ghost" @click="attaching = null">{{ $t('common.cancel') }}</Button>
                </div>
              </template>
              <Button v-else type="button" variant="ghost" @click="attaching = row.id">
                {{ $t('inbox.attach') }}
              </Button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

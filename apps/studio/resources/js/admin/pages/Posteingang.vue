<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import { Button } from '@/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/ui/alert-dialog'

defineProps<{
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

function rotate() {
  router.post('/posteingang/adresse-erneuern', {}, { preserveScroll: true })
}

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

    <div class="card static" style="margin-top: var(--s5)">
      <div class="cardhead"><span class="kick">{{ $t('inbox.addressTitle') }}</span></div>

      <p v-if="!configured" class="hint">{{ $t('inbox.notConfigured') }}</p>
      <template v-else>
        <p v-if="catchAll"><code>{{ catchAll }}</code></p>
        <p v-else class="hint">{{ $t('inbox.noSecret') }}</p>
        <p class="hint">{{ $t('inbox.addressHint') }}</p>

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
        <div v-for="row in unfiled" :key="row.id" class="msgrow">
          <div class="msgtxt">
            <div class="msgwho">
              {{ row.subject }}
              <em>{{ receivedAt(row.startedAt) }}</em>
            </div>
            <div v-if="row.snippet" class="msgbody" style="-webkit-line-clamp: unset">{{ row.snippet }}</div>

            <div style="margin-top: var(--s3)">
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

    <div class="card static" style="margin-top: var(--s4)">
      <div class="cardhead"><span class="kick">{{ $t('inbox.triageTitle') }}</span></div>

      <p v-if="!pending.length" class="hint">{{ $t('inbox.triageEmpty') }}</p>

      <div v-else class="msg" style="gap: var(--s4); margin-top: var(--s3)">
        <div v-for="row in pending" :key="row.id" class="msgrow">
          <div class="msgtxt">
            <div class="msgwho">
              {{ row.from }}
              <em>{{ receivedAt(row.receivedAt) }}</em>
              <em> · {{ row.stateLabel }}</em>
              <em v-if="row.reason"> · {{ $t(`inbox.reason.${row.reason}`) }}</em>
            </div>
            <div v-if="row.subject" class="msgwho">{{ row.subject }}</div>
            <div class="msgbody" style="-webkit-line-clamp: unset">{{ row.body }}</div>

            <!-- Quarantined mail is shown and never attachable: it failed
                 DMARC, so the sender is not established, and attaching it
                 would put an unverified stranger into a client conversation
                 by hand instead of automatically. -->
            <div v-if="row.state !== 'quarantined'" style="margin-top: var(--s3)">
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

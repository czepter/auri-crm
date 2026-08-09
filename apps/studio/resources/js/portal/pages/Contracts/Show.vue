<script setup lang="ts">
import { onBeforeUnmount, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'

type Contract = {
  status: string
  templateName: string
  templateVersion: number
  downloadUrl: string | null
}

const props = defineProps<{
  contact: { name: string }
  showSwitcher: boolean
  project: { id: string; name: string }
  contract: Contract | null
}>()

// Mirrors Projects/Contract.vue on the admin side: the render already
// happened on the queue by the time this page loads, or it is still running
// and this page finds out by asking again — never by triggering one itself.
let timer: ReturnType<typeof setInterval> | undefined

function poll() {
  clearPoll()
  timer = setInterval(() => router.reload({ only: ['contract'] }), 2000)
}

function clearPoll() {
  if (timer) clearInterval(timer)
  timer = undefined
}

watch(
  () => props.contract?.status,
  (status) => (status === 'pending' ? poll() : clearPoll()),
  { immediate: true },
)

onBeforeUnmount(clearPoll)
</script>

<template>
  <header class="phead">
    <div class="pbar">
      <span class="pmark"><i />Atelier Nordlicht <em>Fotografie · Hamburg</em></span>
      <div class="pme">
        <Link v-if="showSwitcher" href="/">{{ $t('portal.allProjects') }}</Link>
        <span>{{ $t('portal.signedInAs') }} <b>{{ contact.name }}</b></span>
        <button type="button" @click="router.post('/abmelden')">
          <svg viewBox="0 0 24 24">
            <path d="M15 17l5-5-5-5" />
            <path d="M20 12H9" />
            <path d="M12 20H6a2 2 0 01-2-2V6a2 2 0 012-2h6" />
          </svg>
          {{ $t('portal.signOut') }}
        </button>
      </div>
    </div>
  </header>

  <main class="pwrap">
    <div class="kick">{{ $t('portal.contractKicker') }}</div>
    <h1>{{ $t('portal.contractTitle') }}</h1>
    <p class="plead">{{ $t('portal.contractLead') }}</p>

    <div v-if="contract" class="pdoc" style="margin-top: var(--s5)">
      <div class="pdochead">
        <span class="kick">
          {{ $t('portal.contractTemplate', { name: contract.templateName, version: contract.templateVersion }) }}
        </span>
        <a v-if="contract.downloadUrl" class="txtbtn" :href="contract.downloadUrl" download>
          <svg viewBox="0 0 24 24"><path d="M12 3v12" /><path d="M7.5 10.5L12 15l4.5-4.5" /><path d="M4 19h16" /></svg>
          {{ $t('portal.contractDownload') }}
        </a>
      </div>

      <div class="pdocscroll">
        <iframe v-if="contract.downloadUrl" :src="contract.downloadUrl" :title="$t('portal.contractTitle')" />
        <p v-else class="pdocwait">{{ $t('portal.contractPending') }}</p>
      </div>
    </div>

    <p v-else class="hint">{{ $t('portal.contractNone') }}</p>
  </main>

  <footer class="pfoot">
    <span>Atelier Nordlicht · Hamburg</span>
  </footer>
</template>

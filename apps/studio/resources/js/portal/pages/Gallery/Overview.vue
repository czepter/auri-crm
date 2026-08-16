<script setup lang="ts">
import { computed, ref } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

type List = { id: string; name: string | null; keys: string[]; covers: string[]; changedAt: string }

const props = defineProps<{
  contact: { name: string }
  gallery: { id: string; title: string | null; count: number; expiresAt: string | null; url: string; cover: string | null }
  gates: { viewPassword: boolean; downloadPin: boolean; emailVerification: boolean }
  lists: List[]
  guests: { views: number; marks: number; comments: number }
}>()

const base = computed(() => `/galerien/${props.gallery.id}/eure-auswahl`)

// The one and only time a freshly set secret is readable. It is hashed the
// moment it is saved, so this flash is not a convenience — it is the whole
// window.
const freshSecret = computed(() => usePage().props.flash?.secret as string | undefined)

const open = ref<string | null>(null)
const gateForm = useForm({ secret: '' })
const listForm = useForm({ name: '' })

function rotate(gate: string) {
  gateForm.post(`${base.value}/tore/${gate}`, {
    preserveScroll: true,
    onSuccess: () => {
      gateForm.reset()
      open.value = null
    },
  })
}

function clearGate(gate: string) {
  router.post(`${base.value}/tore/${gate}`, { secret: null }, { preserveScroll: true })
}

function addList() {
  listForm.post(`${base.value}/listen`, { preserveScroll: true, onSuccess: () => listForm.reset() })
}

function rename(list: List) {
  const name = window.prompt('', list.name ?? '')

  if (name) {
    router.patch(`${base.value}/listen/${list.id}`, { name }, { preserveScroll: true })
  }
}

function remove(list: List) {
  router.delete(`${base.value}/listen/${list.id}`, { preserveScroll: true })
}

const copied = ref(false)

// A browser may refuse the clipboard outright (permission, insecure origin).
// Swallowing that is not hiding an error — the link is on screen either way,
// and an unhandled rejection here would be the only thing that broke.
function copyLink() {
  navigator.clipboard
    ?.writeText(props.gallery.url)
    .then(() => (copied.value = true))
    .catch(() => (copied.value = false))
}

function shortDate(iso: string) {
  return new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

const GATES = [
  { key: 'ansicht', label: 'gallery.gateView', on: () => props.gates.viewPassword },
  { key: 'download', label: 'gallery.gateDownload', on: () => props.gates.downloadPin },
] as const
</script>

<template>
  <PortalHeader :contact="contact" />

  <main class="pwrap wide">
    <div class="ghead">
      <span class="shot">
        <img v-if="gallery.cover" :src="`${gallery.cover}?w=800&fmt=jpeg`" alt="" />
      </span>
      <div style="flex: 1">
        <h1>{{ gallery.title }}</h1>
        <p class="hint">
          {{ $t('portal.imageCount', gallery.count) }}
          <template v-if="gallery.expiresAt">
            · {{ $t('gallery.onlineUntil', { date: shortDate(gallery.expiresAt) }) }}
          </template>
        </p>
      </div>
      <Button as="a" :href="`/galerien/${gallery.id}`">{{ $t('gallery.passwordOpen') }}</Button>
    </div>

    <div class="phome" style="margin-top: var(--s6)">
      <div>
        <section class="psec">
          <p class="lab">{{ $t('gallery.listsTitle') }}</p>

          <div v-for="list in lists" :key="list.id" class="listblock">
            <div class="listbar">
              <b>{{ list.name ?? $t('gallery.heart') }}</b>
              <span class="hint">
                {{ $t('gallery.ofCount', { picked: list.keys.length, total: gallery.count }) }}
                · {{ $t('gallery.changedOn', { date: shortDate(list.changedAt) }) }}
              </span>
              <template v-if="list.name !== null">
                <button type="button" @click="rename(list)">{{ $t('gallery.renameList') }}</button>
                <button type="button" class="warn" @click="remove(list)">{{ $t('common.remove') }}</button>
              </template>
            </div>

            <div v-if="list.covers.length" class="picks">
              <a v-for="src in list.covers" :key="src" :href="`/galerien/${gallery.id}`">
                <img :src="`${src}?w=400&fmt=jpeg`" alt="" loading="lazy" />
              </a>
            </div>
            <p v-else class="hint">{{ $t('gallery.listEmpty') }}</p>
          </div>

          <form class="newlist" @submit.prevent="addList">
            <Input v-model="listForm.name" type="text" :placeholder="$t('gallery.newListName')" />
            <Button type="submit" variant="ghost" :disabled="!listForm.name">{{ $t('gallery.newList') }}</Button>
          </form>
        </section>
      </div>

      <div class="rail">
        <section class="psec">
          <p class="lab">{{ $t('gallery.shareTitle') }}</p>
          <div class="link">
            <code>{{ gallery.url }}</code>
            <button type="button" @click="copyLink">
              {{ copied ? $t('gallery.copied') : $t('gallery.copy') }}
            </button>
          </div>

          <!-- No value is shown, ever: the secret is hashed the moment it is
               saved and is gone for everyone, the studio included. What can
               be done about a link that travelled too far is to rotate it,
               which also turns out every guest already inside. -->
          <div v-for="gate in GATES" :key="gate.key" class="gate">
            <div style="flex: 1">
              <b>{{ $t(gate.label) }}</b>
              <div class="hint">{{ gate.on() ? $t('gallery.gateOn') : $t('gallery.gateOff') }}</div>
            </div>
            <button type="button" @click="open = open === gate.key ? null : gate.key">
              {{ gate.on() ? $t('gallery.gateRotate') : $t('gallery.gateSet') }}
            </button>
            <button v-if="gate.on()" type="button" class="warn" @click="clearGate(gate.key)">
              {{ $t('gallery.gateClear') }}
            </button>
          </div>

          <form v-if="open" @submit.prevent="rotate(open)">
            <Label class="field">
              <span>{{ $t('gallery.gateNewValue') }}</span>
              <Input v-model="gateForm.secret" type="text" autocomplete="off" />
            </Label>
            <p v-if="gateForm.errors.secret" class="err">{{ gateForm.errors.secret }}</p>
            <div class="formfoot">
              <Button type="submit" :disabled="gateForm.processing">{{ $t('common.save') }}</Button>
            </div>
          </form>

          <p v-if="freshSecret" class="hint"><b>{{ freshSecret }}</b> — {{ $t('gallery.secretOnce') }}</p>
        </section>

        <section class="psec">
          <p class="lab">{{ $t('gallery.guestsTitle') }}</p>
          <dl class="facts">
            <dt>{{ $t('gallery.views') }}</dt>
            <dd class="num">{{ guests.views }}</dd>
            <dt>{{ $t('gallery.marks') }}</dt>
            <dd class="num">{{ guests.marks }}</dd>
            <dt>{{ $t('gallery.comments') }}</dt>
            <dd class="num">{{ guests.comments }}</dd>
          </dl>
        </section>
      </div>
    </div>
  </main>

  <PortalFooter />
</template>

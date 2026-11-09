<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { helpPanel, linkZiel } from '@/lib/helpPanel'

type Eintrag = { slug: string; titel: string; art: string }

const page = usePage()
const eintraege = computed(() => (page.props.help as Eintrag[] | null) ?? [])

const gewaehlt = ref<string | null>(null)
const html = ref('')
const fehler = ref(false)
const laedt = ref(false)

const slug = computed(() => {
  const gueltig = eintraege.value.find((e) => e.slug === gewaehlt.value)?.slug

  return helpPanel.ueberschrieben.value ?? gueltig ?? eintraege.value[0]?.slug ?? null
})
const sichtbar = computed(() => helpPanel.offen.value && eintraege.value.length > 0 && (laedt.value || fehler.value || html.value.trim() !== ''))

async function laden() {
  if (!slug.value) return

  const angefragt = slug.value

  laedt.value = true
  fehler.value = false
  html.value = ''

  try {
    const artikel = await helpPanel.artikel(angefragt)
    if (angefragt === slug.value) html.value = artikel.html
  } catch {
    fehler.value = true
  } finally {
    laedt.value = false
  }
}

function aufTaste(event: KeyboardEvent) {
  if (event.key === 'Escape' && helpPanel.offen.value) helpPanel.schliessen()
}

onMounted(() => addEventListener('keydown', aufTaste))
onUnmounted(() => removeEventListener('keydown', aufTaste))

watch(slug, laden, { immediate: true })

// Ein rohes <a href> in einem v-html-Block macht einen vollen
// Seitenladevorgang — die offene Fallakte daneben wäre weg, also genau das,
// wogegen die Spalte gebaut ist.
function aufLink(event: MouseEvent) {
  const ziel = (event.target as HTMLElement).closest('a')
  const href = ziel?.getAttribute('href') ?? null
  const ergebnis = linkZiel(event, href)

  if (!ergebnis || !href) return

  event.preventDefault()

  if (ergebnis === 'artikel') {
    helpPanel.oeffnen(href.slice('/hilfe/'.length))
    return
  }

  router.visit(href)
}
</script>

<template>
  <div v-if="sichtbar" class="fixed inset-0 z-40 bg-foreground/20" @click.self="helpPanel.schliessen()">
    <aside class="absolute inset-y-0 right-0 w-[420px] max-w-full overflow-y-auto bg-background px-5 py-4 shadow-xl">
      <div class="mb-3 flex items-center gap-2">
        <button
          v-for="eintrag in eintraege"
          :key="eintrag.slug"
          class="cursor-pointer rounded-[7px] border-0 bg-transparent px-2 py-1 text-small leading-none"
          :class="eintrag.slug === slug ? 'bg-paper font-semibold text-foreground' : 'text-ink-2'"
          type="button"
          @click="gewaehlt = eintrag.slug; helpPanel.ueberschrieben.value = null"
        >{{ $t(`help.art.${eintrag.art}`) }}</button>

        <button
          class="ml-auto cursor-pointer border-0 bg-transparent text-small text-ink-2"
          type="button"
          @click="helpPanel.schliessen()"
        >{{ $t('help.close') }}</button>
      </div>

      <p v-if="fehler" class="text-small text-foreground">
        {{ $t('help.loadFailed') }}
        <button class="cursor-pointer border-0 bg-transparent p-0 underline" type="button" @click="laden">
          {{ $t('help.retry') }}
        </button>
      </p>

      <div v-else-if="!laedt" class="help-prose" @click="aufLink" v-html="html" />
    </aside>
  </div>
</template>

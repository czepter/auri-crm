<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Rail from '@/ui/page/Rail.vue'
import { helpPanel } from '@/lib/helpPanel'

type Eintrag = { slug: string; titel: string; art: string }

const page = usePage()
const eintraege = computed(() => (page.props.help as Eintrag[] | null) ?? [])

const gewaehlt = ref<string | null>(null)
const html = ref('')
const fehler = ref(false)
const laedt = ref(false)

const slug = computed(() => helpPanel.ueberschrieben.value ?? gewaehlt.value ?? eintraege.value[0]?.slug ?? null)

async function laden() {
  if (!slug.value) return

  laedt.value = true
  fehler.value = false

  try {
    html.value = (await helpPanel.artikel(slug.value)).html
  } catch {
    fehler.value = true
  } finally {
    laedt.value = false
  }
}

watch(slug, laden, { immediate: true })
onMounted(() => helpPanel.gesehen())

// Ein rohes <a href> in einem v-html-Block macht einen vollen
// Seitenladevorgang — die offene Fallakte daneben wäre weg, also genau das,
// wogegen die Spalte gebaut ist.
function aufLink(event: MouseEvent) {
  const ziel = (event.target as HTMLElement).closest('a')
  const href = ziel?.getAttribute('href')

  if (!href || !href.startsWith('/')) return

  event.preventDefault()

  if (href.startsWith('/hilfe/')) {
    helpPanel.oeffnen(href.slice('/hilfe/'.length))
    return
  }

  router.visit(href)
}
</script>

<template>
  <Rail v-if="helpPanel.offen.value && eintraege.length">
    <div class="mb-3 flex items-center gap-2">
      <button
        v-for="eintrag in eintraege"
        :key="eintrag.slug"
        class="cursor-pointer rounded-[7px] border-0 bg-transparent px-2 py-1 text-[12px] leading-none"
        :class="eintrag.slug === slug ? 'bg-paper font-semibold text-foreground' : 'text-ink-2'"
        type="button"
        @click="gewaehlt = eintrag.slug; helpPanel.ueberschrieben.value = null"
      >{{ $t(`help.art.${eintrag.art}`) }}</button>

      <button
        class="ml-auto cursor-pointer border-0 bg-transparent text-[12px] text-ink-2"
        type="button"
        @click="helpPanel.schliessen()"
      >{{ $t('help.close') }}</button>
    </div>

    <p v-if="fehler" class="text-[13px] text-foreground">
      {{ $t('help.loadFailed') }}
      <button class="cursor-pointer border-0 bg-transparent p-0 underline" type="button" @click="laden">
        {{ $t('help.retry') }}
      </button>
    </p>

    <div v-else-if="!laedt" class="help-prose" @click="aufLink" v-html="html" />
  </Rail>
</template>

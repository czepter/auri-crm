<script setup lang="ts">
import { computed } from 'vue'

/*
 * G-15: die Galerie im Browserfenster. Wählen, ohne zu sehen, ist raten — und
 * die Vorgabe wirkt auf eine Seite, die im Studio nirgends aufgeht.
 *
 * Die Werte kommen aus resources/css/gallery.css: das Cover mit seiner
 * Vignette, die .gbar mit Name, Filterpillen und Download, die .gwall als
 * columns-Masonry. Verkleinert, aber nicht linear skaliert — 13.5px Leiste
 * auf ein Drittel gerechnet wäre unleserlich.
 *
 * Was ein Tor abschaltet, fehlt hier: keine Pille, keine Aktion, kein Herz.
 * Bilder sind Flächen, solange keine Galerie danebensteht, die welche hat.
 */
const props = defineProps<{
  design: Record<string, string>
  offers: { downloads: boolean; lists: boolean; comments: boolean }
  title: string
  url: string
}>()

const SCHEMES: Record<string, Record<string, string>> = {
  light: {
    bg: '#FFFFFF', ink: '#151A22', muted: '#626B78', line: '#E2E7EB',
    hero: '#C9D1D7',
  },
  dark: {
    bg: '#0E0C0B', ink: '#F2EDE9', muted: '#8B817B', line: '#241F1C',
    hero: '#2C2521',
  },
  warm: {
    bg: '#FBF7F2', ink: '#2A211B', muted: '#7A6A5C', line: '#E6DBCC',
    hero: '#D8C4AC',
  },
}

const GRIDS: Record<string, { columns: number; heights: number[] }> = {
  wide: { columns: 3, heights: [84, 60, 70, 56, 78, 64] },
  dense: { columns: 4, heights: [62, 46, 54, 44, 58, 50, 42, 56] },
  single: { columns: 1, heights: [118, 96] },
}

const scheme = computed(() => SCHEMES[props.design.colour] ?? SCHEMES.light)
const grid = computed(() => GRIDS[props.design.grid] ?? GRIDS.wide)

const tiles = computed(() =>
  grid.value.heights.map((height, index) => ({
    height: `${height}px`,
    // Drei Töne aus dem Grund gemischt, damit die Wall nicht wie eine Tabelle
    // aussieht: eine echte Galerie hat nie zwei gleiche Kacheln.
    fill: `color-mix(in oklab, ${scheme.value.line} ${[100, 78, 124][index % 3]}%, ${scheme.value.bg})`,
    marked: props.offers.lists && index === 1,
  })),
)

const filters = computed(() => [
  { label: 'gallery.all', count: 128, on: true },
  ...(props.offers.lists ? [{ label: 'gallery.listsTitle', count: 12, on: false }] : []),
])
</script>

<template>
  <div class="overflow-hidden rounded-[10px] border border-line-hover bg-background shadow-[0_12px_32px_rgb(20_16_14_/_0.10)]">
    <div class="flex h-[30px] items-center gap-2 border-b border-border bg-sunk-2 px-2.5">
      <span class="flex flex-none gap-[5px]">
        <span v-for="dot in 3" :key="dot" class="size-2 rounded-full bg-line-hover" />
      </span>
      <span class="flex min-w-0 flex-1 justify-center">
        <span class="max-w-full truncate rounded-full border border-border bg-background px-3 py-[3px] text-[9.5px] leading-[1.3] text-ink-2">
          {{ url }}
        </span>
      </span>
      <span class="w-[34px] flex-none" />
    </div>

    <div
      class="h-[334px] overflow-hidden"
      :style="{ background: scheme.bg, color: scheme.ink }"
    >
      <!-- Ganzflächig: die Vignette bleibt dunkel, gleich welche Farbe die
           Seite trägt — auf einem Foto ist sie das, was den Titel lesbar
           macht, und nicht eine Farbentscheidung. -->
      <div
        v-if="design.cover === 'full'"
        class="relative grid h-[140px] place-items-center overflow-hidden"
        :style="{ background: scheme.hero }"
      >
        <div class="absolute inset-0 bg-[radial-gradient(115%_85%_at_50%_46%,rgba(12,10,9,.48)_0%,rgba(12,10,9,.76)_62%,rgba(12,10,9,.92)_100%)]" />
        <div class="relative px-5 text-center">
          <div class="text-[8px] font-bold tracking-[0.18em] uppercase text-[rgba(242,237,233,.9)]">
            {{ $page.props.brand }}
          </div>
          <div class="mt-2.5 font-[family-name:var(--font-display)] text-[27px] leading-[1.1] font-semibold tracking-[var(--display-tracking)] text-white">
            {{ title }}
          </div>
          <div class="mx-auto mt-3.5 h-px w-[34px] bg-[rgba(242,237,233,.45)]" />
        </div>
      </div>

      <div v-else-if="design.cover === 'split'" class="flex h-[140px] items-stretch">
        <div class="w-[48%] flex-none" :style="{ background: scheme.hero }" />
        <div class="flex min-w-0 flex-1 flex-col justify-center px-6">
          <div class="text-[8px] font-bold tracking-[0.18em] uppercase" :style="{ color: scheme.muted }">
            {{ $page.props.brand }}
          </div>
          <div class="mt-2.5 font-[family-name:var(--font-display)] text-heading leading-[1.1] font-semibold tracking-[var(--display-tracking)]">
            {{ title }}
          </div>
          <div class="mt-3 h-px w-[34px]" :style="{ background: scheme.line }" />
        </div>
      </div>

      <div v-else class="grid h-[140px] place-items-center px-5 text-center">
        <div>
          <div class="text-[8px] font-bold tracking-[0.18em] uppercase" :style="{ color: scheme.muted }">
            {{ $page.props.brand }}
          </div>
          <div class="mt-2.5 font-[family-name:var(--font-display)] text-[27px] leading-[1.1] font-semibold tracking-[var(--display-tracking)]">
            {{ title }}
          </div>
          <div class="mx-auto mt-3.5 h-px w-[34px]" :style="{ background: scheme.line }" />
        </div>
      </div>

      <div class="flex items-center gap-2.5 border-b px-3 py-2.5" :style="{ borderColor: scheme.line }">
        <span class="min-w-0 shrink truncate text-[9.5px] leading-[1.35] font-semibold tracking-[-0.015em]" :style="{ color: scheme.muted }">
          <b class="font-semibold" :style="{ color: scheme.ink }">{{ title }}</b>
        </span>
        <span class="mx-auto flex items-center gap-[5px]">
          <span
            v-for="filter in filters"
            :key="filter.label"
            class="flex flex-none items-center gap-[5px] rounded-full border px-2.5 py-1 text-[8.5px] leading-none"
            :style="filter.on
              ? { borderColor: scheme.ink, background: scheme.ink, color: scheme.bg, fontWeight: 600 }
              : { borderColor: scheme.line, color: scheme.muted, fontWeight: 500 }"
          >
            {{ $t(filter.label) }}<span class="tabular-nums opacity-55">{{ filter.count }}</span>
          </span>
        </span>

        <span
          v-if="offers.downloads"
          class="flex flex-none items-center gap-[5px] rounded-md px-2.5 py-[5px] text-[8.5px] leading-none font-semibold"
          :style="{ background: scheme.ink, color: scheme.bg }"
        >
          <svg class="size-[9px] fill-none stroke-current stroke-[2.2] [stroke-linecap:round] [stroke-linejoin:round]" viewBox="0 0 24 24">
            <path d="M12 4v12M7 12l5 5 5-5M5 20h14" />
          </svg>
          {{ $t('gallery.downloadLink') }}
        </span>
      </div>

      <!-- columns wie .gwall: eine Fotowand behält ihre Proportionen. -->
      <div class="p-3.5" :style="{ columnCount: grid.columns, columnGap: '7px' }">
        <div
          v-for="(tile, index) in tiles"
          :key="index"
          class="relative mb-[7px] break-inside-avoid rounded-[5px]"
          :style="{ height: tile.height, background: tile.fill }"
        >
          <span v-if="tile.marked" class="absolute top-1.5 right-1.5 size-[15px] rounded-full bg-[rgba(12,10,9,.5)]" />
        </div>
      </div>
    </div>
  </div>
</template>

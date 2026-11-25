<script setup lang="ts">
import { computed, ref } from 'vue'
import { ChoiceGroup } from '@/ui/choice-group'
import { columnsOf, COLOURS, complete, FONTS } from '@/admin/lib/galleryDesign'

/*
 * G-15: die Galerie im Browserfenster. Wählen, ohne zu sehen, ist raten — und
 * die Vorgabe wirkt auf eine Seite, die im Studio nirgends aufgeht.
 *
 * Die Werte kommen aus resources/css/gallery.css: das Cover, die .gbar mit
 * Name, Filterpillen und Download, das Raster. Verkleinert, aber nicht linear
 * skaliert — 13.5px Leiste auf ein Drittel gerechnet wäre unleserlich.
 * Telefon und Desktop unterscheiden sich in der Breite und damit in den
 * Spalten, wie beim Betrachter.
 *
 * Was ein Tor abschaltet, fehlt hier: keine Pille, keine Aktion, kein Herz.
 * Bilder sind Flächen, solange keine Galerie danebensteht, die welche hat.
 */
const props = defineProps<{
  design: Record<string, string>
  offers: { downloads: boolean; lists: boolean; comments: boolean }
  title: string
  url: string
  /** Echte Bilder statt Flächen, wo die Galerie welche hat. */
  photos?: { cover: string; focal: { x: number; y: number } | null; rest: string[] } | null
}>()

const device = ref<'desktop' | 'phone'>('desktop')
const phone = computed(() => device.value === 'phone')

const look = computed(() => complete(props.design))
const scheme = computed(() => COLOURS[look.value.colour] ?? COLOURS.hell)
const font = computed(() => FONTS[look.value.type] ?? FONTS.sans)

// Seitenverhältnisse einer Reihe von Aufnahmen: Quer, hoch, quadratisch.
const RATIOS = [1.5, 0.75, 1.33, 1.5, 0.67, 1.78, 1, 1.5, 0.8, 1.33, 1.5, 1.2]
const ROW_HEIGHT = { klein: 26, mittel: 40, gross: 64 } as Record<string, number>
const GAP = { eng: 3, weit: 9 } as Record<string, number>

const columns = computed(() => columnsOf(look.value.size, phone.value))
const gap = computed(() => GAP[look.value.spacing] ?? 3)
const rows = computed(() => look.value.flow === 'zeilen')

const tiles = computed(() =>
  RATIOS.slice(0, phone.value ? 6 : 12).map((ratio, index) => ({
    ratio,
    src: props.photos?.rest.length ? props.photos.rest[index % props.photos.rest.length] : null,
    // Drei Töne aus dem Grund gemischt, damit die Wand nicht wie eine Tabelle
    // aussieht: eine echte Galerie hat nie zwei gleiche Kacheln.
    fill: `color-mix(in oklab, ${scheme.value.line} ${[100, 78, 124][index % 3]}%, ${scheme.value.bg})`,
    marked: props.offers.lists && index === 1,
  })),
)

const filters = computed(() => [
  { label: 'gallery.all', count: 128, on: true },
  ...(props.offers.lists ? [{ label: 'gallery.listsTitle', count: 12, on: false }] : []),
])

const titleStyle = computed(() => ({ fontFamily: font.value.family, fontWeight: font.value.weight }))
</script>

<template>
  <div>
    <ChoiceGroup
      v-model="device"
      class="mb-2.5"
      :options="[{ value: 'phone', label: $t('galleryDesign.phone') }, { value: 'desktop', label: $t('galleryDesign.desktop') }]"
    />

    <div
      class="mx-auto overflow-hidden rounded-[10px] border border-line-hover bg-background transition-[max-width] duration-200"
      :class="phone ? 'max-w-[220px]' : 'max-w-full'"
    >
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
        <!-- Auf dem Foto bleibt der Titel weiß und die Vignette dunkel, gleich
             welche Farbe die Seite trägt: sie macht den Titel lesbar und ist
             keine Farbentscheidung. -->
        <div
          v-if="look.cover !== 'geteilt'"
          class="relative grid min-h-[140px] overflow-hidden py-4"
          :class="[
            look.cover === 'links' ? 'items-end justify-items-start' : 'place-items-center',
            look.cover === 'rahmen' && 'm-2 rounded-[10px]',
          ]"
          :style="{ background: scheme.hero }"
        >
          <img
            v-if="photos"
            :src="photos.cover"
            alt=""
            class="absolute inset-0 size-full object-cover"
            :style="{ objectPosition: photos.focal ? `${photos.focal.x}% ${photos.focal.y}%` : '50% 38%' }"
          >
          <div class="absolute inset-0 bg-[radial-gradient(115%_85%_at_50%_46%,rgba(12,10,9,.48)_0%,rgba(12,10,9,.76)_62%,rgba(12,10,9,.92)_100%)]" />
          <div class="relative px-5" :class="look.cover === 'links' ? 'text-left' : 'text-center'">
            <div class="text-[8px] font-bold tracking-[0.18em] uppercase text-[rgba(242,237,233,.9)]">
              {{ $page.props.brand }}
            </div>
            <div class="mt-2.5 text-heading leading-[1.1] break-words text-white" :style="titleStyle">
              {{ title }}
            </div>
            <div class="mt-3.5 h-px w-[34px] bg-[rgba(242,237,233,.45)]" :class="look.cover !== 'links' && 'mx-auto'" />
          </div>
        </div>

        <div v-else class="flex min-h-[140px] items-stretch" :class="phone && 'flex-col'">
          <div class="flex-none overflow-hidden" :class="phone ? 'h-[70px]' : 'w-[48%]'" :style="{ background: scheme.hero }">
            <img
              v-if="photos"
              :src="photos.cover"
              alt=""
              class="size-full object-cover"
              :style="{ objectPosition: photos.focal ? `${photos.focal.x}% ${photos.focal.y}%` : '50% 38%' }"
            >
          </div>
          <div class="flex min-w-0 flex-1 flex-col justify-center px-6 py-3">
            <div class="text-[8px] font-bold tracking-[0.18em] uppercase" :style="{ color: scheme.muted }">
              {{ $page.props.brand }}
            </div>
            <div class="mt-2.5 text-title leading-[1.1] break-words" :style="titleStyle">
              {{ title }}
            </div>
            <div class="mt-3 h-px w-[34px]" :style="{ background: scheme.line }" />
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
            {{ $t('gallery.download') }}
          </span>
        </div>

        <!-- zeilen: jede Reihe in einer Höhe, die Breite folgt dem Bild.
             spalten: gleiche Breite, die Höhe folgt dem Bild. -->
        <div
          v-if="rows"
          class="flex flex-wrap p-3.5"
          :style="{ gap: `${gap}px` }"
        >
          <div
            v-for="(tile, index) in tiles"
            :key="index"
            class="relative rounded-[3px]"
            :style="{
              height: `${ROW_HEIGHT[look.size] * (phone ? 1.4 : 1)}px`,
              flex: `${tile.ratio * 100} 1 ${tile.ratio * ROW_HEIGHT[look.size] * (phone ? 1.4 : 1)}px`,
              background: tile.fill,
            }"
          >
            <img v-if="tile.src" :src="tile.src" alt="" class="size-full rounded-[3px] object-cover" loading="lazy">
            <span v-if="tile.marked" class="absolute top-1.5 right-1.5 size-[15px] rounded-full bg-[rgba(12,10,9,.5)]" />
          </div>
        </div>
        <div
          v-else
          class="p-3.5"
          :style="{ columnCount: columns, columnGap: `${gap}px` }"
        >
          <div
            v-for="(tile, index) in tiles"
            :key="index"
            class="relative break-inside-avoid rounded-[3px]"
            :style="{ aspectRatio: String(tile.ratio), marginBottom: `${gap}px`, background: tile.fill }"
          >
            <img v-if="tile.src" :src="tile.src" alt="" class="size-full rounded-[3px] object-cover" loading="lazy">
            <span v-if="tile.marked" class="absolute top-1.5 right-1.5 size-[15px] rounded-full bg-[rgba(12,10,9,.5)]" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

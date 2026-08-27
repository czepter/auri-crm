<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { ref } from 'vue'

/*
 * Die Spalte eines Datensatzes: die Bereiche als Wörter mit Zähler rechts,
 * nicht als Streifen über dem Inhalt — Einträge mit einer Zahl daneben
 * brauchen eine linke Kante zum Ausrichten. Unter 640px legt sie sich hin und
 * scrollt seitwärts; Navigation verschwindet nicht, wenn der Schirm schmaler
 * wird.
 *
 * children ist Teil des Vertrags und kein Slot mit dem Sonderfall im Namen:
 * die Sets einer Galerie sind die Gliederung eines Bereichs, und Shootings
 * könnten dasselbe tun, ohne dass hier etwas dazukommt.
 *
 * Ziehen sortiert die Liste selbst, gehört also hierher. Umbenennen und
 * Löschen sind Fachlogik des Datensatzes und hängen an #child-actions.
 */

export interface RecordNavChild {
  key: string
  label: string
  translate: boolean
  href: string
  count: number | null
  reorderable: boolean
}

export interface RecordNavTab {
  key: string
  label: string
  href: string
  count: number | null
  countTo: number | null
  children: RecordNavChild[]
}

const props = withDefaults(
  defineProps<{
    tabs: RecordNavTab[]
    active: string
    activeChild?: string | null
    width?: number
  }>(),
  { activeChild: null, width: 150 },
)

const emit = defineEmits<{ reorder: [string[]] }>()

const dragging = ref<string | null>(null)

function drop(targetKey: string) {
  const from = dragging.value

  dragging.value = null
  if (from === null || from === targetKey) return

  const order = props.tabs
    .flatMap((tab) => tab.children)
    .filter((child) => child.reorderable)
    .map((child) => child.key)

  order.splice(order.indexOf(targetKey), 0, ...order.splice(order.indexOf(from), 1))
  emit('reorder', order)
}
</script>

<template>
  <nav
    class="sticky top-0 flex h-screen w-[var(--record-nav-width)] flex-none flex-col gap-0.5 overflow-y-auto border-r border-border px-2.5 py-4 max-[640px]:static max-[640px]:h-auto max-[640px]:w-full max-[640px]:overflow-visible max-[640px]:flex-row max-[640px]:flex-wrap max-[640px]:self-start max-[640px]:border-r-0 max-[640px]:border-b max-[640px]:px-5 max-[640px]:py-2.5"
    :style="{ '--record-nav-width': `${width}px` }"
  >
    <slot name="above" />

    <template v-for="tab in tabs" :key="tab.key">
      <Link
        class="flex items-center gap-2.5 rounded-[7px] px-2.5 py-[7px] text-[13px] leading-none whitespace-nowrap no-underline transition-[background-color] duration-[.12s] ease-out hover:bg-muted"
        :class="
          tab.key === active
            ? 'bg-muted font-semibold text-foreground'
            : 'font-normal text-muted-foreground'
        "
        :href="tab.href"
      >
        {{ $t(tab.label) }}
        <span v-if="tab.count" class="ml-auto text-[11.5px] tabular-nums text-ink-2">
          {{ tab.count }}<span v-if="tab.countTo !== null" class="px-1 text-faint">→</span>{{ tab.countTo }}
        </span>
      </Link>

      <!-- Aufgeklappt heißt: der Bereich ist aktiv. Kein zweiter Zustand zum
           Merken, und keine Frage bei jedem neuen Bereich. -->
      <div
        v-if="tab.children.length && tab.key === active"
        class="ml-[19px] flex flex-col gap-px border-l border-border pl-2.5 max-[640px]:order-1 max-[640px]:ml-0 max-[640px]:w-full max-[640px]:flex-row max-[640px]:overflow-x-auto max-[640px]:border-l-0 max-[640px]:pl-0"
      >
        <div
          v-for="child in tab.children"
          :key="child.key"
          class="group flex items-center gap-2 rounded-md"
          :class="child.key === activeChild && 'bg-muted font-semibold'"
          :draggable="child.reorderable"
          @dragstart="dragging = child.key"
          @dragover.prevent
          @drop="drop(child.key)"
        >
          <Link
            class="flex min-w-0 flex-1 items-center gap-2 px-2 py-[5px] text-[12.5px] leading-none whitespace-nowrap text-foreground no-underline"
            :class="child.reorderable && 'cursor-grab'"
            :href="child.href"
          >
            <span class="truncate">{{ child.translate ? $t(child.label) : child.label }}</span>
            <span v-if="child.count" class="ml-auto text-[11px] tabular-nums text-ink-2">{{ child.count }}</span>
          </Link>

          <slot name="child-actions" :child="child" />
        </div>

        <slot name="children-append" :tab="tab" />
      </div>
    </template>

    <slot name="below" />
  </nav>
</template>

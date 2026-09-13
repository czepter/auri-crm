<script setup lang="ts">
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import type { PackageItemRow } from '@/admin/lib/packageItem'
import { moveRow } from '@/admin/lib/rows'
import PackageItemInsertRow from '@/admin/components/PackageItemInsertRow.vue'
import PackageItemRowComponent from '@/admin/components/PackageItemRow.vue'

const props = defineProps<{ packageId?: string; catalogItems: App.Catalog.Data.CatalogItemData[] }>()

// Shared by Create and Edit, so the two forms cannot drift into building a
// package item two different ways.
const items = defineModel<PackageItemRow[]>({ required: true })

// Höchstens eine Karte offen — wie im Angebot.
const openIndex = ref<number | null>(null)

function isOpen(index: number) {
  return openIndex.value === index
}

function setOpen(index: number, value: boolean) {
  openIndex.value = value ? index : null
}

function insert(row: PackageItemRow) {
  items.value = [...items.value, row]
  openIndex.value = items.value.length - 1
}

function remove(index: number) {
  items.value = items.value.filter((_, i) => i !== index)
  openIndex.value = null
}

function move(from: number, to: number) {
  const next = moveRow(items.value, from, to)

  if (next === items.value) return

  const openRow = openIndex.value === null ? null : items.value[openIndex.value]
  items.value = next
  openIndex.value = openRow ? items.value.indexOf(openRow) : null
}

const dragging = ref<number | null>(null)
const over = ref<number | null>(null)

// Die Liste zeigt schon beim Ziehen die Reihenfolge nach dem Loslassen; die
// gezogene Karte steht als Platzhalter an ihrem neuen Platz.
const displayed = computed(() => (dragging.value === null || over.value === null ? items.value : moveRow(items.value, dragging.value, over.value)))

function at(row: PackageItemRow) {
  return items.value.indexOf(row)
}

// Erst nach dem dragstart: Chrome baut das Ziehbild aus der Quelle und bricht
// ab, wenn sie im selben Tick unsichtbar wird oder zuklappt.
let pendingDrag: ReturnType<typeof setTimeout> | undefined

function startDrag(position: number) {
  pendingDrag = setTimeout(() => {
    openIndex.value = null
    dragging.value = position
    over.value = position
  })
}

function onDragOver(event: DragEvent, row?: PackageItemRow) {
  if (dragging.value === null) return

  event.preventDefault()
  if (event.dataTransfer) event.dataTransfer.dropEffect = 'move'
  if (!row || over.value === null) return

  // Gezählt wird in der angezeigten Liste; die ohne Platzhalter hat dieselbe
  // Reihenfolge, also landet die Karte genau dort.
  const target = displayed.value.indexOf(row)
  const box = (event.currentTarget as HTMLElement).getBoundingClientRect()
  const upperHalf = event.clientY < box.top + box.height / 2

  // Erst jenseits der Mitte der Nachbarkarte: sonst schiebt jeder Tausch sie
  // wieder unter den Zeiger und die Liste springt hin und her.
  if ((target > over.value && upperHalf) || (target < over.value && !upperHalf)) return

  over.value = target
}

function onDrop() {
  if (dragging.value !== null && over.value !== null) move(dragging.value, over.value)

  endDrag()
}

function endDrag() {
  clearTimeout(pendingDrag)
  dragging.value = null
  over.value = null
}

// Q-10: das Bild haengt an einer Zeile, die es in der Datenbank schon gibt,
// also erst nach dem ersten Speichern — siehe PackageItemRow.vue.
function upload(row: PackageItemRow, file: File) {
  if (!row.id || !props.packageId) return

  router.post(`/pakete/${props.packageId}/positionen/${row.id}/bild`, { image: file }, { preserveScroll: true, forceFormData: true })
}
</script>

<template>
  <div class="flex flex-col gap-3">
    <!-- Die ganze Liste nimmt den Drop an, auch die Lücken zwischen den Karten. -->
    <div class="flex flex-col gap-3" @dragover="onDragOver($event)" @drop.prevent="onDrop">
      <div
        v-for="row in displayed"
        :key="row.id ?? `row-${items.indexOf(row)}`"
        data-position
        class="rounded-[20px]"
        :class="
          dragging === null
            ? ''
            : row === items[dragging]
              ? 'bg-[var(--accent-bg)] outline-2 outline-dashed outline-ring [&>*]:invisible'
              : 'outline-2 outline-offset-2 outline-dashed outline-ring/35'
        "
        @dragover.stop="onDragOver($event, row)"
      >
        <PackageItemRowComponent
          :package-id="packageId"
          :row="row"
          :open="isOpen(at(row))"
          @update:open="(value) => setOpen(at(row), value)"
          @remove="remove(at(row))"
          @move-up="move(at(row), at(row) - 1)"
          @move-down="move(at(row), at(row) + 1)"
          @drag-start="startDrag(at(row))"
          @drag-end="endDrag"
          @upload="(file) => upload(row, file)"
        />
      </div>
    </div>

    <PackageItemInsertRow :catalog-items="catalogItems" @insert="insert" />
  </div>
</template>

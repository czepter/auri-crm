<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Input } from '@/ui/input'
import { NativeSelect } from '@/ui/native-select'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/ui/alert-dialog'

export interface SetRow {
  id: string
  name: string
  count: number
  density: string
}

const props = defineProps<{
  coverSrc: string | null
  tab: string
  sets: SetRow[]
  activeSetId: string | null
  error: { id: string; message: string } | null
  settingsSection: string
  offers: { downloads: boolean; lists: boolean; comments: boolean }
}>()

const emit = defineEmits<{
  'update:tab': [string]
  'update:activeSetId': [string | null]
  'update:settingsSection': [string]
  create: [string]
  rename: [{ id: string; name: string }]
  remove: [{ id: string; targetSetId: string | null }]
  reorder: [string[]]
}>()

const TABS = ['bilder', 'gestaltung', 'einstellungen', 'aktivitaet'] as const

// Pixieset's General Settings shape: a sub-navigation, only while ⚙ is open.
// Download/Listen/Kommentare carry the switch's own state as a pill — the
// same fact GalleryRecord's header chip already reads out elsewhere.
const SETTINGS_SECTIONS = ['allgemein', 'zugang', 'download', 'listen', 'kommentare'] as const

const settingsPill = computed<Record<string, boolean | null>>(() => ({
  allgemein: null,
  zugang: null,
  download: props.offers.downloads,
  listen: props.offers.lists,
  kommentare: props.offers.comments,
}))

const total = computed(() => props.sets.reduce((sum, row) => sum + row.count, 0))

const editing = ref<string | null>(null)
const draft = ref('')
const adding = ref(false)

function startRename(row: SetRow) {
  adding.value = false
  editing.value = row.id
  draft.value = row.name
}

function commitRename(row: SetRow) {
  if (draft.value === '' || draft.value === row.name) {
    editing.value = null
    return
  }

  emit('rename', { id: row.id, name: draft.value })
}

function startAdd() {
  editing.value = null
  draft.value = ''
  adding.value = true
}

function commitCreate() {
  if (draft.value === '') {
    adding.value = false
    return
  }

  emit('create', draft.value)
}

// A row only leaves edit mode once the server has confirmed the new name —
// props.sets carries it back after that redirect. A 422 leaves props.sets
// untouched, so the row (and its error, keyed to the row's own id) stays open.
watch(
  () => props.sets,
  (rows) => {
    if (editing.value !== null && rows.some((row) => row.id === editing.value && row.name === draft.value)) {
      editing.value = null
    }

    if (adding.value && rows.some((row) => row.name === draft.value)) {
      adding.value = false
      draft.value = ''
    }
  },
)

const removing = ref<SetRow | null>(null)
const removeTarget = ref('')

function startRemove(row: SetRow) {
  removing.value = row
  removeTarget.value = ''
}

const DELETE_PICTURES = '__delete__'

function confirmRemove() {
  if (removing.value === null || removeTarget.value === '') return

  emit('remove', { id: removing.value.id, targetSetId: removeTarget.value === DELETE_PICTURES ? null : removeTarget.value })
  removing.value = null
}

// Native drag and drop — no library, this is the only reorderable list in
// the app. The Alle row above is never draggable and never part of order.
const draggingId = ref<string | null>(null)

function onDrop(targetId: string) {
  const from = draggingId.value

  draggingId.value = null
  if (from === null || from === targetId) return

  const ids = props.sets.map((row) => row.id)
  ids.splice(ids.indexOf(targetId), 0, ...ids.splice(ids.indexOf(from), 1))
  emit('reorder', ids)
}
</script>

<template>
  <div class="w-[212px] flex-none border-r border-border">
    <div v-if="coverSrc" class="aspect-4/3 bg-muted">
      <img class="h-full w-full object-cover" :src="`${coverSrc}?w=420&fmt=jpeg`" alt="" />
    </div>

    <div class="flex border-b border-border">
      <button
        v-for="name in TABS"
        :key="name"
        class="flex-1 cursor-pointer border-0 bg-transparent py-2.5 text-[13px]"
        :class="tab === name ? 'text-foreground shadow-[inset_0_-2px_0_var(--ink)]' : 'text-faint'"
        type="button"
        :title="$t(`galleryRecord.tab.${name}`)"
        :aria-label="$t(`galleryRecord.tab.${name}`)"
        @click="emit('update:tab', name)"
      >
        {{ $t(`galleryRecord.tabIcon.${name}`) }}
      </button>
    </div>

    <template v-if="tab === 'bilder'">
      <div class="flex items-center px-3 pt-3 pb-1.5">
        <span class="text-[9.5px] font-semibold tracking-[0.09em] uppercase text-faint">{{ $t('galleryRecord.sets') }}</span>
        <button class="ml-auto cursor-pointer border-0 bg-transparent text-[11.5px] text-action" type="button" @click="startAdd">
          {{ $t('galleryRecord.addSet') }}
        </button>
      </div>

      <template v-if="sets.length > 1">
        <div
          class="mx-1.5 flex items-center gap-2 rounded-lg px-3 py-2 text-[12.5px]"
          :class="activeSetId === null && 'bg-sunk-2 font-semibold'"
          @click="emit('update:activeSetId', null)"
        >
          {{ $t('gallery.all') }}
          <span class="ml-auto text-[11px] tabular-nums text-ink-2">{{ total }}</span>
        </div>

        <div
          v-for="row in sets"
          :key="row.id"
          class="group mx-1.5 flex items-center gap-2 rounded-lg px-3 py-2 text-[12.5px]"
          :class="activeSetId === row.id && 'bg-sunk-2 font-semibold'"
          draggable="true"
          @click="emit('update:activeSetId', row.id)"
          @dblclick="startRename(row)"
          @dragstart="draggingId = row.id"
          @dragover.prevent
          @drop="onDrop(row.id)"
        >
          <div v-if="editing === row.id" class="flex-1" @click.stop>
            <Input
              v-model="draft"
              class="h-6 text-[12.5px]"
              type="text"
              autofocus
              @blur="commitRename(row)"
              @keyup.enter="commitRename(row)"
              @keyup.esc="editing = null"
            />
            <p v-if="error?.id === row.id" class="mt-1 text-[11px] text-destructive">{{ error.message }}</p>
          </div>
          <template v-else>
            {{ row.name }}
            <span class="ml-auto text-[11px] tabular-nums text-ink-2">{{ row.count }}</span>
            <button
              class="hidden cursor-pointer border-0 bg-transparent text-[11px] text-faint group-hover:inline"
              type="button"
              @click.stop="startRemove(row)"
            >
              {{ $t('common.remove') }}
            </button>
          </template>
        </div>
      </template>

      <div v-if="adding" class="mx-1.5 px-3 py-2">
        <Input v-model="draft" class="h-6 text-[12.5px]" type="text" autofocus @blur="commitCreate" @keyup.enter="commitCreate" @keyup.esc="adding = false" />
        <p v-if="error?.id === 'new'" class="mt-1 text-[11px] text-destructive">{{ error.message }}</p>
      </div>
    </template>

    <template v-else-if="tab === 'einstellungen'">
      <div
        v-for="name in SETTINGS_SECTIONS"
        :key="name"
        class="mx-1.5 flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-[12.5px]"
        :class="settingsSection === name && 'bg-sunk-2 font-semibold'"
        @click="emit('update:settingsSection', name)"
      >
        {{ $t(`galleryRecord.settingsNav.${name}`) }}
        <span
          v-if="settingsPill[name] !== null"
          class="ml-auto rounded-[var(--r-sm)] px-1.5 py-[2px] text-[9.5px] leading-[1.5] font-semibold tracking-[0.04em] uppercase"
          :class="settingsPill[name] ? 'bg-ok-bg text-ok' : 'bg-sunk-2 text-faint'"
        >{{ settingsPill[name] ? $t('galleryPresets.on') : $t('galleryPresets.off') }}</span>
      </div>
    </template>

    <AlertDialog :open="removing !== null" @update:open="(open) => { if (!open) removing = null }">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{{ $t('galleryRecord.removeSet', { name: removing?.name ?? '' }) }}</AlertDialogTitle>
        </AlertDialogHeader>

        <div class="px-[var(--s5)]">
          <!-- The empty value is a real placeholder, not a choice: the
               destructive option must never be what fires just because the
               photographer clicked confirm without touching the select. -->
          <NativeSelect v-model="removeTarget" class="w-full">
            <option value="" disabled>{{ $t('galleryRecord.moveTo') }}</option>
            <option v-for="target in sets.filter((row) => row.id !== removing?.id)" :key="target.id" :value="target.id">
              {{ target.name }}
            </option>
            <option :value="DELETE_PICTURES">{{ $t('galleryRecord.removeSetPictures') }}</option>
          </NativeSelect>
        </div>

        <AlertDialogFooter>
          <AlertDialogAction tone="destructive" :disabled="removeTarget === ''" @click="confirmRemove">{{ $t('galleryRecord.deleteSet') }}</AlertDialogAction>
          <AlertDialogCancel>{{ $t('common.cancel') }}</AlertDialogCancel>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
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
  error: string | null
}>()

const emit = defineEmits<{
  'update:tab': [string]
  'update:activeSetId': [string | null]
  create: [string]
  rename: [{ id: string; name: string }]
  remove: [{ id: string; targetSetId: string | null }]
}>()

const TABS = ['bilder', 'gestaltung', 'einstellungen', 'aktivitaet'] as const

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
// untouched, so the row (and its error) stays open.
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

function confirmRemove() {
  if (removing.value === null) return

  emit('remove', { id: removing.value.id, targetSetId: removeTarget.value || null })
  removing.value = null
}
</script>

<template>
  <div class="w-[212px] flex-none border-r border-border">
    <div class="aspect-4/3 bg-muted">
      <img v-if="coverSrc" class="h-full w-full object-cover" :src="`${coverSrc}?w=420&fmt=jpeg`" alt="" />
    </div>

    <div class="flex border-b border-border">
      <button
        v-for="name in TABS"
        :key="name"
        class="flex-1 cursor-pointer border-0 bg-transparent py-2.5 text-[13px]"
        :class="tab === name ? 'text-foreground shadow-[inset_0_-2px_0_var(--ink)]' : 'text-faint'"
        type="button"
        :title="$t(`galleryRecord.tab.${name}`)"
        @click="emit('update:tab', name)"
      >
        {{ $t(`galleryRecord.tabIcon.${name}`) }}
      </button>
    </div>

    <template v-if="tab === 'bilder' && sets.length > 1">
      <div class="flex items-center px-3 pt-3 pb-1.5">
        <span class="text-[9.5px] font-semibold tracking-[0.09em] uppercase text-faint">{{ $t('galleryRecord.sets') }}</span>
        <button class="ml-auto cursor-pointer border-0 bg-transparent text-[11.5px] text-action" type="button" @click="startAdd">
          {{ $t('galleryRecord.addSet') }}
        </button>
      </div>

      <div
        v-for="row in sets"
        :key="row.id"
        class="group mx-1.5 flex items-center gap-2 rounded-lg px-3 py-2 text-[12.5px]"
        :class="activeSetId === row.id && 'bg-sunk-2 font-semibold'"
        @click="emit('update:activeSetId', row.id)"
        @dblclick="startRename(row)"
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
          <p v-if="error" class="mt-1 text-[11px] text-destructive">{{ error }}</p>
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

      <div v-if="adding" class="mx-1.5 px-3 py-2">
        <Input v-model="draft" class="h-6 text-[12.5px]" type="text" autofocus @blur="commitCreate" @keyup.enter="commitCreate" @keyup.esc="adding = false" />
        <p v-if="error" class="mt-1 text-[11px] text-destructive">{{ error }}</p>
      </div>
    </template>

    <AlertDialog :open="removing !== null" @update:open="(open) => { if (!open) removing = null }">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{{ $t('galleryRecord.removeSet', { name: removing?.name ?? '' }) }}</AlertDialogTitle>
        </AlertDialogHeader>

        <div class="px-[var(--s5)]">
          <NativeSelect v-model="removeTarget" class="w-full">
            <option value="">{{ $t('galleryRecord.removeSetPictures') }}</option>
            <option v-for="target in sets.filter((row) => row.id !== removing?.id)" :key="target.id" :value="target.id">
              {{ target.name }}
            </option>
          </NativeSelect>
        </div>

        <AlertDialogFooter>
          <AlertDialogAction tone="destructive" @click="confirmRemove">{{ $t('galleryRecord.deleteSet') }}</AlertDialogAction>
          <AlertDialogCancel>{{ $t('common.cancel') }}</AlertDialogCancel>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </div>
</template>

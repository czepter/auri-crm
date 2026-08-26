<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
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
import RecordNav from '@/admin/components/RecordNav.vue'

/*
 * Die Galerie füllt zwei Dinge in RecordNav, die ein Projekt nicht hat: das
 * Titelbild über den Einträgen, und an den Sets das Umbenennen und Löschen.
 *
 * Das Ziehen macht RecordNav selbst — es sortiert die Liste. Umbenennen und
 * Löschen bleiben hier, weil ein Lösch-Dialog mit Ziel-Set nichts ist, was
 * ein Projekt je brauchte.
 */

const props = defineProps<{
  nav: App.Gallery.Data.GalleryNavData
  area: string
  activeSetId: string | null
  error: { id: string; message: string } | null
  galleryId: string
}>()

const emit = defineEmits<{
  create: [string]
  rename: [{ id: string; name: string }]
  remove: [{ id: string; targetSetId: string | null }]
  reorder: [string[]]
}>()

const sets = computed(() => props.nav.tabs.find((tab) => tab.key === 'bilder')?.children.filter((child) => child.reorderable) ?? [])

const editing = ref<string | null>(null)
const draft = ref('')
const adding = ref(false)

function startRename(key: string, name: string) {
  adding.value = false
  editing.value = key
  draft.value = name
}

function commitRename(key: string, name: string) {
  if (draft.value === '' || draft.value === name) {
    editing.value = null

    return
  }

  emit('rename', { id: key, name: draft.value })
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

// Eine Zeile verlässt den Bearbeitungszustand erst, wenn der Server den neuen
// Namen zurückgeschickt hat. Ein 422 lässt sie mit ihrer Meldung offen.
watch(
  () => sets.value,
  (rows) => {
    if (editing.value !== null && rows.some((row) => row.key === editing.value && row.label === draft.value)) {
      editing.value = null
    }

    if (adding.value && rows.some((row) => row.label === draft.value)) {
      adding.value = false
      draft.value = ''
    }
  },
)

const removing = ref<{ id: string; name: string } | null>(null)
const removeTarget = ref('')

const DELETE_PICTURES = '__delete__'

function confirmRemove() {
  if (removing.value === null || removeTarget.value === '') return

  emit('remove', {
    id: removing.value.id,
    targetSetId: removeTarget.value === DELETE_PICTURES ? null : removeTarget.value,
  })
  removing.value = null
}
</script>

<template>
  <RecordNav
    :tabs="nav.tabs"
    :active="area"
    :active-child="activeSetId ?? (area === 'bilder' ? 'alle' : null)"
    :width="212"
    @reorder="(order) => emit('reorder', order)"
  >
    <!-- Das Titelbild ist kein Bereich, sondern das Gesicht des Datensatzes.
         Ein leerer Zustand steht hier nicht: das erste Bild wird Titelbild
         und beim Löschen rückt das nächste nach, also fehlt es nur, wenn es
         gar keine Bilder gibt — und dann wäre auch nichts zu wählen.

         Der Klick führt zu den Bildern und nicht zur Gestaltung: dort wird
         gewählt, welches es ist. Die Gestaltung entscheidet, wie es
         aufgemacht wird — ein Bild ist ein Bild. -->
    <template v-if="nav.cover" #above>
      <Link :href="`/galerien/${galleryId}/bilder`" class="mb-3 block no-underline max-[640px]:mb-2 max-[640px]:w-full">
        <img class="aspect-4/3 w-full rounded-[var(--r-sm)] object-cover max-[640px]:aspect-[16/5]" :src="`${nav.cover}?w=400&fmt=jpeg`" alt="" />
      </Link>
    </template>

    <template #child-actions="{ child }">
      <div v-if="editing === child.key" class="flex-1 px-2 py-1" @click.stop>
        <Input
          v-model="draft"
          class="h-6 text-[12.5px]"
          type="text"
          autofocus
          @blur="commitRename(child.key, child.label)"
          @keyup.enter="commitRename(child.key, child.label)"
          @keyup.esc="editing = null"
        />
        <p v-if="error?.id === child.key" class="mt-1 text-[11px] text-destructive">{{ error.message }}</p>
      </div>

      <template v-else-if="child.reorderable">
        <button
          class="hidden cursor-pointer border-0 bg-transparent pr-1 text-[11px] text-faint group-hover:inline"
          type="button"
          @click.stop="startRename(child.key, child.label)"
        >
          {{ $t('common.rename') }}
        </button>
        <button
          class="hidden cursor-pointer border-0 bg-transparent pr-2 text-[11px] text-faint group-hover:inline"
          type="button"
          @click.stop="removing = { id: child.key, name: child.label }"
        >
          {{ $t('common.remove') }}
        </button>
      </template>
    </template>

    <template #children-append>
      <div v-if="adding" class="px-2 py-1">
        <Input v-model="draft" class="h-6 text-[12.5px]" type="text" autofocus @blur="commitCreate" @keyup.enter="commitCreate" @keyup.esc="adding = false" />
        <p v-if="error?.id === 'new'" class="mt-1 text-[11px] text-destructive">{{ error.message }}</p>
      </div>
      <button v-else class="cursor-pointer border-0 bg-transparent px-2 py-[5px] text-left text-[12px] text-action" type="button" @click="startAdd">
        {{ $t('galleryRecord.addSet') }}
      </button>
    </template>
  </RecordNav>

  <AlertDialog :open="removing !== null" @update:open="(open) => { if (!open) removing = null }">
    <AlertDialogContent>
      <AlertDialogHeader>
        <AlertDialogTitle>{{ $t('galleryRecord.removeSet', { name: removing?.name ?? '' }) }}</AlertDialogTitle>
      </AlertDialogHeader>

      <div class="px-[var(--s5)]">
        <!-- Der leere Wert ist ein echter Platzhalter, keine Wahl: die
             zerstörende Möglichkeit darf nie die sein, die feuert, weil
             jemand bestätigt hat, ohne das Feld anzufassen. -->
        <NativeSelect v-model="removeTarget" class="w-full">
          <option value="" disabled>{{ $t('galleryRecord.moveTo') }}</option>
          <option v-for="target in sets.filter((row) => row.key !== removing?.id)" :key="target.key" :value="target.key">
            {{ target.label }}
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
</template>

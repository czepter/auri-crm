<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { DropArea } from '@/ui/drop-area'

// Q-10: der Bildersatz des Reglerrasters — am Paket der Startsatz, am
// Angebot der, den der Kunde sieht. Beide Wege sind dieselben drei Routen
// unter ihrer eigenen Basis, also trägt der Aufrufer nur die Basis bei.
const props = defineProps<{ base: string; count: number }>()

// Ein Satz wächst, deshalb geht jede gezogene Datei einzeln hoch: der
// Endpunkt hängt an, und Inertia schickt die Anfragen nacheinander los.
function add(files: File[]) {
  files.forEach((image) => router.post(props.base, { image }, { preserveScroll: true, forceFormData: true }))
}
</script>

<!--
  Die Miniaturen stehen neben der Fläche, nicht darin: ein Knopf in einem
  Label loest das Label mit aus, und Entfernen wuerde den Dateidialog oeffnen.
-->
<template>
  <div class="flex flex-wrap items-center gap-2">
    <div v-for="index in count" :key="index" class="relative">
      <img class="size-14 rounded-[6px] object-cover" :src="`${base}/${index - 1}`" alt="" />
      <button
        class="absolute -top-1.5 -right-1.5 flex size-5 cursor-pointer items-center justify-center rounded-full bg-sunk-2 text-small leading-none"
        type="button"
        :aria-label="$t('common.remove')"
        @click="router.delete(`${base}/${index - 1}`, { preserveScroll: true })"
      >×</button>
    </div>
    <DropArea class="min-w-[220px] flex-1" accept="image/png,image/jpeg" multiple @files="add">
      <span class="text-small text-foreground">{{ $t('common.drop') }}</span>
    </DropArea>
  </div>
</template>

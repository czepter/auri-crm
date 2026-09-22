<script setup lang="ts">
import { ref } from 'vue'
import { Input } from '@/ui/input'

/*
 * Die gestrichelte Fläche, auf die eine Datei gezogen oder in die geklickt
 * wird. Das Eingabefeld bleibt ein echtes <input type="file"> im Label, also
 * trägt der Klickweg sich selbst und die Tastatur erreicht es weiter.
 */
withDefaults(defineProps<{ id?: string; accept: string; multiple?: boolean }>(), { multiple: false })
const emit = defineEmits<{ files: [File[]] }>()

const over = ref(false)

function picked(list: FileList | null | undefined) {
  const files = Array.from(list ?? [])

  if (files.length > 0) emit('files', files)
}
</script>

<template>
  <label
    class="flex cursor-pointer items-center gap-4 rounded-lg border border-dashed p-4 transition-colors"
    :class="over ? 'border-foreground bg-muted' : 'border-border'"
    @dragover.prevent="over = true"
    @dragleave="over = false"
    @drop.prevent="over = false; picked($event.dataTransfer?.files)"
  >
    <slot />
    <Input
      :id="id"
      type="file"
      class="hidden"
      :accept="accept"
      :multiple="multiple"
      @change="picked(($event.target as HTMLInputElement).files)"
    />
  </label>
</template>

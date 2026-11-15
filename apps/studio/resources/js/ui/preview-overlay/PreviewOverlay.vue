<script setup lang="ts">
import { Eye } from 'lucide-vue-next'
import { useMediaQuery } from '@vueuse/core'
import { computed, ref } from 'vue'
import { Button } from '@/ui/button'
import { Dialog, DialogContent, DialogTitle } from '@/ui/dialog'

/*
 * Eine Vorschau neben ihrem Formular. Ab `min` Pixel Fensterbreite steht sie
 * an ihrem Platz; darunter fehlt der Platz, und ein Knopf öffnet sie als
 * Überlagerung. Der Inhalt wird nur einmal gerendert: entweder hier oder dort.
 */
const props = defineProps<{
  min: number
  label: string
  /** Klassen des Trägers, solange die Vorschau an ihrem Platz steht. */
  wrapperClass?: string
}>()

const beside = useMediaQuery(computed(() => `(min-width: ${props.min}px)`))
const open = ref(false)
</script>

<template>
  <div v-if="beside" :class="wrapperClass">
    <slot />
  </div>

  <template v-else>
    <Button
      class="fixed right-6 bottom-6 z-40 shadow-[0_8px_24px_rgb(20_16_14_/_0.16)]"
      variant="default"
      type="button"
      @click="open = true"
    >
      <Eye />
      {{ label }}
    </Button>

    <Dialog v-model:open="open">
      <DialogContent class="max-w-[860px]">
        <DialogTitle class="sr-only">{{ label }}</DialogTitle>
        <div class="min-h-0 overflow-y-auto p-4">
          <slot />
        </div>
      </DialogContent>
    </Dialog>
  </template>
</template>

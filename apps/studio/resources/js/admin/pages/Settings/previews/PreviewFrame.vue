<script setup lang="ts">
import { Eye } from 'lucide-vue-next'
import { useMediaQuery } from '@vueuse/core'
import { ref } from 'vue'
import { Button } from '@/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/ui/dialog'

defineProps<{ label: string; hint?: string; wide?: boolean }>()

// Neben dem Formular ist erst ab 1441px Platz: der Rahmen steht dort mit
// der Seitenleiste und den 620px des Formulars in einer Zeile. Darunter
// öffnet ihn ein Knopf als Überlagerung.
const beside = useMediaQuery('(min-width: 1441px)')
const open = ref(false)
</script>

<!--
  The right-hand column every settings pane that has something to show hangs
  its sample in.

  Everything a setting does not control renders as a grey bar inside these,
  never as invented sample text: the point is seeing your own data land.
-->
<template>
  <aside
    v-if="beside"
    class="sticky top-6"
    :class="wide ? 'min-w-[352px] max-w-[1100px] flex-1' : 'w-[352px] flex-none'"
  >
    <div class="mb-2.5 text-small leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ label }}</div>
    <p v-if="hint" class="mb-2.5 text-small leading-[1.45] text-faint">{{ hint }}</p>
    <div class="rounded-lg bg-muted p-4">
      <slot />
    </div>
  </aside>

  <template v-else>
    <Button
      class="fixed right-6 bottom-6 z-40 shadow-[0_8px_24px_rgb(20_16_14_/_0.16)]"
      variant="pill"
      type="button"
      @click="open = true"
    >
      <Eye />
      {{ $t('settings.previewShow') }}
    </Button>

    <Dialog v-model:open="open">
      <DialogContent :class="wide ? 'max-w-[760px]' : 'max-w-[400px]'">
        <DialogHeader>
          <DialogTitle>{{ label }}</DialogTitle>
          <DialogDescription v-if="hint">{{ hint }}</DialogDescription>
        </DialogHeader>
        <div class="min-h-0 overflow-y-auto rounded-b-md bg-muted p-4">
          <slot />
        </div>
      </DialogContent>
    </Dialog>
  </template>
</template>

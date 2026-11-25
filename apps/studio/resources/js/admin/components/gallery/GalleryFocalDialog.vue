<script setup lang="ts">
import { ref, watch } from 'vue'
import { Button } from '@/ui/button'
import { Dialog, DialogBody, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/ui/dialog'

const props = defineProps<{ src: string; focal: { x: number; y: number } | null }>()
const open = defineModel<boolean>('open', { required: true })
const emit = defineEmits<{ save: [focal: { x: number; y: number }] }>()

const point = ref(props.focal ?? { x: 50, y: 50 })

watch(open, (now) => now && (point.value = props.focal ?? { x: 50, y: 50 }))

function pick(event: MouseEvent) {
  const box = (event.currentTarget as HTMLElement).getBoundingClientRect()
  const percent = (value: number, size: number) => Math.min(100, Math.max(0, Math.round((value / size) * 100)))

  point.value = { x: percent(event.clientX - box.left, box.width), y: percent(event.clientY - box.top, box.height) }
}
</script>

<template>
  <Dialog v-model:open="open">
    <DialogContent>
      <DialogHeader>
        <DialogTitle>{{ $t('galleryDesign.focus') }}</DialogTitle>
      </DialogHeader>

      <DialogBody>
        <div class="relative cursor-crosshair overflow-hidden rounded-md" @click="pick">
          <img :src="src" alt="" class="block max-h-[60vh] w-full object-contain">
          <span
            class="pointer-events-none absolute size-6 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white outline outline-1 outline-black/40"
            :style="{ left: `${point.x}%`, top: `${point.y}%` }"
          />
        </div>
      </DialogBody>

      <DialogFooter>
        <Button class="ml-auto" type="button" @click="emit('save', point); open = false">{{ $t('common.save') }}</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

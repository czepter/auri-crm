<script setup lang="ts">
import { ref } from 'vue'
import { Input } from '@/ui/input'

const props = defineProps<{ disabled: boolean }>()
const emit = defineEmits<{ files: [File[]] }>()
const over = ref(false)

function onDrop(event: DragEvent) {
  over.value = false
  if (props.disabled) return

  emit('files', Array.from(event.dataTransfer?.files ?? []))
}
</script>

<template>
  <label
    class="block rounded-lg border border-dashed border-border px-6 py-9 text-center text-[12.5px] text-ink-2"
    :class="[disabled ? 'cursor-default' : 'cursor-pointer', over && !disabled && 'border-action bg-accent-bg']"
    @dragover.prevent="over = !disabled"
    @dragleave="over = false"
    @drop.prevent="onDrop"
  >
    <template v-if="disabled">
      {{ $t('media.pickSetFirst') }}
    </template>
    <template v-else>
      <b class="block text-[14px] font-semibold text-foreground">{{ $t('media.dropHere') }}</b>
      {{ $t('media.pickFiles') }}
      <Input
        type="file"
        multiple
        class="hidden"
        :disabled="disabled"
        @change="emit('files', Array.from(($event.target as HTMLInputElement).files ?? []))"
      />
    </template>
  </label>
</template>

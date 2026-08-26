<script setup lang="ts">
import { Input } from '@/ui/input'

const emit = defineEmits<{ files: [File[]] }>()
</script>

<!-- Ziehen faengt die ganze Flaeche ab (Images.vue), hier bleibt der Klickweg. -->
<template>
  <label class="block cursor-pointer rounded-lg border border-dashed border-border px-6 py-9 text-center text-[12.5px] text-ink-2">
    <b class="block text-[14px] font-semibold text-foreground">{{ $t('media.dropHere') }}</b>
    {{ $t('media.upload') }}
    <Input
      type="file"
      multiple
      class="hidden"
      @change="emit('files', Array.from(($event.target as HTMLInputElement).files ?? []))"
    />
  </label>
</template>

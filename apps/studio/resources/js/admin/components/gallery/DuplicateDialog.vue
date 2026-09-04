<script setup lang="ts">
import { Button } from '@/ui/button'
import { Dialog, DialogBody, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/ui/dialog'

defineProps<{ open: boolean; duplicates: { filename: string; size: number }[] }>()
const emit = defineEmits<{ skip: []; replace: [] }>()

function megabytes(bytes: number) {
  return `${(bytes / 1_000_000).toFixed(1)} MB`
}
</script>

<template>
  <Dialog :open="open">
    <DialogContent hide-close>
      <DialogHeader>
        <DialogTitle>{{ $t('media.duplicatesFound', duplicates.length) }}</DialogTitle>
      </DialogHeader>
      <DialogBody>
        <div v-for="row in duplicates" :key="row.filename" class="flex gap-3 py-1 text-[12.5px]">
          <span class="min-w-0 flex-1 truncate">{{ row.filename }}</span>
          <span class="tabular-nums text-ink-2">{{ megabytes(row.size) }}</span>
        </div>
      </DialogBody>
      <DialogFooter>
        <Button type="button" @click="emit('replace')">{{ $t('media.replace') }}</Button>
        <Button variant="ghost" type="button" @click="emit('skip')">{{ $t('media.skipDuplicates') }}</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

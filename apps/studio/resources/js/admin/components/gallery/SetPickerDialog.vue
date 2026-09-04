<script setup lang="ts">
import { Button } from '@/ui/button'
import { Dialog, DialogBody, DialogContent, DialogHeader, DialogTitle } from '@/ui/dialog'

defineProps<{ open: boolean; sets: { id: string; name: string }[] }>()
const emit = defineEmits<{ choose: [string]; cancel: [] }>()
</script>

<template>
  <Dialog :open="open" @update:open="(next: boolean) => !next && emit('cancel')">
    <DialogContent>
      <DialogHeader>
        <DialogTitle>{{ $t('media.chooseSet') }}</DialogTitle>
      </DialogHeader>
      <DialogBody>
        <Button
          v-for="row in sets"
          :key="row.id"
          class="w-full justify-start"
          variant="ghost"
          type="button"
          @click="emit('choose', row.id)"
        >
          {{ row.name }}
        </Button>
      </DialogBody>
    </DialogContent>
  </Dialog>
</template>

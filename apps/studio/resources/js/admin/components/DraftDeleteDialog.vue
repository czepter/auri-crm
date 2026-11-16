<script setup lang="ts">
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { AlertDialog, AlertDialogCancel, AlertDialogContent, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/ui/alert-dialog'
import { HoldToConfirm } from '@/ui/hold-to-confirm'

const url = defineModel<string | null>('url', { required: true })
const open = computed({ get: () => url.value !== null, set: (value) => { if (!value) url.value = null } })

function destroy() {
  if (url.value) router.delete(url.value, { preserveScroll: true })
  url.value = null
}
</script>

<template>
  <AlertDialog v-model:open="open">
    <AlertDialogContent>
      <AlertDialogHeader>
        <AlertDialogTitle>{{ $t('quotes.deleteTitle') }}</AlertDialogTitle>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <HoldToConfirm tone="destructive" @confirm="destroy">{{ $t('common.delete') }}</HoldToConfirm>
        <AlertDialogCancel>{{ $t('common.cancel') }}</AlertDialogCancel>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>

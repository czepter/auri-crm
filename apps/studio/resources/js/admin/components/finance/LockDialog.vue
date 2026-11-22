<script setup lang="ts">
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { AlertDialog, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/ui/alert-dialog'
import { HoldToConfirm } from '@/ui/hold-to-confirm'

const props = defineProps<{ year: number }>()
const open = defineModel<boolean>('open', { default: false })

const error = computed(() => (usePage().props.errors as Record<string, string> | undefined)?.year)

function lock() {
  router.post(`/finanzen/euer/${props.year}/sperren`, {}, { preserveScroll: true, onSuccess: () => (open.value = false) })
}
</script>

<template>
  <AlertDialog v-model:open="open">
    <AlertDialogContent>
      <AlertDialogHeader>
        <AlertDialogTitle>{{ $t('financeEuer.lockTitle', { year }) }}</AlertDialogTitle>
        <AlertDialogDescription>{{ $t('financeEuer.lockFact') }}</AlertDialogDescription>
      </AlertDialogHeader>
      <p v-if="error" class="text-small text-destructive">{{ error }}</p>
      <AlertDialogFooter>
        <HoldToConfirm tone="destructive" @confirm="lock">{{ $t('financeEuer.lock') }}</HoldToConfirm>
        <AlertDialogCancel>{{ $t('common.cancel') }}</AlertDialogCancel>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import ProfileForm from './ProfileForm.vue'
import { Button } from '@/ui/button'
import { Dialog, DialogBody, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/ui/dialog'

defineProps<{
  open: boolean
  first?: boolean
  settings: App.Settings.Data.StudioSettingsData
}>()

const emit = defineEmits<{ done: []; cancel: [] }>()

const profileRef = ref<InstanceType<typeof ProfileForm>>()
</script>

<template>
  <Dialog :open="open" @update:open="(value) => value || emit('cancel')">
    <DialogContent hide-close class="max-w-[560px]">
      <DialogHeader>
        <span v-if="first" class="text-small leading-none font-semibold text-muted-foreground">{{ $t('gates.beforeInvoice') }}</span>
        <DialogTitle :class="first && 'mt-3'">{{ $t('gates.invoiceDataTitle') }}</DialogTitle>
        <DialogDescription>{{ $t('gates.invoiceDataHint') }}</DialogDescription>
      </DialogHeader>

      <DialogBody>
        <ProfileForm ref="profileRef" :settings="settings" :sections="['tax', 'bank']" @saved="emit('done')" />
      </DialogBody>

      <DialogFooter class="justify-end">
        <Button variant="outline" type="button" @click="emit('cancel')">{{ $t('common.cancel') }}</Button>
        <Button type="button" :disabled="profileRef?.form.processing" @click="profileRef?.submit()">
          {{ $t('gates.saveAndContinue') }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

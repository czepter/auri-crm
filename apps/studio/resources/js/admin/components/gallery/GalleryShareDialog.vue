<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { CopyButton } from '@/ui/copy-button'
import { Dialog, DialogBody, DialogContent, DialogHeader, DialogTitle } from '@/ui/dialog'
import { Label } from '@/ui/label'
import { InputGroup, InputGroupInput } from '@/ui/input-group'

const props = defineProps<{ gallery: { url: string; viewPassword: string | null; downloadPin: string | null } }>()
const open = defineModel<boolean>('open', { required: true })

const { t } = useI18n()

const fields = computed(() => [
  { id: 'link', label: t('galleryRecord.link'), value: props.gallery.url },
  { id: 'password', label: t('galleries.passwordField'), value: props.gallery.viewPassword },
  { id: 'pin', label: t('gallery.pinLabel'), value: props.gallery.downloadPin },
].filter((field): field is { id: string; label: string; value: string } => field.value !== null))
</script>

<template>
  <Dialog v-model:open="open">
    <DialogContent>
      <DialogHeader>
        <DialogTitle>{{ $t('galleryRecord.share') }}</DialogTitle>
      </DialogHeader>

      <DialogBody class="flex flex-col gap-4">
        <div v-for="field in fields" :key="field.id" class="flex flex-col gap-1.5">
          <Label :for="`share-${field.id}`">{{ field.label }}</Label>
          <InputGroup class="bg-sunk">
            <InputGroupInput :id="`share-${field.id}`" :model-value="field.value" readonly @focus="($event.target as HTMLInputElement).select()" />
            <CopyButton :value="field.value" variant="ghost" class="flex-none px-3" />
          </InputGroup>
        </div>
      </DialogBody>
    </DialogContent>
  </Dialog>
</template>

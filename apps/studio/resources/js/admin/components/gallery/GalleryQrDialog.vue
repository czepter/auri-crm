<script setup lang="ts">
import { ref, watchEffect } from 'vue'
import { toDataURL } from 'qrcode'
import { Button } from '@/ui/button'
import { CopyButton } from '@/ui/copy-button'
import { Dialog, DialogBody, DialogContent, DialogHeader, DialogTitle } from '@/ui/dialog'
import { InputGroup, InputGroupInput } from '@/ui/input-group'
import { Label } from '@/ui/label'

const props = defineProps<{ gallery: { slug: string; url: string; viewPassword: string | null } }>()
const open = defineModel<boolean>('open', { required: true })

const png = ref('')

watchEffect(async () => {
  png.value = await toDataURL(props.gallery.url, { width: 1024, margin: 2 })
})
</script>

<template>
  <Dialog v-model:open="open">
    <DialogContent>
      <DialogHeader>
        <DialogTitle>{{ $t('galleryRecord.qr') }}</DialogTitle>
      </DialogHeader>

      <DialogBody class="flex flex-col gap-4">
        <img v-if="png" :src="png" :alt="$t('galleryRecord.qr')" class="mx-auto block size-[220px] rounded-md border border-border">

        <div class="flex flex-col gap-1.5">
          <Label for="qr-link">{{ $t('galleryRecord.link') }}</Label>
          <InputGroup class="bg-sunk">
            <InputGroupInput id="qr-link" :model-value="gallery.url" readonly @focus="($event.target as HTMLInputElement).select()" />
            <CopyButton :value="gallery.url" variant="ghost" class="flex-none px-3" />
          </InputGroup>
        </div>

        <div v-if="gallery.viewPassword" class="flex flex-col gap-1.5">
          <Label for="qr-password">{{ $t('galleries.passwordField') }}</Label>
          <InputGroup class="bg-sunk">
            <InputGroupInput id="qr-password" :model-value="gallery.viewPassword" readonly @focus="($event.target as HTMLInputElement).select()" />
            <CopyButton :value="gallery.viewPassword" variant="ghost" class="flex-none px-3" />
          </InputGroup>
        </div>

        <Button as="a" :href="png" :download="`${gallery.slug}.png`" variant="pill">{{ $t('galleryRecord.qrDownload') }}</Button>
      </DialogBody>
    </DialogContent>
  </Dialog>
</template>

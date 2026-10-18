<script setup lang="ts">
import { ref } from 'vue'
import ProfileForm from './ProfileForm.vue'
import VatForm from './VatForm.vue'
import { Button } from '@/ui/button'
import { Dialog, DialogBody, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/ui/dialog'

defineProps<{
  open: boolean
  settings: App.Settings.Data.StudioSettingsData
}>()

const emit = defineEmits<{ done: []; cancel: [] }>()

// Zwei Formulare, zwei Endpunkte: das Profil speichert zuerst, sein `saved`
// startet die Umsatzsteuer, deren `saved` schliesst das Fenster. Nacheinander
// statt parallel, sonst entscheidet die Reihenfolge der Antworten, welcher
// Redirect gewinnt.
const profileRef = ref<InstanceType<typeof ProfileForm>>()
const vatRef = ref<InstanceType<typeof VatForm>>()
</script>

<template>
  <Dialog :open="open" @update:open="(value) => value || emit('cancel')">
    <DialogContent hide-close class="max-w-[560px]">
      <DialogHeader>
        <span class="text-small leading-none font-semibold text-muted-foreground">{{ $t('gates.beforeQuote') }}</span>
        <DialogTitle class="mt-3">{{ $t('gates.businessDataTitle') }}</DialogTitle>
        <DialogDescription>{{ $t('gates.businessDataHint') }}</DialogDescription>
      </DialogHeader>

      <DialogBody>
        <ProfileForm ref="profileRef" :settings="settings" :sections="['legal']" @saved="vatRef?.submit()" />
        <VatForm ref="vatRef" :settings="settings" @saved="emit('done')" />
      </DialogBody>

      <DialogFooter class="justify-end">
        <Button variant="outline" type="button" @click="emit('cancel')">{{ $t('common.cancel') }}</Button>
        <Button type="button" :disabled="profileRef?.form.processing || vatRef?.form.processing" @click="profileRef?.submit()">
          {{ $t('gates.saveAndContinue') }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { useI18n } from 'vue-i18n'
import ContactTypeahead from '@/admin/components/crm/ContactTypeahead.vue'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/ui/alert-dialog'
import { Button } from '@/ui/button'
import { ChoiceGroup } from '@/ui/choice-group'
import { Field as FormField } from '@/ui/field'
import { focusFirstError } from '@/admin/lib/focusInvalid'
import { Dialog, DialogBody, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/ui/dialog'

const FIELDS = ['name', 'email', 'phone', 'street', 'postcode', 'city'] as const
type Field = (typeof FIELDS)[number]
type Sheet = Record<Field, string | null>

const open = defineModel<boolean>('open', { required: true })
const props = defineProps<{ id: string; sheet: Sheet }>()

const other = ref<App.Crm.Data.ContactSuggestionData | null>(null)
const duplicate = ref<Sheet | null>(null)
const choices = reactive<Record<string, 'survivor' | 'duplicate'>>({})
const processing = ref(false)
const confirming = ref(false)
const unpicked = ref(false)
const { t } = useI18n()

const differing = computed(() => (duplicate.value ? FIELDS.filter((field) => (duplicate.value![field] ?? '') !== (props.sheet[field] ?? '')) : []))

watch(other, async (contact) => {
  duplicate.value = null

  if (contact === null) {
    return
  }

  if (contact.id === props.id) {
    other.value = null
    return
  }

  const response = await fetch(`/kontakte/${props.id}/zusammenfuehren/${contact.id}`, { headers: { Accept: 'application/json' } })

  if (!response.ok) {
    toast.error(t('contacts.mergeLoadFailed'))
    other.value = null
    return
  }

  const sheet: Sheet = await response.json()

  for (const field of FIELDS) {
    choices[field] = props.sheet[field] ? 'survivor' : 'duplicate'
  }

  duplicate.value = sheet
})

watch(open, (value) => {
  if (!value) {
    other.value = null
  }
})

const options = (field: Field) => [
  { value: 'survivor', label: props.sheet[field] || '—' },
  { value: 'duplicate', label: duplicate.value?.[field] || '—' },
]

function confirm() {
  if (duplicate.value) {
    confirming.value = true
    return
  }

  unpicked.value = true
  nextTick(focusFirstError)
}

function merge() {
  router.post(
    `/kontakte/${props.id}/zusammenfuehren`,
    { duplicate: other.value!.id, choices: Object.fromEntries(differing.value.map((field) => [field, choices[field]])) },
    {
      onStart: () => (processing.value = true),
      onSuccess: () => (open.value = false),
      onFinish: () => (processing.value = false),
    },
  )
}
</script>

<template>
  <Dialog v-model:open="open">
    <DialogContent>
      <DialogHeader>
        <DialogTitle>{{ $t('contacts.merge') }}</DialogTitle>
      </DialogHeader>
      <DialogBody class="flex flex-col gap-4">
        <FormField :error="unpicked && !duplicate ? t('common.invalid.valueMissing') : undefined">
          <ContactTypeahead v-model="other" :creatable="false" />
        </FormField>
        <ChoiceGroup
          v-for="field in differing"
          :key="field"
          v-model="choices[field]"
          :label="$t(`contacts.${field}`)"
          :options="options(field)"
        />
      </DialogBody>
      <DialogFooter>
        <Button class="ml-auto" type="button" :disabled="processing" @click="confirm">{{ $t('contacts.merge') }}</Button>
        <Button variant="ghost" type="button" @click="open = false">{{ $t('common.cancel') }}</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>

  <AlertDialog v-model:open="confirming">
    <AlertDialogContent>
      <AlertDialogHeader>
        <AlertDialogTitle>{{ $t('contacts.mergeConfirm', { name: other?.name }) }}</AlertDialogTitle>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <AlertDialogAction tone="destructive" @click="merge">{{ $t('contacts.merge') }}</AlertDialogAction>
        <AlertDialogCancel>{{ $t('common.cancel') }}</AlertDialogCancel>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>

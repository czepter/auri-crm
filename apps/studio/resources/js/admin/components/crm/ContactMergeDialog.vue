<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import ContactTypeahead from '@/admin/components/crm/ContactTypeahead.vue'
import { Button } from '@/ui/button'
import { ChoiceGroup } from '@/ui/choice-group'
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
        <ContactTypeahead v-model="other" :creatable="false" />
        <ChoiceGroup
          v-for="field in differing"
          :key="field"
          v-model="choices[field]"
          :label="$t(`contacts.${field}`)"
          :options="options(field)"
        />
      </DialogBody>
      <DialogFooter>
        <Button type="button" :disabled="!duplicate || processing" @click="merge">{{ $t('contacts.merge') }}</Button>
        <Button variant="ghost" type="button" @click="open = false">{{ $t('common.cancel') }}</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

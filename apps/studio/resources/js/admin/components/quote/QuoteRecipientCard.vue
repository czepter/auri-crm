<script setup lang="ts">
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import type { QuoteContact } from './quotePayload'
import { ChoiceGroup } from '@/ui/choice-group'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { Button } from '@/ui/button'

const props = defineProps<{
  quoteId: string
  contacts: QuoteContact[]
  savedStreet: string | null
  savedPostcode: string | null
  savedCity: string | null
}>()
const contact = defineModel<string | null>('contact', { required: true })
const street = defineModel<string | null>('street', { required: true })
const postcode = defineModel<string | null>('postcode', { required: true })
const city = defineModel<string | null>('city', { required: true })
const emit = defineEmits<{ change: [] }>()

// Input carries a string, the draft carries a nullable one — the empty
// string is the only value that needs translating between the two.
function field(model: typeof street) {
  return computed({
    get: () => model.value ?? '',
    set: (value: string) => (model.value = value === '' ? null : value),
  })
}

const streetField = field(street)
const postcodeField = field(postcode)
const cityField = field(city)

const selected = computed(() => props.contacts.find((entry) => entry.id === contact.value) ?? null)

function pick(value: string) {
  contact.value = value

  const next = props.contacts.find((entry) => entry.id === value)

  // §4.4: eine eigene Anschrift am Angebot bleibt stehen, eine leere nimmt
  // die des Kontakts an.
  if (next && !street.value && !postcode.value && !city.value) {
    street.value = next.street
    postcode.value = next.postcode
    city.value = next.city
  }

  emit('change')
}

const differsFromContact = computed(
  () =>
    selected.value !== null &&
    ((street.value ?? '') !== (selected.value.street ?? '') ||
      (postcode.value ?? '') !== (selected.value.postcode ?? '') ||
      (city.value ?? '') !== (selected.value.city ?? '')),
)

// Der Endpunkt liest die gespeicherten Spalten; solange die Autospeicherung
// noch aussteht, traegt er die vorige Anschrift in den Kontakt.
const pending = computed(
  () =>
    (street.value ?? '') !== (props.savedStreet ?? '') ||
    (postcode.value ?? '') !== (props.savedPostcode ?? '') ||
    (city.value ?? '') !== (props.savedCity ?? ''),
)

function saveToContact() {
  router.post(`/angebote/${props.quoteId}/empfaenger-uebernehmen`, {}, { preserveScroll: true })
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <div v-if="contacts.length" class="flex items-center gap-2.5">
      <ChoiceGroup
        :model-value="contact"
        :options="contacts.map((entry) => ({ value: entry.id, label: entry.name }))"
        @update:model-value="pick"
      />
      <template v-if="differsFromContact">
        <span class="ml-auto text-[11.5px] text-ink-2">{{ $t('quotes.differsFromContact') }}</span>
        <Button type="button" variant="outline" :disabled="pending" @click="saveToContact">
          {{ $t('quotes.saveToContact') }}
        </Button>
      </template>
    </div>

    <div class="grid grid-cols-[120px_1fr] gap-4">
      <div class="col-span-2 flex min-w-0 flex-col gap-1.5">
        <Label :for="`recipient-street-${props.quoteId}`">{{ $t('contacts.street') }}</Label>
        <Input :id="`recipient-street-${props.quoteId}`" v-model="streetField" type="text" @blur="emit('change')" />
      </div>
      <div class="flex min-w-0 flex-col gap-1.5">
        <Label :for="`recipient-postcode-${props.quoteId}`">{{ $t('contacts.postcode') }}</Label>
        <Input :id="`recipient-postcode-${props.quoteId}`" v-model="postcodeField" type="text" @blur="emit('change')" />
      </div>
      <div class="flex min-w-0 flex-col gap-1.5">
        <Label :for="`recipient-city-${props.quoteId}`">{{ $t('contacts.city') }}</Label>
        <Input :id="`recipient-city-${props.quoteId}`" v-model="cityField" type="text" @blur="emit('change')" />
      </div>
    </div>
  </div>
</template>

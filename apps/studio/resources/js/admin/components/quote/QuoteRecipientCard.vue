<script setup lang="ts">
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { Button } from '@/ui/button'

const props = defineProps<{
  quoteId: string
  recipientName: string | null
  recipientContactId: string | null
  contactStreet: string | null
  contactPostcode: string | null
  contactCity: string | null
}>()
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

const differsFromContact = computed(
  () =>
    props.recipientContactId !== null &&
    ((street.value ?? '') !== (props.contactStreet ?? '') ||
      (postcode.value ?? '') !== (props.contactPostcode ?? '') ||
      (city.value ?? '') !== (props.contactCity ?? '')),
)

function saveToContact() {
  router.post(`/angebote/${props.quoteId}/empfaenger-uebernehmen`, {}, { preserveScroll: true })
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <div v-if="recipientName" class="flex items-center gap-2.5">
      <p class="text-[13.5px] font-medium">{{ recipientName }}</p>
      <template v-if="differsFromContact">
        <span class="ml-auto text-[11.5px] text-ink-2">{{ $t('quotes.differsFromContact') }}</span>
        <Button type="button" variant="outline" @click="saveToContact">
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
